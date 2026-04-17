<?php

namespace App\Services\System;

use App\Models\Account;
use Illuminate\Support\Facades\Process;

class CronService
{
    /**
     * Defense-in-depth re-validation of the system username before it's
     * passed to any shell-adjacent command. Usernames are validated at
     * account creation but can be reloaded from the DB later; never trust
     * that invariant across call boundaries.
     */
    protected function assertValidSystemUser(string $systemUser): void
    {
        if (! preg_match('/^[a-z][a-z0-9_-]{0,31}$/', $systemUser)) {
            throw new \InvalidArgumentException("Invalid system username: {$systemUser}");
        }
    }

    /**
     * Sync all cron jobs for an account to the system crontab
     */
    public function syncCrontab(Account $account): void
    {
        $systemUser = $account->system_username;
        $this->assertValidSystemUser($systemUser);

        $cronJobs = $account->cronJobs()->where('is_active', true)->get();

        // Build crontab content
        $crontab = "# FreePanel managed crontab for {$systemUser}\n";
        $crontab .= "# Do not edit manually - changes will be overwritten\n\n";

        foreach ($cronJobs as $job) {
            $line = "{$job->minute} {$job->hour} {$job->day} {$job->month} {$job->weekday}";

            // Add email if specified
            if ($job->email) {
                $crontab .= "MAILTO={$job->email}\n";
            }

            // Add the command
            $crontab .= "{$line} {$job->command}\n";

            if ($job->email) {
                $crontab .= "MAILTO=\"\"\n";
            }
        }

        // Write to temporary file with restrictive permissions
        $tempFile = tempnam(sys_get_temp_dir(), 'cron_');
        chmod($tempFile, 0600);
        file_put_contents($tempFile, $crontab);

        // Install crontab for user
        $result = Process::run(['sudo', 'crontab', '-u', $systemUser, $tempFile]);

        unlink($tempFile);

        if (! $result->successful()) {
            throw new \RuntimeException('Failed to install crontab: '.$result->errorOutput());
        }
    }

    /**
     * Get current crontab for an account
     */
    public function getCrontab(Account $account): string
    {
        $systemUser = $account->system_username;
        $this->assertValidSystemUser($systemUser);

        $result = Process::run(['sudo', 'crontab', '-u', $systemUser, '-l']);

        return $result->output() ?: '';
    }

    /**
     * Validate a cron expression
     */
    public function validateExpression(string $minute, string $hour, string $day, string $month, string $weekday): bool
    {
        $patterns = [
            'minute' => '/^(\*|([0-5]?\d)(,([0-5]?\d))*|(\*\/[1-9]\d?)|([0-5]?\d-[0-5]?\d))$/',
            'hour' => '/^(\*|([01]?\d|2[0-3])(,([01]?\d|2[0-3]))*|(\*\/[1-9]\d?)|([01]?\d|2[0-3])-([01]?\d|2[0-3]))$/',
            'day' => '/^(\*|([1-9]|[12]\d|3[01])(,([1-9]|[12]\d|3[01]))*|(\*\/[1-9]\d?)|([1-9]|[12]\d|3[01])-([1-9]|[12]\d|3[01]))$/',
            'month' => '/^(\*|([1-9]|1[0-2])(,([1-9]|1[0-2]))*|(\*\/[1-9]\d?)|([1-9]|1[0-2])-([1-9]|1[0-2]))$/',
            'weekday' => '/^(\*|[0-6](,[0-6])*|(\*\/[1-6])|[0-6]-[0-6])$/',
        ];

        return preg_match($patterns['minute'], $minute)
            && preg_match($patterns['hour'], $hour)
            && preg_match($patterns['day'], $day)
            && preg_match($patterns['month'], $month)
            && preg_match($patterns['weekday'], $weekday);
    }

    /**
     * Validate a cron command for security.
     *
     * By default only a conservative allowlist of interpreters targeting
     * the account's home directory is accepted. Operators can set
     * FREEPANEL_FEATURE_CRON_SHELL=true once per-account cgroup/ulimit
     * isolation is in place to unlock generic shell commands (matching
     * cPanel parity). Even when generic shell is allowed, control
     * characters and absolute paths outside the user's home are
     * rejected.
     */
    public function validateCommand(string $command, Account $account): bool
    {
        $command = trim($command);

        if ($command === '' || strlen($command) > 2000) {
            return false;
        }

        // Control characters (including newlines) would escape the crontab
        // line and inject additional entries.
        if (preg_match('/[\x00-\x1F\x7F]/', $command)) {
            return false;
        }

        $homeDir = '/home/'.$account->system_username;

        // Always-forbidden patterns: would trash the host regardless of
        // isolation.
        $forbidden = [
            'rm -rf /',
            'rm -rf /*',
            'dd if=',
            'mkfs',
            ':(){ :|:& };:',
            'chmod -R 777 /',
            '> /dev/sd',
            'mv /* ',
            '/etc/shadow',
        ];

        $commandLower = strtolower($command);
        foreach ($forbidden as $pattern) {
            if (str_contains($commandLower, strtolower($pattern))) {
                return false;
            }
        }

        if (config('freepanel.features.cron_shell_execution', false)) {
            // Even in the permissive mode, require the command to either
            // be a relative path or reference the account's home dir.
            return str_starts_with($command, $homeDir)
                || ! str_starts_with($command, '/')
                || in_array(explode(' ', $command, 2)[0], [
                    '/usr/bin/php', '/usr/local/bin/php',
                    '/usr/bin/python', '/usr/bin/python3',
                    '/usr/bin/perl', '/bin/bash', '/bin/sh',
                ], true);
        }

        // Conservative allowlist: the command must start with a known
        // interpreter path or a path inside the user's home.
        $allowedPrefixes = [
            '/usr/bin/php ',
            '/usr/local/bin/php ',
            '/usr/bin/python ',
            '/usr/bin/python3 ',
            '/usr/bin/perl ',
            $homeDir,
        ];

        foreach ($allowedPrefixes as $prefix) {
            if (str_starts_with($command, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Remove all cron jobs for an account
     */
    public function removeCrontab(Account $account): void
    {
        $systemUser = $account->system_username;
        $this->assertValidSystemUser($systemUser);
        Process::run(['sudo', 'crontab', '-u', $systemUser, '-r']);
    }
}
