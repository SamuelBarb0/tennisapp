<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::where('is_admin', false);
        if ($request->filled('search')) $query->where('name', 'like', '%'.$request->search.'%');
        $users = $query->latest()->paginate(20);
        return view('admin.users.index', compact('users'));
    }

    public function show(User $user)
    {
        $predictions = $user->predictions()->with(['match.player1', 'match.player2', 'predictedWinner'])->latest()->take(20)->get();
        $redemptions = $user->redemptions()->with('prize')->latest()->get();
        return view('admin.users.show', compact('user', 'predictions', 'redemptions'));
    }

    public function toggleBlock(User $user)
    {
        $user->update(['is_blocked' => !$user->is_blocked]);
        $status = $user->is_blocked ? 'bloqueado' : 'desbloqueado';
        return back()->with('success', "Usuario {$status}.");
    }
}
