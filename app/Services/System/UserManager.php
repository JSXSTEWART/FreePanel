<?php

namespace App\Services\System;

use App\Models\Account;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

class UserManager
{
    protected string $skelDir;

    protected int $minUid;

    protected int $maxUid;

    public function __construct()
    {
        $this->skelDir = config('freepanel.skel_dir', '/etc/skel');
        $this->minUid = config('freepanel.min_uid', 1000);
        $this->maxUid = config('freepanel.max_uid', 65534);
    }

    /**
     * Create a system user for a hosting account
     */
    public function createUser(string $username, string $password): array
    {
        $this->validateUsername($username);

        // Check if user already exists
        if ($this->userExists($username)) {
            throw new \RuntimeException("User {$username} already exists");
        }

        // Find next available UID
        $uid = $this->getNextUid();

        // Create group
        $result = Process::run(['groupadd', '-g', (string) $uid, $username]);
        if (! $result->successful()) {
            throw new \RuntimeException('Failed to create group: '.$result->errorOutput());
        }

        // Create user
        $homeDir = "/home/{$username}";
        $result = Process::run([
            'useradd',
            '-u', (string) $uid,
            '-g', (string) $uid,
            '-d', $homeDir,
            '-m',
            '-s', '/bin/bash',
            $username,
        ]);

        if (! $result->successful()) {
            // Cleanup group if user creation fails
            Process::run(['groupdel', $username]);
            throw new \RuntimeException('Failed to create user: '.$result->errorOutput());
        }

        // Set password
        $this->setPassword($username, $password);

        return [
            'username' => $username,
            'uid' => $uid,
            'gid' => $uid,
            'home' => $homeDir,
        ];
    }

    /**
     * Create home directory structure for hosting account
     */
    public function createHomeDirectory(Account $account): void
    {
        $home = $account->home_directory;
        $uid = $account->uid;
        $gid = $account->gid;

        // Create directory structure
        $directories = [
            $home,
            "{$home}/public_html",
            "{$home}/logs",
            "{$home}/tmp",
            "{$home}/mail",
            "{$home}/backups",
            "{$home}/.ssh",
            "{$home}/.config",
        ];

        foreach ($directories as $dir) {
            if (! File::isDirectory($dir)) {
                File::makeDirectory($dir, 0755, true);
            }
            chown($dir, $uid);
            chgrp($dir, $gid);
        }

        // Set proper permissions
        chmod("{$home}/.ssh", 0700);
        chmod("{$home}/tmp", 0700);

        // Create default index page
        $indexPath = "{$home}/public_html/index.html";
        if (! File::exists($indexPath)) {
            $defaultContent = $this->getDefaultIndexPage($account->domain);
            File::put($indexPath, $defaultContent);
            chown($indexPath, $uid);
            chgrp($indexPath, $gid);
        }

        // Copy skel files if configured
        if (File::isDirectory($this->skelDir)) {
            $this->copySkelFiles($this->skelDir, $home, $uid, $gid);
        }
    }

    /**
     * Delete a system user
     */
    public function deleteUser(string $username): void
    {
        $this->validateUsername($username);

        if (! $this->userExists($username)) {
            return; // User doesn't exist, nothing to do
        }

        // Kill all user processes
        Process::run(['pkill', '-u', $username]);

        // Wait a moment for processes to terminate
        usleep(500000);

        // Delete user and home directory
        $result = Process::run(['userdel', '-r', $username]);

        // Delete group if it still exists
        Process::run(['groupdel', $username]);

        if (! $result->successful() && ! str_contains($result->errorOutput(), 'does not exist')) {
            throw new \RuntimeException('Failed to delete user: '.$result->errorOutput());
        }
    }

    /**
     * Change user password
     */
    public function changePassword(string $username, string $password): void
    {
        $this->validateUsername($username);
        $this->setPassword($username, $password);
    }

    /**
     * Suspend a user (disable login)
     */
    public function suspendUser(string $username): void
    {
        $this->validateUsername($username);

        $result = Process::run(['usermod', '-L', '-s', '/sbin/nologin', $username]);

        if (! $result->successful()) {
            throw new \RuntimeException('Failed to suspend user: '.$result->errorOutput());
        }
    }

    /**
     * Unsuspend a user (enable login)
     */
    public function unsuspendUser(string $username): void
    {
        $this->validateUsername($username);

        $result = Process::run(['usermod', '-U', '-s', '/bin/bash', $username]);

        if (! $result->successful()) {
            throw new \RuntimeException('Failed to unsuspend user: '.$result->errorOutput());
        }
    }

