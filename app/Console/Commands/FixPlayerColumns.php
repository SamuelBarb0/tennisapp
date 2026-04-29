<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-shot fix: relaxes legacy NOT NULL constraints on the players table
 * (`country` and `nationality_code`) that block inserts from the Matchstat sync.
 *
 * Use this when you can't run `php artisan migrate` and don't have tinker access.
 *   php artisan players:relax-legacy-columns
 */
class FixPlayerColumns extends Command
{
    protected $signature = 'players:relax-legacy-columns';
    protected $description = 'Make legacy player columns (country, nationality_code) nullable';

    public function handle(): int
    {
        try {
            DB::statement("ALTER TABLE players MODIFY country VARCHAR(255) NULL DEFAULT NULL");
            $this->info('✓ players.country is now nullable');
        } catch (\Throwable $e) {
            $this->warn('country: ' . $e->getMessage());
        }

        try {
            DB::statement("ALTER TABLE players MODIFY nationality_code VARCHAR(255) NULL DEFAULT NULL");
            $this->info('✓ players.nationality_code is now nullable');
        } catch (\Throwable $e) {
            $this->warn('nationality_code: ' . $e->getMessage());
        }

        $this->newLine();
        $this->line('Now you can run: <fg=cyan>php artisan tennis:sync-rankings</>');
        return self::SUCCESS;
    }
}
