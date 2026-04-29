<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BracketPrediction;
use App\Models\TennisMatch;
use App\Models\Tournament;
use App\Models\TournamentPayment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'users' => User::where('is_admin', false)->count(),
            'tournaments_total' => Tournament::count(),
            'tournaments_active' => Tournament::where('is_active', true)
                ->whereIn('status', ['upcoming', 'in_progress', 'live'])->count(),
            'predictions' => BracketPrediction::count(),
            'revenue_total' => TournamentPayment::where('status', 'approved')->sum('amount'),
            'revenue_pending' => TournamentPayment::where('status', 'pending')->sum('amount'),
            'paid_users' => TournamentPayment::where('status', 'approved')->distinct('user_id')->count('user_id'),
            'live_matches' => TennisMatch::where('status', 'live')->count(),
            'pending_redemptions' => \App\Models\PrizeRedemption::where('status', 'pending')->count(),
        ];

        $recentUsers = User::where('is_admin', false)->latest()->take(5)->get();

        // Latest bracket predictions (instead of the legacy per-match Prediction model)
        $recentPredictions = BracketPrediction::with(['user', 'tournament', 'predictedWinner'])
            ->latest()
            ->take(8)
            ->get();

        // Latest payments (any status)
        $recentPayments = TournamentPayment::with(['user', 'tournament'])
            ->latest()
            ->take(8)
            ->get();

        // Tournaments needing attention (active with bracket but no predictions yet)
        $tournamentsAtAGlance = Tournament::where('is_active', true)
            ->whereHas('matches')
            ->withCount([
                'matches as total_matches',
                'matches as finished_matches' => fn($q) => $q->where('status', 'finished'),
            ])
            ->orderBy('start_date')
            ->take(6)
            ->get();

        return view('admin.dashboard', compact(
            'stats', 'recentUsers', 'recentPredictions', 'recentPayments', 'tournamentsAtAGlance'
        ));
    }
}
