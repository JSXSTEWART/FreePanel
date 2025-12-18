<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Validator;

class RemoteMysqlController extends Controller
{
    /**
     * List remote MySQL access hosts
     */
    public function index(Request $request)
    {
        $account = $request->user()->account;
        $prefix = $account->username . '_';

        // Get all databases for this account
        $databases = $account->databases;
        $remoteHosts = [];

        foreach ($databases as $database) {
            $hosts = $this->getRemoteHosts($database->name, $prefix);
            foreach ($hosts as $host) {
                $key = $database->name . '_' . $host['host'];
                if (!isset($remoteHosts[$key])) {
                    $remoteHosts[$key] = [
                        'database' => $database->name,
                        'host' => $host['host'],
                        'users' => [],
                    ];
                }
                $remoteHosts[$key]['users'][] = $host['user'];
            }
        }

        return $this->success([
            'remote_hosts' => array_values($remoteHosts),
        ]);
    }

    /**
     * Add remote MySQL access
     */
    public function store(Request $request)
    {
        $account = $request->user()->account;

        $validator = Validator::make($request->all(), [
            'host' => 'required|string|max:255',
            'database' => 'nullable|string',
            'user' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        // Validate host format (IP, CIDR, domain, or %)
        $host = $request->host;
        if (!$this->validateHost($host)) {
            return $this->error('Invalid host format. Use IP address, CIDR notation, domain, or % for wildcard', 422);
        }

        $prefix = $account->username . '_';

        // If specific database provided, grant on that database
        if ($request->database) {
            $database = $account->databases()->where('name', $request->database)->first();
            if (!$database) {
                return $this->error('Database not found', 404);
            }

            $user = $request->user ?: $prefix . 'user';
            $this->grantRemoteAccess($database->name, $user, $host);
        } else {
            // Grant on all user's databases
            foreach ($account->databases as $database) {
                foreach ($account->databaseUsers as $dbUser) {
                    $this->grantRemoteAccess($database->name, $dbUser->username, $host);
                }
            }
        }

        Process::run('sudo /usr/bin/mysql -e "FLUSH PRIVILEGES"');

        return $this->success(null, 'Remote MySQL access granted');
    }

    /**
     * Remove remote MySQL access
     */
    public function destroy(Request $request)
    {
        $account = $request->user()->account;

        $validator = Validator::make($request->all(), [
            'host' => 'required|string',
            'database' => 'nullable|string',
            'user' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $host = $request->host;
        $prefix = $account->username . '_';

        if ($request->database) {
            $database = $account->databases()->where('name', $request->database)->first();
            if (!$database) {
                return $this->error('Database not found', 404);
            }

            $user = $request->user ?: $prefix . 'user';
            $this->revokeRemoteAccess($database->name, $user, $host);
        } else {
            // Revoke from all user's databases
            foreach ($account->databases as $database) {
                foreach ($account->databaseUsers as $dbUser) {
                    $this->revokeRemoteAccess($database->name, $dbUser->username, $host);
                }
            }
        }

        Process::run('sudo /usr/bin/mysql -e "FLUSH PRIVILEGES"');

        return $this->success(null, 'Remote MySQL access revoked');
    }

    /**
     * Test remote MySQL connection
     */
    public function test(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'host' => 'required|string',
            'database' => 'required|string',
            'user' => 'required|string',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        try {
            $connection = new \PDO(
                "mysql:host={$request->host};dbname={$request->database}",
                $request->user,
                $request->password,
                [\PDO::ATTR_TIMEOUT => 5]
            );

            return $this->success(['connected' => true], 'Connection successful');
        } catch (\Exception $e) {
            return $this->success([
                'connected' => false,
                'error' => $e->getMessage(),
            ], 'Connection failed');
        }
    }

    /**
     * Get remote hosts with access to a database
     */
    protected function getRemoteHosts(string $database, string $prefix): array
    {
        $hosts = [];

        $result = Process::run(
            "sudo /usr/bin/mysql -N -e \"SELECT User, Host FROM mysql.db WHERE Db = '{$database}' AND Host != 'localhost' AND User LIKE '{$prefix}%'\""
        );

        foreach (explode("\n", $result->output()) as $line) {
            $parts = preg_split('/\s+/', trim($line));
            if (count($parts) >= 2) {
                $hosts[] = [
                    'user' => $parts[0],
                    'host' => $parts[1],
                ];
            }
        }

        return $hosts;
    }

    /**
     * Grant remote access
     */
    protected function grantRemoteAccess(string $database, string $user, string $host): void
    {
        $escapedHost = addslashes($host);
        $escapedUser = addslashes($user);
        $escapedDb = addslashes($database);

        // First ensure user exists for this host
        Process::run(
            "sudo /usr/bin/mysql -e \"CREATE USER IF NOT EXISTS '{$escapedUser}'@'{$escapedHost}' IDENTIFIED BY UUID()\""
        );

        // Grant privileges
        Process::run(
            "sudo /usr/bin/mysql -e \"GRANT ALL PRIVILEGES ON \`{$escapedDb}\`.* TO '{$escapedUser}'@'{$escapedHost}'\""
        );
    }

    /**
     * Revoke remote access
     */
    protected function revokeRemoteAccess(string $database, string $user, string $host): void
    {
        $escapedHost = addslashes($host);
        $escapedUser = addslashes($user);
        $escapedDb = addslashes($database);

        Process::run(
            "sudo /usr/bin/mysql -e \"REVOKE ALL PRIVILEGES ON \`{$escapedDb}\`.* FROM '{$escapedUser}'@'{$escapedHost}'\""
        );

        // Drop user if no more grants
        $result = Process::run(
            "sudo /usr/bin/mysql -N -e \"SELECT COUNT(*) FROM mysql.db WHERE User = '{$escapedUser}' AND Host = '{$escapedHost}'\""
        );

        if ((int) trim($result->output()) === 0) {
            Process::run(
                "sudo /usr/bin/mysql -e \"DROP USER IF EXISTS '{$escapedUser}'@'{$escapedHost}'\""
            );
        }
    }

    /**
     * Validate host format
     */
    protected function validateHost(string $host): bool
    {
        // Allow localhost
        if ($host === 'localhost') {
            return true;
        }

        // Allow wildcard
        if ($host === '%') {
            return true;
        }

        // Allow IP address
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return true;
        }

        // Allow CIDR notation
        if (preg_match('/^(\d{1,3}\.){3}\d{1,3}\/\d{1,2}$/', $host)) {
            return true;
        }

        // Allow domain names
        if (preg_match('/^[a-zA-Z0-9][a-zA-Z0-9\-\.]*[a-zA-Z0-9]$/', $host)) {
            return true;
        }

        // Allow partial wildcards like %.example.com
        if (preg_match('/^%\.[a-zA-Z0-9][a-zA-Z0-9\-\.]*$/', $host)) {
            return true;
        }

        return false;
    }
}
