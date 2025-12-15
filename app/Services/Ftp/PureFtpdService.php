<?php

namespace App\Services\Ftp;

use App\Models\Account;
use App\Models\FtpAccount;
use Illuminate\Support\Facades\File;
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

    public function createAccount(FtpAccount $account, string $password): void
    {
        $homeAccount = $account->account;

        // Use pure-pw to create the user
        $command = sprintf(
            'pure-pw useradd %s -u %d -g %d -d %s -m',
            escapeshellarg($account->username),
            $homeAccount->uid,
            $homeAccount->gid,
            escapeshellarg($account->directory)
        );

        // Set quota if configured
        if ($account->quota > 0) {
            $command .= sprintf(' -n %dM', $account->quota);
        }

        // Set password via stdin
        $result = Process::run("echo " . escapeshellarg($password) . " | {$command}");

        if (!$result->successful()) {
            throw new \RuntimeException("Failed to create FTP account: " . $result->errorOutput());
        }

        // Rebuild the PDB database
        $this->rebuildDatabase();
    }

    public function updateAccount(FtpAccount $account): void
    {
        $homeAccount = $account->account;

        // Update user details
        $command = sprintf(
            'pure-pw usermod %s -d %s',
            escapeshellarg($account->username),
            escapeshellarg($account->directory)
        );

        if ($account->quota > 0) {
            $command .= sprintf(' -n %dM', $account->quota);
        } else {
            $command .= ' -N'; // Remove quota
        }

        $result = Process::run($command);

        if (!$result->successful()) {
            throw new \RuntimeException("Failed to update FTP account: " . $result->errorOutput());
        }

        $this->rebuildDatabase();
    }

    public function deleteAccount(FtpAccount $account): void
    {
        $result = Process::run(sprintf(
            'pure-pw userdel %s -m',
            escapeshellarg($account->username)
        ));

        if (!$result->successful() && !str_contains($result->errorOutput(), 'not found')) {
            throw new \RuntimeException("Failed to delete FTP account: " . $result->errorOutput());
        }

        $this->rebuildDatabase();
    }

    public function updatePassword(FtpAccount $account, string $password): void
    {
        $result = Process::run(sprintf(
            'echo %s | pure-pw passwd %s -m',
            escapeshellarg($password),
            escapeshellarg($account->username)
        ));

        if (!$result->successful()) {
            throw new \RuntimeException("Failed to update FTP password: " . $result->errorOutput());
        }

        $this->rebuildDatabase();
    }

    public function getActiveSessions(Account $account): array
    {
        $sessions = [];

        // Read pure-ftpwho output
        $result = Process::run('pure-ftpwho -s 2>/dev/null');

        if (!$result->successful()) {
            return [];
        }

        $lines = explode("\n", trim($result->output()));
        array_shift($lines); // Remove header

        foreach ($lines as $line) {
            $parts = preg_split('/\s+/', trim($line));
            if (count($parts) >= 6) {
                $username = $parts[1];

                // Only include sessions for this account's FTP users
                if (str_contains($username, '@' . $account->domain)) {
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
        // Verify session belongs to this account before killing
        $sessions = $this->getActiveSessions($account);
        $found = false;

        foreach ($sessions as $session) {
            if ($session['id'] === $sessionId) {
                $found = true;
                break;
            }
        }

        if (!$found) {
            throw new \InvalidArgumentException("Session not found or access denied");
        }

        // Kill the FTP session
        $result = Process::run("pure-ftpwho -k {$sessionId}");

        if (!$result->successful()) {
            throw new \RuntimeException("Failed to kill FTP session");
        }
    }

    protected function rebuildDatabase(): void
    {
        Process::run("pure-pw mkdb {$this->pdbFile}");
    }
}
