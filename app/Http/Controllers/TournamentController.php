<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use App\Models\TennisMatch;
use Illuminate\Http\Request;

class TournamentController extends Controller
{
    public function index(Request $request)
    {
        $query = Tournament::where('is_active', true);
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        $tournaments = $query->orderBy('start_date')->paginate(12);
        return view('tournaments.index', compact('tournaments'));
    }

    public function show(Tournament $tournament)
    {
        $matches = TennisMatch::with(['player1', 'player2', 'winner'])
            ->where('tournament_id', $tournament->id)
            ->orderBy('scheduled_at')
            ->get()
            ->groupBy('round');
        return view('tournaments.show', compact('tournament', 'matches'));
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
