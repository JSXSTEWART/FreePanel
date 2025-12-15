<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Renew SSL certificates daily
        $schedule->command('freepanel:renew-ssl')
            ->daily()
            ->at('02:00')
            ->withoutOverlapping();

        // Check disk quotas hourly
        $schedule->command('freepanel:check-quotas')
            ->hourly()
            ->withoutOverlapping();

        // Run scheduled backups
        $schedule->command('freepanel:run-backups')
            ->daily()
            ->at('03:00')
            ->withoutOverlapping();

        // Cleanup old backups
        $schedule->command('freepanel:cleanup-backups')
            ->weekly()
            ->withoutOverlapping();

        // Update bandwidth statistics
        $schedule->command('freepanel:update-bandwidth')
            ->everyFiveMinutes()
            ->withoutOverlapping();

        // Cleanup expired sessions
        $schedule->command('sanctum:prune-expired --hours=24')
            ->daily();

        // Cleanup old audit logs
        $schedule->command('freepanel:cleanup-audit-logs')
            ->monthly()
            ->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
