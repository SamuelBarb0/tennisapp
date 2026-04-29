<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ─────────────────────────────────────────────────────────────────────────────
// Cron heartbeat — runs every minute. Use `php artisan tennis:cron-check` to
// see if the scheduler is alive (the timestamp in `cron_heartbeat` should be
// less than ~2 minutes old).
// ─────────────────────────────────────────────────────────────────────────────
Schedule::call(function () {
    \App\Models\Setting::set('cron_heartbeat', now()->toDateTimeString());
})->everyMinute()->name('cron-heartbeat')->onOneServer();

// ─────────────────────────────────────────────────────────────────────────────
// Matchstat (Tennis API ATP/WTA/ITF) — fully automated sync
// ─────────────────────────────────────────────────────────────────────────────
// Live scores: poll every 2 minutes during typical match hours (UTC).
// 10:00–23:59 UTC covers most ATP/WTA prime time across timezones.
Schedule::command('tennis:sync-live --all')
    ->everyTwoMinutes()
    ->between('10:00', '23:59')
    ->withoutOverlapping(10) // bail if a previous run is still going
    ->onOneServer()
    ->runInBackground();

// Rankings update once a week (Mondays — when ATP/WTA publish new rankings)
Schedule::command('tennis:sync-rankings --top=200')
    ->weeklyOn(1, '05:00')
    ->onOneServer();
