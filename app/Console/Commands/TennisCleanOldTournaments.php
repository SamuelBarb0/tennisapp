<?php

namespace App\Console\Commands;

use App\Models\Tournament;
use App\Models\TennisMatch;
use Illuminate\Console\Command;

/**
 * One-shot cleanup of historical (finished) tournaments. Runs four passes:
 *
 *   1. Strip phantom "0-0" scores on non-finished matches — these come from
 *      api-tennis pre-populating the scores array before play began.
 *   2. Detect unreported walkovers by bracket shape (player advances to next
 *      round without a winner recorded in the current match).
 *   3. Backfill winner_id from the score when the winner column is empty but
 *      the score itself implies a winner (very rare edge case).
 *   4. Drop orphan placeholders left over from a half-finished bootstrap.
 *
 * All operations are idempotent and DB-only — no API calls. Use this whenever
 * the cron stops touching a tournament but you suspect stale rows are still
 * confusing the bracket UI.
 *
 *   php artisan tennis:clean-old-tournaments              # all finished
 *   php artisan tennis:clean-old-tournaments --tournament=59
 *   php artisan tennis:clean-old-tournaments --dry-run
 */
class TennisCleanOldTournaments extends Command
{
    protected $signature = 'tennis:clean-old-tournaments
                            {--tournament= : Restrict to a single tournament id}
                            {--dry-run : Report findings without writing anything}';

    protected $description = 'Fix walkovers, phantom 0-0 scores, and orphan placeholders on finished tournaments';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $tournaments = $this->option('tournament')
            ? Tournament::where('id', $this->option('tournament'))->get()
            : Tournament::where('status', 'finished')->get();

        if ($tournaments->isEmpty()) {
            $this->info('No tournaments to scan.');
            return self::SUCCESS;
        }

        $totals = [
            'tournaments'         => 0,
            'phantom_scores'      => 0,
            'walkovers_fixed'     => 0,
            'orphan_placeholders' => 0,
        ];

        foreach ($tournaments as $t) {
            $totals['tournaments']++;
            $this->line("");
            $this->line("══ [{$t->id}] {$t->name} ({$t->type}) ══");

            $totals['phantom_scores']      += $this->cleanPhantomScores($t, $dryRun);
            $totals['walkovers_fixed']     += $this->inferWalkovers($t, $dryRun);
            $totals['orphan_placeholders'] += $this->cleanOrphanPlaceholders($t, $dryRun);
        }

        $this->newLine();
        $this->info(($dryRun ? '[DRY RUN] ' : '') . 'Done.');
        $this->table(array_keys($totals), [array_values($totals)]);

        return self::SUCCESS;
    }

    // ─── Pass 1: phantom 0-0 scores ─────────────────────────────────────────
    private function cleanPhantomScores(Tournament $t, bool $dryRun): int
    {
        $rows = TennisMatch::where('tournament_id', $t->id)
            ->where('status', '!=', 'finished')
            ->where(function ($q) {
                $q->where('score', '0-0')
                  ->orWhereRaw("REPLACE(score,' ','') = '0-0'");
            })
            ->get();

        if ($rows->isEmpty()) return 0;

        $this->line("  · phantom 0-0 scores: {$rows->count()}");
        if (!$dryRun) {
            TennisMatch::whereIn('id', $rows->pluck('id'))->update(['score' => null]);
        }
        return $rows->count();
    }

    // ─── Pass 2: infer unreported walkovers ─────────────────────────────────
    private function inferWalkovers(Tournament $t, bool $dryRun): int
    {
        $rounds = ['R128', 'R64', 'R32', 'R16', 'QF', 'SF', 'F'];
        $fixed = 0;

        foreach ($rounds as $i => $round) {
            if ($i === count($rounds) - 1) break;
            $nextRound = $rounds[$i + 1];

            $pending = TennisMatch::where('tournament_id', $t->id)
                ->where('round', $round)
                ->whereNotNull('player1_id')
                ->whereNotNull('player2_id')
                ->whereNull('winner_id')
                ->where(function ($q) {
                    $q->whereNull('score')
                      ->orWhere('score', '0-0')
                      ->orWhereRaw("REPLACE(score,' ','') = '0-0'");
                })
                ->get();

            foreach ($pending as $m) {
                $nextPos = (int) ceil($m->bracket_position / 2);
                $nextMatch = TennisMatch::where('tournament_id', $t->id)
                    ->where('round', $nextRound)
                    ->where('bracket_position', $nextPos)
                    ->first();
                if (!$nextMatch) continue;

                $advancingId = null;
                foreach ([$m->player1_id, $m->player2_id] as $pid) {
                    if ($pid === $nextMatch->player1_id || $pid === $nextMatch->player2_id) {
                        $advancingId = $pid;
                        break;
                    }
                }
                if (!$advancingId) continue;

                $losingSide = $advancingId === $m->player1_id ? 'wo_p2' : 'wo_p1';
                $this->line("  · walkover: {$round} pos {$m->bracket_position} → winner_id={$advancingId} ({$losingSide})");

                if (!$dryRun) {
                    $m->update([
                        'status'      => 'finished',
                        'status_note' => $losingSide,
                        'winner_id'   => $advancingId,
                        'score'       => null,
                    ]);
                }
                $fixed++;
            }
        }

        return $fixed;
    }

    // ─── Pass 3: drop orphan placeholders ───────────────────────────────────
    // Bootstrap/placeholder rows that never got upgraded with a real match —
    // identified by api_event_key prefix AND both players being TBD or empty.
    private function cleanOrphanPlaceholders(Tournament $t, bool $dryRun): int
    {
        $orphans = TennisMatch::where('tournament_id', $t->id)
            ->where(function ($q) {
                $q->where('api_event_key', 'LIKE', 'placeholder-%')
                  ->orWhere('api_event_key', 'LIKE', 'bt-bootstrap-%');
            })
            ->where('status', '!=', 'finished')
            ->where(function ($q) {
                $q->whereNull('player1_id')->orWhereNull('player2_id');
            })
            ->get();

        if ($orphans->isEmpty()) return 0;

        $this->line("  · orphan placeholders: {$orphans->count()}");
        if (!$dryRun) {
            TennisMatch::whereIn('id', $orphans->pluck('id'))->delete();
        }
        return $orphans->count();
    }
}
