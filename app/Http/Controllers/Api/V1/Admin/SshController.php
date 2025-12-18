<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\SshKey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Validator;

class SshController extends Controller
{
    /**
     * List all SSH keys across accounts
     */
    public function index(Request $request)
    {
        $query = SshKey::with('account:id,username,domain');

        if ($request->has('account_id')) {
            $query->where('account_id', $request->account_id);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $keys = $query->orderBy('created_at', 'desc')->paginate(20);

        return $this->success($keys);
    }

    /**
     * Get SSH access settings
     */
    public function settings()
    {
        // Read sshd config settings
        $sshdConfig = $this->readSshdConfig();

        return $this->success([
            'port' => $sshdConfig['Port'] ?? 22,
            'permit_root_login' => $sshdConfig['PermitRootLogin'] ?? 'prohibit-password',
            'password_authentication' => ($sshdConfig['PasswordAuthentication'] ?? 'yes') === 'yes',
            'pubkey_authentication' => ($sshdConfig['PubkeyAuthentication'] ?? 'yes') === 'yes',
            'max_auth_tries' => (int) ($sshdConfig['MaxAuthTries'] ?? 6),
            'login_grace_time' => (int) ($sshdConfig['LoginGraceTime'] ?? 120),
            'allow_users' => $sshdConfig['AllowUsers'] ?? null,
            'deny_users' => $sshdConfig['DenyUsers'] ?? null,
        ]);
    }

    /**
     * Update SSH settings
     */
    public function updateSettings(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'port' => 'integer|min:1|max:65535',
            'permit_root_login' => 'in:yes,no,prohibit-password,forced-commands-only',
            'password_authentication' => 'boolean',
            'pubkey_authentication' => 'boolean',
            'max_auth_tries' => 'integer|min:1|max:10',
            'login_grace_time' => 'integer|min:30|max:600',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $configUpdates = [];

        if ($request->has('port')) {
            $configUpdates['Port'] = $request->port;
        }
        if ($request->has('permit_root_login')) {
            $configUpdates['PermitRootLogin'] = $request->permit_root_login;
        }
        if ($request->has('password_authentication')) {
            $configUpdates['PasswordAuthentication'] = $request->password_authentication ? 'yes' : 'no';
        }
        if ($request->has('pubkey_authentication')) {
            $configUpdates['PubkeyAuthentication'] = $request->pubkey_authentication ? 'yes' : 'no';
        }
        if ($request->has('max_auth_tries')) {
            $configUpdates['MaxAuthTries'] = $request->max_auth_tries;
        }
        if ($request->has('login_grace_time')) {
            $configUpdates['LoginGraceTime'] = $request->login_grace_time;
        }

        if (!empty($configUpdates)) {
            $this->updateSshdConfig($configUpdates);

            // Validate config before restarting
            $result = Process::run('sudo /usr/sbin/sshd -t');
            if (!$result->successful()) {
                return $this->error('Invalid SSH configuration: ' . $result->errorOutput(), 422);
            }

            // Restart SSH service
            Process::run('sudo /usr/bin/systemctl restart sshd');
        }

        return $this->success(null, 'SSH settings updated');
    }

    /**
     * Enable SSH access for an account
     */
    public function enableSshAccess(Account $account)
    {
        // Create .ssh directory if it doesn't exist
        $sshDir = "/home/{$account->username}/.ssh";
        Process::run("sudo mkdir -p {$sshDir}");
        Process::run("sudo chown {$account->username}:{$account->username} {$sshDir}");
        Process::run("sudo chmod 700 {$sshDir}");

        // Create authorized_keys file
        $authKeys = "{$sshDir}/authorized_keys";
        Process::run("sudo touch {$authKeys}");
        Process::run("sudo chown {$account->username}:{$account->username} {$authKeys}");
        Process::run("sudo chmod 600 {$authKeys}");

        // Add user to allowed users if using AllowUsers
        $this->addAllowedUser($account->username);

        // Ensure user has shell access
        Process::run("sudo usermod -s /bin/bash {$account->username}");

        return $this->success(null, 'SSH access enabled for ' . $account->username);
    }

    /**
     * Disable SSH access for an account
     */
    public function disableSshAccess(Account $account)
    {
        // Remove from allowed users
        $this->removeAllowedUser($account->username);

        // Set shell to nologin
        Process::run("sudo usermod -s /usr/sbin/nologin {$account->username}");

        return $this->success(null, 'SSH access disabled for ' . $account->username);
    }

    /**
     * Add SSH key for an account
     */
    public function addKey(Request $request, Account $account)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'public_key' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        // Validate key format
        if (!SshKey::validatePublicKey($request->public_key)) {
            return $this->error('Invalid SSH public key format', 422);
        }

        try {
            $fingerprint = SshKey::generateFingerprint($request->public_key);
        } catch (\Exception $e) {
            return $this->error('Could not generate key fingerprint: ' . $e->getMessage(), 422);
        }

        // Check for duplicate
        if (SshKey::where('fingerprint', $fingerprint)->exists()) {
            return $this->error('This SSH key is already registered', 422);
        }

        $keyInfo = SshKey::parseKeyInfo($request->public_key);

        $sshKey = SshKey::create([
            'account_id' => $account->id,
            'name' => $request->name,
            'public_key' => $request->public_key,
            'fingerprint' => $fingerprint,
            'key_type' => $keyInfo['type'],
            'key_bits' => $keyInfo['bits'],
        ]);

        // Sync authorized_keys file
        $this->syncAuthorizedKeys($account);

        return $this->success($sshKey, 'SSH key added');
    }

