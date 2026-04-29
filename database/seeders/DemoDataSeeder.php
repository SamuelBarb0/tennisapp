<?php

namespace Database\Seeders;

use App\Http\Controllers\Admin\SimulationController;
use App\Models\BracketPrediction;
use App\Models\Player;
use App\Models\TennisMatch;
use App\Models\Tournament;
use App\Models\TournamentPayment;
use App\Models\TournamentRoundPoints;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Populates several test tournaments with realistic predictions from multiple users
 * so the admin dashboard, rankings, profile and "view other user's bracket" views
 * have real data to render.
 *
 * Run with:  php artisan db:seed --class=DemoDataSeeder
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🎾 Demo data seeder starting...');

        // 1. Make sure we have payments for the premium tournament
        $premiumSlugs = ['roma-premium-test-2026'];
        $this->seedPayments($premiumSlugs);

        // 2. Pick the test tournaments to populate with predictions
        $targets = [
            'stuttgart-open-test-2026'   => ['simulate' => 'all',     'upset' => 25],
            'barcelona-open-test-2026'   => ['simulate' => 'all',     'upset' => 20],
            'australian-test-open-2026'  => ['simulate' => 'partial', 'upset' => 30, 'rounds' => 3],
            'roma-premium-test-2026'     => ['simulate' => 'partial', 'upset' => 20, 'rounds' => 2],
        ];

        foreach ($targets as $slug => $cfg) {
            $tournament = Tournament::where('slug', $slug)->first();
            if (!$tournament) {
                $this->command->warn("  · Tournament not found: {$slug} (skip)");
                continue;
            }
            $this->populateTournament($tournament, $cfg);
        }

        $this->command->newLine();
        $this->command->info('✅ Demo data ready. Try:');
        $this->command->line('   - /admin (dashboard with revenue & predictions)');
        $this->command->line('   - /rankings (top 10 + click any user to see their bracket)');
        $this->command->line('   - /profile (your own brackets)');
    }

    private function seedPayments(array $premiumSlugs): void
    {
        foreach ($premiumSlugs as $slug) {
            $t = Tournament::where('slug', $slug)->first();
            if (!$t || !$t->is_premium || !$t->price) continue;

            $users = User::where('is_admin', false)->take(6)->get();
            foreach ($users as $i => $u) {
                $status = match (true) {
                    $i < 4 => 'approved', // 4 approved
                    $i === 4 => 'pending',
                    default => 'rejected',
                };
                TournamentPayment::updateOrCreate(
                    ['user_id' => $u->id, 'tournament_id' => $t->id, 'status' => $status],
                    [
                        'amount'       => $t->price,
                        'currency'     => 'COP',
                        'mp_payment_id'=> 'demo-' . $u->id . '-' . $t->id,
                        'preference_id'=> 'demo-pref-' . $u->id . '-' . $t->id,
                        'paid_at'      => $status === 'approved' ? now()->subDays(rand(1, 14)) : null,
                    ]
                );
            }
            $this->command->line("  · Seeded payments for: {$t->name}");
        }
    }

    private function populateTournament(Tournament $tournament, array $cfg): void
    {
        $this->command->info("→ Populating {$tournament->name}");

        // Make sure round points are configured (needed for scoring)
        $defaultPoints = [
            'R128' => 5, 'R64' => 10, 'R32' => 10, 'R16' => 25,
            'QF' => 50, 'SF' => 100, 'F' => 200,
        ];
        foreach ($defaultPoints as $round => $pts) {
            // Only insert if the round exists in this tournament AND has no row yet
            $hasRound = TennisMatch::where('tournament_id', $tournament->id)->where('round', $round)->exists();
            if (!$hasRound) continue;
            TournamentRoundPoints::firstOrCreate(
                ['tournament_id' => $tournament->id, 'round' => $round],
                ['points' => $pts]
            );
        }

        // Reset prior demo state
        BracketPrediction::where('tournament_id', $tournament->id)->delete();
        TennisMatch::where('tournament_id', $tournament->id)
            ->whereNot('status', 'bye')
            ->update(['status' => 'pending', 'winner_id' => null, 'score' => null]);

        // Restore TBD slots for non-first rounds (they may have been filled before)
        $roundOrder = ['R128', 'R64', 'R32', 'R16', 'QF', 'SF', 'F'];
        $firstRound = collect($roundOrder)->first(fn($r) =>
            TennisMatch::where('tournament_id', $tournament->id)->where('round', $r)->exists()
        );
        $tbd = Player::where('name', 'TBD')->first();
        if ($tbd && $firstRound) {
            foreach ($roundOrder as $r) {
                if ($r === $firstRound) continue;
                TennisMatch::where('tournament_id', $tournament->id)
                    ->where('round', $r)
                    ->update(['player1_id' => $tbd->id, 'player2_id' => $tbd->id]);
            }
        }

        // Pick demo users (mix of all non-admins so the ranking has variety)
        $users = User::where('is_admin', false)->get();
        if ($users->isEmpty()) {
            $this->command->warn('  · No demo users available, skipping');
            return;
        }

        // First-round matches with real players
        $firstRoundMatches = TennisMatch::where('tournament_id', $tournament->id)
            ->where('round', $firstRound)
            ->whereNot('status', 'bye')
            ->orderBy('bracket_position')
            ->get();

        if ($firstRoundMatches->isEmpty()) {
            $this->command->warn('  · No first-round matches found, skipping');
            return;
        }

        // Build a "favorites" reference bracket — what would happen if every favorite wins
        $favoritesBracket = $this->buildFavoritesBracket($tournament, $firstRoundMatches, $roundOrder, $firstRound);

        // For each user, generate picks with some randomness around the favorites
        // to create natural variation in the ranking.
        foreach ($users as $idx => $user) {
            // Different "skill" per user so the ranking shows spread
            $skill = match (true) {
                $idx < 2  => 0.85, // top users — usually pick favorites (15% chance to deviate)
                $idx < 5  => 0.70,
                $idx < 8  => 0.55,
                default   => 0.40, // bottom — more random
            };

            $userBracket = $this->generateUserPicks($favoritesBracket, $firstRoundMatches, $skill, $tournament);

            foreach ($userBracket as $round => $positions) {
                foreach ($positions as $position => $playerId) {
                    $attrs = ['predicted_winner_id' => $playerId];
                    // Random final score predictions for tiebreaker variety
                    if ($round === 'F' && $position === 1) {
                        $finals = ['6-3 6-4', '7-5 6-3', '6-4 6-2', '6-2 7-5', '7-6 6-4', '6-3 4-6 6-3'];
                        $attrs['final_score_prediction'] = $finals[array_rand($finals)];
                    }
                    BracketPrediction::create([
                        'tournament_id' => $tournament->id,
                        'user_id'       => $user->id,
                        'round'         => $round,
                        'position'      => $position,
                    ] + $attrs);
                }
            }
        }
        $this->command->line("  · Created predictions for {$users->count()} users");

        // Now simulate the tournament so points get awarded
        $sim = new SimulationController();
        $reflection = new \ReflectionClass($sim);
        $simulate = $reflection->getMethod('simulateRound');
        $simulate->setAccessible(true);

        if ($cfg['simulate'] === 'all') {
            foreach ($roundOrder as $r) {
                if (TennisMatch::where('tournament_id', $tournament->id)
                    ->where('round', $r)->where('status', 'pending')->exists()) {
                    $simulate->invoke($sim, $tournament, $r, $cfg['upset'] ?? 20);
                }
            }
            $tournament->update(['status' => 'finished']);
            $this->command->line("  · Simulated all rounds, status=finished");
        } else {
            // Partial simulation — only the first N rounds
            $count = $cfg['rounds'] ?? 1;
            $done = 0;
            foreach ($roundOrder as $r) {
                if ($done >= $count) break;
                if (TennisMatch::where('tournament_id', $tournament->id)
                    ->where('round', $r)->where('status', 'pending')->exists()) {
                    $simulate->invoke($sim, $tournament, $r, $cfg['upset'] ?? 20);
                    $done++;
                }
            }
            $tournament->update(['status' => 'in_progress']);
            $this->command->line("  · Simulated first {$done} round(s), status=in_progress");
        }
    }

    /**
     * Build a "favorites" reference bracket: at every match, the player with the
     * better (lower) ranking is the predicted winner. Used as a base from which we
     * randomly deviate to give each user a distinct bracket.
     */
    private function buildFavoritesBracket(Tournament $tournament, $firstRoundMatches, array $roundOrder, string $firstRound): array
    {
        $bracket = [];
        // First round: pick the higher-seeded
        foreach ($firstRoundMatches as $i => $m) {
            $p1Rank = $m->player1->ranking ?? 999;
            $p2Rank = $m->player2->ranking ?? 999;
            $bracket[$firstRound][$i + 1] = ($p1Rank <= $p2Rank) ? $m->player1_id : $m->player2_id;
        }

        // Subsequent rounds: take winners from previous round in pairs
        $startIdx = array_search($firstRound, $roundOrder);
        for ($r = $startIdx + 1; $r < count($roundOrder); $r++) {
            $prev = $roundOrder[$r - 1];
            $curr = $roundOrder[$r];
            if (!TennisMatch::where('tournament_id', $tournament->id)->where('round', $curr)->exists()) break;

            $prevCount = count($bracket[$prev] ?? []);
            for ($i = 0; $i < $prevCount / 2; $i++) {
                $left  = $bracket[$prev][$i * 2 + 1] ?? null;
                $right = $bracket[$prev][$i * 2 + 2] ?? null;
                if (!$left || !$right) continue;
                $leftRank  = Player::find($left)?->ranking ?? 999;
                $rightRank = Player::find($right)?->ranking ?? 999;
                $bracket[$curr][$i + 1] = ($leftRank <= $rightRank) ? $left : $right;
            }
        }
        return $bracket;
    }

    /**
     * Generate one user's picks: at each round we keep the favorite with prob=$skill,
     * otherwise we deviate (pick the underdog or "the other" already-advanced player).
     */
    private function generateUserPicks(array $favorites, $firstRoundMatches, float $skill, Tournament $tournament): array
    {
        $picks = [];
        $roundOrder = ['R128', 'R64', 'R32', 'R16', 'QF', 'SF', 'F'];

        // Round 1: favorite vs upset
        $firstRound = $firstRoundMatches->first()->round;
        foreach ($firstRoundMatches as $i => $m) {
            $favorite = $favorites[$firstRound][$i + 1];
            $other = $favorite === $m->player1_id ? $m->player2_id : $m->player1_id;
            $picks[$firstRound][$i + 1] = (mt_rand(1, 100) <= $skill * 100) ? $favorite : $other;
        }

        // Subsequent rounds: pick one of the two predicted winners from the prev round
        $startIdx = array_search($firstRound, $roundOrder);
        for ($r = $startIdx + 1; $r < count($roundOrder); $r++) {
            $prev = $roundOrder[$r - 1];
            $curr = $roundOrder[$r];
            if (!isset($favorites[$curr])) break;

            $prevCount = count($picks[$prev] ?? []);
            for ($i = 0; $i < $prevCount / 2; $i++) {
                $left  = $picks[$prev][$i * 2 + 1] ?? null;
                $right = $picks[$prev][$i * 2 + 2] ?? null;
                if (!$left || !$right) continue;

                // Skill-weighted: pick the better-ranked of the two with prob=$skill
                $leftRank  = Player::find($left)?->ranking ?? 999;
                $rightRank = Player::find($right)?->ranking ?? 999;
                $favorite  = ($leftRank <= $rightRank) ? $left : $right;
                $other     = ($favorite === $left) ? $right : $left;
                $picks[$curr][$i + 1] = (mt_rand(1, 100) <= $skill * 100) ? $favorite : $other;
            }
        }

        return $picks;
    }
}
