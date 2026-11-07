<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('contracts:expire')->daily();

Schedule::command('notifications:scan-contracts')->daily();
Schedule::command('notifications:scan-tasks')->daily();
Schedule::command('notifications:scan-custodies')->daily();
Schedule::command('notifications:scan-low-stock')->daily();
Schedule::command('notifications:scan-season-reminders')->daily();
Schedule::command('notifications:scan-meetings-soon')->everyFifteenMinutes();

Schedule::command('purchase-requests:reconcile-routing')->everyFiveMinutes();

// S06 E-Q05 — persist delay_state projection daily (authoritative calc on write + this job).
// External cron / scheduler runner remains an ops prerequisite; this only registers the app schedule.
Schedule::command('tasks:recompute-delay')->daily();
