<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BracketPrediction;
use App\Models\TennisMatch;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Http\Request;

class SimulationController extends Controller
{
    private array $roundOrder = ['R128', 'R64', 'R32', 'R16', 'QF', 'SF', 'F'];

    public function simulateNextRound(Request $request)
    {
        $tournament = Tournament::findOrFail($request->tournament_id);
        $upsetChance = (int) $request->input('upset', 20);

        $nextRound = $this->findNextRound($tournament);
        if (!$nextRound) {
            return back()->with('error', "El torneo \"{$tournament->name}\" ya está completo.");
        }

        $result = $this->simulateRound($tournament, $nextRound, $upsetChance);
        $this->updateTournamentStatus($tournament);

        return back()->with('success', "✓ {$tournament->name} — Ronda {$nextRound} simulada: {$result['matches']} partidos, {$result['scored']} predicciones puntuadas.");
    }

    public function simulateAll(Request $request)
    {
        $tournament = Tournament::findOrFail($request->tournament_id);
        $upsetChance = (int) $request->input('upset', 20);

        $totalMatches = 0;
        $totalScored  = 0;
        $roundsDone   = [];

        foreach ($this->roundOrder as $round) {
            $result = $this->simulateRound($tournament, $round, $upsetChance);
            if ($result['matches'] === 0) continue;
            $totalMatches += $result['matches'];
            $totalScored  += $result['scored'];
            $roundsDone[]  = $round;
        }

        $this->updateTournamentStatus($tournament);

        if (empty($roundsDone)) {
            return back()->with('error', "El torneo \"{$tournament->name}\" ya está completo.");
        }

        return back()->with('success', "✓ {$tournament->name} — Simulado completo: " . implode(' → ', $roundsDone) . ". {$totalMatches} partidos, {$totalScored} predicciones puntuadas.");
    }

    public function reset(Request $request)
    {
        $tournament = Tournament::findOrFail($request->tournament_id);
        $tbd = \App\Models\Player::where('name', 'TBD')->value('id') ?? 1057;

        // Reconstruir el cuadro completo desde cero usando los top 32 originales
        $category = str_starts_with($tournament->type, 'WTA') ? 'WTA' : 'ATP';

        $players = \App\Models\Player::where('category', $category)
            ->where('name', '!=', 'TBD')
            ->whereNotNull('ranking')
            ->orderBy('ranking')
            ->take(32)
            ->pluck('id')
            ->toArray();

        // Borrar todos los partidos y recrearlos limpios
        TennisMatch::where('tournament_id', $tournament->id)->delete();

        // R32: jugadores reales (1 vs 32, 2 vs 31, ...)
        for ($i = 0; $i < 16; $i++) {
            TennisMatch::create([
                'tournament_id'    => $tournament->id,
                'player1_id'       => $players[$i],
                'player2_id'       => $players[31 - $i],
                'round'            => 'R32',
                'bracket_position' => $i + 1,
                'scheduled_at'     => $tournament->start_date,
                'status'           => 'pending',
            ]);
        }

        // R16, QF, SF, F: TBD
        $tbdRounds = ['R16' => 8, 'QF' => 4, 'SF' => 2, 'F' => 1];
        $dayOffset = 2;
        foreach ($tbdRounds as $round => $count) {
            for ($i = 0; $i < $count; $i++) {
                TennisMatch::create([
                    'tournament_id'    => $tournament->id,
                    'player1_id'       => $tbd,
                    'player2_id'       => $tbd,
                    'round'            => $round,
                    'bracket_position' => $i + 1,
                    'scheduled_at'     => $tournament->start_date->copy()->addDays($dayOffset),
                    'status'           => 'pending',
                ]);
            }
            $dayOffset++;
        }

        // Revertir puntos ganados y borrar todas las predicciones
        BracketPrediction::where('tournament_id', $tournament->id)
            ->where('points_earned', '>', 0)
            ->get()
            ->groupBy('user_id')
            ->each(function ($preds, $userId) {
                $total = $preds->sum('points_earned');
                User::where('id', $userId)->decrement('points', $total);
            });

        BracketPrediction::where('tournament_id', $tournament->id)->delete();

        $tournament->update(['status' => 'upcoming']);

        return back()->with('success', "↺ {$tournament->name} — Torneo reiniciado completamente (31 partidos recreados).");
    }

    // ── Private helpers ────────────────────────────────────────────

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

