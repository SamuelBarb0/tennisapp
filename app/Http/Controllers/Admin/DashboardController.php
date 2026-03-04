<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Prediction;
use App\Models\TennisMatch;
use App\Models\Tournament;
use App\Models\User;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'users' => User::where('is_admin', false)->count(),
            'tournaments' => Tournament::count(),
            'predictions' => Prediction::count(),
            'revenue' => Payment::where('status', 'approved')->sum('amount'),
            'activeMatches' => TennisMatch::where('status', 'live')->count(),
            'pendingRedemptions' => \App\Models\PrizeRedemption::where('status', 'pending')->count(),
        ];
        $recentUsers = User::where('is_admin', false)->latest()->take(5)->get();
        $recentPredictions = Prediction::with(['user', 'match.player1', 'match.player2'])->latest()->take(10)->get();
        return view('admin.dashboard', compact('stats', 'recentUsers', 'recentPredictions'));
    }
}
