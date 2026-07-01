<?php

namespace Tests\Feature\Tennis;

use App\Models\BracketPrediction;
use App\Models\BracketPredictionBackup;
use App\Models\Player;
use App\Models\Tournament;
use App\Services\Tennis\PredictionReconciler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PredictionResilienceTest extends TestCase
{
    use RefreshDatabase;

    private function makeTournament(): Tournament
    {
        $id = DB::table('tournaments')->insertGetId([
            'name' => 'Test Cup', 'slug' => 'test-cup', 'type' => 'ATP',
            'timezone' => 'UTC', 'status' => 'in_progress',
            'points_multiplier' => 1.0, 'is_active' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return Tournament::find($id);
    }

    private function makePlayer(string $name, string $slug): int
    {
        return DB::table('players')->insertGetId([
            'name' => $name, 'slug' => $slug, 'category' => 'ATP',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function makeMatch(int $tid, int $p1, int $p2, int $pos, string $round = 'R128'): void
    {
        DB::table('matches')->insert([
            'tournament_id' => $tid, 'player1_id' => $p1, 'player2_id' => $p2,
            'round' => $round, 'bracket_position' => $pos, 'scheduled_at' => now()->addDay(),
            'status' => 'pending', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function makeUser(): int
    {
        return DB::table('users')->insertGetId([
            'name' => 'U', 'email' => 'u'.uniqid().'@t.co', 'password' => bcrypt('x'),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_snapshot_is_captured_on_save(): void
    {
        $t = $this->makeTournament();
        $a = $this->makePlayer('Alice Ace', 'alice-ace');
        $b = $this->makePlayer('Bob Baseline', 'bob-baseline');
        $this->makeMatch($t->id, $a, $b, 1);
        $u = $this->makeUser();

        $pred = BracketPrediction::create([
            'tournament_id' => $t->id, 'user_id' => $u, 'round' => 'R128',
            'position' => 1, 'predicted_winner_id' => $a,
        ]);

        $this->assertSame('alice-ace', $pred->fresh()->predicted_player_slug);
        $this->assertSame('Alice Ace', $pred->fresh()->predicted_player_name);
    }

    public function test_pick_survives_player_row_deletion_and_relinks_by_slug(): void
    {
        $t = $this->makeTournament();
        $a = $this->makePlayer('Alice Ace', 'alice-ace');
        $b = $this->makePlayer('Bob Baseline', 'bob-baseline');
        $this->makeMatch($t->id, $a, $b, 1);
        $u = $this->makeUser();

        $pred = BracketPrediction::create([
            'tournament_id' => $t->id, 'user_id' => $u, 'round' => 'R128',
            'position' => 1, 'predicted_winner_id' => $a,
        ]);

        // Simulate a sync that replaced Alice's row with a fresh one (new id, same slug)
        // and nulled the FK (ON DELETE SET NULL behaviour).
        $pred->update(['predicted_winner_id' => null]);
        DB::table('players')->where('id', $a)->delete();
        $aNew = $this->makePlayer('Alice Ace', 'alice-ace');
        DB::table('matches')->where('tournament_id', $t->id)->where('bracket_position', 1)
            ->update(['player1_id' => $aNew]);

        app(PredictionReconciler::class)->reconcile($t);

        $fresh = $pred->fresh();
        $this->assertSame($aNew, $fresh->predicted_winner_id, 'pick re-linked to the new Alice by slug');
        $this->assertSame(1, $fresh->position, 'position stayed put');
    }

    public function test_pick_transfers_to_lucky_loser_replacement(): void
    {
        $t = $this->makeTournament();
        $x = $this->makePlayer('Xavier Withdrawn', 'xavier-withdrawn');
        $b = $this->makePlayer('Bob Baseline', 'bob-baseline');
        $y = $this->makePlayer('Yuki Luckyloser', 'yuki-luckyloser');
        $this->makeMatch($t->id, $x, $b, 1);
        $u = $this->makeUser();

        $pred = BracketPrediction::create([
            'tournament_id' => $t->id, 'user_id' => $u, 'round' => 'R128',
            'position' => 1, 'predicted_winner_id' => $x,
        ]);

        // Xavier withdraws: the slot now holds lucky loser Yuki; Xavier is nowhere in the draw.
        DB::table('matches')->where('tournament_id', $t->id)->where('bracket_position', 1)
            ->update(['player1_id' => $y]);

        app(PredictionReconciler::class)->reconcile($t);

        $fresh = $pred->fresh();
        $this->assertSame($y, $fresh->predicted_winner_id, 'pick transferred to the lucky loser in the slot');
        $this->assertSame(1, $fresh->position, 'position stayed put');
    }

    public function test_backup_and_restore_round_trip(): void
    {
        $t = $this->makeTournament();
        $a = $this->makePlayer('Alice Ace', 'alice-ace');
        $b = $this->makePlayer('Bob Baseline', 'bob-baseline');
        $this->makeMatch($t->id, $a, $b, 1);
        $u = $this->makeUser();

        BracketPrediction::create([
            'tournament_id' => $t->id, 'user_id' => $u, 'round' => 'R128',
            'position' => 1, 'predicted_winner_id' => $a,
        ]);

        [$batch, $n] = BracketPredictionBackup::snapshotTournament($t->id, 'reimport');
        $this->assertSame(1, $n);

        // Simulate the destructive wipe of predictions.
        BracketPrediction::where('tournament_id', $t->id)->delete();
        $this->assertSame(0, BracketPrediction::where('tournament_id', $t->id)->count());

        $this->artisan('tennis:restore-bracket-predictions', ['--tournament' => $t->id])
            ->assertExitCode(0);

        $restored = BracketPrediction::where('tournament_id', $t->id)->first();
        $this->assertNotNull($restored, 'prediction restored from backup');
        $this->assertSame($a, $restored->predicted_winner_id);
        $this->assertSame(1, $restored->position);
    }
}
