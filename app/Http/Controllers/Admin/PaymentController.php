<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tournament;
use App\Models\TournamentPayment;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $query = TournamentPayment::with(['user', 'tournament'])->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('tournament_id')) {
            $query->where('tournament_id', $request->tournament_id);
        }
        if ($request->filled('search')) {
            $term = $request->search;
            $query->whereHas('user', fn($q) => $q->where('name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%"));
        }

        $payments = $query->paginate(25)->withQueryString();

        // Aggregates for the header strip
        $aggregates = [
            'approved_count' => TournamentPayment::where('status', 'approved')->count(),
            'approved_total' => TournamentPayment::where('status', 'approved')->sum('amount'),
            'pending_count'  => TournamentPayment::where('status', 'pending')->count(),
            'pending_total'  => TournamentPayment::where('status', 'pending')->sum('amount'),
            'rejected_count' => TournamentPayment::where('status', 'rejected')->count(),
        ];

        // For tournament filter dropdown — only premium ones
        $tournaments = Tournament::where('is_premium', true)->orderBy('name')->get(['id', 'name']);

        return view('admin.payments.index', compact('payments', 'aggregates', 'tournaments'));
    }
}
