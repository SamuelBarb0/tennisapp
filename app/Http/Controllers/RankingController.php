<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RankingController extends Controller
{
    public function index()
    {
        // Show only NON-finished tournaments (per spec: hide finalized ones)
        $tournaments = Tournament::where('is_active', true)
            ->where('start_date', '>=', '2026-01-01')
            ->where(function ($q) {
                $q->whereNull('status')
                  ->orWhereNotIn('status', ['finished']);
            })
            ->whereHas('matches')
            ->orderByDesc('start_date')
            ->get();

        $tournamentRankings = [];

        foreach ($tournaments as $tournament) {
            // Full ranking with manual_rank as secondary sort (so admin tiebreaks apply)
            $allUsers = User::select(
                    'users.id', 'users.name',
                    DB::raw('SUM(bracket_predictions.points_earned) as tournament_points'),
                    DB::raw('COUNT(bracket_predictions.id) as total_predictions'),
                    DB::raw('SUM(CASE WHEN bracket_predictions.is_correct = 1 THEN 1 ELSE 0 END) as correct_predictions'),
                    DB::raw('COALESCE(tournament_tiebreaks.manual_rank, 9999) as manual_rank')
                )
                ->join('bracket_predictions', 'users.id', '=', 'bracket_predictions.user_id')
                ->leftJoin('tournament_tiebreaks', function ($j) use ($tournament) {
                    $j->on('tournament_tiebreaks.user_id', '=', 'users.id')
                      ->where('tournament_tiebreaks.tournament_id', '=', $tournament->id);
                })
                ->where('bracket_predictions.tournament_id', $tournament->id)
                ->groupBy('users.id', 'users.name', 'tournament_tiebreaks.manual_rank')
                ->having('tournament_points', '>', 0)
                ->orderByDesc('tournament_points')
                ->orderBy('manual_rank')
                ->orderBy('users.name')
                ->get();

            if ($allUsers->isEmpty()) continue;

            // Assign positions
            foreach ($allUsers as $i => $u) {
                $u->position = $i + 1;
            }

            $top10 = $allUsers->take(10);
            $rest = $allUsers->slice(10)->values();

            // If logged-in user is outside top 10, find their entry to highlight
            $currentUserEntry = null;
            if (auth()->check()) {
                $userId = auth()->id();
                $inTop10 = $top10->contains('id', $userId);
                if (!$inTop10) {
                    $currentUserEntry = $allUsers->firstWhere('id', $userId);
                }
            }

            $tournamentRankings[] = [
                'tournament' => $tournament,
                'top10' => $top10,
                'rest' => $rest,
                'currentUser' => $currentUserEntry,
                'totalUsers' => $allUsers->count(),
            ];
        }

        return view('rankings.index', compact('tournamentRankings'));
    }
}
