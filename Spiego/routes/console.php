<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule notification commands
Schedule::command('notifications:send-idle-alerts')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('notifications:send-offline-alerts')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground();

// Summary reports - run based on frequency setting
Schedule::command('notifications:send-summary-reports --frequency=daily')
    ->dailyAt('08:00')
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('notifications:send-summary-reports --frequency=weekly')
    ->weeklyOn(1, '08:00') // Monday at 8 AM
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('notifications:send-summary-reports --frequency=monthly')
    ->monthlyOn(1, '08:00') // 1st of month at 8 AM
    ->withoutOverlapping()
    ->runInBackground();
