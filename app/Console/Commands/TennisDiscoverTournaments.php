<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Services\Tennis\MatchstatSyncService;
use Illuminate\Console\Command;

/**
 * Auto-discovers covered tournaments (Grand Slams, ATP Masters 1000, WTA 1000)
 * for a given season and upserts them into the local DB. Idempotent.
 *
 *   php artisan tennis:discover-tournaments
 *   php artisan tennis:discover-tournaments --year=2027
 *
 * Designed to run unattended via the scheduler — once a day is plenty since
 * the calendar barely changes.
 */
class TennisDiscoverTournaments extends Command
{
    protected $signature = 'tennis:discover-tournaments
                            {--year= : Season year (defaults to current year)}';
    protected $description = 'Auto-discover and upsert covered tournaments from Matchstat';

    public function handle(MatchstatSyncService $sync): int
    {
        $year = (int) ($this->option('year') ?: now()->year);
        $this->info("Discovering tournaments for {$year}…");

        $stats = $sync->syncCalendar($year);

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
