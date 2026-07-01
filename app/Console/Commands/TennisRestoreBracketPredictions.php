<?php

namespace App\Console\Commands;

use App\Models\BracketPrediction;
use App\Models\BracketPredictionBackup;
use App\Models\Player;
use App\Models\Tournament;
use App\Services\Tennis\PredictionReconciler;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Restore bracket predictions from a backup taken by the re-import/reset flow
 * (table `bracket_prediction_backups`). This is the automated version of the
 * manual Wimbledon recovery:
 *
 *   - Inserts only the picks a user is currently MISSING (never clobbers a
 *     pick they already have at that round/position).
 *   - Re-links the chosen player from the stable snapshot (slug, then name) so
 *     it works even if the player's row id changed.
 *   - Runs the reconciler afterwards so every restored pick lands on the chosen
 *     player's CURRENT bracket position.
 *
 *   php artisan tennis:restore-bracket-predictions --tournament=63
 *   php artisan tennis:restore-bracket-predictions --tournament=63 --batch=reimport-63-20260701181500
 *   php artisan tennis:restore-bracket-predictions --tournament=63 --dry-run
 */
class TennisRestoreBracketPredictions extends Command
{
    protected $signature = 'tennis:restore-bracket-predictions
                            {--tournament= : Tournament id to restore}
                            {--batch= : Specific backup batch (defaults to the latest for the tournament)}
                            {--dry-run : Report what would happen without writing}';

    protected $description = 'Restore bracket predictions from a backup and re-anchor them to the current draw';

    public function handle(PredictionReconciler $reconciler): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $tournamentId = $this->option('tournament');

        if (!$tournamentId) {
            $this->error('Pass --tournament=<id>.');
            return self::FAILURE;
        }
        $tournament = Tournament::find($tournamentId);
        if (!$tournament) {
            $this->error("Tournament {$tournamentId} not found.");
            return self::FAILURE;
        }

        $batch = $this->option('batch') ?: BracketPredictionBackup::where('tournament_id', $tournamentId)
            ->orderByDesc('id')->value('batch');
        if (!$batch) {
            $this->error("No backups found for tournament {$tournamentId}.");
            return self::FAILURE;
        }

        $backups = BracketPredictionBackup::where('tournament_id', $tournamentId)
            ->where('batch', $batch)->get();
        $this->line("Batch <fg=cyan>{$batch}</> — {$backups->count()} backed-up picks.");

        // Stable-reference lookups for re-linking.
        $bySlug = Player::whereNotNull('slug')->pluck('id', 'slug')->all();
        $byName = [];
        foreach (Player::select('id', 'name')->get() as $p) {
            $byName[mb_strtolower(trim($p->name))][] = $p->id;
        }
        $existing = BracketPrediction::where('tournament_id', $tournamentId)
            ->get()->keyBy(fn ($p) => $p->round . '|' . $p->position);

        $stats = ['inserted' => 0, 'skipped_existing' => 0, 'unresolved_player' => 0];
        $toInsert = [];
        $now = now();

        foreach ($backups as $b) {
            if ($existing->has($b->round . '|' . $b->position)) {
                $stats['skipped_existing']++;
                continue;
            }
            // Resolve the chosen player against the CURRENT players table.
            $winnerId = null;
            if ($b->predicted_winner_id && Player::whereKey($b->predicted_winner_id)->exists()) {
                $winnerId = $b->predicted_winner_id;
            } elseif ($b->predicted_player_slug && isset($bySlug[$b->predicted_player_slug])) {
                $winnerId = $bySlug[$b->predicted_player_slug];
            } elseif ($b->predicted_player_name) {
                $key = mb_strtolower(trim($b->predicted_player_name));
                if (isset($byName[$key]) && count($byName[$key]) === 1) $winnerId = $byName[$key][0];
            }
            if (!$winnerId) { $stats['unresolved_player']++; continue; }

            $toInsert[] = [
                'tournament_id' => $b->tournament_id,
                'user_id' => $b->user_id,
                'round' => $b->round,
                'position' => $b->position,
                'predicted_winner_id' => $winnerId,
                'predicted_player_slug' => $b->predicted_player_slug,
                'predicted_player_name' => $b->predicted_player_name,
                'is_correct' => null,   // let the scorer recompute against real results
                'points_earned' => 0,
                'final_score_prediction' => $b->final_score_prediction,
                'created_at' => $b->original_created_at ?? $now,
                'updated_at' => $now,
            ];
            $stats['inserted']++;
        }

        $this->table(['inserted', 'skipped_existing', 'unresolved_player'],
            [[$stats['inserted'], $stats['skipped_existing'], $stats['unresolved_player']]]);

        if ($dryRun) {
            $this->info('[DRY RUN] nothing written.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($toInsert) {
            foreach (array_chunk($toInsert, 200) as $chunk) {
                BracketPrediction::insert($chunk);
            }
        });

        // Re-anchor everything to the current draw (positions + LL transfers).
        $rec = $reconciler->reconcile($tournament);
        $this->line('reconcile: ' . json_encode($rec));
        $this->info('✓ Restore complete.');

        return self::SUCCESS;
    }
}
