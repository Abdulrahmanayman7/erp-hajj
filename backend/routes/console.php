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
Schedule::command('notifications:scan-meetings-soon')->everyFifteenMinutes();
