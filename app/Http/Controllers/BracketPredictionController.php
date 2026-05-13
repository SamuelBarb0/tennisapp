<?php

namespace App\Http\Controllers;

use App\Mail\PredictionConfirmedMail;
use App\Models\BracketPrediction;
use App\Models\Player;
use App\Models\Tournament;
use App\Models\TennisMatch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

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

        $request->validate([
            'picks' => 'required|array',
            'picks.*' => 'array',
            'picks.*.*' => 'integer|exists:players,id',
            'final_score' => 'nullable|string|max:20',
        ]);

        $picks = $request->input('picks');
        $finalScore = $request->input('final_score');

        // Before rewriting, clean picks that are no longer valid (player changed in a round)
        // We allow full update while tournament hasn't started.
        $existingRows = BracketPrediction::where('tournament_id', $tournament->id)
            ->where('user_id', auth()->id())
            ->get();

        // Delete rows for round/position not present in new picks
        $newKeys = [];
        foreach ($picks as $round => $positions) {
            foreach ($positions as $position => $playerId) {
                $newKeys[] = $round . '|' . (int)$position;
            }
        }
        foreach ($existingRows as $row) {
            $key = $row->round . '|' . (int)$row->position;
            if (!in_array($key, $newKeys, true)) {
                $row->delete();
            }
        }

        foreach ($picks as $round => $positions) {
            foreach ($positions as $position => $playerId) {
                $attrs = ['predicted_winner_id' => $playerId];
                if ($round === 'F' && (int)$position === 1 && $finalScore !== null) {
                    $attrs['final_score_prediction'] = $finalScore;
                }
                BracketPrediction::updateOrCreate(
                    [
                        'tournament_id' => $tournament->id,
                        'user_id' => auth()->id(),
                        'round' => $round,
                        'position' => (int) $position,
                    ],
                    $attrs
                );
            }
        }

        // Confirmation email — send only the first time the user saves a complete
        // bracket for this tournament (not every re-save). The "F" round with a
        // pick at position 1 is our champion signal.
        $champion = null;
        if (isset($picks['F'][1])) {
            $champion = Player::find($picks['F'][1])?->name;
        }

        $alreadyNotified = $existingRows->isNotEmpty();
        if (!$alreadyNotified) {
            try {
                Mail::to(auth()->user()->email)->send(
                    new PredictionConfirmedMail(auth()->user(), $tournament, $champion)
                );
            } catch (\Throwable $e) {
                Log::warning('Prediction confirmation mail failed', [
                    'user'       => auth()->id(),
                    'tournament' => $tournament->id,
                    'error'      => $e->getMessage(),
                ]);
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

        // Get all finished matches grouped by round with bracket position
        $matches = $tournament->matches()
            ->where('status', 'finished')
            ->whereNotNull('winner_id')
            ->orderBy('bracket_position')
            ->get();

        // Collect eliminated player IDs (matches finished where they lost)
        $eliminated = [];
        foreach ($matches as $match) {
            if ($match->player1_id && $match->player1_id != $match->winner_id) $eliminated[$match->player1_id] = true;
            if ($match->player2_id && $match->player2_id != $match->winner_id) $eliminated[$match->player2_id] = true;
        }

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

        // Mark as incorrect any pending prediction whose picked player is already eliminated —
        // they can't possibly win a later round.
        if (!empty($eliminated)) {
            $stale = BracketPrediction::where('tournament_id', $tournament->id)
                ->whereNull('is_correct')
                ->whereIn('predicted_winner_id', array_keys($eliminated))
                ->get();
            foreach ($stale as $pred) {
                $pred->update(['is_correct' => false, 'points_earned' => 0]);
                $scored++;
            }
        }

        return $scored;
    }
}
