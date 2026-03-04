<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Player;
use Illuminate\Http\Request;

class PlayerController extends Controller
{
    public function index(Request $request)
    {
        $query = Player::query();
        if ($request->filled('category')) $query->where('category', $request->category);
        if ($request->filled('search')) $query->where('name', 'like', '%'.$request->search.'%');
        $players = $query->orderBy('ranking')->paginate(20);
        return view('admin.players.index', compact('players'));
    }

    public function create()
    {
        return view('admin.players.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'country' => 'required|string',
            'nationality_code' => 'required|string|max:3',
            'ranking' => 'nullable|integer|min:1',
            'category' => 'required|in:ATP,WTA',
            'bio' => 'nullable|string',
            'photo' => 'nullable|image|max:2048',
        ]);
        if ($request->hasFile('photo')) {
            $data['photo'] = $request->file('photo')->store('players', 'public');
        }
        Player::create($data);
        return redirect()->route('admin.players.index')->with('success', 'Jugador creado.');
    }

    public function edit(Player $player)
    {
        return view('admin.players.edit', compact('player'));
    }

    public function update(Request $request, Player $player)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'country' => 'required|string',
            'nationality_code' => 'required|string|max:3',
            'ranking' => 'nullable|integer|min:1',
            'category' => 'required|in:ATP,WTA',
            'bio' => 'nullable|string',
            'photo' => 'nullable|image|max:2048',
        ]);
        if ($request->hasFile('photo')) {
            $data['photo'] = $request->file('photo')->store('players', 'public');
        }
        $player->update($data);
        return redirect()->route('admin.players.index')->with('success', 'Jugador actualizado.');
    }

    public function destroy(Player $player)
    {
        $player->delete();
        return redirect()->route('admin.players.index')->with('success', 'Jugador eliminado.');
    }
}
