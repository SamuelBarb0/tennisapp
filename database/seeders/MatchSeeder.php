<?php

namespace Database\Seeders;

use App\Models\Player;
use App\Models\TennisMatch;
use App\Models\Tournament;
use Illuminate\Database\Seeder;

class MatchSeeder extends Seeder
{
    public function run(): void
    {
        // Get the Indian Wells tournament (currently active)
        $indianWells = Tournament::where('name', 'BNP Paribas Open')->first();
        $atpPlayers = Player::where('category', 'ATP')->orderBy('ranking')->take(16)->get();

        $rounds = ['R64', 'R32', 'R16', 'QF', 'SF', 'F'];

        // Create R16 matches for Indian Wells
        $matchups = [
            [0, 15], [7, 8], [3, 12], [4, 11],
            [1, 14], [6, 9], [2, 13], [5, 10],
        ];

        foreach ($matchups as $i => $pair) {
            $isFinished = $i < 4;
            $winner = $isFinished ? $atpPlayers[$pair[0]] : null;
            $scores = ['6-4, 7-5', '7-6(4), 6-3', '6-3, 6-4', '6-7(5), 6-4, 6-2'];

            TennisMatch::create([
                'tournament_id' => $indianWells->id,
                'player1_id' => $atpPlayers[$pair[0]]->id,
                'player2_id' => $atpPlayers[$pair[1]]->id,
                'round' => 'R16',
                'scheduled_at' => now()->subDays(2 - floor($i / 4))->setHour(10 + ($i * 2)),
                'score' => $isFinished ? $scores[$i] : null,
                'winner_id' => $winner?->id,
                'status' => $isFinished ? 'finished' : ($i === 4 ? 'live' : 'pending'),
            ]);
        }

        // Create QF matches
        TennisMatch::create([
            'tournament_id' => $indianWells->id,
            'player1_id' => $atpPlayers[0]->id,
            'player2_id' => $atpPlayers[7]->id,
            'round' => 'QF',
            'scheduled_at' => now()->addDay()->setHour(12),
            'status' => 'pending',
        ]);

        TennisMatch::create([
            'tournament_id' => $indianWells->id,
            'player1_id' => $atpPlayers[3]->id,
            'player2_id' => $atpPlayers[4]->id,
            'round' => 'QF',
            'scheduled_at' => now()->addDay()->setHour(16),
            'status' => 'pending',
        ]);

        // WTA Indian Wells matches
        $indianWellsWTA = Tournament::where('name', 'BNP Paribas Open WTA')->first();
        $wtaPlayers = Player::where('category', 'WTA')->orderBy('ranking')->take(8)->get();

        $wtaMatchups = [[0, 7], [3, 4], [1, 6], [2, 5]];

        foreach ($wtaMatchups as $i => $pair) {
            $isFinished = $i < 2;
            $winner = $isFinished ? $wtaPlayers[$pair[0]] : null;

            TennisMatch::create([
                'tournament_id' => $indianWellsWTA->id,
                'player1_id' => $wtaPlayers[$pair[0]]->id,
                'player2_id' => $wtaPlayers[$pair[1]]->id,
                'round' => 'QF',
                'scheduled_at' => now()->subDay()->setHour(11 + ($i * 2)),
                'score' => $isFinished ? '6-3, 6-4' : null,
                'winner_id' => $winner?->id,
                'status' => $isFinished ? 'finished' : ($i === 2 ? 'live' : 'pending'),
            ]);
        }
    }
}