    /**
     * Remove SSH key
     */
    public function removeKey(SshKey $sshKey)
    {
        $account = $sshKey->account;
        $sshKey->delete();

        // Sync authorized_keys file
        $this->syncAuthorizedKeys($account);

        return $this->success(null, 'SSH key removed');
    }

    /**
     * Toggle SSH key active state
     */
    public function toggleKey(SshKey $sshKey)
    {
        $sshKey->update(['is_active' => !$sshKey->is_active]);

        // Sync authorized_keys file
        $this->syncAuthorizedKeys($sshKey->account);

        $status = $sshKey->is_active ? 'enabled' : 'disabled';
        return $this->success($sshKey, "SSH key {$status}");
    }

    /**
     * Get SSH access logs
     */
    public function logs(Request $request)
    {
        $lines = $request->input('lines', 100);

        // Read auth.log for SSH entries
        $result = Process::run("sudo grep -i sshd /var/log/auth.log | tail -{$lines}");

        $logs = [];
        foreach (explode("\n", $result->output()) as $line) {
            if (empty(trim($line))) continue;

            $logs[] = [
                'raw' => $line,
                'parsed' => $this->parseAuthLogLine($line),
            ];
        }

        return $this->success(['logs' => array_reverse($logs)]);
    }

    /**
     * Get currently connected SSH sessions
     */
    public function sessions()
    {
        $result = Process::run('who');
        $sessions = [];

        foreach (explode("\n", $result->output()) as $line) {
            if (empty(trim($line))) continue;

            preg_match('/^(\S+)\s+(\S+)\s+(.+?)\s+\((.+?)\)/', $line, $matches);

            if ($matches) {
                $sessions[] = [
                    'user' => $matches[1],
                    'terminal' => $matches[2],
                    'login_time' => $matches[3],
                    'from' => $matches[4],
                ];
            }
        }

        return $this->success(['sessions' => $sessions]);
    }

    /**
     * Terminate SSH session
     */
    public function terminateSession(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'terminal' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        // Get PID of the session
        $result = Process::run("ps aux | grep '{$request->terminal}' | grep -v grep | awk '{print $2}'");
        $pid = trim($result->output());

        if ($pid) {
            Process::run("sudo kill -9 {$pid}");
            return $this->success(null, 'Session terminated');
        }

        return $this->error('Session not found', 404);
    }

    /**
     * Read sshd_config file
     */
    protected function readSshdConfig(): array
    {
        $result = Process::run('sudo cat /etc/ssh/sshd_config');
        $config = [];

        foreach (explode("\n", $result->output()) as $line) {
            $line = trim($line);
            if (empty($line) || str_starts_with($line, '#')) continue;

            $parts = preg_split('/\s+/', $line, 2);
            if (count($parts) === 2) {
                $config[$parts[0]] = $parts[1];
            }
        }

        return $config;
    }

