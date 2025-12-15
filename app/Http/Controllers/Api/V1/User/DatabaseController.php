<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use App\Models\Database;
use App\Models\DatabaseUser;
use App\Services\Database\DatabaseInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class DatabaseController extends Controller
{
    protected DatabaseInterface $database;

    public function __construct(DatabaseInterface $database)
    {
        $this->database = $database;
    }

    public function index(Request $request)
    {
        $account = $request->user()->account;

        $databases = Database::where('account_id', $account->id)
            ->with('users')
            ->get()
            ->map(function ($db) {
                $db->size_formatted = $this->formatBytes($db->size);
                return $db;
            });

        return $this->success($databases);
    }

    public function store(Request $request)
    {
        $account = $request->user()->account;

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:64|regex:/^[a-z][a-z0-9_]*$/',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        // Check database quota
        $currentDbs = Database::where('account_id', $account->id)->count();
        if ($account->package->max_databases != -1 && $currentDbs >= $account->package->max_databases) {
            return $this->error('Database quota exceeded', 403);
        }

        // Prefix with account username
        $dbName = $account->username . '_' . $request->name;

        // Check uniqueness
        if (Database::where('name', $dbName)->exists()) {
            return $this->error('Database name already exists', 422);
        }

        DB::beginTransaction();
        try {
            $database = Database::create([
                'account_id' => $account->id,
                'name' => $dbName,
            ]);

            $this->database->createDatabase($dbName);

            DB::commit();
            return $this->success($database, 'Database created successfully', 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to create database: ' . $e->getMessage(), 500);
        }
    }

    public function show(Request $request, int $id)
    {
        $account = $request->user()->account;

        $database = Database::where('account_id', $account->id)
            ->with('users')
            ->findOrFail($id);

        // Get real-time size
        $database->size = $this->database->getDatabaseSize($database->name);
        $database->size_formatted = $this->formatBytes($database->size);
        $database->table_count = $this->database->getTableCount($database->name);

        return $this->success($database);
    }

    public function destroy(Request $request, int $id)
    {
        $account = $request->user()->account;

        $database = Database::where('account_id', $account->id)->findOrFail($id);

        DB::beginTransaction();
        try {
            // Revoke all user privileges first
            foreach ($database->users as $user) {
                $this->database->revokePrivileges($user->username, $database->name);
            }

            // Drop database
            $this->database->dropDatabase($database->name);

            $database->delete();

            DB::commit();
            return $this->success(null, 'Database deleted successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to delete database: ' . $e->getMessage(), 500);
        }
    }

    // Database Users
    public function users(Request $request)
    {
        $account = $request->user()->account;

        $users = DatabaseUser::where('account_id', $account->id)
            ->with('databases')
            ->get();

        return $this->success($users);
    }

    public function storeUser(Request $request)
    {
        $account = $request->user()->account;

        $validator = Validator::make($request->all(), [
            'username' => 'required|string|max:32|regex:/^[a-z][a-z0-9_]*$/',
            'password' => 'required|string|min:8',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        // Prefix with account username
        $dbUsername = $account->username . '_' . $request->username;

        // Check uniqueness
        if (DatabaseUser::where('username', $dbUsername)->exists()) {
            return $this->error('Database user already exists', 422);
        }

        DB::beginTransaction();
        try {
            $user = DatabaseUser::create([
                'account_id' => $account->id,
                'username' => $dbUsername,
            ]);

            $this->database->createUser($dbUsername, $request->password);

            DB::commit();
            return $this->success($user, 'Database user created successfully', 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to create database user: ' . $e->getMessage(), 500);
        }
    }

    public function updateUserPassword(Request $request, int $id)
    {
        $account = $request->user()->account;

        $user = DatabaseUser::where('account_id', $account->id)->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'password' => 'required|string|min:8',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        try {
            $this->database->changePassword($user->username, $request->password);
            return $this->success($user, 'Password updated successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to update password: ' . $e->getMessage(), 500);
        }
    }

    public function destroyUser(Request $request, int $id)
    {
        $account = $request->user()->account;

        $user = DatabaseUser::where('account_id', $account->id)->findOrFail($id);

        DB::beginTransaction();
        try {
            // Revoke all privileges first
            foreach ($user->databases as $database) {
                $this->database->revokePrivileges($user->username, $database->name);
            }

            // Drop user
            $this->database->dropUser($user->username);

            $user->delete();

            DB::commit();
            return $this->success(null, 'Database user deleted successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to delete database user: ' . $e->getMessage(), 500);
        }
    }

    // Privileges
    public function grant(Request $request, int $databaseId)
    {
        $account = $request->user()->account;

        $database = Database::where('account_id', $account->id)->findOrFail($databaseId);

        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer|exists:database_users,id',
            'privileges' => 'required|array',
            'privileges.*' => 'string|in:SELECT,INSERT,UPDATE,DELETE,CREATE,DROP,INDEX,ALTER,CREATE TEMPORARY TABLES,LOCK TABLES,EXECUTE,CREATE VIEW,SHOW VIEW,CREATE ROUTINE,ALTER ROUTINE,EVENT,TRIGGER,ALL PRIVILEGES',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $user = DatabaseUser::where('account_id', $account->id)->findOrFail($request->user_id);

        try {
            $this->database->grantPrivileges($user->username, $database->name, $request->privileges);

            // Update pivot table
            $database->users()->syncWithoutDetaching([
                $user->id => ['privileges' => json_encode($request->privileges)]
            ]);

            return $this->success(null, 'Privileges granted successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to grant privileges: ' . $e->getMessage(), 500);
        }
    }

    public function revoke(Request $request, int $databaseId)
    {
        $account = $request->user()->account;

        $database = Database::where('account_id', $account->id)->findOrFail($databaseId);

        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer|exists:database_users,id',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $user = DatabaseUser::where('account_id', $account->id)->findOrFail($request->user_id);

        try {
            $this->database->revokePrivileges($user->username, $database->name);
            $database->users()->detach($user->id);

            return $this->success(null, 'Privileges revoked successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to revoke privileges: ' . $e->getMessage(), 500);
        }
    }

    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
}
