<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Tournament;
use App\Models\Player;
use Illuminate\Support\Facades\DB;

class TestTournamentsSeeder extends Seeder
{
    private const TBD = 1057; // TBD player id

    public function run(): void
    {
        // Borrar torneos de test anteriores si existen
        $existing = Tournament::whereIn('api_tournament_key', [
            'test-barcelona-atp-2026',
            'test-stuttgart-wta-2026',
        ])->get();

        foreach ($existing as $t) {
            DB::table('matches')->where('tournament_id', $t->id)->delete();
            $t->delete();
            $this->command->info("Deleted existing tournament: {$t->name}");
        }

        // ATP Test Tournament - Barcelona (ATP, Clay)
        $atp = Tournament::create([
            'api_tournament_key' => 'test-barcelona-atp-2026',
            'api_event_type_key' => 'test-atp-500',
            'name'              => 'Barcelona Open Test',
            'slug'              => 'barcelona-open-test-2026',
            'type'              => 'ATP',
            'location'          => 'Barcelona',
            'city'              => 'Barcelona',
            'country'           => 'Spain',
            'surface'           => 'Clay',
            'start_date'        => '2026-04-20',
            'end_date'          => '2026-04-27',
            'season'            => '2026',
            'is_premium'        => false,
            'is_active'         => true,
            'status'            => 'upcoming',
            'points_multiplier' => 1.0,
        ]);
        $this->command->info("Created ATP: {$atp->id} - {$atp->name}");

        // WTA Test Tournament - Stuttgart (WTA, Grass)
        $wta = Tournament::create([
            'api_tournament_key' => 'test-stuttgart-wta-2026',
            'api_event_type_key' => 'test-wta-500',
            'name'              => 'Stuttgart Open Test',
            'slug'              => 'stuttgart-open-test-2026',
            'type'              => 'WTA',
            'location'          => 'Stuttgart',
            'city'              => 'Stuttgart',
            'country'           => 'Germany',
            'surface'           => 'Grass',
            'start_date'        => '2026-06-15',
            'end_date'          => '2026-06-22',
            'season'            => '2026',
            'is_premium'        => false,
            'is_active'         => true,
            'status'            => 'upcoming',
            'points_multiplier' => 1.0,
        ]);
        $this->command->info("Created WTA: {$wta->id} - {$wta->name}");

        // Top 32 ATP players by ranking
        $atpPlayers = Player::where('category', 'ATP')
            ->where('name', '!=', 'TBD')
            ->whereNotNull('ranking')
            ->orderBy('ranking')
            ->take(32)
            ->pluck('id')
            ->toArray();

        // Top 32 WTA players by ranking
        $wtaPlayers = Player::where('category', 'WTA')
            ->where('name', '!=', 'TBD')
            ->whereNotNull('ranking')
            ->orderBy('ranking')
            ->take(32)
            ->pluck('id')
            ->toArray();

        $this->createBracket($atp->id, $atpPlayers, '2026-04-20');
        $this->command->info("Created full ATP bracket (31 matches)");

        $this->createBracket($wta->id, $wtaPlayers, '2026-06-15');
        $this->command->info("Created full WTA bracket (31 matches)");
    }

    /**
     * Crea un cuadro completo de 32 jugadores con todas las rondas.
     * R32: 16 partidos con jugadores reales (1vs32, 2vs31, ...)
     * R16, QF, SF, F: partidos TBD (se rellenan cuando avancen los resultados)
     */
    private function createBracket(int $tournamentId, array $players, string $startDate): void
    {
        $tbd = self::TBD;

        // R32 - 16 partidos: seed 1 vs 32, 2 vs 31, ..., 16 vs 17
        for ($i = 0; $i < 16; $i++) {
            DB::table('matches')->insert([
                'tournament_id'    => $tournamentId,
                'player1_id'       => $players[$i],
                'player2_id'       => $players[31 - $i],
                'round'            => 'R32',
                'bracket_position' => $i + 1,
                'scheduled_at'     => $startDate . ' 10:00:00',
                'status'           => 'pending',
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);
        }

        // R16 - 8 partidos TBD
        for ($i = 0; $i < 8; $i++) {
            DB::table('matches')->insert([
                'tournament_id'    => $tournamentId,
                'player1_id'       => $tbd,
                'player2_id'       => $tbd,
                'round'            => 'R16',
                'bracket_position' => $i + 1,
                'scheduled_at'     => date('Y-m-d', strtotime($startDate . ' +2 days')) . ' 10:00:00',
                'status'           => 'pending',
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);
        }

        // QF - 4 partidos TBD
        for ($i = 0; $i < 4; $i++) {
            DB::table('matches')->insert([
                'tournament_id'    => $tournamentId,
                'player1_id'       => $tbd,
                'player2_id'       => $tbd,
                'round'            => 'QF',
                'bracket_position' => $i + 1,
                'scheduled_at'     => date('Y-m-d', strtotime($startDate . ' +4 days')) . ' 10:00:00',
                'status'           => 'pending',
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);
        }

        // SF - 2 partidos TBD
        for ($i = 0; $i < 2; $i++) {
            DB::table('matches')->insert([
                'tournament_id'    => $tournamentId,
                'player1_id'       => $tbd,
                'player2_id'       => $tbd,
                'round'            => 'SF',
                'bracket_position' => $i + 1,
                'scheduled_at'     => date('Y-m-d', strtotime($startDate . ' +5 days')) . ' 10:00:00',
                'status'           => 'pending',
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);
        }

        // F - 1 partido TBD
        DB::table('matches')->insert([
            'tournament_id'    => $tournamentId,
            'player1_id'       => $tbd,
            'player2_id'       => $tbd,
            'round'            => 'F',
            'bracket_position' => 1,
            'scheduled_at'     => date('Y-m-d', strtotime($startDate . ' +6 days')) . ' 10:00:00',
            'status'           => 'pending',
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);
    }
}
