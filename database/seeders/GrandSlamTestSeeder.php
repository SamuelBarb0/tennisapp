<?php

namespace Database\Seeders;

use App\Models\Player;
use App\Models\Tournament;
use App\Models\TournamentRoundPoints;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds a Grand Slam-sized test tournament with a full 128-player draw.
 * Rounds: R128 → R64 → R32 → R16 → QF → SF → F (127 matches total).
 */
class GrandSlamTestSeeder extends Seeder
{
    private const TBD = 1057;
    private const API_KEY = 'test-grandslam-atp-2026';

    public function run(): void
    {
        // Wipe any prior run so seeding is idempotent
        $existing = Tournament::where('api_tournament_key', self::API_KEY)->first();
        if ($existing) {
            DB::table('bracket_predictions')->where('tournament_id', $existing->id)->delete();
            DB::table('tournament_tiebreaks')->where('tournament_id', $existing->id)->delete();
            DB::table('tournament_round_points')->where('tournament_id', $existing->id)->delete();
            DB::table('matches')->where('tournament_id', $existing->id)->delete();
            $existing->delete();
            $this->command->info("Deleted previous Grand Slam Test.");
        }

        $tournament = Tournament::create([
            'api_tournament_key' => self::API_KEY,
            'api_event_type_key' => 'test-grand-slam',
            'name'               => 'Australian Test Open',
            'slug'                => 'australian-test-open-2026',
            'type'               => 'GrandSlam',
            'location'           => 'Melbourne',
            'city'               => 'Melbourne',
            'country'            => 'Australia',
            'surface'            => 'Hard',
            'start_date'         => '2026-05-04',
            'end_date'           => '2026-05-17',
            'season'             => '2026',
            'is_premium'         => false,
            'is_active'          => true,
            'status'             => 'upcoming',
            'points_multiplier'  => 1.0,
        ]);
        $this->command->info("Created Grand Slam: {$tournament->id} · {$tournament->name}");

        // Configure per-round points (more points for later rounds)
        $roundPoints = [
            'R128' => 5,
            'R64'  => 10,
            'R32'  => 20,
            'R16'  => 40,
            'QF'   => 80,
            'SF'   => 150,
            'F'    => 300,
        ];
        foreach ($roundPoints as $round => $pts) {
            TournamentRoundPoints::create([
                'tournament_id' => $tournament->id,
                'round'         => $round,
                'points'        => $pts,
            ]);
        }
        $this->command->info("Configured round points (R128=5 ... F=300).");

        // Top 128 ATP players for a men's Grand Slam draw
        $players = Player::where('category', 'ATP')
            ->where('name', '!=', 'TBD')
            ->whereNotNull('ranking')
            ->orderBy('ranking')
            ->take(128)
            ->pluck('id')
            ->toArray();

        if (count($players) < 128) {
            $this->command->error('Not enough ATP players with ranking (need 128).');
            return;
        }

        $this->createFullBracket($tournament->id, $players, '2026-05-04');
        $this->command->info("Created full 128-player bracket (127 matches across 7 rounds).");
    }

    /**
     * 128-player draw: seed 1 vs 128, 2 vs 127, ..., 64 vs 65.
     * Subsequent rounds are all TBD placeholders that the simulator fills in.
     */
    private function createFullBracket(int $tournamentId, array $players, string $startDate): void
    {
        $tbd = self::TBD;
        $now = now();

        // R128 — 64 matches with real players. Seeding 1v128, 2v127, ..., 64v65
        // to mirror the standard Grand Slam draw layout.
        for ($i = 0; $i < 64; $i++) {
            DB::table('matches')->insert([
                'tournament_id'    => $tournamentId,
                'player1_id'       => $players[$i],
                'player2_id'       => $players[127 - $i],
                'round'            => 'R128',
                'bracket_position' => $i + 1,
                'scheduled_at'     => $startDate . ' 10:00:00',
                'status'           => 'pending',
                'created_at'       => $now,
                'updated_at'       => $now,
            ]);
        }

        // Subsequent rounds: match count halves each time, all TBD
        $roundsAfter = [
            ['R64',  32, '+2 days'],
            ['R32',  16, '+4 days'],
            ['R16',   8, '+6 days'],
            ['QF',    4, '+8 days'],
            ['SF',    2, '+10 days'],
            ['F',     1, '+12 days'],
        ];

        foreach ($roundsAfter as [$round, $count, $offset]) {
            $date = date('Y-m-d', strtotime($startDate . ' ' . $offset));
            for ($i = 0; $i < $count; $i++) {
                DB::table('matches')->insert([
                    'tournament_id'    => $tournamentId,
                    'player1_id'       => $tbd,
                    'player2_id'       => $tbd,
                    'round'            => $round,
                    'bracket_position' => $i + 1,
                    'scheduled_at'     => $date . ' 10:00:00',
                    'status'           => 'pending',
                    'created_at'       => $now,
                    'updated_at'       => $now,
                ]);
            }
        }
    }
}
