<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Package;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

class SetupController extends Controller
{
    /**
     * Check if initial setup is required
     */
    public function status()
    {
        // Check if any admin user exists
        $hasAdmin = User::where('role', 'admin')->exists();

        // Check if default package exists
        $hasDefaultPackage = Package::where('is_default', true)->exists();

        // Check system requirements
        $requirements = $this->checkRequirements();

        return response()->json([
            'setup_required' => !$hasAdmin,
            'has_admin' => $hasAdmin,
            'has_default_package' => $hasDefaultPackage,
            'requirements' => $requirements,
            'version' => config('app.version', '1.0.0'),
        ]);
    }

    /**
     * Check system requirements
     */
    public function requirements()
    {
        $requirements = $this->checkRequirements();

        return response()->json([
            'requirements' => $requirements,
            'all_met' => collect($requirements)->every(fn($r) => $r['status']),
        ]);
    }

    /**
     * Complete initial setup
     */
    public function initialize(Request $request)
    {
        // Check if setup already completed
        if (User::where('role', 'admin')->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Setup has already been completed.',
            ], 400);
        }

        // Validate input
        $validator = Validator::make($request->all(), [
            'admin_username' => 'required|string|min:3|max:16|regex:/^[a-z0-9]+$/|unique:users,username',
            'admin_email' => 'required|email|unique:users,email',
            'admin_password' => 'required|string|min:8|confirmed',
            'server_hostname' => 'nullable|string|max:255',
            'server_ip' => 'nullable|ip',
            'nameservers' => 'nullable|array',
            'nameservers.*' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Create admin user
            $admin = User::create([
                'uuid' => Str::uuid(),
                'username' => $request->admin_username,
                'email' => $request->admin_email,
                'password' => Hash::make($request->admin_password),
                'role' => 'admin',
                'email_verified_at' => now(),
            ]);

            // Create default package if not exists
            if (!Package::where('is_default', true)->exists()) {
                Package::create([
                    'name' => 'Default',
                    'description' => 'Default hosting package',
                    'disk_quota' => 10 * 1024 * 1024 * 1024, // 10GB
                    'bandwidth' => 100 * 1024 * 1024 * 1024, // 100GB
                    'max_domains' => 5,
                    'max_subdomains' => 20,
                    'max_email_accounts' => 50,
                    'max_databases' => 10,
                    'max_ftp_accounts' => 10,
                    'is_default' => true,
                ]);
            }

            // Save server configuration if provided
            if ($request->server_hostname) {
                $this->updateConfig('app.hostname', $request->server_hostname);
            }

            if ($request->server_ip) {
                $this->updateConfig('app.server_ip', $request->server_ip);
            }

            if ($request->nameservers) {
                $this->updateConfig('app.nameservers', array_filter($request->nameservers));
            }

            // Clear config cache
            Artisan::call('config:clear');

            DB::commit();

            // Generate token for auto-login
            $token = $admin->createToken('api')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Setup completed successfully',
                'user' => [
                    'id' => $admin->id,
                    'uuid' => $admin->uuid,
                    'username' => $admin->username,
                    'email' => $admin->email,
                    'role' => $admin->role,
                ],
                'token' => $token,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Setup failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Check system requirements
     */
    private function checkRequirements(): array
    {
        return [
            [
                'name' => 'PHP Version',
                'required' => '8.1+',
                'current' => PHP_VERSION,
                'status' => version_compare(PHP_VERSION, '8.1.0', '>='),
            ],
            [
                'name' => 'PDO Extension',
                'required' => 'Required',
                'current' => extension_loaded('pdo') ? 'Installed' : 'Not installed',
                'status' => extension_loaded('pdo'),
            ],
            [
                'name' => 'OpenSSL Extension',
                'required' => 'Required',
                'current' => extension_loaded('openssl') ? 'Installed' : 'Not installed',
                'status' => extension_loaded('openssl'),
            ],
            [
                'name' => 'Mbstring Extension',
                'required' => 'Required',
                'current' => extension_loaded('mbstring') ? 'Installed' : 'Not installed',
                'status' => extension_loaded('mbstring'),
            ],
            [
                'name' => 'JSON Extension',
                'required' => 'Required',
                'current' => extension_loaded('json') ? 'Installed' : 'Not installed',
                'status' => extension_loaded('json'),
            ],
            [
                'name' => 'BCMath Extension',
                'required' => 'Required',
                'current' => extension_loaded('bcmath') ? 'Installed' : 'Not installed',
                'status' => extension_loaded('bcmath'),
            ],
            [
                'name' => 'Ctype Extension',
                'required' => 'Required',
                'current' => extension_loaded('ctype') ? 'Installed' : 'Not installed',
                'status' => extension_loaded('ctype'),
            ],
            [
                'name' => 'Fileinfo Extension',
                'required' => 'Required',
                'current' => extension_loaded('fileinfo') ? 'Installed' : 'Not installed',
                'status' => extension_loaded('fileinfo'),
            ],
            [
                'name' => 'Storage Directory',
                'required' => 'Writable',
                'current' => is_writable(storage_path()) ? 'Writable' : 'Not writable',
                'status' => is_writable(storage_path()),
            ],
            [
                'name' => 'Cache Directory',
                'required' => 'Writable',
                'current' => is_writable(storage_path('framework/cache')) ? 'Writable' : 'Not writable',
                'status' => is_writable(storage_path('framework/cache')),
            ],
            [
                'name' => 'Database Connection',
                'required' => 'Connected',
                'current' => $this->checkDatabaseConnection() ? 'Connected' : 'Not connected',
                'status' => $this->checkDatabaseConnection(),
            ],
        ];
    }

    /**
     * Check database connection
     */
    private function checkDatabaseConnection(): bool
    {
        try {
            DB::connection()->getPdo();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Update configuration value
     */
    private function updateConfig(string $key, $value): void
    {
        if (is_string($value)) {
            $value = trim($value);

            if ($value === '') {
                return;
            }
        }

        if ($key === 'app.nameservers' && is_array($value)) {
            $value = $this->formatNameservers($value);

            if (empty($value)) {
                return;
            }
        }

        config([$key => $value]);

        $this->mirrorFreepanelConfig($key, $value);

        if ($key !== 'app.nameservers') {
            $envKey = strtoupper(str_replace('.', '_', $key));
            $this->writeEnvironmentValue($envKey, $this->stringifyValue($value));
        }
    }

    /**
     * Mirror settings into the freepanel config namespace when applicable.
     */
    private function mirrorFreepanelConfig(string $key, $value): void
    {
        if ($key === 'app.hostname') {
            config(['freepanel.hostname' => $value]);
            $this->writeEnvironmentValue('FREEPANEL_HOSTNAME', $this->stringifyValue($value));
        }

        if ($key === 'app.server_ip') {
            config(['freepanel.server_ip' => $value]);
            $this->writeEnvironmentValue('FREEPANEL_SERVER_IP', $this->stringifyValue($value));
        }

        if ($key === 'app.nameservers' && is_array($value)) {
            $nameservers = $value;

            config(['freepanel.nameservers' => $nameservers]);

            $this->writeEnvironmentValue('FREEPANEL_NAMESERVERS', json_encode($nameservers, JSON_UNESCAPED_SLASHES));
        }
    }

    /**
     * Ensure nameservers have sequential ns* keys.
     */
    private function formatNameservers(array $nameservers): array
    {
        $formatted = [];
        $counter = 1;

        foreach (array_values($nameservers) as $server) {
            $server = is_string($server) ? trim($server) : $server;

            if ($server === null || $server === '' || $server === false) {
                continue;
            }

            $formatted['ns' . $counter] = $server;
            $counter++;
        }

        return $formatted;
    }

    /**
     * Persist a single environment value to the current environment file.
     */
    private function writeEnvironmentValue(string $envKey, string $value): void
    {
        $envPath = app()->environmentFilePath();
        $envContent = file_exists($envPath) ? file_get_contents($envPath) : '';
        $pattern = '/^' . preg_quote($envKey, '/') . '=.*$/m';
        $replacement = $envKey . '=' . $value;

        if (preg_match($pattern, $envContent)) {
            $envContent = preg_replace($pattern, $replacement, $envContent);
        } else {
            if ($envContent !== '' && !str_ends_with($envContent, "\n")) {
                $envContent .= "\n";
            }

            $envContent .= $replacement . "\n";
        }

        file_put_contents($envPath, $envContent, LOCK_EX);
    }

    /**
     * Normalize values for environment storage.
     */
    private function stringifyValue($value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_SLASHES);
        }

        return (string) $value;
    }
}
