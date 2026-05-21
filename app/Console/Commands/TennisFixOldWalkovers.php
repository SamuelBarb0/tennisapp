<?php

namespace App\Console\Commands;

use App\Models\Tournament;
use App\Models\TennisMatch;
use Illuminate\Console\Command;

/**
 * Detect and fix unreported walkovers in historical tournaments without calling
 * the API. Uses the same bracket-shape inference as ApiTennisSyncService::
 * inferUnreportedWalkovers(): a match that's pending/incomplete but whose
 * player already appears in the next round was a walkover.
 *
 *   php artisan tennis:fix-old-walkovers              # all finished tournaments
 *   php artisan tennis:fix-old-walkovers --tournament=59
 *   php artisan tennis:fix-old-walkovers --dry-run
 */
class TennisFixOldWalkovers extends Command
{
    protected $signature = 'tennis:fix-old-walkovers
                            {--tournament= : Restrict to a single tournament id}
                            {--dry-run : Report findings without writing anything}';

    protected $description = 'Mark unreported walkovers in finished tournaments (sets winner_id + status_note)';

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

        $rounds = ['R128', 'R64', 'R32', 'R16', 'QF', 'SF', 'F'];
        $totals = ['tournaments' => 0, 'walkovers_fixed' => 0];

        foreach ($tournaments as $t) {
            $totals['tournaments']++;
            $fixedInTournament = 0;

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

                    $this->line("· [{$t->id}] {$t->name} | {$round} pos {$m->bracket_position}: walkover (winner_id={$advancingId}, note={$losingSide})");
                    if (!$dryRun) {
                        $m->update([
                            'status'      => 'finished',
                            'status_note' => $losingSide,
                            'winner_id'   => $advancingId,
                            'score'       => null,
                        ]);
                    }
                    $fixedInTournament++;
                    $totals['walkovers_fixed']++;
                }
            }

            if ($fixedInTournament === 0) {
                $this->line("  [{$t->id}] {$t->name}: clean, nothing to fix.");
            }
        }

        $this->newLine();
        $this->info(($dryRun ? '[DRY RUN] ' : '') . 'Done.');
        $this->table(array_keys($totals), [array_values($totals)]);

        return self::SUCCESS;
    }
}
