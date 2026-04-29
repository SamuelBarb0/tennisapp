<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BracketPrediction;
use App\Models\Tournament;
use App\Models\TournamentRoundPoints;
use App\Models\TournamentTiebreak;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TournamentController extends Controller
{
    public function index()
    {
        $tournaments = Tournament::latest()->paginate(15);
        return view('admin.tournaments.index', compact('tournaments'));
    }

    public function create()
    {
        return view('admin.tournaments.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:ATP,WTA,GrandSlam',
            'location' => 'required|string',
            'city' => 'required|string',
            'country' => 'required|string',
            'surface' => 'nullable|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'is_premium' => 'boolean',
            'is_active' => 'boolean',
            'featured_on_home' => 'boolean',
            'price' => 'nullable|numeric|min:0',
            'matchstat_tournament_id' => 'nullable|integer',
            'image' => 'nullable|image|max:2048',
        ]);
        $data['is_premium'] = $request->boolean('is_premium');
        $data['is_active'] = $request->boolean('is_active');
        $data['featured_on_home'] = $request->boolean('featured_on_home');
        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('tournaments', 'public');
        }
        $tournament = Tournament::create($data);

        // Guardar puntos por ronda
        if ($request->has('round_points')) {
            foreach ($request->input('round_points', []) as $round => $points) {
                if ($points !== null && $points !== '') {
                    TournamentRoundPoints::create([
                        'tournament_id' => $tournament->id,
                        'round' => $round,
                        'points' => (int) $points,
                    ]);
                }
            }
        }

        return redirect()->route('admin.tournaments.index')->with('success', 'Torneo creado exitosamente.');
    }

    public function edit(Tournament $tournament)
    {
        $tournament->load('roundPoints');
        return view('admin.tournaments.edit', compact('tournament'));
    }

    public function update(Request $request, Tournament $tournament)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:ATP,WTA,GrandSlam',
            'location' => 'required|string',
            'city' => 'required|string',
            'country' => 'required|string',
            'surface' => 'nullable|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'is_premium' => 'boolean',
            'is_active' => 'boolean',
            'featured_on_home' => 'boolean',
            'price' => 'nullable|numeric|min:0',
            'matchstat_tournament_id' => 'nullable|integer',
            'image' => 'nullable|image|max:2048',
        ]);
        $data['is_premium'] = $request->boolean('is_premium');
        $data['is_active'] = $request->boolean('is_active');
        $data['featured_on_home'] = $request->boolean('featured_on_home');
        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('tournaments', 'public');
        }
        $tournament->update($data);

        // Guardar puntos por ronda
        if ($request->has('round_points')) {
            foreach ($request->input('round_points', []) as $round => $points) {
                if ($points !== null && $points !== '') {
                    TournamentRoundPoints::updateOrCreate(
                        ['tournament_id' => $tournament->id, 'round' => $round],
                        ['points' => (int) $points]
                    );
                } else {
                    TournamentRoundPoints::where('tournament_id', $tournament->id)
                        ->where('round', $round)
                        ->delete();
                }
            }
        }

        return redirect()->route('admin.tournaments.index')->with('success', 'Torneo actualizado.');
    }

    public function destroy(Tournament $tournament)
    {
        $tournament->delete();
        return redirect()->route('admin.tournaments.index')->with('success', 'Torneo eliminado.');
    }

    /**
     * Show tiebreak resolution panel — only surfaces groups of users tied on points.
     */
    public function tiebreaks(Tournament $tournament)
    {
        $tiebreaksLocked = TournamentTiebreak::where('tournament_id', $tournament->id)->exists();

        // Aggregate per-user points for this tournament
        $rows = User::select(
                'users.id', 'users.name',
                DB::raw('COALESCE(SUM(bracket_predictions.points_earned), 0) as tournament_points'),
                DB::raw('SUM(CASE WHEN bracket_predictions.is_correct = 1 THEN 1 ELSE 0 END) as correct_predictions')
            )
            ->join('bracket_predictions', 'users.id', '=', 'bracket_predictions.user_id')
            ->where('bracket_predictions.tournament_id', $tournament->id)
            ->groupBy('users.id', 'users.name')
            ->having('tournament_points', '>', 0)
            ->orderByDesc('tournament_points')
            ->get();

        // Load each user's final-score prediction (row F/position=1)
        $finalScores = BracketPrediction::where('tournament_id', $tournament->id)
            ->where('round', 'F')
            ->where('position', 1)
            ->whereNotNull('final_score_prediction')
            ->pluck('final_score_prediction', 'user_id');

        // Load the real final result (if the tournament has finished)
        $finalMatch = $tournament->matches()
            ->where('round', 'F')
            ->orderBy('bracket_position')
            ->first();

        // Existing manual ordering
        $manualRanks = TournamentTiebreak::where('tournament_id', $tournament->id)
            ->pluck('manual_rank', 'user_id');

        // Group by points (every group — even singletons — so admin sees full ranking)
        // and flag which groups actually have a tie that needs resolving.
        $tieGroups = $rows
            ->groupBy('tournament_points')
            ->map(function ($group) use ($finalScores, $manualRanks) {
                return $group->map(function ($u) use ($finalScores, $manualRanks) {
                    $u->final_score_prediction = $finalScores[$u->id] ?? null;
                    $u->manual_rank = $manualRanks[$u->id] ?? null;
                    return $u;
                })->sortBy(fn($u) => $u->manual_rank ?? PHP_INT_MAX)->values();
            });

        $hasAnyTie = $tieGroups->contains(fn($g) => $g->count() >= 2);

        return view('admin.tournaments.tiebreaks', compact(
            'tournament', 'tieGroups', 'finalMatch', 'tiebreaksLocked', 'hasAnyTie'
        ));
    }

    /**
     * Persist the admin's chosen tiebreak ordering for one or more tied groups.
     * Expects: order[user_id] = rank (1-based) — lower rank wins.
     */
    public function saveTiebreaks(Request $request, Tournament $tournament)
    {
        // Once a tiebreak order exists it is final — further edits are rejected.
        if (TournamentTiebreak::where('tournament_id', $tournament->id)->exists()) {
            return redirect()->route('admin.tournaments.tiebreaks', $tournament)
                ->with('error', 'El orden de desempate ya fue guardado y no se puede modificar.');
        }

        $data = $request->validate([
            'order' => 'required|array',
            'order.*' => 'required|integer|min:1',
        ]);

        foreach ($data['order'] as $userId => $rank) {
            TournamentTiebreak::create([
                'tournament_id' => $tournament->id,
                'user_id' => (int) $userId,
                'manual_rank' => (int) $rank,
            ]);
        }

        return redirect()->route('admin.tournaments.tiebreaks', $tournament)
            ->with('success', 'Orden de desempate guardado. Esta decisión es final.');
    }
}
