<?php

namespace App\Console\Commands;

use App\Models\BracketPrediction;
use App\Models\TennisMatch;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Reconstruct missing first-round picks by walking BACK from later rounds.
 *
 * Use case: when an earlier sync/realigner bug deleted user picks from
 * round R, but their picks in round R+1 (and beyond) survived. If the user
 * predicted "Sinner wins R64 pos=1", they must have also predicted
 * "Sinner wins R128 pos=1 or pos=2" (whichever R128 slot Sinner currently
 * occupies). We infer those missing R128 picks from the R64 picks.
 *
 * Only creates picks that don't already exist. Never overwrites.
 *
 * Run with --dry-run first to preview.
 */
class TennisInferMissingPicks extends Command
{
    protected $signature = 'tennis:infer-missing-picks
                            {--tournament= : Tournament id (default: all active)}
                            {--user= : Restrict to a single user id (default: all users)}
                            {--dry-run : Show what would be created without writing}';

    protected $description = 'Reconstruye picks faltantes de rondas anteriores usando picks de rondas posteriores.';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        if ($dry) $this->warn('DRY RUN — ningún cambio será persistido.');

        $tournaments = $this->option('tournament')
            ? Tournament::where('id', $this->option('tournament'))->get()
            : Tournament::whereIn('status', ['in_progress', 'upcoming', 'live'])->get();

        $userIds = $this->option('user')
            ? [(int) $this->option('user')]
            : BracketPrediction::distinct()->pluck('user_id')->all();

        $rounds = ['R128', 'R64', 'R32', 'R16', 'QF', 'SF', 'F'];

        foreach ($tournaments as $t) {
            $this->info(PHP_EOL . "→ {$t->name} [{$t->type}] (id={$t->id})");

            // Walk backwards: for each round, use round+1 picks to fill
            // missing round picks.
            for ($i = count($rounds) - 2; $i >= 0; $i--) {
                $earlierRound = $rounds[$i];
                $laterRound   = $rounds[$i + 1];

                // Build position map of the earlier round so we know which
                // (player1_id, player2_id) pair feeds each later-round slot.
                $earlierByPos = $t->matches()
                    ->where('round', $earlierRound)
                    ->get()
                    ->keyBy('bracket_position');
                if ($earlierByPos->isEmpty()) continue;

                $created = 0;
                foreach ($userIds as $uid) {
                    $laterPicks = BracketPrediction::where('tournament_id', $t->id)
                        ->where('user_id', $uid)
                        ->where('round', $laterRound)
                        ->get();

                    foreach ($laterPicks as $lp) {
                        // The two earlier-round slots that feed laterPos:
                        // pos 2K-1 and 2K.
                        $feed1 = $lp->position * 2 - 1;
                        $feed2 = $lp->position * 2;

                        // Skip if user already has both earlier-round picks.
                        $existingForBoth = BracketPrediction::where('tournament_id', $t->id)
                            ->where('user_id', $uid)
                            ->where('round', $earlierRound)
                            ->whereIn('position', [$feed1, $feed2])
                            ->pluck('position')
                            ->all();

                        foreach ([$feed1, $feed2] as $feedPos) {
                            if (in_array($feedPos, $existingForBoth, true)) continue;

                            $feedMatch = $earlierByPos[$feedPos] ?? null;
                            if (!$feedMatch) continue;

                            // Check if the user's later-round pick lives in
                            // this feed slot. If yes, this is the pick we
                            // should reconstruct.
                            $playersInFeed = [$feedMatch->player1_id, $feedMatch->player2_id];
                            if (!in_array($lp->predicted_winner_id, $playersInFeed, true)) {
                                continue;
                            }

                            $this->line(sprintf(
                                '  user=%d %s pos=%d → predict %s (from %s pos=%d)',
                                $uid,
                                $earlierRound,
                                $feedPos,
                                optional(\App\Models\Player::find($lp->predicted_winner_id))->name ?? '?',
                                $laterRound,
                                $lp->position,
                            ));

                            if (!$dry) {
                                BracketPrediction::create([
                                    'tournament_id'       => $t->id,
                                    'user_id'             => $uid,
                                    'round'               => $earlierRound,
                                    'position'            => $feedPos,
                                    'predicted_winner_id' => $lp->predicted_winner_id,
                                ]);
                            }
                            $created++;
                        }
                    }
                }

                if ($created > 0) {
                    $this->info("  {$earlierRound}: creadas {$created} picks faltantes");
                }
            }
        }

        // Re-score everything after the reconstruction so the new picks
        // count points immediately.
        if (!$dry) {
            $this->info(PHP_EOL . 'Re-scoring tournaments...');
            foreach ($tournaments as $t) {
                $scored = \App\Http\Controllers\BracketPredictionController::scoreTournament($t);
                $this->info("  {$t->name}: scored={$scored}");
            }

            $this->info(PHP_EOL . 'Recalculando puntos totales de usuarios...');
            foreach ($userIds as $uid) {
                $total = BracketPrediction::where('user_id', $uid)->where('is_correct', true)->sum('points_earned');
                User::where('id', $uid)->update(['points' => $total]);
            }
        }

        return self::SUCCESS;
    }
}
