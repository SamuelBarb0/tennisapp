<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// API Tennis auto-sync schedules
Schedule::command('tennis:sync-livescores')->everyThreeMinutes()->between('10:00', '23:59');
Schedule::command('tennis:sync-fixtures')->dailyAt('06:00');
Schedule::command('tennis:sync-players --category=all')->weeklyOn(1, '05:00');
Schedule::command('tennis:sync-tournaments')->weeklyOn(1, '04:00');
