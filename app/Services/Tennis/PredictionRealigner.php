<?php

namespace App\Services\Tennis;

use App\Models\BracketPrediction;
use App\Models\Player;
use App\Models\Tournament;

/**
 * Repairs bracket predictions after a sync moved players around.
 *
 * Three operations, applied in order per round:
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
 *  3. REPLACEMENT TRANSFER
 *     If a prediction targets a real player who is no longer ANYWHERE in
 *     the bracket (withdrawal / Lucky Loser substitution mid-tournament),
 *     and the slot the user picked now holds a different real player, the
 *     prediction is transferred to that replacement — in this round AND in
 *     every later round where the same user picked the withdrawn player.
 *     This honors the user's intent: "I bet on this slot, whoever fills it".
 *
 * Returns a summary array (counts of promoted / moved / orphaned per round).
 */
class PredictionRealigner
{
    /** @return array{promoted:int,moved:int,orphaned:int,transferred:int} */
    public function realign(Tournament $tournament): array
    {
        $totals = ['promoted' => 0, 'moved' => 0, 'orphaned' => 0, 'transferred' => 0];
        $placeholderIds = $this->placeholderPlayerIds();
        $rounds = ['R128', 'R64', 'R32', 'R16', 'QF', 'SF', 'F'];

        // Build a set of every Player ID currently anchored to ANY slot of
        // the bracket. Used by the "replacement transfer" pass to detect
        // players who were dropped from the draw entirely (withdrawal).
        $playersInBracket = [];
        foreach ($tournament->matches()->get() as $m) {
            if ($m->player1_id) $playersInBracket[$m->player1_id] = true;
            if ($m->player2_id) $playersInBracket[$m->player2_id] = true;
        }

        // Track per-user replacements detected at the starting round so we
        // can fan them out to later rounds in a single second pass.
        // Format: [user_id => [withdrawn_player_id => replacement_player_id]]
        $userReplacements = [];

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

                // 3) REPLACEMENT TRANSFER
                //    The predicted player is no longer anywhere in the
                //    bracket (full withdrawal) and the slot the user picked
                //    now holds a different real player. Transfer the pick
                //    to that replacement, AND remember the X→Y mapping so
                //    we can fan it out to later rounds in pass 2.
                $isWithdrawn = !isset($playersInBracket[$predictedId])
                    && !in_array($predictedId, $placeholderIds, true);
                if ($isWithdrawn && $currentMatch) {
                    $replacementId = null;
                    foreach ([$currentMatch->player1_id, $currentMatch->player2_id] as $candidate) {
                        if (!$candidate) continue;
                        if (in_array($candidate, $placeholderIds, true)) continue;
                        $replacementId = $candidate;
                        break;
                    }
                    if ($replacementId) {
                        $p->update(['predicted_winner_id' => $replacementId]);
                        $userReplacements[$p->user_id][$predictedId] = $replacementId;
                        $totals['transferred']++;
                        continue;
                    }
                }

                $totals['orphaned']++;
            }
        }

        // PASS 2 — fan out withdrawn-player replacements to later rounds.
        // If user 25 picked Fils to win R64, R32 and R16, and we detected
        // in R128 that Fils withdrew and was replaced by Wong, we now
        // change every "Fils" pick of user 25 to "Wong" — across all
        // later rounds.
        foreach ($userReplacements as $userId => $map) {
            foreach ($map as $withdrawnId => $replacementId) {
                $extra = BracketPrediction::where('tournament_id', $tournament->id)
                    ->where('user_id', $userId)
                    ->where('predicted_winner_id', $withdrawnId)
                    ->get();
                foreach ($extra as $p) {
                    $p->update(['predicted_winner_id' => $replacementId]);
                    $totals['transferred']++;
                }
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
