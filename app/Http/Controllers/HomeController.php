<?php

namespace App\Http\Controllers;

use App\Models\Banner;
use App\Models\TennisMatch;
use App\Models\Tournament;
use App\Models\User;
use App\Models\Player;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;

class HomeController extends Controller
{
    public function index()
    {
        $banners = Banner::active()->get();

        // Próximo torneo para predecir (el más cercano con partidos pendientes)
        $nextTournament = Tournament::where('is_active', true)
            ->where('start_date', '>=', '2026-01-01')
            ->whereHas('matches')
            ->withCount(['matches as pending_matches_count' => function ($q) {
                $q->where('status', 'pending');
            }])
            ->having('pending_matches_count', '>', 0)
            ->orderBy('start_date')
            ->first();

        // Próximos torneos (con partidos, excluyendo el principal)
        $upcomingTournaments = Tournament::where('is_active', true)
            ->where('start_date', '>=', '2026-01-01')
            ->where('end_date', '>=', now()->subDays(7))
            ->whereHas('matches')
            ->withCount(['matches as pending_matches_count' => function ($q) {
                $q->where('status', 'pending');
            }])
            ->when($nextTournament, function ($q) use ($nextTournament) {
                $q->where('id', '!=', $nextTournament->id);
            })
            ->orderBy('start_date')
            ->take(6)
            ->get();

        // Torneo en vivo (con partidos live)
        $liveTournament = Tournament::where('is_active', true)
            ->whereHas('matches', function ($q) {
                $q->where('status', 'live');
            })
            ->with(['matches' => function ($q) {
                $q->where('status', 'live')->with(['player1', 'player2']);
            }])
            ->first();

        // Resultados recientes
        $recentResults = TennisMatch::with(['player1', 'player2', 'winner', 'tournament'])
            ->where('status', 'finished')
            ->whereNotNull('winner_id')
            ->orderBy('scheduled_at', 'desc')
            ->take(6)
            ->get();

        // Rankings per active tournament (instead of general ranking)
        $activeTournaments = Tournament::where('is_active', true)
            ->where('start_date', '>=', '2026-01-01')
            ->whereIn('status', ['in_progress', 'live'])
            ->whereHas('matches')
            ->orderBy('start_date')
            ->take(4)
            ->get();

        $tournamentRankings = [];
        foreach ($activeTournaments as $at) {
            $ranking = User::select(
                    'users.id', 'users.name',
                    DB::raw('SUM(bracket_predictions.points_earned) as tournament_points'),
                    DB::raw('SUM(CASE WHEN bracket_predictions.is_correct = 1 THEN 1 ELSE 0 END) as correct_count')
                )
                ->join('bracket_predictions', 'users.id', '=', 'bracket_predictions.user_id')
                ->where('bracket_predictions.tournament_id', $at->id)
                ->groupBy('users.id', 'users.name')
                ->having('tournament_points', '>', 0)
                ->orderByDesc('tournament_points')
                ->take(5)
                ->get();

            if ($ranking->isNotEmpty()) {
                $tournamentRankings[] = [
                    'tournament' => $at,
                    'ranking' => $ranking,
                ];
            }
        }

        // Stats dinámicos
        $stats = [
            'tournaments' => Tournament::where('is_active', true)->where('start_date', '>=', '2026-01-01')->whereHas('matches')->count(),
            'players' => Player::count(),
            'total_points' => User::where('is_admin', false)->sum('points'),
            'users' => User::where('is_admin', false)->count(),
        ];

        return view('home', compact(
            'banners', 'nextTournament', 'upcomingTournaments',
            'liveTournament', 'recentResults', 'tournamentRankings', 'stats'
        ));
    }
}
