<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Player;
use App\Models\TennisMatch;
use App\Models\Tournament;
use Illuminate\Http\Request;

class MatchController extends Controller
{
    public function index(Request $request)
    {
        $query = TennisMatch::with(['tournament', 'player1', 'player2', 'winner']);
        if ($request->filled('tournament_id')) $query->where('tournament_id', $request->tournament_id);
        if ($request->filled('status')) $query->where('status', $request->status);
        $matches = $query->latest('scheduled_at')->paginate(20);
        $tournaments = Tournament::orderBy('name')->get();
        return view('admin.matches.index', compact('matches', 'tournaments'));
    }

    public function create()
    {
        $tournaments = Tournament::where('is_active', true)->orderBy('name')->get();
        $players = Player::orderBy('ranking')->get()->groupBy('category');
        return view('admin.matches.create', compact('tournaments', 'players'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'tournament_id' => 'required|exists:tournaments,id',
            'player1_id' => 'required|exists:players,id',
            'player2_id' => 'required|exists:players,id|different:player1_id',
            'round' => 'required|string',
            'scheduled_at' => 'required|date',
        ]);
        // The admin form shows the datetime as Colombia time (the input label
        // says "(hora Colombia)"), so we treat the raw input as Bogotá-local
        // and convert to UTC for storage to keep the rest of the app consistent.
        $data['scheduled_at'] = \Carbon\Carbon::parse($data['scheduled_at'], 'America/Bogota')->setTimezone('UTC');
        $data['status'] = 'pending';
        TennisMatch::create($data);
        return redirect()->route('admin.matches.index')->with('success', 'Partido creado.');
    }

    public function edit(TennisMatch $match)
    {
        $tournaments = Tournament::orderBy('name')->get();
        $players = Player::orderBy('ranking')->get()->groupBy('category');
        return view('admin.matches.edit', compact('match', 'tournaments', 'players'));
    }

    public function update(Request $request, TennisMatch $match)
    {
        $data = $request->validate([
            'tournament_id' => 'required|exists:tournaments,id',
            'player1_id' => 'required|exists:players,id',
            'player2_id' => 'required|exists:players,id|different:player1_id',
            'round' => 'required|string',
            'scheduled_at' => 'required|date',
            'score' => 'nullable|string',
            'winner_id' => 'nullable|exists:players,id',
            'status' => 'required|in:pending,live,finished',
        ]);
        // See store(): same Bogotá → UTC conversion for the datetime input.
        $data['scheduled_at'] = \Carbon\Carbon::parse($data['scheduled_at'], 'America/Bogota')->setTimezone('UTC');
        $match->update($data);
        return redirect()->route('admin.matches.index')->with('success', 'Partido actualizado.');
    }

    public function destroy(TennisMatch $match)
    {
        $match->delete();
        return redirect()->route('admin.matches.index')->with('success', 'Partido eliminado.');
    }
}
