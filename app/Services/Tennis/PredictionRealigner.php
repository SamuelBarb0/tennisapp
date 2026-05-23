<?php

namespace App\Services\Tennis;

use App\Models\BracketPrediction;
use App\Models\Player;
use App\Models\Tournament;

/**
 * Repairs bracket predictions after a sync moved players around.
 *
 * Two operations, applied in order per round:
 *
 *  1. PLACEHOLDER PROMOTION
 *     If a prediction targets a placeholder player (TBD / Qualifier / LL /
 *     Lucky Loser) at position P, and that slot has since been filled with
 *     a real player, the prediction is updated to point at the real player.
 *     This honors the user's intent: "I'm betting on whoever comes out of
 *     qualifying for this slot".
 *
 *  2. POSITION MIGRATION
 *     If a prediction targets a real player that is no longer in the
 *     position the user picked (because the scraper reordered the bracket),
 *     find where that player is NOW and move the prediction to that new
 *     position. If the user already has a valid prediction at that new
 *     position, the broken one is left alone (very rare).
 *
 * Returns a summary array (counts of promoted / moved / orphaned per round).
 */
class PredictionRealigner
{
    /** @return array{promoted:int,moved:int,orphaned:int} */
    public function realign(Tournament $tournament): array
    {
        $totals = ['promoted' => 0, 'moved' => 0, 'orphaned' => 0];
        $placeholderIds = $this->placeholderPlayerIds();
        $rounds = ['R128', 'R64', 'R32', 'R16', 'QF', 'SF', 'F'];

        foreach ($rounds as $round) {
            $matchesByPos = $tournament->matches()->where('round', $round)->get()->keyBy('bracket_position');
            if ($matchesByPos->isEmpty()) continue;

            $playerToPos = [];
            foreach ($matchesByPos as $pos => $m) {
                if ($m->player1_id) $playerToPos[$m->player1_id] = $pos;
                if ($m->player2_id) $playerToPos[$m->player2_id] = $pos;
            }

            $preds = BracketPrediction::where('tournament_id', $tournament->id)
                ->where('round', $round)
                ->get();

            foreach ($preds as $p) {
                $currentMatch = $matchesByPos[$p->position] ?? null;
                $predictedId  = $p->predicted_winner_id;
                $isValid = $currentMatch
                    && ($predictedId === $currentMatch->player1_id || $predictedId === $currentMatch->player2_id);
                if ($isValid) continue;

                // SKIP "waiting" predictions for future rounds. A R64 match
                // with both sides TBD means R128 hasn't been played yet — the
                // user's pick (e.g. "Sinner wins R64 pos=1") isn't broken,
                // it's just waiting for R128 to resolve. Leaving these alone
                // prevents the realigner from clobbering 100% legitimate
                // future-round picks every sync.
                if ($currentMatch
                    && in_array($currentMatch->player1_id, $placeholderIds, true)
                    && in_array($currentMatch->player2_id, $placeholderIds, true)) {
                    continue;
                }

                // 1) PLACEHOLDER PROMOTION
                if (in_array($predictedId, $placeholderIds, true) && $currentMatch) {
                    $newId = null;
                    if ($currentMatch->player1_id && !in_array($currentMatch->player1_id, $placeholderIds, true)) {
                        $newId = $currentMatch->player1_id;
                    } elseif ($currentMatch->player2_id && !in_array($currentMatch->player2_id, $placeholderIds, true)) {
                        $newId = $currentMatch->player2_id;
                    }
                    if ($newId) {
                        $p->update(['predicted_winner_id' => $newId]);
                        $totals['promoted']++;
                        continue;
                    }
                }

                // 2) POSITION MIGRATION
                $newPos = $playerToPos[$predictedId] ?? null;
                if ($newPos !== null && $newPos !== $p->position) {
                    $conflict = BracketPrediction::where('tournament_id', $tournament->id)
                        ->where('user_id', $p->user_id)
                        ->where('round', $round)
                        ->where('position', $newPos)
                        ->first();

                    if ($conflict) {
                        $conflictMatch = $matchesByPos[$newPos] ?? null;
                        $conflictValid = $conflictMatch
                            && ($conflict->predicted_winner_id === $conflictMatch->player1_id
                                || $conflict->predicted_winner_id === $conflictMatch->player2_id);
                        if ($conflictValid) {
                            $totals['orphaned']++;
                            continue;
                        }
                        $conflict->delete();
                    }

                    $p->update(['position' => $newPos]);
                    $totals['moved']++;
                    continue;
                }

                $totals['orphaned']++;
            }
        }

        return $totals;
    }

    /** @return int[] */
    private function placeholderPlayerIds(): array
    {
        return Player::where(function ($q) {
            $q->where('name', 'TBD')
              ->orWhere('name', 'like', '%Qualifier%')
              ->orWhere('name', 'like', '%Lucky Loser%')
              ->orWhere('name', 'like', '%Por definir%')
              ->orWhereRaw('LOWER(name) = ?', ['ll']);
        })->pluck('id')->all();
    }
}
