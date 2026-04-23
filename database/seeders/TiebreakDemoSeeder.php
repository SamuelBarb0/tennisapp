<?php

namespace Database\Seeders;

use App\Http\Controllers\Admin\SimulationController;
use App\Models\BracketPrediction;
use App\Models\TennisMatch;
use App\Models\Tournament;
use App\Models\TournamentRoundPoints;
use App\Models\TournamentTiebreak;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Demo seeder that creates a ranked tie between several users in a test tournament,
 * so the admin's "Desempates" panel has something real to work with.
 *
 * Strategy: give every participating user the same bracket picks → they earn identical
 * points as matches are simulated. Only the final-score prediction differs between them.
 */
class TiebreakDemoSeeder extends Seeder
{
    public function run(): void
    {
        $tournament = Tournament::where('slug', 'stuttgart-open-test-2026')->first();
        if (!$tournament) {
            $this->command->error('Stuttgart test tournament not found.');
            return;
        }

        // Configure per-round points if missing (needed for the scorer)
        $defaultPoints = ['R32' => 10, 'R16' => 20, 'QF' => 40, 'SF' => 70, 'F' => 100];
        foreach ($defaultPoints as $round => $pts) {
            TournamentRoundPoints::updateOrCreate(
                ['tournament_id' => $tournament->id, 'round' => $round],
                ['points' => $pts]
            );
        }

        // Reset: clear prior predictions, tiebreaks, and match results for a clean demo
        BracketPrediction::where('tournament_id', $tournament->id)->delete();
        TournamentTiebreak::where('tournament_id', $tournament->id)->delete();
        TennisMatch::where('tournament_id', $tournament->id)
            ->update(['status' => 'pending', 'winner_id' => null, 'score' => null]);

        // Restore original first-round players if later rounds were populated
        $firstRoundKey = 'R32';
        TennisMatch::where('tournament_id', $tournament->id)
            ->whereNotIn('round', [$firstRoundKey])
            ->get()
            ->each(function ($m) {
                // Only clear TBD slots in later rounds
                $p1 = \App\Models\Player::find($m->player1_id);
                $p2 = \App\Models\Player::find($m->player2_id);
                $tbd = \App\Models\Player::where('name', 'TBD')->first();
                if ($tbd) {
                    $m->update(['player1_id' => $tbd->id, 'player2_id' => $tbd->id]);
                }
            });

        // Users who will tie (must be 2+ to appear in the tiebreak panel)
        $tyingUsers = User::whereIn('email', [
            'carlos@example.com',
            'maria@example.com',
            'andres@example.com',
        ])->get();

        if ($tyingUsers->count() < 2) {
            $this->command->error('Not enough demo users found.');
            return;
        }

        // Build a single shared bracket — winner of match 1, winner of match 2, etc.
        // Every user gets the SAME picks, so they earn the same points round by round.
        $firstRoundMatches = TennisMatch::where('tournament_id', $tournament->id)
            ->where('round', $firstRoundKey)
            ->orderBy('bracket_position')
            ->get();

        // Shared picks: pick the higher-seeded player (lower ranking number)
        $sharedPicks = [];
        foreach ($firstRoundMatches as $i => $match) {
            $p1Rank = $match->player1?->ranking ?? 999;
            $p2Rank = $match->player2?->ranking ?? 999;
            $winnerId = ($p1Rank <= $p2Rank) ? $match->player1_id : $match->player2_id;
            $sharedPicks[$firstRoundKey][$i + 1] = $winnerId;
        }

        // Rounds R16..F: for each pair of picks in the previous round, propagate the higher seed
        $roundOrder = ['R32', 'R16', 'QF', 'SF', 'F'];
        for ($r = 1; $r < count($roundOrder); $r++) {
            $prev = $roundOrder[$r - 1];
            $curr = $roundOrder[$r];
            $prevCount = count($sharedPicks[$prev] ?? []);
            for ($i = 0; $i < $prevCount / 2; $i++) {
                $leftId  = $sharedPicks[$prev][$i * 2 + 1] ?? null;
                $rightId = $sharedPicks[$prev][$i * 2 + 2] ?? null;
                if (!$leftId || !$rightId) continue;
                $leftRank  = \App\Models\Player::find($leftId)?->ranking ?? 999;
                $rightRank = \App\Models\Player::find($rightId)?->ranking ?? 999;
                $winnerId = ($leftRank <= $rightRank) ? $leftId : $rightId;
                $sharedPicks[$curr][$i + 1] = $winnerId;
            }
        }

        // Different final-score predictions per user so the admin has data to judge by
        $finalScorePerUser = [
            $tyingUsers[0]->id => '6-3 6-4',
            $tyingUsers[1]->id => '7-5 6-7 6-4',
            $tyingUsers[2]->id ?? null => '6-2 6-2',
        ];

        // Insert predictions for each tying user
        foreach ($tyingUsers as $user) {
            foreach ($sharedPicks as $round => $positions) {
                foreach ($positions as $pos => $playerId) {
                    $attrs = ['predicted_winner_id' => $playerId];
                    if ($round === 'F' && $pos === 1 && isset($finalScorePerUser[$user->id])) {
                        $attrs['final_score_prediction'] = $finalScorePerUser[$user->id];
                    }
                    BracketPrediction::updateOrCreate(
                        [
                            'tournament_id' => $tournament->id,
                            'user_id' => $user->id,
                            'round' => $round,
                            'position' => $pos,
                        ],
                        $attrs
                    );
                }
            }
        }

        // Simulate every round → results mirror the shared picks so all users score identically
        $sim = new SimulationController();
        $reflection = new \ReflectionClass($sim);
        $simulate = $reflection->getMethod('simulateRound');
        $simulate->setAccessible(true);

        foreach ($roundOrder as $round) {
            $simulate->invoke($sim, $tournament, $round, 0); // upsetChance=0 → favorites win
        }

        $tournament->update(['status' => 'finished']);

        $this->command->info("✓ Tiebreak demo ready: {$tyingUsers->count()} users tied in '{$tournament->name}'.");
        foreach ($tyingUsers as $u) {
            $total = BracketPrediction::where('tournament_id', $tournament->id)
                ->where('user_id', $u->id)
                ->sum('points_earned');
            $this->command->line("  - {$u->name}: {$total} pts");
        }
    }
}
