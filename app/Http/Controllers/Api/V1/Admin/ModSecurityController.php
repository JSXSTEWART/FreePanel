<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Validator;

class ModSecurityController extends Controller
{
    protected string $configPath = '/etc/modsecurity/modsecurity.conf';
    protected string $rulesPath = '/etc/modsecurity/rules';
    protected string $auditLogPath = '/var/log/modsec_audit.log';

    /**
     * Get ModSecurity status and configuration
     */
    public function index()
    {
        $status = $this->getModSecurityStatus();
        $config = $this->getConfiguration();
        $rulesets = $this->getInstalledRulesets();

        return $this->success([
            'status' => $status,
            'config' => $config,
            'rulesets' => $rulesets,
        ]);
    }

    /**
     * Enable ModSecurity
     */
    public function enable()
    {
        $this->updateConfig('SecRuleEngine', 'On');
        $this->reloadApache();

        return $this->success(null, 'ModSecurity enabled');
    }

    /**
     * Disable ModSecurity
     */
    public function disable()
    {
        $this->updateConfig('SecRuleEngine', 'Off');
        $this->reloadApache();

        return $this->success(null, 'ModSecurity disabled');
    }

    /**
     * Set detection only mode
     */
    public function detectionOnly()
    {
        $this->updateConfig('SecRuleEngine', 'DetectionOnly');
        $this->reloadApache();

        return $this->success(null, 'ModSecurity set to detection only mode');
    }

    /**
     * Update ModSecurity configuration
     */
    public function updateConfiguration(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'audit_log_level' => 'in:0,1,2,3,4,5,6,7,8,9',
            'request_body_limit' => 'integer|min:0',
            'request_body_no_files_limit' => 'integer|min:0',
            'response_body_limit' => 'integer|min:0',
            'pcre_match_limit' => 'integer|min:1000',
            'pcre_match_limit_recursion' => 'integer|min:1000',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $updates = [];

        if ($request->has('audit_log_level')) {
            $updates['SecAuditLogParts'] = $this->getAuditLogParts($request->audit_log_level);
        }
        if ($request->has('request_body_limit')) {
            $updates['SecRequestBodyLimit'] = $request->request_body_limit;
        }
        if ($request->has('request_body_no_files_limit')) {
            $updates['SecRequestBodyNoFilesLimit'] = $request->request_body_no_files_limit;
        }
        if ($request->has('response_body_limit')) {
            $updates['SecResponseBodyLimit'] = $request->response_body_limit;
        }
        if ($request->has('pcre_match_limit')) {
            $updates['SecPcreMatchLimit'] = $request->pcre_match_limit;
        }
        if ($request->has('pcre_match_limit_recursion')) {
            $updates['SecPcreMatchLimitRecursion'] = $request->pcre_match_limit_recursion;
        }

        foreach ($updates as $key => $value) {
            $this->updateConfig($key, $value);
        }

        $this->reloadApache();

        return $this->success(null, 'Configuration updated');
    }

    /**
     * Get audit log entries
     */
    public function auditLog(Request $request)
    {
        $lines = $request->input('lines', 100);
        $filter = $request->input('filter');

        $cmd = "sudo tail -{$lines} {$this->auditLogPath}";

        if ($filter) {
            $cmd .= " | grep -i " . escapeshellarg($filter);
        }

        $result = Process::run($cmd . " 2>/dev/null");

        $entries = $this->parseAuditLog($result->output());

        return $this->success([
            'entries' => array_reverse($entries),
        ]);
    }

    /**
     * Get blocked requests
     */
    public function blockedRequests(Request $request)
    {
        $hours = $request->input('hours', 24);
        $since = now()->subHours($hours)->format('d/M/Y:H:i:s');

        $result = Process::run(
            "sudo grep 'Access denied' {$this->auditLogPath} 2>/dev/null | tail -500"
        );

        $blocked = [];
        foreach (explode("\n", $result->output()) as $line) {
            if (empty(trim($line))) continue;

            $entry = $this->parseAuditEntry($line);
            if ($entry) {
                $blocked[] = $entry;
            }
        }

        // Group by IP
        $byIp = [];
        foreach ($blocked as $entry) {
            $ip = $entry['client_ip'] ?? 'unknown';
            if (!isset($byIp[$ip])) {
                $byIp[$ip] = [
                    'ip' => $ip,
                    'count' => 0,
                    'requests' => [],
                ];
            }
            $byIp[$ip]['count']++;
            if (count($byIp[$ip]['requests']) < 10) {
                $byIp[$ip]['requests'][] = $entry;
            }
        }

        // Sort by count
        usort($byIp, fn($a, $b) => $b['count'] - $a['count']);

        return $this->success([
            'total_blocked' => count($blocked),
            'by_ip' => array_values($byIp),
        ]);
    }

