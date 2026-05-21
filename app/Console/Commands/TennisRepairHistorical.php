<?php

namespace App\Console\Commands;

use App\Models\TennisMatch;
use App\Models\Tournament;
use App\Services\Tennis\ApiTennisSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Repair finished tournaments whose matches never received final results.
 *
 * Why this exists: `tennis:sync-live` only looked at `now() ± 21 days` (pre-fix),
 * so any tournament whose end_date drifted further into the past kept its
 * placeholders (status=pending, scheduled_at=created_at) forever. After the
 * sync window was anchored to start_date / end_date, this command goes back
 * and repairs the historical mess for every affected tournament in one pass.
 *
 * It is also idempotent: re-running it on a healthy tournament does nothing.
 */
class TennisRepairHistorical extends Command
{
    protected $signature = 'tennis:repair-historical
                            {--dry-run : Show what would change without writing anything}
                            {--only-broken : Skip tournaments that already have finished matches}';

    protected $description = 'Re-sync finished/past tournaments from api-tennis and clean orphan pending placeholders';

    public function handle(ApiTennisSyncService $sync): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $onlyBroken = (bool) $this->option('only-broken');

        // Targets: tournaments that ended in the past — either explicitly marked
        // as 'finished' OR whose end_date has slipped past today. We don't filter
        // by is_active because a finished tournament might still be set inactive.
        $tournaments = Tournament::query()
            ->whereNotNull('api_tournament_key')
            ->where(function ($q) {
                $q->where('status', 'finished')
                  ->orWhere('end_date', '<', now()->toDateString());
            })
            ->orderBy('end_date', 'desc')
            ->get();

        if ($tournaments->isEmpty()) {
            $this->info('No past tournaments need repair.');
            return self::SUCCESS;
        }

        $this->info(($dryRun ? '[DRY RUN] ' : '') . "Found {$tournaments->count()} past tournaments. Starting…");
        $this->newLine();

        $totals = [
            'tournaments_processed' => 0,
            'tournaments_skipped'   => 0,
            'fixtures_pulled'       => 0,
            'matches_finished'      => 0,
            'orphans_deleted'       => 0,
            'tournaments_failed'    => 0,
        ];

        foreach ($tournaments as $t) {
            $pendingBefore  = $t->matches()->where('status', 'pending')->count();
            $finishedBefore = $t->matches()->where('status', 'finished')->count();

            // Skip tournaments that already have results when --only-broken is set.
            // "Broken" here means: status=finished but no matches finished yet.
            if ($onlyBroken && $finishedBefore > 0) {
                $totals['tournaments_skipped']++;
                continue;
            }

            $this->line("· [{$t->id}] {$t->name} ({$t->end_date?->toDateString()})");
            $this->line("    before: finished={$finishedBefore}, pending={$pendingBefore}");

            if ($dryRun) {
                $totals['tournaments_processed']++;
                continue;
            }

            try {
                $result = $sync->syncTournamentLive($t);
                $totals['fixtures_pulled']  += (int) ($result['fixtures'] ?? 0);
                $totals['matches_finished'] += (int) ($result['finished'] ?? 0);
                $this->line("    sync:   " . json_encode($result));
            } catch (\Throwable $e) {
                $totals['tournaments_failed']++;
                $this->error("    sync FAILED: {$e->getMessage()}");
                Log::error("tennis:repair-historical failed for tournament {$t->id}", [
                    'tournament' => $t->name,
                    'error'      => $e->getMessage(),
                ]);
                continue;
            }

            // Clean orphan placeholders: only synthetic placeholder rows (those
            // ensureBracketPlaceholders() created with TBD players to render
            // later rounds), NOT bootstrap rows with real players from
            // bracket.tennis. Otherwise we wipe out R128/R64 brackets that
            // never matched up with an api-tennis fixture.
            //
            // We identify true orphans by api_event_key prefix: 'placeholder-%'.
            // bracket.tennis bootstrap rows use 'bt-bootstrap-%' and stay.
            $finishedAfter = $t->matches()->where('status', 'finished')->count();
            if ($finishedAfter > $finishedBefore) {
                $deleted = TennisMatch::where('tournament_id', $t->id)
                    ->where('status', 'pending')
                    ->where('api_event_key', 'LIKE', 'placeholder-%')
                    ->delete();
                $totals['orphans_deleted'] += $deleted;
                $this->line("    cleanup: deleted {$deleted} synthetic placeholder rows");
            } else {
                $this->line("    cleanup: skipped (no new finished matches — keeping all rows)");
            }

            $totals['tournaments_processed']++;
            $this->newLine();
        }

        // Final summary.
        $this->info('═══ Summary ═══');
        $this->table(array_keys($totals), [array_values($totals)]);

        return self::SUCCESS;
    }
}
