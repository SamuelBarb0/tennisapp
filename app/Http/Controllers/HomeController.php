<?php

namespace App\Http\Controllers;

use App\Models\Banner;
use App\Models\TennisMatch;
use App\Models\Tournament;
use App\Models\User;
use App\Models\Player;
use App\Models\Setting;

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

        // Resultados recientes (partidos terminados, más recientes primero)
        $recentResults = TennisMatch::with(['player1', 'player2', 'winner', 'tournament'])
            ->where('status', 'finished')
            ->whereNotNull('winner_id')
            ->orderBy('scheduled_at', 'desc')
            ->take(6)
            ->get();

        // Top ranking de usuarios
        $topUsers = User::where('is_admin', false)
            ->where('points', '>', 0)
            ->orderBy('points', 'desc')
            ->take(10)
            ->get();

        // Stats dinámicos
        $stats = [
            'tournaments' => Tournament::where('is_active', true)->where('start_date', '>=', '2026-01-01')->whereHas('matches')->count(),
            'players' => Player::count(),
            'total_points' => User::where('is_admin', false)->sum('points'),
            'users' => User::where('is_admin', false)->count(),
        ];

        return view('home', compact(
            'banners', 'nextTournament', 'upcomingTournaments',
            'liveTournament', 'recentResults', 'topUsers', 'stats'
        ));
    }
}