    /**
     * Get disk usage for a user
     */
    public function getDiskUsage(string $username): int
    {
        $this->validateUsername($username);

        $home = "/home/{$username}";

        if (! File::isDirectory($home)) {
            return 0;
        }

        $result = Process::run(['du', '-sb', $home]);
        $line = trim($result->output());
        // Output format: "<bytes>\t<path>"
        $first = strtok($line, "\t") ?: '0';

        return (int) $first;
    }

    /**
     * Check if user exists
     */
    public function userExists(string $username): bool
    {
        $this->validateUsername($username);
        $result = Process::run(['id', $username]);

        return $result->successful();
    }

    /**
     * Get user info
     */
    public function getUserInfo(string $username): ?array
    {
        if (! $this->userExists($username)) {
            return null;
        }

        $result = Process::run(['getent', 'passwd', $username]);
        if (! $result->successful()) {
            return null;
        }

        $parts = explode(':', trim($result->output()));

        return [
            'username' => $parts[0],
            'uid' => (int) $parts[2],
            'gid' => (int) $parts[3],
            'home' => $parts[5],
            'shell' => $parts[6],
        ];
    }

    protected function setPassword(string $username, string $password): void
    {
        // chpasswd reads `user:password` entries separated by newlines from
        // stdin. A password containing a newline would terminate its own
        // record and inject a second one, so reject any control character.
        if (preg_match('/[\x00-\x1F\x7F]/', $password)) {
            throw new \InvalidArgumentException('Password contains disallowed control characters');
        }

        // Passing the password through stdin (not argv or a shell pipeline)
        // keeps it out of the process list and avoids any need to escape
        // shell metacharacters.
        $result = Process::input("{$username}:{$password}\n")->run(['chpasswd']);

        if (! $result->successful()) {
            throw new \RuntimeException('Failed to set password: '.$result->errorOutput());
        }
    }

    protected function getNextUid(): int
    {
        // Cast to int at the boundary so config values can never be
        // interpreted by awk even if they later become admin-writable.
        $minUid = (int) $this->minUid;
        $maxUid = (int) $this->maxUid;

        // Fetch the passwd database once and filter in PHP instead of
        // building a shell pipeline.
        $result = Process::run(['getent', 'passwd']);
        if (! $result->successful()) {
            return $minUid;
        }

        $highest = 0;
        foreach (explode("\n", $result->output()) as $line) {
            $parts = explode(':', $line);
            if (count($parts) < 3) {
                continue;
            }
            $uid = (int) $parts[2];
            if ($uid >= $minUid && $uid <= $maxUid && $uid > $highest) {
                $highest = $uid;
            }
        }

        if ($highest < $minUid) {
            return $minUid;
        }

        $nextUid = $highest + 1;

        if ($nextUid > $maxUid) {
            throw new \RuntimeException('No available UIDs in range');
        }

        return $nextUid;
    }

    protected function validateUsername(string $username): void
    {
        if (! preg_match('/^[a-z][a-z0-9]{2,15}$/', $username)) {
            throw new \InvalidArgumentException("Invalid username format: {$username}");
        }

        // Check for reserved usernames
        $reserved = ['root', 'admin', 'administrator', 'www-data', 'apache', 'nginx', 'mysql', 'nobody'];
        if (in_array($username, $reserved)) {
            throw new \InvalidArgumentException("Username is reserved: {$username}");
        }
    }

    protected function copySkelFiles(string $skelDir, string $homeDir, int $uid, int $gid): void
    {
        $files = File::allFiles($skelDir);

        foreach ($files as $file) {
            $relativePath = $file->getRelativePathname();
            $destPath = "{$homeDir}/{$relativePath}";

            // Create directory if needed
            $destDir = dirname($destPath);
            if (! File::isDirectory($destDir)) {
                File::makeDirectory($destDir, 0755, true);
                chown($destDir, $uid);
                chgrp($destDir, $gid);
            }

            // Copy file
            File::copy($file->getPathname(), $destPath);
            chown($destPath, $uid);
            chgrp($destPath, $gid);
        }
    }

    protected function getDefaultIndexPage(string $domain): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to {$domain}</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .container {
            text-align: center;
            padding: 40px;
        }
        h1 {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        p {
            font-size: 1.2rem;
            opacity: 0.9;
        }
        .domain {
            font-weight: bold;
            color: #ffd700;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Welcome!</h1>
        <p>Your website <span class="domain">{$domain}</span> is ready.</p>
        <p>Upload your website files to get started.</p>
    </div>
</body>
</html>
HTML;
    }
}
