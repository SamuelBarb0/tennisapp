<?php

namespace Tests\Feature\Tennis;

use App\Models\Tournament;
use App\Services\Tennis\ApiTennisSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LuckyLoserHealTest extends TestCase
{
    use RefreshDatabase;

    private function player(string $name, string $slug): int
    {
        return DB::table('players')->insertGetId([
            'name' => $name, 'slug' => $slug, 'category' => 'ATP',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function heal(Tournament $t, array $fixtures): int
    {
        $svc = app(ApiTennisSyncService::class);
        $m = new \ReflectionMethod($svc, 'healCancelledReplacements');
        $m->setAccessible(true);
        return $m->invoke($svc, $t, $fixtures);
    }

    private function makeTournament(): Tournament
    {
        $id = DB::table('tournaments')->insertGetId([
            'name' => 'Wimbledon', 'slug' => 'wimby', 'type' => 'ATP Grand Slam',
            'timezone' => 'UTC', 'status' => 'in_progress', 'points_multiplier' => 1.0,
            'is_active' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        return Tournament::find($id);
    }

    public function test_lucky_loser_replacement_is_healed_from_apitennis(): void
    {
        DB::table('players')->insert(['name' => 'TBD', 'slug' => 'tbd', 'category' => 'ATP', 'created_at' => now(), 'updated_at' => now()]);
        $t = $this->makeTournament();
        $bellucci = $this->player('M. Bellucci', 'm-bellucci');
        $svajda   = $this->player('Z. Svajda', 'z-svajda');
        $llamas   = $this->player('P. Llamas Ruiz', 'p-llamas-ruiz');

        DB::table('matches')->insert([
            'tournament_id' => $t->id, 'player1_id' => $bellucci, 'player2_id' => $svajda,
            'round' => 'R128', 'bracket_position' => 35, 'scheduled_at' => now(),
            'status' => 'pending', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $fixtures = [
            [ // the original match, called off
                'event_key' => 111, 'tournament_round' => 'ATP Wimbledon - 1/64-finals',
                'event_first_player' => 'M. Bellucci', 'event_second_player' => 'Z. Svajda',
                'event_status' => 'Cancelled', 'event_winner' => '', 'event_qualification' => 'False',
            ],
            [ // the lucky loser actually played and lost
                'event_key' => 222, 'tournament_round' => 'ATP Wimbledon - 1/64-finals',
                'event_first_player' => 'P. Llamas Ruiz', 'event_second_player' => 'Z. Svajda',
                'event_status' => 'Finished', 'event_winner' => 'Second Player',
                'event_final_result' => '0 - 3', 'event_qualification' => 'False',
            ],
        ];

        $healed = $this->heal($t, $fixtures);
        $this->assertSame(1, $healed);

        $slot = DB::table('matches')->where('tournament_id', $t->id)
            ->where('round', 'R128')->where('bracket_position', 35)->first();
        $this->assertSame($llamas, (int) $slot->player1_id, 'withdrawn Bellucci replaced by lucky loser Llamas Ruiz');
        $this->assertSame($svajda, (int) $slot->player2_id, 'survivor Svajda stays');
        $this->assertSame('LL', $slot->player1_seed);
        $this->assertSame('finished', $slot->status);
        $this->assertSame($svajda, (int) $slot->winner_id, 'Svajda recorded as winner');
    }

    public function test_no_heal_when_no_cancelled_fixture(): void
    {
        DB::table('players')->insert(['name' => 'TBD', 'slug' => 'tbd', 'category' => 'ATP', 'created_at' => now(), 'updated_at' => now()]);
        $t = $this->makeTournament();
        $bellucci = $this->player('M. Bellucci', 'm-bellucci');
        $svajda   = $this->player('Z. Svajda', 'z-svajda');
        DB::table('matches')->insert([
            'tournament_id' => $t->id, 'player1_id' => $bellucci, 'player2_id' => $svajda,
            'round' => 'R128', 'bracket_position' => 35, 'scheduled_at' => now(),
            'status' => 'pending', 'created_at' => now(), 'updated_at' => now(),
        ]);

        // Only a finished fixture for the SAME two players — no cancellation, no replacement.
        $fixtures = [[
            'event_key' => 222, 'tournament_round' => 'ATP Wimbledon - 1/64-finals',
            'event_first_player' => 'M. Bellucci', 'event_second_player' => 'Z. Svajda',
            'event_status' => 'Finished', 'event_winner' => 'Second Player', 'event_qualification' => 'False',
        ]];

        $this->assertSame(0, $this->heal($t, $fixtures));
        $slot = DB::table('matches')->where('tournament_id', $t->id)->where('bracket_position', 35)->first();
        $this->assertSame('pending', $slot->status, 'nothing healed without a cancelled fixture');
    }
}
