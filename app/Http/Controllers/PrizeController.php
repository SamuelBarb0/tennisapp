<?php

namespace App\Http\Controllers;

use App\Models\Prize;
use App\Models\PrizeRedemption;
use Illuminate\Http\Request;

class PrizeController extends Controller
{
    public function index()
    {
        $prizes = Prize::where('is_active', true)->where('stock', '>', 0)->get();
        return view('prizes.index', compact('prizes'));
    }

    public function redeem(Request $request, Prize $prize)
    {
        $user = auth()->user();
        if ($user->points < $prize->points_required) {
            return back()->with('error', 'No tienes suficientes puntos.');
        }
        if ($prize->stock <= 0) {
            return back()->with('error', 'Este premio ya no está disponible.');
        }

        $user->decrement('points', $prize->points_required);
        $prize->decrement('stock');
        PrizeRedemption::create([
            'user_id' => $user->id,
            'prize_id' => $prize->id,
            'status' => 'pending',
        ]);

        return back()->with('success', 'Premio canjeado exitosamente. Pronto nos comunicaremos contigo.');
    }
}
