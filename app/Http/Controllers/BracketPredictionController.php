<?php

namespace App\Http\Controllers;

use App\Models\BracketPrediction;
use App\Models\Tournament;
use App\Models\TennisMatch;
use Illuminate\Http\Request;

class BracketPredictionController extends Controller
{
    /**
     * Save the user's full bracket prediction for a tournament.
     * Expects JSON: { picks: { "R32": { "1": playerId, "2": playerId, ... }, "R16": {...}, ... } }
     */
    public function store(Request $request, Tournament $tournament)
    {
        // Check if predictions are still allowed (before first match starts)
        $firstMatch = $tournament->matches()
            ->whereNotIn('status', ['cancelled'])
            ->orderBy('scheduled_at')
            ->first();

        if (!$firstMatch || now()->gte($firstMatch->scheduled_at)) {
            return response()->json([
                'success' => false,
                'message' => 'Las predicciones ya están cerradas. El torneo ya comenzó.',
            ], 422);
        }

        // Check if user already saved a bracket (no changes allowed)
        $existing = BracketPrediction::where('tournament_id', $tournament->id)
            ->where('user_id', auth()->id())
            ->exists();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'Ya guardaste tu bracket. No se puede modificar.',
            ], 422);
        }

        $request->validate([
            'picks' => 'required|array',
            'picks.*' => 'array',
            'picks.*.*' => 'integer|exists:players,id',
        ]);

        $picks = $request->input('picks');

        foreach ($picks as $round => $positions) {
            foreach ($positions as $position => $playerId) {
                BracketPrediction::updateOrCreate(
                    [
                        'tournament_id' => $tournament->id,
                        'user_id' => auth()->id(),
                        'round' => $round,
                        'position' => (int) $position,
                    ],
                    ['predicted_winner_id' => $playerId]
                );
            }
        }

        return response()->json(['success' => true, 'message' => 'Bracket guardado exitosamente.']);
    }

    /**
     * Get the user's bracket prediction for a tournament.
     */
    public function show(Tournament $tournament)
    {
        $predictions = BracketPrediction::where('tournament_id', $tournament->id)
            ->where('user_id', auth()->id())
            ->get()
            ->groupBy('round')
            ->map(fn($items) => $items->pluck('predicted_winner_id', 'position'));

        return response()->json(['success' => true, 'picks' => $predictions]);
    }

    /**
     * Score bracket predictions for a tournament after results come in.
     * Called by the sync process when matches finish.
     */
    public static function scoreTournament(Tournament $tournament): int
    {
        $scored = 0;
        $roundOrder = ['R128', 'R64', 'R32', 'R16', 'QF', 'SF', 'F'];

        // Get all finished matches grouped by round with bracket position
        $matches = $tournament->matches()
            ->where('status', 'finished')
            ->whereNotNull('winner_id')
            ->orderBy('bracket_position')
            ->get();

        foreach ($matches as $match) {
            $round = $match->round;
            // Calculate the bracket position for this match within its round
            $roundMatches = $matches->where('round', $round)->values();
            $matchIndex = $roundMatches->search(fn($m) => $m->id === $match->id);
            $position = $matchIndex + 1;

            $points = $tournament->getPointsForRound($round);

            // Find all unsettled predictions for this round+position
            $predictions = BracketPrediction::where('tournament_id', $tournament->id)
                ->where('round', $round)
                ->where('position', $position)
                ->whereNull('is_correct')
                ->get();

            foreach ($predictions as $pred) {
                $isCorrect = $pred->predicted_winner_id == $match->winner_id;
                $earned = $isCorrect ? $points : 0;

                $pred->update([
                    'is_correct' => $isCorrect,
                    'points_earned' => $earned,
                ]);

                if ($isCorrect) {
                    \App\Models\User::where('id', $pred->user_id)->increment('points', $earned);
                }

                $scored++;
            }
        }

        return $scored;
    }
}
