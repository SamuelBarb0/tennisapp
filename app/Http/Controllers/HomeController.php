<?php

namespace App\Http\Controllers;

use App\Models\Banner;
use App\Models\TennisMatch;
use App\Models\Tournament;
use App\Models\User;

class HomeController extends Controller
{
    public function index()
    {
        $banners = Banner::active()->get();
        $tournaments = Tournament::where('is_active', true)->orderBy('start_date')->take(6)->get();
        $liveMatches = TennisMatch::with(['player1', 'player2', 'tournament'])->where('status', 'live')->get();
        $upcomingMatches = TennisMatch::with(['player1', 'player2', 'tournament'])->where('status', 'pending')->orderBy('scheduled_at')->take(4)->get();
        $topUsers = User::where('is_admin', false)->orderBy('points', 'desc')->take(5)->get();
        return view('home', compact('banners', 'tournaments', 'liveMatches', 'upcomingMatches', 'topUsers'));
    }
}
