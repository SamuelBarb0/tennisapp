<?php

namespace App\Http\Controllers;

use App\Models\BracketPrediction;
use App\Models\Tournament;
use App\Models\TennisMatch;
use App\Models\User;
use App\Services\Sync\MatchSync;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TournamentController extends Controller
{
    public function index(Request $request)
    {
        $query = Tournament::where('is_active', true)
            ->where('start_date', '>=', '2026-01-01')
            ->whereHas('matches');
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->filled('surface')) {
            $query->where('surface', $request->surface);
        }
        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $tournaments = $query->orderBy('start_date')->get();

        // Group by month for the calendar layout
        $tournamentsByMonth = $tournaments->groupBy(fn($t) => $t->start_date->format('Y-m'));

        return view('tournaments.index', compact('tournaments', 'tournamentsByMonth'));
    }

    public function show(Tournament $tournament, Request $request)
    {
        // Optional: view another user's saved bracket (via ?user=ID query param).
        // Falls back to the authenticated user when not provided.
        $viewingUserId = $request->integer('user') ?: auth()->id();
        $viewingUser = null;
        $viewingOtherUser = false;
        if ($viewingUserId) {
            $viewingUser = User::find($viewingUserId);
            $viewingOtherUser = auth()->check() && $viewingUserId !== auth()->id();
        }

        // Auto-sync if stale (>10 min since last sync) and tournament is active
        if ($tournament->is_active && $tournament->status !== 'finished'
            && (!$tournament->last_synced_at || $tournament->last_synced_at->diffInMinutes(now()) >= 10)
        ) {
            try {
                $sync = app(MatchSync::class);
                $sync->syncTournament($tournament);
                $tournament->update(['last_synced_at' => now()]);
            } catch (\Throwable $e) {
                Log::warning("Auto-sync failed for {$tournament->name}: {$e->getMessage()}");
            }
        }

        $tournament->load('roundPoints');

        $matches = TennisMatch::with(['player1', 'player2', 'winner'])
            ->where('tournament_id', $tournament->id)
            ->whereNotIn('status', ['cancelled'])
            ->whereNotNull('bracket_position')
            ->orderByRaw('bracket_position')
            ->get()
            ->groupBy('round');

        // Inject BYE slots in the first round where seeded players skip it
        $roundOrder = ['R128', 'R64', 'R32', 'R16', 'QF', 'SF', 'F'];
        $firstRound = collect($roundOrder)->first(fn($r) => isset($matches[$r]));
        $secondRound = collect($roundOrder)->first(fn($r) => isset($matches[$r]) && $r !== $firstRound);

        if ($firstRound && $secondRound && isset($matches[$firstRound]) && isset($matches[$secondRound])) {
            $firstRoundMatches = $matches[$firstRound]->sortBy('bracket_position')->values();
            $secondRoundMatches = $matches[$secondRound]->sortBy('bracket_position')->values();
            $expectedFirstCount = $secondRoundMatches->count() * 2;

            if ($firstRoundMatches->count() < $expectedFirstCount) {
                // Map of player_id → R1 match (both players of each R1 match)
                $r1ByPlayer = [];
                foreach ($firstRoundMatches as $m) {
                    if ($m->player1_id) $r1ByPlayer[$m->player1_id] = $m;
                    if ($m->player2_id) $r1ByPlayer[$m->player2_id] = $m;
                }

                // Build merged array: for each R2 match, find its 2 R1 feeders (real or BYE)
                // Each R2 match's feeders are the R1 match where player1 won, and R1 match where player2 won
                // If a player has no R1 match → BYE
                $merged = collect();
                $byeIdCounter = 1;
                $usedR1Ids = collect();

                foreach ($secondRoundMatches as $r2i => $r2match) {
                    // Top feeder: the R1 match that produced player1 of this R2 match
                    $topFeeder = $r2match->player1_id && isset($r1ByPlayer[$r2match->player1_id])
                        ? $r1ByPlayer[$r2match->player1_id]
                        : null;

                    // Bottom feeder: the R1 match that produced player2 of this R2 match
                    $botFeeder = $r2match->player2_id && isset($r1ByPlayer[$r2match->player2_id])
                        ? $r1ByPlayer[$r2match->player2_id]
                        : null;

                    // Top slot
                    if ($topFeeder) {
                        $merged->push($topFeeder);
                    } elseif ($r2match->player1_id) {
                        $bye = new TennisMatch();
                        $bye->id = -$byeIdCounter++;
                        $bye->tournament_id = $tournament->id;
                        $bye->round = $firstRound;
                        $bye->status = 'bye';
                        $bye->player1_id = $r2match->player1_id;
                        $bye->player2_id = null;
                        $bye->winner_id = $r2match->player1_id;
                        $bye->bracket_position = $r2i * 2;
                        $bye->setRelation('player1', $r2match->player1);
                        $bye->setRelation('player2', null);
                        $bye->setRelation('winner', $r2match->player1);
                        $merged->push($bye);
                    }

                    // Bottom slot
                    if ($botFeeder) {
                        $merged->push($botFeeder);
                    } elseif ($r2match->player2_id) {
                        $bye = new TennisMatch();
                        $bye->id = -$byeIdCounter++;
                        $bye->tournament_id = $tournament->id;
                        $bye->round = $firstRound;
                        $bye->status = 'bye';
                        $bye->player1_id = $r2match->player2_id;
                        $bye->player2_id = null;
                        $bye->winner_id = $r2match->player2_id;
                        $bye->bracket_position = $r2i * 2 + 1;
                        $bye->setRelation('player1', $r2match->player2);
                        $bye->setRelation('player2', null);
                        $bye->setRelation('winner', $r2match->player2);
                        $merged->push($bye);
                    }
                }

                $matches[$firstRound] = $merged;
            }
        }

        // Tournament ranking from bracket_predictions.
        // Manual tiebreak order (set by admin) is applied as a secondary sort:
        // users whose points tie are ordered by the admin's manual_rank (lower = better).
        $tournamentRanking = User::select(
                'users.id', 'users.name',
                DB::raw('SUM(bracket_predictions.points_earned) as tournament_points'),
                DB::raw('COUNT(bracket_predictions.id) as tournament_predictions'),
                DB::raw('SUM(CASE WHEN bracket_predictions.is_correct = 1 THEN 1 ELSE 0 END) as correct_predictions'),
                DB::raw('COALESCE(tournament_tiebreaks.manual_rank, 9999) as manual_rank')
            )
            ->join('bracket_predictions', 'users.id', '=', 'bracket_predictions.user_id')
            ->leftJoin('tournament_tiebreaks', function ($j) use ($tournament) {
                $j->on('tournament_tiebreaks.user_id', '=', 'users.id')
                  ->where('tournament_tiebreaks.tournament_id', '=', $tournament->id);
            })
            ->where('bracket_predictions.tournament_id', $tournament->id)
            ->groupBy('users.id', 'users.name', 'tournament_tiebreaks.manual_rank')
            ->having('tournament_points', '>', 0)
            ->orderByDesc('tournament_points')
            ->orderBy('manual_rank')
            ->orderBy('users.name')
            ->take(20)
            ->get();

        // Determine if predictions are locked
        $firstMatch = $tournament->matches()
            ->whereNotIn('status', ['cancelled'])
            ->orderBy('scheduled_at')
            ->first();
        $predictionsLocked = !$firstMatch || now()->gte($firstMatch->scheduled_at);
        $lockDate = $firstMatch?->scheduled_at;

        // Bracket predictions for the viewing user (self or another user's, if requested)
        $userBracketPicks = collect();
        $bracketSaved = false;
        $userFinalScore = null;
        if ($viewingUserId) {
            $userBracketPicks = BracketPrediction::where('tournament_id', $tournament->id)
                ->where('user_id', $viewingUserId)
                ->get()
                ->groupBy('round')
                ->map(fn($items) => $items->keyBy('position'));
            $bracketSaved = $userBracketPicks->isNotEmpty();

            // Final score prediction (stored on the F/position=1 row)
            $finalPrediction = BracketPrediction::where('tournament_id', $tournament->id)
                ->where('user_id', $viewingUserId)
                ->where('round', 'F')
                ->where('position', 1)
                ->first();
            $userFinalScore = $finalPrediction?->final_score_prediction;
        }

        // Points earned per round (for the viewing user)
        $userRoundPoints = [];
        $userTotalPoints = 0;
        if ($viewingUserId && $bracketSaved) {
            foreach ($userBracketPicks as $round => $positions) {
                $roundTotal = 0;
                foreach ($positions as $pred) {
                    $roundTotal += (int) ($pred->points_earned ?? 0);
                }
                $userRoundPoints[$round] = $roundTotal;
                $userTotalPoints += $roundTotal;
            }
        }

        // Detect if bracket is "fillable" — every non-bye first-round match has two real players
        $roundOrderCheck = ['R128', 'R64', 'R32', 'R16', 'QF', 'SF', 'F'];
        $firstRoundKey = collect($roundOrderCheck)->first(fn($r) => isset($matches[$r]));
        $bracketFillable = false;
        if ($firstRoundKey) {
            $placeholderRe = '/^(Qf|SF|WSF|WQF|F|Ganador|TBD)(\s?\d+)?$/i';
            $hasReal = 0;
            $totalSlots = 0;
            foreach ($matches[$firstRoundKey] as $m) {
                if ($m->status === 'bye') continue;
                $totalSlots++;
                $p1Real = $m->player1 && !preg_match($placeholderRe, $m->player1->name);
                $p2Real = $m->player2 && !preg_match($placeholderRe, $m->player2->name);
                if ($p1Real && $p2Real) $hasReal++;
            }
            $bracketFillable = $totalSlots > 0 && $hasReal === $totalSlots;
        }

        // Build bracket structure: for each round, list of matchups with bracket positions
        $roundOrder = ['R128', 'R64', 'R32', 'R16', 'QF', 'SF', 'F'];
        $bracketData = [];
        foreach ($roundOrder as $round) {
            if (!isset($matches[$round])) continue;
            $roundMatches = $matches[$round]->values();
            $bracketData[$round] = $roundMatches->map(function ($match, $index) {
                return [
                    'id' => $match->id,
                    'position' => $index + 1,
                    'status' => $match->status,
                    'player1' => $match->player1 ? [
                        'id' => $match->player1->id,
                        'name' => $match->player1->name,
                        'flag' => $match->player1->flag_url,
                        'ranking' => $match->player1->ranking,
                        'nationality' => $match->player1->nationality_code,
                    ] : null,
                    'player2' => $match->player2 ? [
                        'id' => $match->player2->id,
                        'name' => $match->player2->name,
                        'flag' => $match->player2->flag_url,
                        'ranking' => $match->player2->ranking,
                        'nationality' => $match->player2->nationality_code,
                    ] : null,
                    'winner_id' => $match->winner_id,
                    'score' => $match->score,
                    'scheduled_at' => $match->scheduled_at?->format('d/m H:i'),
                ];
            });
        }

        // User's picks as simple array for JS
        $userPicksJs = [];
        foreach ($userBracketPicks as $round => $positions) {
            foreach ($positions as $pos => $pred) {
                $userPicksJs[$round][$pos] = [
                    'player_id' => $pred->predicted_winner_id,
                    'is_correct' => $pred->is_correct,
                    'points_earned' => $pred->points_earned,
                ];
            }
        }

        // Paywall: only block when viewing OWN bracket and the tournament is paid.
        // Admins always pass through (so they can simulate/test).
        $hasPaid = $tournament->hasUserPaid(auth()->id());
        $needsPayment = !$viewingOtherUser
            && $tournament->requiresPayment()
            && !$hasPaid
            && !(auth()->check() && auth()->user()->is_admin);

        return view('tournaments.show', compact(
            'tournament', 'matches', 'tournamentRanking',
            'predictionsLocked', 'lockDate', 'bracketData', 'userPicksJs', 'bracketSaved',
            'bracketFillable', 'userFinalScore', 'userRoundPoints', 'userTotalPoints',
            'viewingUser', 'viewingOtherUser', 'needsPayment', 'hasPaid'
        ));
    }

    public function predict(Tournament $tournament)
    {
        $matches = TennisMatch::with(['player1', 'player2'])
            ->where('tournament_id', $tournament->id)
            ->where('status', 'pending')
            ->orderBy('scheduled_at')
            ->get();
        return view('tournaments.predict', compact('tournament', 'matches'));
    }
}
