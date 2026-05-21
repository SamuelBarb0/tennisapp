<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ═════════════════════════════════════════════════════════════════════════════
//  Scheduler — Tennis Challenge
//  ────────────────────────────────────────────────────────────────────────────
//  Times below are interpreted in Bogotá (UTC-5) so admin schedules read
//  naturally. Cron infrastructure (Hostinger) fires every 15 min — anything
//  finer is silently dropped.
//
//  Health check: `php artisan tennis:cron-check` should report a heartbeat
//  timestamp < 20 minutes old.
// ═════════════════════════════════════════════════════════════════════════════

// ── 1. Heartbeat (every 15 min) ─────────────────────────────────────────────
// Writes a timestamp the admin can use to verify the cron is actually firing.
Schedule::call(function () {
    \App\Models\Setting::set('cron_heartbeat', now()->toDateTimeString());
})->everyFifteenMinutes()->name('cron-heartbeat')->onOneServer();

// ── 2. Live sync (every 15 min, 24/7) ───────────────────────────────────────
// The workhorse: pulls fresh fixtures + scores from api-tennis for every
// active tournament. Also bootstraps brackets from bracket.tennis when
// api-tennis hasn't published a draw yet. This is the most API-call-intensive
// job; it has to run 24/7 because draws and live matches happen in every
// timezone.
Schedule::command('tennis:sync-live --all')
    ->everyFifteenMinutes()
    ->withoutOverlapping(10)
    ->onOneServer()
    ->runInBackground();

// ── 3. Payment sweeper (every 15 min) ───────────────────────────────────────
// Resolves Mercado Pago checkouts the user abandoned. Cancels truly abandoned
// pending payments and rescues any approved payments whose webhook was lost.
Schedule::command('payments:cancel-abandoned --minutes=30')
    ->everyFifteenMinutes()
    ->withoutOverlapping(10)
    ->onOneServer();

// ── 4. Email blasts (hourly) ────────────────────────────────────────────────
// Sends tournament-opening, countdown and closing emails. Idempotent via
// tournament_email_log so re-running never resends.
Schedule::command('tennis:send-tournament-emails')
    ->hourly()
    ->onOneServer();

// ── 5. Status recompute (daily 03:00 Bogotá) ────────────────────────────────
// Moves tournaments from in_progress→finished once their end_date has passed.
// Cheap (DB-only) so daily is fine.
Schedule::command('tennis:recompute-status')
    ->dailyAt('03:00')
    ->timezone('America/Bogota')
    ->onOneServer();

// ── 6. Discover the season calendar (daily 04:00 Bogotá) ────────────────────
// Pulls the master list of Grand Slams + Masters 1000 + WTA 1000 from
// api-tennis. Idempotent — only writes when a new tournament appears or a
// date changes.
Schedule::command('tennis:discover-tournaments')
    ->dailyAt('04:00')
    ->timezone('America/Bogota')
    ->onOneServer();

// ── 7. Backfill dates from bracket.tennis (Lun + Jue 04:30 Bogotá) ──────────
// When api-tennis hasn't published fixtures yet, bracket.tennis already shows
// the official dates. Only touches tournaments with NULL dates, so it's a
// no-op most of the year. Dropped from daily to twice-weekly because there's
// rarely new work for it.
Schedule::command('tennis:fill-tournament-dates')
    ->weeklyOn(1, '04:30')   // Lunes
    ->timezone('America/Bogota')
    ->onOneServer();
Schedule::command('tennis:fill-tournament-dates')
    ->weeklyOn(4, '04:30')   // Jueves
    ->timezone('America/Bogota')
    ->onOneServer();

// ── 8. Rankings (Lun 05:00 Bogotá) ──────────────────────────────────────────
// ATP/WTA publish updated rankings on Mondays — we sync the top 200 right after.
Schedule::command('tennis:sync-rankings --top=200')
    ->weeklyOn(1, '05:00')
    ->timezone('America/Bogota')
    ->onOneServer();

// ── 9. Repair historical (Lun 03:30 Bogotá) ─────────────────────────────────
// Safety net: re-sync finished tournaments whose results never landed (e.g.
// API down at the time the match finished). --only-broken skips healthy
// tournaments, so this is cheap once everything is in good shape.
Schedule::command('tennis:repair-historical --only-broken')
    ->weeklyOn(1, '03:30')
    ->timezone('America/Bogota')
    ->onOneServer();

// ── 10. Map bracket.tennis slugs (monthly + yearly Jan 1) ───────────────────
// Every January 1st, force-remap every tournament's bracket.tennis slug to
// the new season (e.g. roland-garros-2026 → roland-garros-2027). The monthly
// pass below catches anything that drifts mid-season (rare). Dropped from
// weekly to monthly because the yearly --force handles 99% of the work.
Schedule::command('tennis:map-bracket-slugs --force')
    ->yearlyOn(1, 1, '05:00')
    ->timezone('America/Bogota')
    ->onOneServer();

Schedule::command('tennis:map-bracket-slugs')
    ->monthlyOn(1, '05:30')
    ->timezone('America/Bogota')
    ->onOneServer();
