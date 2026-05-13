<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ─────────────────────────────────────────────────────────────────────────────
// Cron heartbeat — runs every 15 minutes (matching Hostinger's shared-plan
// minimum cron interval). Use `php artisan tennis:cron-check` to verify the
// scheduler is alive — the timestamp in `cron_heartbeat` should be < ~20 min old.
// ─────────────────────────────────────────────────────────────────────────────
Schedule::call(function () {
    \App\Models\Setting::set('cron_heartbeat', now()->toDateTimeString());
})->everyFifteenMinutes()->name('cron-heartbeat')->onOneServer();

// ─────────────────────────────────────────────────────────────────────────────
// Matchstat (Tennis API ATP/WTA/ITF) — fully automated sync
// ─────────────────────────────────────────────────────────────────────────────
// Live scores: poll every 15 minutes during typical match hours (UTC).
// 10:00–23:59 UTC covers most ATP/WTA prime time across timezones.
// Cadence is 15 min (not 2 min) because Hostinger shared plans only allow
// the system cron to fire every 15 minutes — anything more frequent is moot.
Schedule::command('tennis:sync-live --all')
    ->everyFifteenMinutes()
    ->between('10:00', '23:59')
    ->withoutOverlapping(10) // bail if a previous run is still going
    ->onOneServer()
    ->runInBackground();

// Rankings update once a week (Mondays — when ATP/WTA publish new rankings)
Schedule::command('tennis:sync-rankings --top=200')
    ->weeklyOn(1, '05:00')
    ->onOneServer();

// Discover the season calendar (Grand Slams + Masters 1000 + WTA 1000) once a
// day at 04:00 UTC. Idempotent — picks up new tournaments and date changes
// without manual intervention.
Schedule::command('tennis:discover-tournaments')
    ->dailyAt('04:00')
    ->onOneServer();

// Re-map bracket.tennis slugs once a year on January 1st. When a season
// rolls over, the same tournaments get new slugs (e.g. roland-garros-2027
// instead of -2026). --force overwrites the stale 2026 ones.
Schedule::command('tennis:map-bracket-slugs --force')
    ->yearlyOn(1, 1, '05:00')
    ->onOneServer();

// As a safety net: also try to map any UNMAPPED slugs every Monday. Catches
// cases where bracket.tennis publishes a new tournament name mid-season or
// where the early-January attempt failed because draws hadn't been published.
Schedule::command('tennis:map-bracket-slugs')
    ->weeklyOn(1, '05:30')
    ->onOneServer();

// Tournament email blasts (opening / countdown / closing). Idempotent — the
// command guards against re-sending the same blast via tournament_email_log.
// Hourly is plenty: opening goes out within an hour of the bracket being
// ready, countdown fires inside the 24h window, closing right after the final.
Schedule::command('tennis:send-tournament-emails')
    ->hourly()
    ->onOneServer();
