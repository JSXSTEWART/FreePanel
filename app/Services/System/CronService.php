<?php

namespace App\Services\System;

use App\Models\Account;
use App\Models\CronJob;
use Illuminate\Support\Facades\Process;

class CronService
{
    /**
     * Sync all cron jobs for an account to the system crontab
     */
    public function syncCrontab(Account $account): void
    {
        $systemUser = $account->system_username;
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

        // Write to temporary file
        $tempFile = tempnam(sys_get_temp_dir(), 'cron_');
        file_put_contents($tempFile, $crontab);

        // Install crontab for user
        $result = Process::run("sudo crontab -u {$systemUser} {$tempFile}");

        unlink($tempFile);

        if (!$result->successful()) {
            throw new \RuntimeException("Failed to install crontab: " . $result->errorOutput());
        }
    }

    /**
     * Get current crontab for an account
     */
    public function getCrontab(Account $account): string
    {
        $systemUser = $account->system_username;
        $result = Process::run("sudo crontab -u {$systemUser} -l 2>/dev/null");

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
     * Validate a cron command for security
     */
    public function validateCommand(string $command, Account $account): bool
    {
        // Disallow dangerous commands
        $forbidden = [
            'rm -rf /',
            'dd if=',
            'mkfs',
            ':(){ :|:& };:',
            'chmod -R 777 /',
            'wget',
            'curl',
            '> /dev/sd',
            'mv /* ',
        ];

        $commandLower = strtolower($command);
        foreach ($forbidden as $pattern) {
            if (str_contains($commandLower, strtolower($pattern))) {
                return false;
            }
        }

        // Ensure command stays within user's home directory context
        $homeDir = "/home/{$account->system_username}";

        // Allow PHP, Python, Perl, and common utilities
        $allowedPrefixes = [
            '/usr/bin/php',
            '/usr/local/bin/php',
            'php ',
            '/usr/bin/python',
            '/usr/bin/perl',
            '/bin/bash',
            '/bin/sh',
            'cd ' . $homeDir,
        ];

        // If command starts with allowed prefix or is a relative path, it's OK
        $isAllowed = false;
        foreach ($allowedPrefixes as $prefix) {
            if (str_starts_with($command, $prefix)) {
                $isAllowed = true;
                break;
            }
        }

        // Also allow commands that reference files in user's home
        if (str_contains($command, $homeDir)) {
            $isAllowed = true;
        }

        return $isAllowed;
    }

    /**
     * Remove all cron jobs for an account
     */
    public function removeCrontab(Account $account): void
    {
        $systemUser = $account->system_username;
        Process::run("sudo crontab -u {$systemUser} -r 2>/dev/null");
    }
}
