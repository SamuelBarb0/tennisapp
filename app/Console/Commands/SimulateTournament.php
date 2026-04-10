<?php

namespace App\Console\Commands;

use App\Models\BracketPrediction;
use App\Models\Player;
use App\Models\TennisMatch;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Console\Command;

class SimulateTournament extends Command
{
    protected $signature = 'tennis:simulate
                            {tournament? : Tournament ID or slug}
                            {--round= : Specific round to simulate (R128, R64, R32, R16, QF, SF, F)}
                            {--upset=20 : Upset probability % (higher = more upsets)}
                            {--all : Simulate all remaining rounds at once}
                            {--reset : Reset tournament back to all-pending}';

    protected $description = 'Simulate tournament results round by round for testing';

    private array $roundOrder = ['R128', 'R64', 'R32', 'R16', 'QF', 'SF', 'F'];

    public function handle(): int
    {
        $tournament = $this->resolveTournament();
        if (!$tournament) return 1;

        $this->info("Tournament: {$tournament->name} (ID: {$tournament->id})");

        if ($this->option('reset')) {
            return $this->resetTournament($tournament);
        }

        $upsetChance = (int) $this->option('upset');
        $simulateAll = $this->option('all');
        $specificRound = $this->option('round');

        if ($specificRound) {
            $this->simulateRound($tournament, strtoupper($specificRound), $upsetChance);
        } elseif ($simulateAll) {
            foreach ($this->roundOrder as $round) {
                if (!$this->simulateRound($tournament, $round, $upsetChance)) break;
            }
        } else {
            // Find next round to simulate
            $nextRound = $this->findNextRound($tournament);
            if (!$nextRound) {
                $this->warn('Tournament is already complete!');
                return 0;
            }
            $this->simulateRound($tournament, $nextRound, $upsetChance);
        }

        return 0;
    }

    private function resolveTournament(): ?Tournament
    {
        $input = $this->argument('tournament');

        if (!$input) {
            $tests = Tournament::where('slug', 'like', '%test%')->get();
            if ($tests->count() === 1) return $tests->first();
            if ($tests->count() > 1) {
                $this->table(['ID', 'Name', 'Status'], $tests->map(fn($t) => [$t->id, $t->name, $t->status]));
                $input = $this->ask('Enter tournament ID');
            } else {
                $this->error('No test tournament found. Pass an ID or slug.');
                return null;
            }
        }

        return Tournament::where('id', $input)->orWhere('slug', $input)->first()
            ?? tap(null, fn() => $this->error("Tournament '{$input}' not found."));
    }

    private function findNextRound(Tournament $tournament): ?string
    {
        foreach ($this->roundOrder as $round) {
            $pending = TennisMatch::where('tournament_id', $tournament->id)
                ->where('round', $round)
                ->where('status', 'pending')
                ->count();
            if ($pending > 0) return $round;
        }
        return null;
    }

    private function simulateRound(Tournament $tournament, string $round, int $upsetChance): bool
    {
        $matches = TennisMatch::where('tournament_id', $tournament->id)
            ->where('round', $round)
            ->where('status', 'pending')
            ->with(['player1', 'player2'])
            ->orderBy('bracket_position')
            ->get();

        if ($matches->isEmpty()) {
            $this->warn("No pending matches in {$round}.");
            return false;
        }

        // Check if players are real (not TBD placeholders)
        $firstMatch = $matches->first();
        $isPlaceholder = str_starts_with($firstMatch->player1->name, 'TBD');
        if ($isPlaceholder) {
            // Need to populate from previous round winners
            $this->populateFromPreviousRound($tournament, $round);
            // Reload matches
            $matches = TennisMatch::where('tournament_id', $tournament->id)
                ->where('round', $round)
                ->where('status', 'pending')
                ->with(['player1', 'player2'])
                ->orderBy('bracket_position')
                ->get();
        }

        $this->info("Simulating {$round}: {$matches->count()} matches");
        $scored = 0;

        foreach ($matches as $match) {
            // Determine winner: higher seed (lower ranking number) usually wins
            $p1Rank = $match->player1->ranking ?? 999;
            $p2Rank = $match->player2->ranking ?? 999;

            // Upset?
            $isUpset = rand(1, 100) <= $upsetChance;
            $favoriteIsP1 = $p1Rank <= $p2Rank;

            if ($isUpset) {
                $winnerId = $favoriteIsP1 ? $match->player2_id : $match->player1_id;
            } else {
                $winnerId = $favoriteIsP1 ? $match->player1_id : $match->player2_id;
            }

            // Generate realistic score
            $score = $this->generateScore($isUpset);

            $match->update([
                'status' => 'finished',
                'winner_id' => $winnerId,
                'score' => $score,
            ]);

            $winner = $winnerId === $match->player1_id ? $match->player1 : $match->player2;
            $loser = $winnerId === $match->player1_id ? $match->player2 : $match->player1;

            $marker = $isUpset ? ' 🔥 UPSET!' : '';
            $this->line("  [{$winner->ranking}] {$winner->name} def. [{$loser->ranking}] {$loser->name} {$score}{$marker}");

            // Score bracket predictions
            $scored += $this->scoreBracketPredictions($tournament, $match, $round);
        }

        $this->info("✓ {$round} complete. {$scored} predictions scored.");
        $this->newLine();

        // Update tournament status
        $this->updateTournamentStatus($tournament);

        return true;
    }

