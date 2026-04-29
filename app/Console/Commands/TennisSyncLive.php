<?php

namespace App\Console\Commands;

use App\Models\Tournament;
use App\Services\Tennis\MatchstatSyncService;
use Illuminate\Console\Command;

class TennisSyncLive extends Command
{
    protected $signature = 'tennis:sync-live
                            {--tournament= : Slug or ID of a single tournament to sync}
                            {--all : Sync every active tournament with a matchstat id}';
    protected $description = 'Pull latest fixtures and scores for active tournaments';

    public function handle(MatchstatSyncService $sync): int
    {
        if ($slug = $this->option('tournament')) {
            $t = is_numeric($slug)
                ? Tournament::find($slug)
                : Tournament::where('slug', $slug)->first();
            if (!$t) {
                $this->error("Tournament not found: {$slug}");
                return self::FAILURE;
            }
            $this->info("Syncing {$t->name}...");
            $result = $sync->syncTournamentLive($t);
            $this->table(array_keys($result), [array_values($result)]);
            return self::SUCCESS;
        }

        if ($this->option('all') || true) {
            $this->info('Syncing all active tournaments...');
            $results = $sync->syncAllActive();
            if (empty($results)) {
                $this->warn('No active tournaments with matchstat_tournament_id.');
                return self::SUCCESS;
            }
            foreach ($results as $slug => $result) {
                $this->line("· {$slug}: " . json_encode($result));
            }
            return self::SUCCESS;
        }
    }
}