    /**
     * Add rule exclusion (whitelist)
     */
    public function addExclusion(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|in:rule,ip,uri,domain',
            'value' => 'required|string',
            'rule_id' => 'nullable|integer',
            'comment' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $exclusionFile = '/etc/modsecurity/exclusions.conf';
        $content = '';

        if (file_exists($exclusionFile)) {
            $result = Process::run("sudo cat {$exclusionFile}");
            $content = $result->output();
        }

        $comment = $request->comment ? "# {$request->comment}\n" : '';
        $newRule = '';

        switch ($request->type) {
            case 'rule':
                $newRule = "SecRuleRemoveById {$request->value}";
                break;

            case 'ip':
                $newRule = "SecRule REMOTE_ADDR \"@ipMatch {$request->value}\" \"id:1000001,phase:1,allow,nolog\"";
                break;

            case 'uri':
                $ruleId = $request->rule_id ?? '*';
                $newRule = "SecRule REQUEST_URI \"@beginsWith {$request->value}\" \"id:1000002,phase:1,ctl:ruleRemoveById={$ruleId}\"";
                break;

            case 'domain':
                $newRule = "SecRule SERVER_NAME \"@streq {$request->value}\" \"id:1000003,phase:1,allow,nolog\"";
                break;
        }

        $content .= "\n{$comment}{$newRule}\n";

        $tempFile = tempnam('/tmp', 'modsec_');
        file_put_contents($tempFile, $content);
        Process::run("sudo mv {$tempFile} {$exclusionFile}");
        Process::run("sudo chmod 644 {$exclusionFile}");

        $this->reloadApache();

        return $this->success(null, 'Exclusion added');
    }

    /**
     * Get exclusions
     */
    public function getExclusions()
    {
        $exclusionFile = '/etc/modsecurity/exclusions.conf';
        $exclusions = [];

        if (file_exists($exclusionFile)) {
            $result = Process::run("sudo cat {$exclusionFile}");
            $lines = explode("\n", $result->output());

            $currentComment = '';
            foreach ($lines as $line) {
                $line = trim($line);
                if (str_starts_with($line, '#')) {
                    $currentComment = substr($line, 1);
                    continue;
                }

                if (str_starts_with($line, 'Sec')) {
                    $exclusions[] = [
                        'rule' => $line,
                        'comment' => trim($currentComment),
                    ];
                    $currentComment = '';
                }
            }
        }

        return $this->success(['exclusions' => $exclusions]);
    }

    /**
     * Remove exclusion
     */
    public function removeExclusion(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'rule' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $exclusionFile = '/etc/modsecurity/exclusions.conf';

        if (file_exists($exclusionFile)) {
            $result = Process::run("sudo cat {$exclusionFile}");
            $content = $result->output();

            // Remove the rule line (and preceding comment if any)
            $pattern = '/(?:#[^\n]*\n)?' . preg_quote($request->rule, '/') . '\n?/';
            $content = preg_replace($pattern, '', $content);

            $tempFile = tempnam('/tmp', 'modsec_');
            file_put_contents($tempFile, $content);
            Process::run("sudo mv {$tempFile} {$exclusionFile}");

            $this->reloadApache();
        }

        return $this->success(null, 'Exclusion removed');
    }

    /**
     * Install OWASP Core Rule Set
     */
    public function installOwaspCrs()
    {
        // Download and install CRS
        $crsUrl = 'https://github.com/coreruleset/coreruleset/archive/refs/tags/v3.3.4.tar.gz';
        $crsDir = '/etc/modsecurity/crs';

        $result = Process::timeout(120)->run(
            "sudo wget -q {$crsUrl} -O /tmp/crs.tar.gz && " .
            "sudo mkdir -p {$crsDir} && " .
            "sudo tar -xzf /tmp/crs.tar.gz -C {$crsDir} --strip-components=1 && " .
            "sudo cp {$crsDir}/crs-setup.conf.example {$crsDir}/crs-setup.conf && " .
            "sudo rm /tmp/crs.tar.gz"
        );

        if (!$result->successful()) {
            return $this->error('Failed to install OWASP CRS: ' . $result->errorOutput(), 500);
        }

        // Include CRS in ModSecurity config
        $includeContent = "\nInclude {$crsDir}/crs-setup.conf\nInclude {$crsDir}/rules/*.conf\n";
        Process::run("echo " . escapeshellarg($includeContent) . " | sudo tee -a {$this->configPath}");

        $this->reloadApache();

        return $this->success(null, 'OWASP Core Rule Set installed');
    }

    /**
     * Get ModSecurity status
     */
    protected function getModSecurityStatus(): array
    {
        // Check if mod_security is loaded
        $result = Process::run("apachectl -M 2>/dev/null | grep security");
        $moduleLoaded = str_contains($result->output(), 'security');

        // Get engine status from config
        $engineStatus = 'Off';
        if (file_exists($this->configPath)) {
            $result = Process::run("sudo grep -E '^SecRuleEngine' {$this->configPath}");
            if (preg_match('/SecRuleEngine\s+(\w+)/', $result->output(), $matches)) {
                $engineStatus = $matches[1];
            }
        }

        return [
            'module_loaded' => $moduleLoaded,
            'engine_status' => $engineStatus,
            'is_active' => $moduleLoaded && $engineStatus === 'On',
        ];
    }

