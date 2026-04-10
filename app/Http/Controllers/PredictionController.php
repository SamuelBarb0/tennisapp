<?php

namespace App\Http\Controllers;

use App\Models\Prediction;
use App\Models\TennisMatch;
use App\Models\User;
use Illuminate\Http\Request;

class PredictionController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'match_id' => 'required|exists:matches,id',
            'predicted_winner_id' => 'required|exists:players,id',
        ]);

        $match = TennisMatch::findOrFail($request->match_id);
        if ($match->status !== 'pending') {
            return back()->with('error', 'Este partido ya no acepta pronósticos.');
        }

        Prediction::updateOrCreate(
            ['user_id' => auth()->id(), 'match_id' => $request->match_id],
            ['predicted_winner_id' => $request->predicted_winner_id]
        );

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'Pronóstico guardado exitosamente.']);
        }

        return back()->with('success', 'Pronóstico guardado exitosamente.');
    }

    /**
     * Debug: simulate match result (win/lose) for testing.
     * Only in local/debug environment.
     */
    public function debugResolve(Request $request)
    {
        if (!config('app.debug')) {
            abort(403);
        }

        $request->validate([
            'match_id' => 'required|exists:matches,id',
            'result' => 'required|in:win,lose',
        ]);

        $match = TennisMatch::findOrFail($request->match_id);
        $prediction = Prediction::where('user_id', auth()->id())
            ->where('match_id', $match->id)
            ->first();

        if (!$prediction) {
            return response()->json(['success' => false, 'message' => 'No prediction found']);
        }

        // Determine winner based on result
        if ($request->result === 'win') {
            $winnerId = $prediction->predicted_winner_id;
        } else {
            $winnerId = $prediction->predicted_winner_id == $match->player1_id
                ? $match->player2_id
                : $match->player1_id;
        }

        // Update match
        $match->update([
            'status' => 'finished',
            'winner_id' => $winnerId,
            'score' => '6-4 7-5',
        ]);

        // Score prediction
        $match->loadMissing('tournament');
        $roundPoints = $match->tournament->getPointsForRound($match->round);
        $isCorrect = $prediction->predicted_winner_id == $winnerId;
        $pointsEarned = $isCorrect ? $roundPoints : 0;

        $prediction->update([
            'is_correct' => $isCorrect,
            'points_earned' => $pointsEarned,
        ]);

        if ($isCorrect) {
            User::where('id', auth()->id())->increment('points', $pointsEarned);
        }

        $user = auth()->user()->fresh();

        return response()->json([
            'success' => true,
            'correct' => $isCorrect,
            'points_earned' => $pointsEarned,
            'total_points' => $user->points,
            'round' => $match->round,
        ]);
    }
}