    private function populateFromPreviousRound(Tournament $tournament, string $round): void
    {
        $prevRoundIndex = array_search($round, $this->roundOrder);
        if ($prevRoundIndex === false || $prevRoundIndex === 0) return;

        $prevRound = $this->roundOrder[$prevRoundIndex - 1];

        $prevWinners = TennisMatch::where('tournament_id', $tournament->id)
            ->where('round', $prevRound)
            ->where('status', 'finished')
            ->whereNotNull('winner_id')
            ->orderBy('bracket_position')
            ->pluck('winner_id')
            ->values();

        $currentMatches = TennisMatch::where('tournament_id', $tournament->id)
            ->where('round', $round)
            ->orderBy('bracket_position')
            ->get();

        foreach ($currentMatches as $i => $match) {
            $p1Index = $i * 2;
            $p2Index = $i * 2 + 1;

            $p1Id = $prevWinners[$p1Index] ?? null;
            $p2Id = $prevWinners[$p2Index] ?? null;

            if ($p1Id && $p2Id) {
                $match->update([
                    'player1_id' => $p1Id,
                    'player2_id' => $p2Id,
                ]);
            }
        }

        $this->info("  Populated {$round} from {$prevRound} winners");
    }

    private function scoreBracketPredictions(Tournament $tournament, TennisMatch $match, string $round): int
    {
        $roundMatches = TennisMatch::where('tournament_id', $tournament->id)
            ->where('round', $round)
            ->orderBy('bracket_position')
            ->pluck('id')
            ->values();
        $position = $roundMatches->search($match->id) + 1;

        if ($position <= 0) return 0;

        $points = $tournament->getPointsForRound($round);
        $scored = 0;

        $predictions = BracketPrediction::where('tournament_id', $tournament->id)
            ->where('round', $round)
            ->where('position', $position)
            ->whereNull('is_correct')
            ->get();

        foreach ($predictions as $pred) {
            $isCorrect = $pred->predicted_winner_id == $match->winner_id;
            $earned = $isCorrect ? $points : 0;

            $pred->update([
                'is_correct' => $isCorrect,
                'points_earned' => $earned,
            ]);

            if ($isCorrect) {
                User::where('id', $pred->user_id)->increment('points', $earned);
            }
            $scored++;
        }

        return $scored;
    }

    private function generateScore(bool $isUpset): string
    {
        $formats = $isUpset
            ? ['6-4 3-6 7-5', '7-6 4-6 6-3', '6-7 6-4 7-6', '3-6 6-4 6-4', '6-3 4-6 7-6']
            : ['6-3 6-4', '6-4 6-2', '7-5 6-3', '6-2 6-4', '6-4 7-5', '6-1 6-4', '7-6 6-4'];

        return $formats[array_rand($formats)];
    }

    private function resetTournament(Tournament $tournament): int
    {
        if (!$this->confirm('This will reset ALL matches to pending. Continue?')) return 0;

        // Reset all matches
        TennisMatch::where('tournament_id', $tournament->id)->update([
            'status' => 'pending',
            'winner_id' => null,
            'score' => null,
        ]);

        // Reset bracket predictions
        BracketPrediction::where('tournament_id', $tournament->id)->update([
            'is_correct' => null,
            'points_earned' => 0,
        ]);

        $tournament->update(['status' => 'upcoming']);

        $this->info('Tournament reset to upcoming. All matches pending.');
        return 0;
    }

    private function updateTournamentStatus(Tournament $tournament): void
    {
        $total = $tournament->matches()->count();
        $finished = $tournament->matches()->where('status', 'finished')->count();

        if ($total > 0 && $finished === $total) {
            $tournament->update(['status' => 'finished']);
        } elseif ($finished > 0) {
            $tournament->update(['status' => 'in_progress']);
        }
    }
}
