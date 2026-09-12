<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Runs every minute via system cron -> `php artisan schedule:run`. Uses a
// database lock (withoutOverlapping) so two cron ticks never run this
// concurrently even without Redis.
Schedule::command('imports:cleanup')
    ->everyMinute()
    ->withoutOverlapping(2)
    ->onOneServer();
