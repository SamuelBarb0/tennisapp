<?php

namespace App\Http\Controllers;

use App\Models\Prediction;
use App\Models\Tournament;
use App\Models\TennisMatch;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TournamentController extends Controller
{
    public function index(Request $request)
    {
        $query = Tournament::where('is_active', true)
            ->where('end_date', '>=', now()->subDays(7))
            ->where('start_date', '>=', '2026-01-01')
            ->whereHas('matches');
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        $tournaments = $query->orderByDesc('start_date')->paginate(12);
        return view('tournaments.index', compact('tournaments'));
    }

    public function show(Tournament $tournament)
    {
        $tournament->load('roundPoints');

        $matches = TennisMatch::with(['player1', 'player2', 'winner'])
            ->where('tournament_id', $tournament->id)
            ->orderBy('scheduled_at')
            ->get()
            ->groupBy('round');

        // Ranking del torneo: usuarios con más puntos ganados en este torneo
        $tournamentRanking = User::select(
                'users.id', 'users.name',
                DB::raw('SUM(predictions.points_earned) as tournament_points'),
                DB::raw('COUNT(predictions.id) as tournament_predictions'),
                DB::raw('SUM(CASE WHEN predictions.is_correct = 1 THEN 1 ELSE 0 END) as correct_predictions')
            )
            ->join('predictions', 'users.id', '=', 'predictions.user_id')
            ->join('matches', 'predictions.match_id', '=', 'matches.id')
            ->where('matches.tournament_id', $tournament->id)
            ->where('users.is_admin', false)
            ->groupBy('users.id', 'users.name')
            ->having('tournament_points', '>', 0)
            ->orderByDesc('tournament_points')
            ->take(10)
            ->get();

        // User's existing predictions for this tournament
        $userPredictions = collect();
        if (auth()->check()) {
            $matchIds = $matches->flatten()->pluck('id');
            $userPredictions = Prediction::where('user_id', auth()->id())
                ->whereIn('match_id', $matchIds)
                ->pluck('predicted_winner_id', 'match_id');
        }

        return view('tournaments.show', compact('tournament', 'matches', 'tournamentRanking', 'userPredictions'));
    }

    public function predict(Tournament $tournament)
    {
        $matches = TennisMatch::with(['player1', 'player2'])
            ->where('tournament_id', $tournament->id)
            ->where('status', 'pending')
            ->orderBy('scheduled_at')
            ->get();
        return view('tournaments.predict', compact('tournament', 'matches'));
    }
}
