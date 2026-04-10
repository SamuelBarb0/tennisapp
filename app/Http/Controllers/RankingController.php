<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RankingController extends Controller
{
    public function index()
    {
        // Get active tournaments that have bracket predictions with points
        $tournaments = Tournament::where('is_active', true)
            ->where('start_date', '>=', '2026-01-01')
            ->whereHas('matches')
            ->orderByDesc('start_date')
            ->get();

        $tournamentRankings = [];

        foreach ($tournaments as $tournament) {
            // Top 10 for this tournament
            $top10 = User::select(
                    'users.id', 'users.name',
                    DB::raw('SUM(bracket_predictions.points_earned) as tournament_points'),
                    DB::raw('COUNT(bracket_predictions.id) as total_predictions'),
                    DB::raw('SUM(CASE WHEN bracket_predictions.is_correct = 1 THEN 1 ELSE 0 END) as correct_predictions')
                )
                ->join('bracket_predictions', 'users.id', '=', 'bracket_predictions.user_id')
                ->where('bracket_predictions.tournament_id', $tournament->id)
                ->groupBy('users.id', 'users.name')
                ->having('tournament_points', '>', 0)
                ->orderByDesc('tournament_points')
                ->limit(10)
                ->get();

            if ($top10->isEmpty()) continue;

            // Check if the logged-in user is outside top 10
            $currentUserEntry = null;
            if (auth()->check()) {
                $userId = auth()->id();
                $isInTop10 = $top10->contains('id', $userId);

                if (!$isInTop10) {
                    // Get the user's ranking position and stats
                    $userStats = User::select(
                            'users.id', 'users.name',
                            DB::raw('SUM(bracket_predictions.points_earned) as tournament_points'),
                            DB::raw('COUNT(bracket_predictions.id) as total_predictions'),
                            DB::raw('SUM(CASE WHEN bracket_predictions.is_correct = 1 THEN 1 ELSE 0 END) as correct_predictions')
                        )
                        ->join('bracket_predictions', 'users.id', '=', 'bracket_predictions.user_id')
                        ->where('bracket_predictions.tournament_id', $tournament->id)
                        ->where('users.id', $userId)
                        ->groupBy('users.id', 'users.name')
                        ->having('tournament_points', '>', 0)
                        ->first();

                    if ($userStats) {
                        // Calculate real position
                        $position = DB::table('bracket_predictions')
                            ->select('user_id', DB::raw('SUM(points_earned) as total'))
                            ->where('tournament_id', $tournament->id)
                            ->groupBy('user_id')
                            ->having('total', '>', $userStats->tournament_points)
                            ->count() + 1;

                        $currentUserEntry = (object) [
                            'id' => $userStats->id,
                            'name' => $userStats->name,
                            'tournament_points' => $userStats->tournament_points,
                            'total_predictions' => $userStats->total_predictions,
                            'correct_predictions' => $userStats->correct_predictions,
                            'position' => $position,
                        ];
                    }
                }
            }

            $tournamentRankings[] = [
                'tournament' => $tournament,
                'top10' => $top10,
                'currentUser' => $currentUserEntry,
            ];
        }

        return view('rankings.index', compact('tournamentRankings'));
    }
}
