<?php

namespace App\Console\Commands;

use App\Models\Tournament;
use App\Models\TennisMatch;
use App\Models\BracketPrediction;
use App\Models\BracketPredictionBackup;
use App\Services\Tennis\ApiTennisSyncService;
use App\Services\Tennis\PredictionReconciler;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Wipe matches + predictions for finished tournaments and re-import the bracket
 * fresh from bracket.tennis using the new size-aware bootstrap (which puts a
 * 56-draw event like Dubai in R64 instead of mis-mapping it to R128).
 *
 * Only operates on finished tournaments — predictions on those are no longer
 * useful for scoring (the tournament is over), so wiping them is acceptable.
 *
 * After re-import we trigger a regular sync so api-tennis can overlay scores
 * and winners on top of the freshly imported brackets.
 *
 *   php artisan tennis:reimport-from-bracket-tennis --dry-run
 *   php artisan tennis:reimport-from-bracket-tennis --tournament=81
 *   php artisan tennis:reimport-from-bracket-tennis              # all finished
 */
class TennisReimportFromBracketTennis extends Command
{
    protected $signature = 'tennis:reimport-from-bracket-tennis
                            {--tournament= : Restrict to a single tournament id}
                            {--force : Allow re-import of a tournament that is NOT finished (its picks are backed up + preserved)}
                            {--dry-run : Report what would happen without writing}';

    protected $description = 'Wipe and re-import finished tournament brackets from bracket.tennis (size-aware)';

    public function handle(ApiTennisSyncService $sync): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $tournaments = $this->option('tournament')
            ? Tournament::where('id', $this->option('tournament'))->get()
            : Tournament::where('status', 'finished')
                ->whereNotNull('tennisexplorer_slug')
                ->get();

        if ($tournaments->isEmpty()) {
            $this->info('No tournaments to re-import.');
            return self::SUCCESS;
        }

        $totals = [
            'tournaments'        => 0,
            'matches_deleted'    => 0,
            'predictions_deleted' => 0,
            'matches_after_import' => 0,
            'tournaments_failed' => 0,
        ];

        $force = (bool) $this->option('force');

        foreach ($tournaments as $t) {
            $totals['tournaments']++;
            $matchCount = $t->matches()->count();
            $predictionCount = BracketPrediction::where('tournament_id', $t->id)->count();

            $this->line("");
            $this->line("══ [{$t->id}] {$t->name} ({$t->type}) — status: {$t->status} ══");

            // Guard: never blow away picks of a live/upcoming tournament unless
            // explicitly forced. This is exactly how Wimbledon's picks got wiped
            // (reimport with --tournament=63 while it was in progress).
            if ($t->status !== 'finished' && !$force) {
                $this->warn("  · SKIP: tournament is '{$t->status}', not 'finished'. "
                    . "Re-run with --force to re-import (picks are backed up + preserved).");
                continue;
            }

            $this->line("  · would delete: {$matchCount} matches | preserve+backup: {$predictionCount} predictions");

            if ($dryRun) continue;

            try {
                $backup = ['batch' => null, 'count' => 0];
                DB::transaction(function () use ($t, &$totals, $matchCount, &$backup) {
                    // 1) Always snapshot picks into the backup table first.
                    [$batch, $n] = BracketPredictionBackup::snapshotTournament($t->id, 'reimport');
                    $backup = ['batch' => $batch, 'count' => $n];

                    // 2) Delete ONLY matches. Predictions do NOT reference matches
                    //    (only players/tournament/user), so we keep them in place —
                    //    the reconciler re-anchors them after the fresh import.
                    TennisMatch::where('tournament_id', $t->id)->delete();
                    $totals['matches_deleted'] += $matchCount;
                });
                $this->line("  · backed up {$backup['count']} predictions (batch {$backup['batch']})");

                // Trigger a normal sync — bootstrapFromBracketTennis runs because
                // the tournament has no R128/R64 rows anymore.
                $result = $sync->syncTournamentLive($t);
                $this->line("  · sync result: " . json_encode($result));

                // Re-link + realign the preserved picks against the fresh bracket.
                $rec = app(PredictionReconciler::class)->reconcile($t);
                $this->line("  · reconcile: " . json_encode($rec));

                $newCount = $t->matches()->count();
                $totals['matches_after_import'] += $newCount;
                $this->line("  · matches after re-import: {$newCount}");
            } catch (\Throwable $e) {
                $totals['tournaments_failed']++;
                $this->error("  · FAILED: " . $e->getMessage());
            }
        }

        $this->newLine();
        $this->info(($dryRun ? '[DRY RUN] ' : '') . 'Done.');
        $this->table(array_keys($totals), [array_values($totals)]);

        return self::SUCCESS;
    }
}
