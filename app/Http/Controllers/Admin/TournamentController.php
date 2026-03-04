<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tournament;
use Illuminate\Http\Request;

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
            'points_multiplier' => 'numeric|min:0.5|max:5',
            'image' => 'nullable|image|max:2048',
        ]);
        $data['is_premium'] = $request->boolean('is_premium');
        $data['is_active'] = $request->boolean('is_active');
        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('tournaments', 'public');
        }
        Tournament::create($data);
        return redirect()->route('admin.tournaments.index')->with('success', 'Torneo creado exitosamente.');
    }

    public function edit(Tournament $tournament)
    {
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
            'points_multiplier' => 'numeric|min:0.5|max:5',
            'image' => 'nullable|image|max:2048',
        ]);
        $data['is_premium'] = $request->boolean('is_premium');
        $data['is_active'] = $request->boolean('is_active');
        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('tournaments', 'public');
        }
        $tournament->update($data);
        return redirect()->route('admin.tournaments.index')->with('success', 'Torneo actualizado.');
    }

    public function destroy(Tournament $tournament)
    {
        $tournament->delete();
        return redirect()->route('admin.tournaments.index')->with('success', 'Torneo eliminado.');
    }
}
