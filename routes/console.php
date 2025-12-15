<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled Tasks
|--------------------------------------------------------------------------
*/

// SSL certificate renewal check - daily at 2 AM
Schedule::command('freepanel:ssl-renew')
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->runInBackground();

// Backup cleanup - daily at 3 AM
Schedule::command('freepanel:backup-cleanup')
    ->dailyAt('03:00')
    ->withoutOverlapping();

// Bandwidth reset (monthly)
Schedule::command('freepanel:bandwidth-reset')
    ->monthlyOn(1, '00:00')
    ->withoutOverlapping();

// Disk quota check - every 6 hours
Schedule::command('freepanel:quota-check')
    ->everySixHours()
    ->withoutOverlapping();

// Service health check - every 5 minutes
Schedule::command('freepanel:health-check')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground();
