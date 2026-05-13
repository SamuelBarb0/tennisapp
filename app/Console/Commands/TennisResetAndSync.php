<?php

namespace App\Console\Commands;

use App\Models\BracketPrediction;
use App\Models\Player;
use App\Models\TennisMatch;
use App\Models\Tournament;
use App\Models\TournamentPayment;
use App\Models\TournamentRoundPoints;
use App\Models\TournamentTiebreak;
use App\Services\Tennis\ApiTennisSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Wipe legacy seeded data (players + non-test tournaments + their matches)
 * and re-populate from the Matchstat API.
 *
 * What is preserved:
 *   - Test tournaments (slug contains "test") — they are fictional and used by
 *     the simulator. Their matches and bracket predictions stay intact.
 *   - The TBD player (id=1057) — used as placeholder in test brackets.
 *   - Users, payments, redemptions, banners, settings.
 *
 * What is wiped:
 *   - All real ATP/WTA/GS tournaments seeded manually
 *   - Their matches
 *   - All players except the TBD placeholder and players still referenced by
 *     surviving test matches
 *   - Bracket predictions tied to wiped tournaments
 *
 * Usage:
 *   php artisan tennis:reset-and-sync
 *   php artisan tennis:reset-and-sync --no-confirm
 *   php artisan tennis:reset-and-sync --skip-sync   (just clean, don't pull yet)
 */
class TennisResetAndSync extends Command
{
    protected $signature = 'tennis:reset-and-sync
                            {--no-confirm : Skip the confirmation prompt}
                            {--skip-sync : Only clean, do not call Matchstat afterwards}';

    protected $description = 'Wipe legacy data and rebuild players from Matchstat (keeps test tournaments)';

    public function handle(ApiTennisSyncService $sync): int
    {
        $this->newLine();
        $this->line('<bg=red;fg=white;options=bold> ⚠  DESTRUCTIVE OPERATION </>');
        $this->newLine();

        // Show what will happen
        $testTournaments = Tournament::where('slug', 'like', '%test%')->get();
        $realTournaments = Tournament::where('slug', 'not like', '%test%')->get();

        $this->line('<options=bold>Will be PRESERVED:</>');
        $this->line("  · Test tournaments: <fg=green>{$testTournaments->count()}</> ("
            . $testTournaments->pluck('name')->take(5)->implode(', ') . ($testTournaments->count() > 5 ? '...' : '') . ')');
        $this->line('  · TBD placeholder player');
        $this->line('  · Users, payments, banners, settings');
        $this->newLine();

        $this->line('<options=bold>Will be WIPED:</>');
        $this->line("  · Real tournaments: <fg=red>{$realTournaments->count()}</>");
        $this->line("  · All players (except TBD and those used by test tournaments)");
        $this->line("  · All matches in real tournaments");
        $this->line("  · Bracket predictions tied to those tournaments");
        $this->newLine();

        if (!$this->option('no-confirm') && !$this->confirm('¿Continuar?')) {
            $this->warn('Cancelled.');
            return self::FAILURE;
        }

        DB::transaction(function () use ($testTournaments) {
            $this->line('🗑  Wiping legacy data...');

            // 1. Delete real tournaments and cascade-delete their matches/predictions
            // Cascade is on the FKs so this is enough.
            $deletedTournaments = Tournament::where('slug', 'not like', '%test%')->delete();
            $this->line("  · Deleted {$deletedTournaments} tournaments (and their matches/predictions/payments via FK cascade)");

            // 2. Find players still referenced by surviving (test) matches
            $survivingPlayerIds = TennisMatch::whereIn('tournament_id', $testTournaments->pluck('id'))
                ->select('player1_id', 'player2_id', 'winner_id')
                ->get()
                ->flatMap(fn($m) => [$m->player1_id, $m->player2_id, $m->winner_id])
                ->filter()
                ->unique()
                ->values()
                ->all();

            // 3. Always preserve TBD placeholder (id 1057 by convention)
            $tbd = Player::where('name', 'TBD')->first();
            if ($tbd) $survivingPlayerIds[] = $tbd->id;
            $survivingPlayerIds = array_unique($survivingPlayerIds);

            // 4. Delete players not in the surviving set
            $deletedPlayers = Player::whereNotIn('id', $survivingPlayerIds)->delete();
            $this->line("  · Deleted {$deletedPlayers} legacy players, kept "
                . count($survivingPlayerIds) . " referenced by test brackets / TBD");

            // 5. Reset api_player_key on the surviving players so the next sync
            //    can re-link them by name without unique constraint conflicts.
            Player::whereIn('id', $survivingPlayerIds)
                ->update(['api_player_key' => null]);
        });

        $this->info('✓ Cleanup complete.');

        if ($this->option('skip-sync')) {
            $this->newLine();
            $this->line('Skipping Matchstat sync (--skip-sync).');
            $this->line('Run <fg=cyan>php artisan tennis:sync-rankings</> when ready.');
            return self::SUCCESS;
        }

        $this->newLine();
        $this->line('🌐 Pulling rankings from api-tennis.com...');

        try {
            $stats = $sync->syncRankings(200);
        } catch (\Throwable $e) {
            $this->error('api-tennis.com sync failed: ' . $e->getMessage());
            $this->warn('Cleanup succeeded — you can retry with `php artisan tennis:sync-rankings`.');
            return self::FAILURE;
        }

        $this->table(
            ['Tour', 'Total', 'Created', 'Matched'],
            [
                ['ATP', $stats['atp'], $stats['created'] ?? 0, $stats['matched'] ?? 0],
                ['WTA', $stats['wta'], '—', '—'],
            ]
        );

        $totalPlayers = Player::count();
        $totalTournaments = Tournament::count();
        $this->newLine();
        $this->info("✓ Reset complete.");
        $this->line("  · Players in DB: <fg=cyan>{$totalPlayers}</>");
        $this->line("  · Tournaments in DB: <fg=cyan>{$totalTournaments}</> (test only)");
        $this->newLine();
        $this->line('Next steps:');
        $this->line('  · Run <fg=cyan>php artisan tennis:discover-tournaments</> to link the 23 covered tournaments');
        $this->line('  · Visit <fg=yellow>/admin/api-sync</> to see linked tournaments');

        return self::SUCCESS;
    }
}
