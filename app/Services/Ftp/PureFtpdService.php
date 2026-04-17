<?php

namespace App\Services\Ftp;

use App\Models\Account;
use App\Models\FtpAccount;
use Illuminate\Support\Facades\Process;

class PureFtpdService implements FtpInterface
{
    protected string $passwdFile;

    protected string $pdbFile;

    public function __construct()
    {
        $this->passwdFile = config('freepanel.ftp.passwd_file', '/etc/pureftpd.passwd');
        $this->pdbFile = config('freepanel.ftp.pdb_file', '/etc/pureftpd.pdb');
    }

    /**
     * pure-pw accepts usernames in `name@virtualhost` form. Lock the
     * character set explicitly; this is defense-in-depth on top of any
     * validation done when the FtpAccount row was created.
     */
    protected function assertValidUsername(string $username): void
    {
        if (! preg_match('/^[A-Za-z0-9._-]+(@[A-Za-z0-9.-]+)?$/', $username) || strlen($username) > 128) {
            throw new \InvalidArgumentException("Invalid FTP username: {$username}");
        }
    }

    /**
     * Passwords must not contain control characters or newlines since they
     * are piped to pure-pw on stdin.
     */
    protected function assertValidPassword(string $password): void
    {
        if (preg_match('/[\x00-\x1F\x7F]/', $password)) {
            throw new \InvalidArgumentException('Password contains disallowed control characters');
        }
    }

    public function createAccount(FtpAccount $account, string $password): void
    {
        $this->assertValidUsername($account->username);
        $this->assertValidPassword($password);

        $homeAccount = $account->account;

        $cmd = [
            'pure-pw', 'useradd', $account->username,
            '-u', (string) (int) $homeAccount->uid,
            '-g', (string) (int) $homeAccount->gid,
            '-d', $account->directory,
            '-m',
        ];

        // Set quota if configured
        if ($account->quota > 0) {
            $cmd[] = '-n';
            $cmd[] = ((int) $account->quota).'M';
        }

        // pure-pw reads the password twice from stdin (new + confirm).
        $result = Process::input("{$password}\n{$password}\n")->run($cmd);

        if (! $result->successful()) {
            throw new \RuntimeException('Failed to create FTP account: '.$result->errorOutput());
        }

        // Rebuild the PDB database
        $this->rebuildDatabase();
    }

    public function updateAccount(FtpAccount $account): void
    {
        $this->assertValidUsername($account->username);

        $homeAccount = $account->account;

        // Update user details
        $cmd = [
            'pure-pw', 'usermod', $account->username,
            '-d', $account->directory,
        ];

        if ($account->quota > 0) {
            $cmd[] = '-n';
            $cmd[] = ((int) $account->quota).'M';
        } else {
            $cmd[] = '-N'; // Remove quota
        }

        $result = Process::run($cmd);

        if (! $result->successful()) {
            throw new \RuntimeException('Failed to update FTP account: '.$result->errorOutput());
        }

        $this->rebuildDatabase();
    }

    public function deleteAccount(FtpAccount $account): void
    {
        $this->assertValidUsername($account->username);

        $result = Process::run(['pure-pw', 'userdel', $account->username, '-m']);

        if (! $result->successful() && ! str_contains($result->errorOutput(), 'not found')) {
            throw new \RuntimeException('Failed to delete FTP account: '.$result->errorOutput());
        }

        $this->rebuildDatabase();
    }

    public function updatePassword(FtpAccount $account, string $password): void
    {
        $this->assertValidUsername($account->username);
        $this->assertValidPassword($password);

        $result = Process::input("{$password}\n{$password}\n")->run([
            'pure-pw', 'passwd', $account->username, '-m',
        ]);

        if (! $result->successful()) {
            throw new \RuntimeException('Failed to update FTP password: '.$result->errorOutput());
        }

        $this->rebuildDatabase();
    }

    public function getActiveSessions(Account $account): array
    {
        $sessions = [];

        // Read pure-ftpwho output
        $result = Process::run(['pure-ftpwho', '-s']);

        if (! $result->successful()) {
            return [];
        }

        $lines = explode("\n", trim($result->output()));
        array_shift($lines); // Remove header

        foreach ($lines as $line) {
            $parts = preg_split('/\s+/', trim($line));
            if (count($parts) >= 6) {
                $username = $parts[1];

                // Only include sessions for this account's FTP users
                if (str_contains($username, '@'.$account->domain)) {
                    $sessions[] = [
                        'id' => $parts[0],
                        'username' => $username,
                        'ip' => $parts[2],
                        'status' => $parts[3],
                        'file' => $parts[5] ?? null,
                        'percent' => $parts[4] ?? '0%',
                    ];
                }
            }
        }

        return $sessions;
    }

    public function killSession(string $sessionId, Account $account): void
    {
        // Session IDs returned by pure-ftpwho are numeric PIDs.
        if (! preg_match('/^[0-9]+$/', $sessionId)) {
            throw new \InvalidArgumentException('Invalid session id');
        }

        // Verify session belongs to this account before killing
        $sessions = $this->getActiveSessions($account);
        $found = false;

        foreach ($sessions as $session) {
            if ($session['id'] === $sessionId) {
                $found = true;
                break;
            }
        }

        if (! $found) {
            throw new \InvalidArgumentException('Session not found or access denied');
        }

        // Kill the FTP session
        $result = Process::run(['pure-ftpwho', '-k', $sessionId]);

        if (! $result->successful()) {
            throw new \RuntimeException('Failed to kill FTP session');
        }
    }

    protected function rebuildDatabase(): void
    {
        Process::run(['pure-pw', 'mkdb', $this->pdbFile]);
    }
}
