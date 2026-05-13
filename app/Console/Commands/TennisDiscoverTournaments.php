<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Services\Tennis\ApiTennisSyncService;
use Illuminate\Console\Command;

/**
 * Auto-discovers the 23 covered tournaments (Grand Slams, ATP Masters 1000,
 * WTA 1000) from the api-tennis.com master catalog and upserts them.
 * Idempotent.
 *
 *   php artisan tennis:discover-tournaments
 *
 * Designed to run unattended via the scheduler — once a day is plenty since
 * the catalog barely changes.
 */
class TennisDiscoverTournaments extends Command
{
    protected $signature = 'tennis:discover-tournaments';
    protected $description = 'Auto-discover and upsert covered tournaments from api-tennis.com';

    public function handle(ApiTennisSyncService $sync): int
    {
        $this->info('Discovering covered tournaments from api-tennis.com…');

        $stats = $sync->syncCalendar();

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