    /**
     * Get current configuration
     */
    protected function getConfiguration(): array
    {
        $config = [
            'SecRuleEngine' => 'Off',
            'SecRequestBodyLimit' => 13107200,
            'SecRequestBodyNoFilesLimit' => 131072,
            'SecResponseBodyLimit' => 524288,
            'SecPcreMatchLimit' => 1000,
            'SecPcreMatchLimitRecursion' => 1000,
        ];

        if (!file_exists($this->configPath)) {
            return $config;
        }

        $result = Process::run("sudo cat {$this->configPath}");

        foreach (explode("\n", $result->output()) as $line) {
            $line = trim($line);
            if (empty($line) || str_starts_with($line, '#')) continue;

            foreach (array_keys($config) as $key) {
                if (preg_match("/^{$key}\s+(.+)$/", $line, $matches)) {
                    $config[$key] = trim($matches[1]);
                }
            }
        }

        return $config;
    }

    /**
     * Get installed rulesets
     */
    protected function getInstalledRulesets(): array
    {
        $rulesets = [];

        // Check for OWASP CRS
        if (is_dir('/etc/modsecurity/crs')) {
            $rulesets[] = [
                'name' => 'OWASP Core Rule Set',
                'path' => '/etc/modsecurity/crs',
                'installed' => true,
            ];
        }

        // Check for custom rules
        if (is_dir($this->rulesPath)) {
            $result = Process::run("ls {$this->rulesPath}/*.conf 2>/dev/null | wc -l");
            $customRuleCount = (int) trim($result->output());

            if ($customRuleCount > 0) {
                $rulesets[] = [
                    'name' => 'Custom Rules',
                    'path' => $this->rulesPath,
                    'count' => $customRuleCount,
                ];
            }
        }

        return $rulesets;
    }

    /**
     * Update a config value
     */
    protected function updateConfig(string $key, string $value): void
    {
        if (!file_exists($this->configPath)) {
            return;
        }

        $result = Process::run("sudo cat {$this->configPath}");
        $content = $result->output();

        // Check if key exists
        if (preg_match("/^{$key}\s/m", $content)) {
            // Update existing
            $content = preg_replace("/^{$key}\s+.+$/m", "{$key} {$value}", $content);
        } else {
            // Add new
            $content .= "\n{$key} {$value}\n";
        }

        $tempFile = tempnam('/tmp', 'modsec_');
        file_put_contents($tempFile, $content);
        Process::run("sudo mv {$tempFile} {$this->configPath}");
    }

    /**
     * Reload Apache
     */
    protected function reloadApache(): void
    {
        Process::run("sudo /usr/bin/systemctl reload apache2");
    }

    /**
     * Get audit log parts for level
     */
    protected function getAuditLogParts(int $level): string
    {
        $parts = ['A', 'B', 'C', 'E', 'F', 'H', 'I', 'J', 'K', 'Z'];
        $selected = array_slice($parts, 0, min($level + 1, count($parts)));
        return implode('', $selected);
    }

    /**
     * Parse audit log
     */
    protected function parseAuditLog(string $content): array
    {
        $entries = [];
        $current = [];

        foreach (explode("\n", $content) as $line) {
            if (preg_match('/^--[a-f0-9]+-[A-Z]--/', $line)) {
                if (!empty($current)) {
                    $entries[] = $current;
                }
                $current = ['raw' => $line];
            } elseif (!empty($current)) {
                $current['raw'] .= "\n" . $line;
            }
        }

        if (!empty($current)) {
            $entries[] = $current;
        }

        return array_map([$this, 'parseAuditEntry'], $entries);
    }

    /**
     * Parse single audit entry
     */
    protected function parseAuditEntry($entry): ?array
    {
        $raw = is_array($entry) ? $entry['raw'] : $entry;

        $parsed = [
            'timestamp' => null,
            'client_ip' => null,
            'request_uri' => null,
            'rule_id' => null,
            'message' => null,
        ];

        // Extract timestamp
        if (preg_match('/\[(\d+\/\w+\/\d+:\d+:\d+:\d+)/', $raw, $matches)) {
            $parsed['timestamp'] = $matches[1];
        }

        // Extract client IP
        if (preg_match('/(\d+\.\d+\.\d+\.\d+)/', $raw, $matches)) {
            $parsed['client_ip'] = $matches[1];
        }

        // Extract URI
        if (preg_match('/(?:GET|POST|PUT|DELETE|HEAD|OPTIONS)\s+([^\s]+)/', $raw, $matches)) {
            $parsed['request_uri'] = $matches[1];
        }

        // Extract rule ID
        if (preg_match('/\[id "(\d+)"\]/', $raw, $matches)) {
            $parsed['rule_id'] = $matches[1];
        }

        // Extract message
        if (preg_match('/\[msg "([^"]+)"\]/', $raw, $matches)) {
            $parsed['message'] = $matches[1];
        }

        return $parsed;
    }
}
