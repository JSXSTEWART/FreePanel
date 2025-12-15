<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use App\Models\FtpAccount;
use App\Services\Ftp\FtpInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class FtpController extends Controller
{
    protected FtpInterface $ftp;

    public function __construct(FtpInterface $ftp)
    {
        $this->ftp = $ftp;
    }

    public function index(Request $request)
    {
        $account = $request->user()->account;

        $ftpAccounts = FtpAccount::where('account_id', $account->id)
            ->get()
            ->map(function ($ftp) use ($account) {
                $ftp->full_username = $ftp->username . '@' . $account->domain;
                return $ftp;
            });

        return $this->success($ftpAccounts);
    }

    public function store(Request $request)
    {
        $account = $request->user()->account;

        $validator = Validator::make($request->all(), [
            'username' => 'required|string|max:32|regex:/^[a-z][a-z0-9_]*$/',
            'password' => 'required|string|min:8',
            'directory' => 'required|string|max:500',
            'quota' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        // Check FTP account quota
        $currentFtp = FtpAccount::where('account_id', $account->id)->count();
        if ($account->package->max_ftp_accounts != -1 && $currentFtp >= $account->package->max_ftp_accounts) {
            return $this->error('FTP account quota exceeded', 403);
        }

        // Construct full username
        $username = $request->username . '@' . $account->domain;

        // Check uniqueness
        if (FtpAccount::where('username', $username)->exists()) {
            return $this->error('FTP username already exists', 422);
        }

        // Validate and resolve directory
        $basePath = "/home/{$account->username}";
        $directory = $this->resolveDirectory($basePath, $request->directory);

        if (!str_starts_with($directory, $basePath)) {
            return $this->error('Directory must be within your home directory', 422);
        }

        DB::beginTransaction();
        try {
            $ftpAccount = FtpAccount::create([
                'account_id' => $account->id,
                'username' => $username,
                'password' => Hash::make($request->password),
                'directory' => $directory,
                'quota' => $request->quota ?? 0, // 0 = unlimited
            ]);

            // Create FTP account on server
            $this->ftp->createAccount($ftpAccount, $request->password);

            DB::commit();

            $ftpAccount->full_username = $username;
            return $this->success($ftpAccount, 'FTP account created successfully', 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to create FTP account: ' . $e->getMessage(), 500);
        }
    }

    public function show(Request $request, int $id)
    {
        $account = $request->user()->account;

        $ftpAccount = FtpAccount::where('account_id', $account->id)->findOrFail($id);
        $ftpAccount->full_username = $ftpAccount->username;

        // Get connection info
        $ftpAccount->connection_info = [
            'host' => config('freepanel.hostname'),
            'port' => 21,
            'username' => $ftpAccount->username,
            'protocol' => 'FTP/FTPS',
        ];

        return $this->success($ftpAccount);
    }

    public function update(Request $request, int $id)
    {
        $account = $request->user()->account;

        $ftpAccount = FtpAccount::where('account_id', $account->id)->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'password' => 'nullable|string|min:8',
            'directory' => 'nullable|string|max:500',
            'quota' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $basePath = "/home/{$account->username}";

        DB::beginTransaction();
        try {
            $updates = [];

            if ($request->has('directory')) {
                $directory = $this->resolveDirectory($basePath, $request->directory);
                if (!str_starts_with($directory, $basePath)) {
                    return $this->error('Directory must be within your home directory', 422);
                }
                $updates['directory'] = $directory;
            }

            if ($request->has('quota')) {
                $updates['quota'] = $request->quota;
            }

            if ($request->filled('password')) {
                $updates['password'] = Hash::make($request->password);
                $this->ftp->updatePassword($ftpAccount, $request->password);
            }

            if (!empty($updates)) {
                $ftpAccount->update($updates);
                $this->ftp->updateAccount($ftpAccount);
            }

            DB::commit();
            return $this->success($ftpAccount, 'FTP account updated successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to update FTP account: ' . $e->getMessage(), 500);
        }
    }

    public function destroy(Request $request, int $id)
    {
        $account = $request->user()->account;

        $ftpAccount = FtpAccount::where('account_id', $account->id)->findOrFail($id);

        DB::beginTransaction();
        try {
            $this->ftp->deleteAccount($ftpAccount);
            $ftpAccount->delete();

            DB::commit();
            return $this->success(null, 'FTP account deleted successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to delete FTP account: ' . $e->getMessage(), 500);
        }
    }

    public function sessions(Request $request)
    {
        $account = $request->user()->account;

        try {
            $sessions = $this->ftp->getActiveSessions($account);
            return $this->success($sessions);
        } catch (\Exception $e) {
            return $this->error('Failed to get FTP sessions: ' . $e->getMessage(), 500);
        }
    }

    public function killSession(Request $request, string $sessionId)
    {
        $account = $request->user()->account;

        try {
            $this->ftp->killSession($sessionId, $account);
            return $this->success(null, 'FTP session terminated');
        } catch (\Exception $e) {
            return $this->error('Failed to terminate FTP session: ' . $e->getMessage(), 500);
        }
    }

    protected function resolveDirectory(string $basePath, string $directory): string
    {
        // Handle relative paths
        if (!str_starts_with($directory, '/')) {
            $directory = $basePath . '/' . $directory;
        } elseif (!str_starts_with($directory, $basePath)) {
            $directory = $basePath . $directory;
        }

        // Resolve . and ..
        $parts = [];
        foreach (explode('/', $directory) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                array_pop($parts);
            } else {
                $parts[] = $part;
            }
        }

        return '/' . implode('/', $parts);
    }
}
