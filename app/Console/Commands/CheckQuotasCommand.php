<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Services\System\UserManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class CheckQuotasCommand extends Command
{
    protected $signature = 'freepanel:check-quotas
                            {--threshold=90 : Warning threshold percentage}
                            {--notify : Send email notifications}';

    protected $description = 'Check disk quotas for all accounts';

    public function handle(UserManager $userManager): int
    {
        $threshold = (int) $this->option('threshold');
        $notify = $this->option('notify');

        $this->info('Checking disk quotas...');

        $accounts = Account::where('status', 'active')
            ->with(['user', 'package'])
            ->get();

        $warnings = [];

        foreach ($accounts as $account) {
            $diskUsed = $userManager->getDiskUsage($account->username);
            $diskQuota = $account->package->disk_quota;

            // Skip unlimited quotas
            if ($diskQuota == -1) {
                continue;
            }

            $quotaBytes = $diskQuota * 1024 * 1024; // Convert MB to bytes
            $percentUsed = ($diskUsed / $quotaBytes) * 100;

            // Update account disk usage
            $account->update(['disk_used' => $diskUsed]);

            if ($percentUsed >= $threshold) {
                $warnings[] = [
                    'account' => $account->username,
                    'email' => $account->user->email,
                    'used' => $this->formatBytes($diskUsed),
                    'quota' => $this->formatBytes($quotaBytes),
                    'percent' => round($percentUsed, 1),
                ];
            }

            if ($percentUsed >= 100) {
                $this->warn("OVER QUOTA: {$account->username} - {$this->formatBytes($diskUsed)} / {$this->formatBytes($quotaBytes)}");
            }
        }

        if (!empty($warnings)) {
            $this->table(
                ['Account', 'Used', 'Quota', 'Percent'],
                collect($warnings)->map(fn($w) => [$w['account'], $w['used'], $w['quota'], $w['percent'] . '%'])
            );

            if ($notify) {
                $this->info('Sending notifications...');
                foreach ($warnings as $warning) {
                    // In production, send actual emails
                    $this->line("  Would notify: {$warning['email']}");
                }
            }
        } else {
            $this->info('All accounts within quota limits.');
        }

        return 0;
    }

    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
}
