<?php

namespace App\Console\Commands;

use App\Services\Tennis\MatchstatSyncService;
use Illuminate\Console\Command;

class TennisSyncRankings extends Command
{
    protected $signature = 'tennis:sync-rankings {--top=200 : Top-N players per tour}';
    protected $description = 'Sync ATP/WTA rankings from Matchstat (upserts players)';

    public function handle(MatchstatSyncService $sync): int
    {
        $top = (int) $this->option('top');
        $this->info("Syncing top-{$top} ATP and WTA rankings...");
        $stats = $sync->syncRankings($top);

        $this->table(
            ['Tour', 'Updated'],
            [
                ['ATP', $stats['atp']],
                ['WTA', $stats['wta']],
            ]
        );
        if (!empty($stats['errors'])) {
            $this->warn('Errors: ' . implode(', ', $stats['errors']));
        }
        return self::SUCCESS;
    }
}
