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
// Live scores: poll every 15 minutes, 24h a day. We can't predict when
// bracket.tennis publishes a new draw or when api-tennis flips a tournament
// from "Not started" to live, and those events drive the email blasts, so
// missing them by 10h (the old 10:00–23:59 UTC window) was painful for events
// drawn early morning. Cadence is 15 min — Hostinger shared plans allow no
// finer than that, so anything more frequent is moot.
Schedule::command('tennis:sync-live --all')
    ->everyFifteenMinutes()
    ->withoutOverlapping(10) // bail if a previous run is still going
    ->onOneServer()
    ->runInBackground();

// All daily/weekly housekeeping tasks below interpret their target times in
// Bogotá so admin schedules read naturally ("3am Bogotá" instead of "8am UTC").
// The live-sync above stays unscoped because it runs every 15min — timezone
// doesn't matter for it.

// Rankings update once a week (Mondays — when ATP/WTA publish new rankings)
Schedule::command('tennis:sync-rankings --top=200')
    ->weeklyOn(1, '05:00')
    ->timezone('America/Bogota')
    ->onOneServer();

// Discover the season calendar (Grand Slams + Masters 1000 + WTA 1000) once a
// day at 04:00 Bogotá. Idempotent — picks up new tournaments and date changes
// without manual intervention.
Schedule::command('tennis:discover-tournaments')
    ->dailyAt('04:00')
    ->timezone('America/Bogota')
    ->onOneServer();

// Recompute tournament status every day at 03:00 Bogotá so finished tournaments
// transition away from "in_progress" / "live" once their end_date has passed.
// The live-sync only updates status when fixtures arrive — after a tournament
// ends, no more fixtures come, so we need this separate housekeeping pass.
Schedule::command('tennis:recompute-status')
    ->dailyAt('03:00')
    ->timezone('America/Bogota')
    ->onOneServer();

// Weekly safety net for finished tournaments whose results never landed:
// re-pull fixtures from api-tennis (using the start_date/end_date window) and
// clean any leftover pending placeholders. --only-broken skips tournaments
// that already have finished matches, so this stays cheap once everything is
// healthy. Runs Mondays at 03:30 Bogotá, right after the daily recompute.
Schedule::command('tennis:repair-historical --only-broken')
    ->weeklyOn(1, '03:30')
    ->timezone('America/Bogota')
    ->onOneServer();

// Fill in missing tournament dates from bracket.tennis once a day. This catches
// tournaments where api-tennis hasn't published fixtures yet but bracket.tennis
// already shows the official dates (typical ~2-3 weeks before each event).
Schedule::command('tennis:fill-tournament-dates')
    ->dailyAt('04:30')
    ->timezone('America/Bogota')
    ->onOneServer();

// Re-map bracket.tennis slugs once a year on January 1st. When a season
// rolls over, the same tournaments get new slugs (e.g. roland-garros-2027
// instead of -2026). --force overwrites the stale 2026 ones.
Schedule::command('tennis:map-bracket-slugs --force')
    ->yearlyOn(1, 1, '05:00')
    ->timezone('America/Bogota')
    ->onOneServer();

// As a safety net: also try to map any UNMAPPED slugs every Monday. Catches
// cases where bracket.tennis publishes a new tournament name mid-season or
// where the early-January attempt failed because draws hadn't been published.
Schedule::command('tennis:map-bracket-slugs')
    ->weeklyOn(1, '05:30')
    ->timezone('America/Bogota')
    ->onOneServer();

// Tournament email blasts (opening / countdown / closing). Idempotent — the
// command guards against re-sending the same blast via tournament_email_log.
// Hourly is plenty: opening goes out within an hour of the bracket being
// ready, countdown fires inside the 24h window, closing right after the final.
Schedule::command('tennis:send-tournament-emails')
    ->hourly()
    ->onOneServer();

// Sweep abandoned Mercado Pago checkouts. When the user clicks "Pagar" but
// closes the MP page without completing, no webhook ever fires and our local
// row stays "pending" forever — blocking the UI and making the user see
// "pago en proceso" indefinitely. This sweeper consults MP for each stale
// pending payment: cancels the truly abandoned ones, syncs any approved
// payments whose webhook arrived late.
Schedule::command('payments:cancel-abandoned --minutes=30')
    ->everyFifteenMinutes()
    ->withoutOverlapping(10)
    ->onOneServer();