    private function simulateRound(Tournament $tournament, string $round, int $upsetChance): array
    {
        $matches = TennisMatch::where('tournament_id', $tournament->id)
            ->where('round', $round)
            ->where('status', 'pending')
            ->with(['player1', 'player2'])
            ->orderBy('bracket_position')
            ->get();

        if ($matches->isEmpty()) return ['matches' => 0, 'scored' => 0];

        // Populate TBD slots from previous round winners
        if ($matches->first()->player1->name === 'TBD') {
            $this->populateFromPreviousRound($tournament, $round);
            $matches = TennisMatch::where('tournament_id', $tournament->id)
                ->where('round', $round)
                ->where('status', 'pending')
                ->with(['player1', 'player2'])
                ->orderBy('bracket_position')
                ->get();
        }

        $scored = 0;
        foreach ($matches as $match) {
            $p1Rank  = $match->player1->ranking ?? 999;
            $p2Rank  = $match->player2->ranking ?? 999;
            $isUpset = rand(1, 100) <= $upsetChance;
            $favIsP1 = $p1Rank <= $p2Rank;

            // Without an upset, the favorite wins. The favorite is p1 when $favIsP1 is true.
            // Table:  isUpset=F favIsP1=T → p1 ;  F/F → p2 ;  T/T → p2 ;  T/F → p1
            // => "pick p2" when (isUpset XOR !favIsP1) is true
            $winnerId = ($isUpset xor !$favIsP1) ? $match->player2_id : $match->player1_id;
            $winnerIsP1 = ($winnerId === $match->player1_id);
            // generateScore() returns "winner-loser" pairs. Flip each set if p2 is the winner
            // so player1's column in the view always shows player1's own sets.
            $score = $this->generateScore($isUpset);
            if (!$winnerIsP1) {
                $score = collect(explode(' ', $score))
                    ->map(function ($set) {
                        $parts = explode('-', $set);
                        return (count($parts) === 2) ? $parts[1] . '-' . $parts[0] : $set;
                    })
                    ->implode(' ');
            }

            $match->update(['status' => 'finished', 'winner_id' => $winnerId, 'score' => $score]);
            $scored += $this->scorePredictions($tournament, $match, $round);
        }

        return ['matches' => $matches->count(), 'scored' => $scored];
    }

    private function populateFromPreviousRound(Tournament $tournament, string $round): void
    {
        $prevIndex = array_search($round, $this->roundOrder);
        if ($prevIndex <= 0) return;

        $prevRound  = $this->roundOrder[$prevIndex - 1];
        $prevWinners = TennisMatch::where('tournament_id', $tournament->id)
            ->where('round', $prevRound)
            ->where('status', 'finished')
            ->whereNotNull('winner_id')
            ->orderBy('bracket_position')
            ->pluck('winner_id')
            ->values();

        TennisMatch::where('tournament_id', $tournament->id)
            ->where('round', $round)
            ->orderBy('bracket_position')
            ->get()
            ->each(function ($match, $i) use ($prevWinners) {
                $p1 = $prevWinners[$i * 2]     ?? null;
                $p2 = $prevWinners[$i * 2 + 1] ?? null;
                if ($p1 && $p2) $match->update(['player1_id' => $p1, 'player2_id' => $p2]);
            });
    }

    private function scorePredictions(Tournament $tournament, TennisMatch $match, string $round): int
    {
        $position = TennisMatch::where('tournament_id', $tournament->id)
            ->where('round', $round)
            ->orderBy('bracket_position')
            ->pluck('id')
            ->values()
            ->search($match->id) + 1;

        if ($position <= 0) return 0;

        $points = $tournament->getPointsForRound($round);
        $scored = 0;

        BracketPrediction::where('tournament_id', $tournament->id)
            ->where('round', $round)
            ->where('position', $position)
            ->whereNull('is_correct')
            ->get()
            ->each(function ($pred) use ($match, $points, &$scored) {
                $isCorrect = $pred->predicted_winner_id == $match->winner_id;
                $earned    = $isCorrect ? $points : 0;
                $pred->update(['is_correct' => $isCorrect, 'points_earned' => $earned]);
                if ($isCorrect) User::where('id', $pred->user_id)->increment('points', $earned);
                $scored++;
            });

        return $scored;
    }

    private function generateScore(bool $isUpset): string
    {
        $formats = $isUpset
            ? ['6-4 3-6 7-5', '7-6 4-6 6-3', '6-7 6-4 7-6', '3-6 6-4 6-4']
            : ['6-3 6-4', '6-4 6-2', '7-5 6-3', '6-2 6-4', '6-4 7-5', '7-6 6-4'];

        return $formats[array_rand($formats)];
    }

    private function updateTournamentStatus(Tournament $tournament): void
    {
        $total    = $tournament->matches()->count();
        $finished = $tournament->matches()->where('status', 'finished')->count();

        if ($total > 0 && $finished === $total) {
            $tournament->update(['status' => 'finished']);
        } elseif ($finished > 0) {
            $tournament->update(['status' => 'in_progress']);
        }
    }
}
