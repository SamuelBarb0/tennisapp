<?php

namespace App\Services\Tennis;

use App\Models\BracketPrediction;
use App\Models\Player;
use App\Models\Tournament;

/**
 * Runs after any bracket change to keep user picks intact.
 *
 * Two phases:
 *
 *  1. RE-LINK FROM SNAPSHOT
 *     A pick whose `predicted_winner_id` is NULL (the player row was deleted —
 *     e.g. dedupe / re-import / a scraping bug) or points to a player that no
 *     longer exists is re-linked to the current player using the stable
 *     snapshot (`predicted_player_slug`, then name). The pick's position is
 *     never touched here — it stays exactly where the user put it.
 *
 *  2. REALIGN
 *     Delegates to PredictionRealigner, which promotes placeholder picks,
 *     migrates picks when the draw was reordered, and transfers a pick to the
 *     replacement player when the original withdrew and a lucky loser took the
 *     slot ("I bet on this slot, whoever fills it").
 *
 * Returns a summary array.
 */
class PredictionReconciler
{
    public function __construct(private PredictionRealigner $realigner) {}

    /** @return array{relinked_by_slug:int,relinked_by_name:int,unresolved:int,realign:array} */
    public function reconcile(Tournament $tournament): array
    {
        $out = ['relinked_by_slug' => 0, 'relinked_by_name' => 0, 'unresolved' => 0];

        // Current players keyed by stable references.
        $bySlug = Player::whereNotNull('slug')->pluck('id', 'slug')->all();
        $byName = [];
        foreach (Player::select('id', 'name')->get() as $p) {
            $byName[mb_strtolower(trim($p->name))][] = $p->id;
        }

        $broken = BracketPrediction::where('tournament_id', $tournament->id)
            ->where(function ($q) {
                $q->whereNull('predicted_winner_id')
                  ->orWhereNotIn('predicted_winner_id', Player::select('id'));
            })
            ->get();

        foreach ($broken as $pred) {
            $newId = null;
            $how = null;

            if ($pred->predicted_player_slug && isset($bySlug[$pred->predicted_player_slug])) {
                $newId = $bySlug[$pred->predicted_player_slug];
                $how = 'slug';
            } elseif ($pred->predicted_player_name) {
                $key = mb_strtolower(trim($pred->predicted_player_name));
                if (isset($byName[$key]) && count($byName[$key]) === 1) {
                    $newId = $byName[$key][0];
                    $how = 'name';
                }
            }

            if ($newId) {
                // saving() hook refreshes the snapshot from the new player.
                $pred->update(['predicted_winner_id' => $newId]);
                $out[$how === 'slug' ? 'relinked_by_slug' : 'relinked_by_name']++;
            } else {
                $out['unresolved']++;
            }
        }

        $out['realign'] = $this->realigner->realign($tournament);

        return $out;
    }
}
