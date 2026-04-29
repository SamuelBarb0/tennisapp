<?php

namespace Database\Seeders;

use App\Models\Player;
use App\Models\Tournament;
use App\Models\TournamentRoundPoints;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Premium tournament — 32-player WTA bracket priced in COP.
 * Use this to test the Mercado Pago paywall flow end-to-end.
 */
class PremiumTournamentSeeder extends Seeder
{
    private const TBD = 1057;
    private const API_KEY = 'test-roma-premium-wta-2026';

    public function run(): void
    {
        // Wipe any prior run so the seeder is idempotent
        $existing = Tournament::where('api_tournament_key', self::API_KEY)->first();
        if ($existing) {
            DB::table('tournament_payments')->where('tournament_id', $existing->id)->delete();
            DB::table('bracket_predictions')->where('tournament_id', $existing->id)->delete();
            DB::table('tournament_tiebreaks')->where('tournament_id', $existing->id)->delete();
            DB::table('tournament_round_points')->where('tournament_id', $existing->id)->delete();
            DB::table('matches')->where('tournament_id', $existing->id)->delete();
            $existing->delete();
            $this->command->info("Deleted previous premium test tournament.");
        }

        $tournament = Tournament::create([
            'api_tournament_key' => self::API_KEY,
            'api_event_type_key' => 'test-wta-1000',
            'name'               => 'Roma Premium Test',
            'slug'                => 'roma-premium-test-2026',
            'type'               => 'WTA',
            'location'           => 'Roma',
            'city'               => 'Roma',
            'country'            => 'Italia',
            'surface'            => 'Clay',
            'start_date'         => '2026-05-10',
            'end_date'           => '2026-05-17',
            'season'             => '2026',
            'is_premium'         => true,           // ← paywall ON
            'price'              => 15000,          // COP
            'is_active'          => true,
            'featured_on_home'   => true,
            'status'             => 'upcoming',
            'points_multiplier'  => 1.0,
        ]);
        $this->command->info("Created premium tournament: {$tournament->id} · {$tournament->name}");
        $this->command->info("Price: \${$tournament->price} COP");

        // Round points
        $roundPoints = [
            'R32' => 10,
            'R16' => 25,
            'QF'  => 50,
            'SF'  => 100,
            'F'   => 200,
        ];
        foreach ($roundPoints as $round => $pts) {
            TournamentRoundPoints::create([
                'tournament_id' => $tournament->id,
                'round'         => $round,
                'points'        => $pts,
            ]);
        }

        // Top 32 WTA players
        $players = Player::where('category', 'WTA')
            ->where('name', '!=', 'TBD')
            ->whereNotNull('ranking')
            ->orderBy('ranking')
            ->take(32)
            ->pluck('id')
            ->toArray();

        if (count($players) < 32) {
            $this->command->error('Not enough WTA players (need 32).');
            return;
        }

        $this->createBracket($tournament->id, $players, '2026-05-10');
        $this->command->info("Created full 32-player bracket (31 matches).");
        $this->command->newLine();
        $this->command->line('→ Browse to /tournaments/roma-premium-test-2026 to see the paywall.');
    }

    private function createBracket(int $tournamentId, array $players, string $startDate): void
    {
        $tbd = self::TBD;
        $now = now();

        // R32 — 16 matches with real seeded players
        for ($i = 0; $i < 16; $i++) {
            DB::table('matches')->insert([
                'tournament_id'    => $tournamentId,
                'player1_id'       => $players[$i],
                'player2_id'       => $players[31 - $i],
                'round'            => 'R32',
                'bracket_position' => $i + 1,
                'scheduled_at'     => $startDate . ' 10:00:00',
                'status'           => 'pending',
                'created_at'       => $now,
                'updated_at'       => $now,
            ]);
        }

        // Subsequent rounds: TBD placeholders
        $roundsAfter = [
            ['R16', 8, '+2 days'],
            ['QF',  4, '+4 days'],
            ['SF',  2, '+5 days'],
            ['F',   1, '+6 days'],
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
