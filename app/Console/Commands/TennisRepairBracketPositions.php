<?php

namespace App\Console\Commands;

use App\Models\Player;
use App\Models\TennisMatch;
use App\Models\Tournament;
use App\Services\Tennis\BracketTennisScraper;
use App\Services\Tennis\PredictionRealigner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Re-anchors R128 matches to the canonical bracket.tennis slot order, then
 * realigns predictions so users keep their picks anchored to the player they
 * actually chose.
 *
 * Use case: historic syncs (before the "api-tennis never touches structure"
 * rule was enforced) left R128 rows out of order. The idempotent bootstrap
 * doesn't reshuffle them because their player_ids are real — so we need a
 * one-shot reorder pass.
 *
 * Safe to re-run: it only swaps rows that DON'T already match BT's order, so
 * a second run on a clean tournament is a no-op.
 */
class TennisRepairBracketPositions extends Command
{
    protected $signature = 'tennis:repair-bracket-positions
                            {--tournament= : Tournament id to repair (defaults to all active)}
                            {--dry-run : Show what would change without writing}';

    protected $description = 'Reordena R128 al orden canónico de bracket.tennis y realinea predicciones.';

    public function handle(BracketTennisScraper $scraper, PredictionRealigner $realigner): int
    {
        $tournaments = $this->option('tournament')
            ? Tournament::where('id', $this->option('tournament'))->get()
            : Tournament::where('is_active', true)->whereNotNull('tennisexplorer_slug')->get();

        $dry = (bool) $this->option('dry-run');
        if ($dry) $this->warn('DRY RUN — ningún cambio será persistido.');

        foreach ($tournaments as $t) {
            $this->info(PHP_EOL . "→ {$t->name} [{$t->type}] (id={$t->id})");
            $this->repairOne($t, $scraper, $realigner, $dry);
        }

        return self::SUCCESS;
    }

    private function repairOne(Tournament $t, BracketTennisScraper $scraper, PredictionRealigner $realigner, bool $dry): void
    {
        [$slug, $tour] = $this->parseBracketTennisSlug($t->tennisexplorer_slug, $t);
        $draw = $scraper->draw($slug, $tour);
        if (empty($draw)) {
            $this->warn('  Sin draw de bracket.tennis — nada que reparar.');
            return;
        }

        $entryCount = count($draw);
        $startRound = match (true) {
            $entryCount > 32 => 'R128',
            $entryCount > 16 => 'R64',
            $entryCount > 8  => 'R32',
            $entryCount > 4  => 'R16',
            default          => 'QF',
        };

        // For each BT slot, identify the canonical (player1_id, player2_id)
        // pair. We use the DB players the bootstrap already created for those
        // names — so we look players up by surname+initial like the
        // bootstrap matcher does.
        $expected = [];
        foreach ($draw as $entry) {
            $btPos = $entry['slot'] + 1; // BT slot is 0-indexed, DB is 1-indexed
            $p1Id = $this->resolveDbPlayer($entry['p1'], $t->type);
            $p2Id = $this->resolveDbPlayer($entry['p2'], $t->type);
            $expected[$btPos] = [$p1Id, $p2Id];
        }

        // Walk the existing R128 rows. For each one, find the BT slot whose
        // (p1, p2) pair contains BOTH of its current players (order-agnostic).
        // If the row is already at that slot, skip. Otherwise queue a swap.
        $rows = $t->matches()->where('round', $startRound)->get()->keyBy('bracket_position');

        $moves = []; // [oldPos => newPos]
        $unmatched = [];

        foreach ($rows as $currentPos => $row) {
            $foundSlot = null;
            foreach ($expected as $btPos => [$p1, $p2]) {
                if ($p1 === null || $p2 === null) continue;
                $rowPair = [$row->player1_id, $row->player2_id];
                sort($rowPair);
                $btPair = [$p1, $p2];
                sort($btPair);
                if ($rowPair === $btPair) {
                    $foundSlot = $btPos;
                    break;
                }
            }
            if ($foundSlot === null) {
                $unmatched[] = $currentPos;
                continue;
            }
            if ($foundSlot !== $currentPos) {
                $moves[$currentPos] = $foundSlot;
            }
        }

        $this->info("  Filas: " . $rows->count() . " | reubicaciones necesarias: " . count($moves) . " | sin match con BT: " . count($unmatched));

        if (empty($moves)) {
            $this->info('  Bracket ya está alineado — sin cambios.');
            return;
        }

        if ($dry) {
            foreach (array_slice($moves, 0, 10, true) as $from => $to) {
                $row = $rows[$from];
                $p1 = Player::find($row->player1_id)?->name ?? '?';
                $p2 = Player::find($row->player2_id)?->name ?? '?';
                $this->line("    pos={$from} → pos={$to}  ({$p1} vs {$p2})");
            }
            if (count($moves) > 10) $this->line('    … (' . (count($moves) - 10) . ' más)');
            return;
        }

        // Apply swaps inside a transaction. We first move every row to a
        // unique NEGATIVE position to dodge the (tournament_id, round,
        // bracket_position) unique constraint, then place each row at its
        // target position.
        DB::transaction(function () use ($rows, $moves) {
            $park = -100000;
            $rowTargets = []; // [rowId => finalPos]
            foreach ($rows as $originalPos => $row) {
                $rowTargets[$row->id] = $moves[$originalPos] ?? $originalPos;
                TennisMatch::where('id', $row->id)
                    ->update(['bracket_position' => $park--]);
            }
            foreach ($rowTargets as $rowId => $finalPos) {
                TennisMatch::where('id', $rowId)
                    ->update(['bracket_position' => $finalPos]);
            }
        });

        $this->info("  Movidas " . count($moves) . " filas.");

        // Realign predictions so users keep their picks attached to their chosen players.
        $result = $realigner->realign($t);
        $this->info("  Predicciones — promovidas={$result['promoted']} migradas={$result['moved']} huérfanas={$result['orphaned']}");
    }

    private function parseBracketTennisSlug(string $slug, Tournament $t): array
    {
        $tour = str_starts_with($t->type, 'WTA') ? 'wta' : 'atp';
        // Allow slugs of the form "name-2026" or "name-2026/atp"
        $parts = explode('/', $slug);
        return [trim($parts[0]), $tour];
    }

    private function resolveDbPlayer(?string $name, string $tourType): ?int
    {
        if (!$name || strcasecmp($name, 'Bye') === 0) return null;
        $tour = str_starts_with($tourType, 'WTA') ? 'WTA' : 'ATP';

        $surname = BracketTennisScraper::surnameKey($name);
        if ($surname === '') return null;

        $btFirst = strtolower(trim(preg_split('/\s+/', $name)[0] ?? ''));
        if ($btFirst === '') return null;

        $candidates = Player::where('category', $tour)
            ->where('name', 'like', '%' . substr($surname, 0, 4) . '%')
            ->get()
            ->filter(fn($p) => BracketTennisScraper::surnameKey($p->name) === $surname);

        if ($candidates->count() === 1) return $candidates->first()->id;

        if ($candidates->count() > 1) {
            $hits = $candidates->filter(function ($c) use ($btFirst) {
                $candidatePrefix = strtolower(rtrim(preg_split('/\s+/', $c->name)[0] ?? '', '.'));
                return $candidatePrefix !== '' && str_starts_with($btFirst, $candidatePrefix);
            });
            if ($hits->count() === 1) return $hits->first()->id;
        }

        return null;
    }
}
