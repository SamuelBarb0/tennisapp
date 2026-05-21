<?php

namespace App\Console\Commands;

use App\Models\Tournament;
use App\Models\TennisMatch;
use App\Models\BracketPrediction;
use App\Services\Tennis\ApiTennisSyncService;
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

        foreach ($tournaments as $t) {
            $totals['tournaments']++;
            $matchCount = $t->matches()->count();
            $predictionCount = BracketPrediction::where('tournament_id', $t->id)->count();

            $this->line("");
            $this->line("══ [{$t->id}] {$t->name} ({$t->type}) ══");
            $this->line("  · would delete: {$matchCount} matches, {$predictionCount} predictions");

            if ($dryRun) continue;

            try {
                DB::transaction(function () use ($t, &$totals, $matchCount, $predictionCount) {
                    // Predictions first (FK references matches/players).
                    BracketPrediction::where('tournament_id', $t->id)->delete();
                    // Then matches.
                    TennisMatch::where('tournament_id', $t->id)->delete();

                    $totals['matches_deleted']     += $matchCount;
                    $totals['predictions_deleted'] += $predictionCount;
                });

                // Trigger a normal sync — that will call bootstrapFromBracketTennis
                // because the tournament has no R128/R64 rows anymore, AND now
                // routes to the correct start round based on bracket.tennis size.
                $result = $sync->syncTournamentLive($t);
                $this->line("  · sync result: " . json_encode($result));

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