    /**
     * Update sshd_config file
     */
    protected function updateSshdConfig(array $updates): void
    {
        $result = Process::run('sudo cat /etc/ssh/sshd_config');
        $lines = explode("\n", $result->output());
        $modified = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);

            foreach ($updates as $key => $value) {
                // Match both active and commented settings
                if (preg_match("/^#?\s*{$key}\s/i", $trimmed)) {
                    $line = "{$key} {$value}";
                    unset($updates[$key]);
                    break;
                }
            }

            $modified[] = $line;
        }

        // Add any new settings that weren't in the file
        foreach ($updates as $key => $value) {
            $modified[] = "{$key} {$value}";
        }

        $content = implode("\n", $modified);

        // Write to temp file and move
        $tempFile = tempnam('/tmp', 'sshd_');
        file_put_contents($tempFile, $content);
        Process::run("sudo mv {$tempFile} /etc/ssh/sshd_config");
        Process::run("sudo chmod 644 /etc/ssh/sshd_config");
    }

    /**
     * Sync authorized_keys file for an account
     */
    protected function syncAuthorizedKeys(Account $account): void
    {
        $keys = SshKey::where('account_id', $account->id)
            ->where('is_active', true)
            ->pluck('public_key')
            ->toArray();

        $authKeysFile = "/home/{$account->username}/.ssh/authorized_keys";
        $content = implode("\n", $keys);

        $tempFile = tempnam('/tmp', 'auth_');
        file_put_contents($tempFile, $content);
        Process::run("sudo mv {$tempFile} {$authKeysFile}");
        Process::run("sudo chown {$account->username}:{$account->username} {$authKeysFile}");
        Process::run("sudo chmod 600 {$authKeysFile}");
    }

    /**
     * Add user to AllowUsers if enabled
     */
    protected function addAllowedUser(string $username): void
    {
        $config = $this->readSshdConfig();

        if (isset($config['AllowUsers'])) {
            $users = explode(' ', $config['AllowUsers']);
            if (!in_array($username, $users)) {
                $users[] = $username;
                $this->updateSshdConfig(['AllowUsers' => implode(' ', $users)]);
                Process::run('sudo /usr/bin/systemctl reload sshd');
            }
        }
    }

    /**
     * Remove user from AllowUsers
     */
    protected function removeAllowedUser(string $username): void
    {
        $config = $this->readSshdConfig();

        if (isset($config['AllowUsers'])) {
            $users = array_filter(explode(' ', $config['AllowUsers']), fn($u) => $u !== $username);
            $this->updateSshdConfig(['AllowUsers' => implode(' ', $users)]);
            Process::run('sudo /usr/bin/systemctl reload sshd');
        }
    }

    /**
     * Parse auth.log line
     */
    protected function parseAuthLogLine(string $line): array
    {
        $parsed = [
            'timestamp' => null,
            'event' => 'unknown',
            'user' => null,
            'ip' => null,
        ];

        // Extract timestamp
        if (preg_match('/^(\w+\s+\d+\s+[\d:]+)/', $line, $matches)) {
            $parsed['timestamp'] = $matches[1];
        }

        // Detect event type
        if (str_contains($line, 'Accepted')) {
            $parsed['event'] = 'login_success';
        } elseif (str_contains($line, 'Failed')) {
            $parsed['event'] = 'login_failed';
        } elseif (str_contains($line, 'Disconnected')) {
            $parsed['event'] = 'disconnected';
        } elseif (str_contains($line, 'Invalid user')) {
            $parsed['event'] = 'invalid_user';
        }

        // Extract user
        if (preg_match('/(?:for|user)\s+(\S+)/', $line, $matches)) {
            $parsed['user'] = $matches[1];
        }

        // Extract IP
        if (preg_match('/from\s+(\d+\.\d+\.\d+\.\d+)/', $line, $matches)) {
            $parsed['ip'] = $matches[1];
        }

        return $parsed;
    }
}
