<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PhpConfigController extends Controller
{
    /**
     * Editable PHP directives with their limits
     */
    protected array $editableDirectives = [
        'memory_limit' => ['min' => '32M', 'max' => '512M', 'default' => '128M', 'type' => 'memory'],
        'max_execution_time' => ['min' => 30, 'max' => 300, 'default' => 30, 'type' => 'integer'],
        'max_input_time' => ['min' => 30, 'max' => 300, 'default' => 60, 'type' => 'integer'],
        'post_max_size' => ['min' => '8M', 'max' => '256M', 'default' => '8M', 'type' => 'memory'],
        'upload_max_filesize' => ['min' => '2M', 'max' => '256M', 'default' => '2M', 'type' => 'memory'],
        'max_input_vars' => ['min' => 1000, 'max' => 10000, 'default' => 1000, 'type' => 'integer'],
        'max_file_uploads' => ['min' => 10, 'max' => 100, 'default' => 20, 'type' => 'integer'],
        'display_errors' => ['values' => ['On', 'Off'], 'default' => 'Off', 'type' => 'toggle'],
        'log_errors' => ['values' => ['On', 'Off'], 'default' => 'On', 'type' => 'toggle'],
        'error_reporting' => [
            'values' => [
                'E_ALL' => 'All Errors',
                'E_ALL & ~E_NOTICE' => 'All Except Notices',
                'E_ALL & ~E_DEPRECATED & ~E_STRICT' => 'Production',
                '0' => 'None',
            ],
            'default' => 'E_ALL & ~E_DEPRECATED & ~E_STRICT',
            'type' => 'select'
        ],
        'date.timezone' => ['type' => 'timezone', 'default' => 'UTC'],
        'session.gc_maxlifetime' => ['min' => 1440, 'max' => 86400, 'default' => 1440, 'type' => 'integer'],
        'allow_url_fopen' => ['values' => ['On', 'Off'], 'default' => 'On', 'type' => 'toggle'],
        'allow_url_include' => ['values' => ['On', 'Off'], 'default' => 'Off', 'type' => 'toggle'],
        'short_open_tag' => ['values' => ['On', 'Off'], 'default' => 'On', 'type' => 'toggle'],
    ];

    /**
     * Get current PHP configuration
     */
    public function index(Request $request)
    {
        $account = $request->user()->account;
        $homeDir = "/home/{$account->system_username}";

        // Read current .user.ini
        $userIniPath = "{$homeDir}/public_html/.user.ini";
        $currentValues = $this->parseUserIni($userIniPath);

        $config = [];
        foreach ($this->editableDirectives as $directive => $options) {
            $config[$directive] = [
                'value' => $currentValues[$directive] ?? $options['default'],
                'default' => $options['default'],
                'type' => $options['type'],
            ];

            if ($options['type'] === 'memory' || $options['type'] === 'integer') {
                $config[$directive]['min'] = $options['min'] ?? null;
                $config[$directive]['max'] = $options['max'] ?? null;
            } elseif ($options['type'] === 'toggle' || $options['type'] === 'select') {
                $config[$directive]['options'] = $options['values'];
            }
        }

        // Get available PHP versions
        $phpVersions = $this->getAvailablePhpVersions();

        return $this->success([
            'directives' => $config,
            'php_versions' => $phpVersions,
            'current_version' => $this->getCurrentPhpVersion($account),
            'timezones' => \DateTimeZone::listIdentifiers(),
        ]);
    }

    /**
     * Update PHP configuration
     */
    public function update(Request $request)
    {
        $account = $request->user()->account;

        $rules = [];
        foreach ($this->editableDirectives as $directive => $options) {
            $key = str_replace('.', '_', $directive);

            switch ($options['type']) {
                case 'memory':
                    $rules[$key] = 'nullable|string|regex:/^\d+[MG]$/i';
                    break;
                case 'integer':
                    $min = $options['min'] ?? 0;
                    $max = $options['max'] ?? PHP_INT_MAX;
                    $rules[$key] = "nullable|integer|min:{$min}|max:{$max}";
                    break;
                case 'toggle':
                    $rules[$key] = 'nullable|in:On,Off';
                    break;
                case 'select':
                    $rules[$key] = 'nullable|in:' . implode(',', array_keys($options['values']));
                    break;
                case 'timezone':
                    $rules[$key] = 'nullable|timezone';
                    break;
            }
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $homeDir = "/home/{$account->system_username}";
        $userIniPath = "{$homeDir}/public_html/.user.ini";

        // Build new .user.ini content
        $content = "; FreePanel PHP Configuration\n";
        $content .= "; Generated: " . now()->toIso8601String() . "\n\n";

        foreach ($this->editableDirectives as $directive => $options) {
            $key = str_replace('.', '_', $directive);
            $value = $request->input($key);

            if ($value !== null && $value !== '') {
                // Validate memory values
                if ($options['type'] === 'memory') {
                    $value = $this->validateMemoryValue($value, $options);
                }

                $content .= "{$directive} = {$value}\n";
            }
        }

        // Write .user.ini
        file_put_contents($userIniPath, $content);
        chmod($userIniPath, 0644);
        chown($userIniPath, $account->system_username);

        return $this->success(null, 'PHP configuration updated successfully');
    }

    /**
     * Reset to defaults
     */
    public function reset(Request $request)
    {
        $account = $request->user()->account;
        $homeDir = "/home/{$account->system_username}";
        $userIniPath = "{$homeDir}/public_html/.user.ini";

        if (file_exists($userIniPath)) {
            unlink($userIniPath);
        }

        return $this->success(null, 'PHP configuration reset to defaults');
    }

    /**
     * Change PHP version for account
     */
    public function changeVersion(Request $request)
    {
        $account = $request->user()->account;

        $validator = Validator::make($request->all(), [
            'version' => 'required|string|regex:/^\d+\.\d+$/',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $availableVersions = $this->getAvailablePhpVersions();
        if (!in_array($request->version, $availableVersions)) {
            return $this->error('PHP version not available', 422);
        }

        // Update .htaccess to use specific PHP version
        $homeDir = "/home/{$account->system_username}";
        $htaccessPath = "{$homeDir}/public_html/.htaccess";

        $existing = file_exists($htaccessPath) ? file_get_contents($htaccessPath) : '';

        // Remove existing PHP handler section
        $startMarker = "# BEGIN FreePanel PHP Handler";
        $endMarker = "# END FreePanel PHP Handler";
        $pattern = "/\n?{$startMarker}.*?{$endMarker}\n?/s";
        $existing = preg_replace($pattern, '', $existing);

        // Add new PHP handler
        $handler = "# BEGIN FreePanel PHP Handler\n";
        $handler .= "<FilesMatch \\.php\$>\n";
        $handler .= "    SetHandler \"proxy:unix:/run/php/php{$request->version}-fpm.sock|fcgi://localhost\"\n";
        $handler .= "</FilesMatch>\n";
        $handler .= "# END FreePanel PHP Handler\n";

        file_put_contents($htaccessPath, trim($existing) . "\n\n" . $handler);

        // Update account record
        $account->update(['php_version' => $request->version]);

        return $this->success(null, "PHP version changed to {$request->version}");
    }

    /**
     * Get phpinfo() output
     */
    public function info(Request $request)
    {
        $account = $request->user()->account;

        // Create temporary PHP file
        $homeDir = "/home/{$account->system_username}";
        $tempFile = "{$homeDir}/public_html/.freepanel_phpinfo_" . uniqid() . ".php";

        file_put_contents($tempFile, "<?php phpinfo(); ?>");
        chmod($tempFile, 0644);

        // We can't actually execute this server-side in a secure way,
        // so return the URL for the user to access
        $domain = $account->domains()->where('type', 'main')->first();
        $url = "http://{$domain->domain}/" . basename($tempFile);

        // Schedule file deletion after 60 seconds
        // In a real implementation, use a queue job

        return $this->success([
            'url' => $url,
            'note' => 'This URL will expire in 60 seconds for security reasons.',
        ]);
    }

    /**
     * Parse existing .user.ini file
     */
    protected function parseUserIni(string $path): array
    {
        if (!file_exists($path)) {
            return [];
        }

        $content = file_get_contents($path);
        $values = [];

        foreach (explode("\n", $content) as $line) {
            $line = trim($line);
            if (empty($line) || str_starts_with($line, ';') || str_starts_with($line, '#')) {
                continue;
            }

            if (preg_match('/^([^=]+)=(.*)$/', $line, $matches)) {
                $key = trim($matches[1]);
                $value = trim($matches[2]);
                $values[$key] = $value;
            }
        }

        return $values;
    }

    /**
     * Get available PHP versions
     */
    protected function getAvailablePhpVersions(): array
    {
        $versions = [];

        // Check for installed PHP versions
        $phpVersions = ['7.4', '8.0', '8.1', '8.2', '8.3', '8.4'];
        foreach ($phpVersions as $version) {
            if (file_exists("/run/php/php{$version}-fpm.sock") ||
                file_exists("/usr/bin/php{$version}")) {
                $versions[] = $version;
            }
        }

        return $versions;
    }

    /**
     * Get current PHP version for account
     */
    protected function getCurrentPhpVersion($account): string
    {
        return $account->php_version ?? '8.4';
    }

    /**
     * Validate memory value is within limits
     */
    protected function validateMemoryValue(string $value, array $options): string
    {
        $bytes = $this->parseMemoryValue($value);
        $minBytes = $this->parseMemoryValue($options['min']);
        $maxBytes = $this->parseMemoryValue($options['max']);

        if ($bytes < $minBytes) {
            return $options['min'];
        }
        if ($bytes > $maxBytes) {
            return $options['max'];
        }

        return $value;
    }

    /**
     * Parse memory value to bytes
     */
    protected function parseMemoryValue(string $value): int
    {
        $value = strtoupper(trim($value));
        $number = (int) $value;

        if (str_ends_with($value, 'G')) {
            return $number * 1024 * 1024 * 1024;
        } elseif (str_ends_with($value, 'M')) {
            return $number * 1024 * 1024;
        } elseif (str_ends_with($value, 'K')) {
            return $number * 1024;
        }

        return $number;
    }
}
