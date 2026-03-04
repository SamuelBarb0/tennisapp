<?php

namespace App\Http\Controllers;

use App\Models\User;

class RankingController extends Controller
{
    public function index()
    {
        $users = User::where('is_admin', false)
            ->where('points', '>', 0)
            ->orderBy('points', 'desc')
            ->paginate(20);
        return view('rankings.index', compact('users'));
    }
}
