<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Models\InstalledApp;
use App\Services\Apps\AppInstallerFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AppController extends Controller
{
    protected AppInstallerFactory $installerFactory;

    public function __construct(AppInstallerFactory $installerFactory)
    {
        $this->installerFactory = $installerFactory;
    }

    public function available()
    {
        $apps = config('freepanel.applications');

        return $this->success(array_map(function ($key, $app) {
            return [
                'id' => $key,
                'name' => $app['name'],
                'version' => $app['version'],
                'description' => $app['description'] ?? '',
                'category' => $app['category'] ?? 'other',
                'icon' => $app['icon'] ?? null,
                'requirements' => $app['requirements'] ?? [],
            ];
        }, array_keys($apps), $apps));
    }

    public function installed(Request $request)
    {
        $account = $request->user()->account;

        $apps = InstalledApp::where('account_id', $account->id)
            ->with('domain:id,name')
            ->get()
            ->map(function ($app) {
                $app->url = 'https://' . $app->domain->name . $app->path;
                $app->has_update = $this->checkForUpdate($app);
                return $app;
            });

        return $this->success($apps);
    }

    public function install(Request $request)
    {
        $account = $request->user()->account;

        $validator = Validator::make($request->all(), [
            'app' => 'required|string',
            'domain_id' => 'required|integer|exists:domains,id',
            'path' => 'nullable|string|max:255',
            'admin_username' => 'required|string|max:64',
            'admin_password' => 'required|string|min:8',
            'admin_email' => 'required|email',
            'site_name' => 'nullable|string|max:255',
            'database_name' => 'nullable|string|max:64',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        // Verify domain ownership
        $domain = Domain::where('account_id', $account->id)->findOrFail($request->domain_id);

        // Verify app exists
        $appConfig = config("freepanel.applications.{$request->app}");
        if (!$appConfig) {
            return $this->error('Application not found', 404);
        }

        $path = $request->path ? '/' . ltrim($request->path, '/') : '';
        $installPath = "/home/{$account->username}/public_html/{$domain->name}" . $path;

        // Check if path is already used
        if (InstalledApp::where('domain_id', $domain->id)->where('path', $path ?: '/')->exists()) {
            return $this->error('An application is already installed at this path', 422);
        }

        DB::beginTransaction();
        try {
            // Create database for app if needed
            $dbName = $request->database_name ?? $account->username . '_' . $request->app . '_' . substr(md5(uniqid()), 0, 6);
            $dbUser = $dbName;
            $dbPass = bin2hex(random_bytes(16));

            // Get installer for this app
            $installer = $this->installerFactory->create($request->app);

            // Install the application
            $result = $installer->install([
                'path' => $installPath,
                'url' => 'https://' . $domain->name . $path,
                'database' => [
                    'name' => $dbName,
                    'user' => $dbUser,
                    'password' => $dbPass,
                    'host' => 'localhost',
                ],
                'admin' => [
                    'username' => $request->admin_username,
                    'password' => $request->admin_password,
                    'email' => $request->admin_email,
                ],
                'site_name' => $request->site_name ?? $domain->name,
                'account' => $account,
            ]);

            // Record installation
            $installedApp = InstalledApp::create([
                'account_id' => $account->id,
                'domain_id' => $domain->id,
                'app_type' => $request->app,
                'version' => $appConfig['version'],
                'path' => $path ?: '/',
                'database_name' => $dbName,
                'settings' => [
                    'admin_username' => $request->admin_username,
                    'admin_email' => $request->admin_email,
                ],
            ]);

            DB::commit();

            return $this->success([
                'app' => $installedApp,
                'admin_url' => $result['admin_url'] ?? null,
                'credentials' => [
                    'username' => $request->admin_username,
                    'password' => $request->admin_password,
                ],
                'database' => [
                    'name' => $dbName,
                    'user' => $dbUser,
                    'password' => $dbPass,
                ],
            ], 'Application installed successfully', 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to install application: ' . $e->getMessage(), 500);
        }
    }

    public function show(Request $request, int $id)
    {
        $account = $request->user()->account;

        $app = InstalledApp::where('account_id', $account->id)
            ->with('domain:id,name')
            ->findOrFail($id);

        $app->url = 'https://' . $app->domain->name . $app->path;
        $app->has_update = $this->checkForUpdate($app);

        // Get current version from installation
        $installer = $this->installerFactory->create($app->app_type);
        $installPath = "/home/{$account->username}/public_html/{$app->domain->name}" . ($app->path !== '/' ? $app->path : '');
        $app->current_version = $installer->getInstalledVersion($installPath) ?? $app->version;

        return $this->success($app);
    }

    public function update(Request $request, int $id)
    {
        $account = $request->user()->account;

        $app = InstalledApp::where('account_id', $account->id)->findOrFail($id);
        $appConfig = config("freepanel.applications.{$app->app_type}");

        if (!$appConfig) {
            return $this->error('Application configuration not found', 500);
        }

        $installPath = "/home/{$account->username}/public_html/{$app->domain->name}" . ($app->path !== '/' ? $app->path : '');

        DB::beginTransaction();
        try {
            $installer = $this->installerFactory->create($app->app_type);

            // Create backup before update
            $backupPath = "/home/{$account->username}/backups/app_{$app->id}_" . date('Y-m-d_His');
            $installer->backup($installPath, $backupPath);

            // Perform update
            $result = $installer->update($installPath, [
                'current_version' => $app->version,
                'target_version' => $appConfig['version'],
            ]);

            $app->update([
                'version' => $appConfig['version'],
                'updated_at' => now(),
            ]);

            DB::commit();
            return $this->success($app, 'Application updated successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to update application: ' . $e->getMessage(), 500);
        }
    }

    public function uninstall(Request $request, int $id)
    {
        $account = $request->user()->account;

        $validator = Validator::make($request->all(), [
            'delete_files' => 'nullable|boolean',
            'delete_database' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $app = InstalledApp::where('account_id', $account->id)->findOrFail($id);
        $installPath = "/home/{$account->username}/public_html/{$app->domain->name}" . ($app->path !== '/' ? $app->path : '');

        DB::beginTransaction();
        try {
            $installer = $this->installerFactory->create($app->app_type);

            // Uninstall application
            $installer->uninstall($installPath, [
                'delete_files' => $request->delete_files ?? true,
                'delete_database' => $request->delete_database ?? true,
                'database_name' => $app->database_name,
            ]);

            $app->delete();

            DB::commit();
            return $this->success(null, 'Application uninstalled successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to uninstall application: ' . $e->getMessage(), 500);
        }
    }

    public function staging(Request $request, int $id)
    {
        $account = $request->user()->account;
        $app = InstalledApp::where('account_id', $account->id)->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'subdomain' => 'required|string|max:63|regex:/^[a-z0-9]([a-z0-9-]*[a-z0-9])?$/',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $productionPath = "/home/{$account->username}/public_html/{$app->domain->name}" . ($app->path !== '/' ? $app->path : '');
        $stagingDomain = $request->subdomain . '.' . $app->domain->name;
        $stagingPath = "/home/{$account->username}/public_html/{$stagingDomain}";

        DB::beginTransaction();
        try {
            $installer = $this->installerFactory->create($app->app_type);

            // Clone to staging
            $result = $installer->cloneToStaging($productionPath, $stagingPath, [
                'production_url' => 'https://' . $app->domain->name . $app->path,
                'staging_url' => 'https://' . $stagingDomain,
                'database_prefix' => 'stg_',
            ]);

            // Create subdomain record if needed
            // ...

            DB::commit();
            return $this->success([
                'staging_url' => 'https://' . $stagingDomain,
                'staging_path' => $stagingPath,
            ], 'Staging environment created successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to create staging: ' . $e->getMessage(), 500);
        }
    }

    protected function checkForUpdate(InstalledApp $app): bool
    {
        $appConfig = config("freepanel.applications.{$app->app_type}");
        if (!$appConfig) {
            return false;
        }

        return version_compare($appConfig['version'], $app->version, '>');
    }
}
