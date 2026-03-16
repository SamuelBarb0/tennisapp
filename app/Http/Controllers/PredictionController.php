<?php

namespace App\Http\Controllers;

use App\Models\Prediction;
use App\Models\TennisMatch;
use Illuminate\Http\Request;

class PredictionController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'match_id' => 'required|exists:matches,id',
            'predicted_winner_id' => 'required|exists:players,id',
        ]);

        $match = TennisMatch::findOrFail($request->match_id);
        if ($match->status !== 'pending') {
            return back()->with('error', 'Este partido ya no acepta pronósticos.');
        }

        Prediction::updateOrCreate(
            ['user_id' => auth()->id(), 'match_id' => $request->match_id],
            ['predicted_winner_id' => $request->predicted_winner_id]
        );

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'Pronóstico guardado exitosamente.']);
        }

        return back()->with('success', 'Pronóstico guardado exitosamente.');
    }
}
