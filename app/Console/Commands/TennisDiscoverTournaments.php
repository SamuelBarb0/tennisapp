<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Services\Tennis\MatchstatSyncService;
use Illuminate\Console\Command;

/**
 * Auto-discovers covered tournaments (Grand Slams, ATP Masters 1000, WTA 1000)
 * by scanning fixtures forward from today and resolving each tournament's tier.
 * Idempotent.
 *
 *   php artisan tennis:discover-tournaments
 *   php artisan tennis:discover-tournaments --days=90
 *
 * Designed to run unattended via the scheduler. Default 60-day horizon balances
 * coverage (catches the next major tournament) against API quota (one /info
 * call per unique tournament id seen).
 */
class TennisDiscoverTournaments extends Command
{
    protected $signature = 'tennis:discover-tournaments
                            {--days=60 : How many days ahead to scan for fixtures}';
    protected $description = 'Auto-discover and upsert covered tournaments from Matchstat';

    public function handle(MatchstatSyncService $sync): int
    {
        $days = (int) $this->option('days');
        $this->info("Scanning fixtures for the next {$days} days…");

        $stats = $sync->syncCalendar($days);

        $this->table(
            ['Imported', 'Updated', 'Skipped', 'Errors'],
            [[
                $stats['imported'],
                $stats['updated'],
                $stats['skipped'],
                count($stats['errors']),
            ]]
        );

        if (!empty($stats['errors'])) {
            foreach ($stats['errors'] as $err) {
                $this->warn("  ! {$err}");
            }
        }

        Setting::set('last_discover_tournaments', now()->toDateTimeString());
        return self::SUCCESS;
    }
}
