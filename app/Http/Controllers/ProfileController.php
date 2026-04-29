<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\BracketPrediction;
use App\Models\Tournament;
use App\Models\TournamentPayment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile.
     */
    public function show()
    {
        $user = auth()->user();

        // Aggregate bracket prediction stats
        $bracketStats = BracketPrediction::where('user_id', $user->id)
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN is_correct = 1 THEN 1 ELSE 0 END) as correct,
                SUM(CASE WHEN is_correct IS NULL THEN 1 ELSE 0 END) as pending,
                SUM(points_earned) as total_points
            ')
            ->first();

        $totalPicks = (int) ($bracketStats->total ?? 0);
        $correctPicks = (int) ($bracketStats->correct ?? 0);
        $pendingPicks = (int) ($bracketStats->pending ?? 0);
        $resolvedPicks = $totalPicks - $pendingPicks; // exclude unsettled from accuracy
        $accuracy = $resolvedPicks > 0 ? round(($correctPicks / $resolvedPicks) * 100) : 0;
        $totalPointsEarned = (int) ($bracketStats->total_points ?? 0);

        // User's brackets — one card per tournament where the user has predictions
        $myBrackets = Tournament::query()
            ->whereHas('matches')
            ->whereIn('id', BracketPrediction::where('user_id', $user->id)->select('tournament_id'))
            ->withCount(['matches as total_matches'])
            ->orderByDesc('start_date')
            ->get()
            ->map(function ($t) use ($user) {
                $picks = BracketPrediction::where('tournament_id', $t->id)
                    ->where('user_id', $user->id)
                    ->selectRaw('
                        COUNT(*) as total,
                        SUM(CASE WHEN is_correct = 1 THEN 1 ELSE 0 END) as correct,
                        SUM(points_earned) as points
                    ')
                    ->first();

                // Compute the user's rank inside this tournament
                $rank = DB::table('bracket_predictions')
                    ->select('user_id', DB::raw('SUM(points_earned) as total'))
                    ->where('tournament_id', $t->id)
                    ->groupBy('user_id')
                    ->having('total', '>', (int) $picks->points)
                    ->count() + 1;

                $totalPlayers = DB::table('bracket_predictions')
                    ->where('tournament_id', $t->id)
                    ->distinct('user_id')
                    ->count('user_id');

                $t->user_picks_total = (int) $picks->total;
                $t->user_picks_correct = (int) $picks->correct;
                $t->user_points_earned = (int) $picks->points;
                $t->user_rank = $rank;
                $t->user_total_players = $totalPlayers;
                return $t;
            });

        // Payment history (only premium tournaments — frees never have rows)
        $myPayments = TournamentPayment::with('tournament')
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->take(10)
            ->get();

        $redemptions = $user->redemptions()->with('prize')->latest()->get();

        return view('profile.show', compact(
            'user',
            'totalPicks', 'correctPicks', 'pendingPicks', 'accuracy', 'totalPointsEarned',
            'myBrackets', 'myPayments', 'redemptions'
        ));
    }

    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
