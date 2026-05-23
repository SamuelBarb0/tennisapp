<?php

namespace App\Console\Commands;

use App\Models\Player;
use App\Services\Tennis\BracketTennisScraper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Merge duplicate Player rows. A "duplicate pair" is two players that:
 *   - share the same tour (category)
 *   - share the same surnameKey()
 *   - one's first-name prefix is a prefix of the other's (so "Susan" ⊃ "S",
 *     "Z" ⊂ "Zarina", "Juan Manuel" ⊃ "J. M.")
 *
 * For each pair, we pick a canonical row (the one with more dependent
 * matches+predictions, ties broken by lower id) and re-point every foreign
 * key to it: matches.player1_id / player2_id / winner_id,
 * predictions.predicted_winner_id, bracket_predictions.predicted_winner_id,
 * player_seed_overrides.player_id. The orphan is then deleted.
 *
 * Real siblings (Jones, Cerundolo F vs JM, Harris Lloyd vs Billy) are
 * preserved: the prefix-of test fails when the first names actually differ.
 */
class TennisDedupePlayers extends Command
{
    protected $signature = 'tennis:dedupe-players
                            {--dry-run : Show planned merges without writing anything}';

    protected $description = 'Fusiona Players duplicados creados por diferentes fuentes (api-tennis vs bracket.tennis).';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        if ($dry) $this->warn('DRY RUN — ningún cambio será persistido.');

        // Group all players by (category, surnameKey). Anything with >1 row
        // is a candidate group to inspect.
        $groups = Player::all()
            ->groupBy(fn($p) => ($p->category ?? '') . '|' . BracketTennisScraper::surnameKey($p->name ?? ''))
            ->filter(fn($g) => $g->count() > 1);

        $merged = 0;
        $skipped = 0;
        foreach ($groups as $key => $players) {
            [$tour, $surname] = explode('|', $key, 2);
            if ($surname === '') { $skipped++; continue; }

            // Walk every pair inside the group and decide if it's a real
            // duplicate (prefix-compatible) or distinct siblings.
            $list = $players->values();
            $alreadyMerged = []; // ids we already merged away

            for ($i = 0; $i < $list->count(); $i++) {
                for ($j = $i + 1; $j < $list->count(); $j++) {
                    $a = $list[$i];
                    $b = $list[$j];
                    if (isset($alreadyMerged[$a->id]) || isset($alreadyMerged[$b->id])) continue;

                    if (!$this->arePrefixCompatible($a->name, $b->name)) continue;

                    [$canonical, $orphan] = $this->pickCanonical($a, $b);

                    $this->line(sprintf(
                        '  [%s|%s] merge orphan "%s" (id=%d) → canonical "%s" (id=%d)',
                        $tour, $surname, $orphan->name, $orphan->id, $canonical->name, $canonical->id,
                    ));

                    if (!$dry) {
                        $this->mergeInto($canonical->id, $orphan->id);
                        $alreadyMerged[$orphan->id] = true;
                    }
                    $merged++;
                }
            }
        }

        $this->info(PHP_EOL . "Fusionados: {$merged}. Grupos saltados (sin surname): {$skipped}.");
        return self::SUCCESS;
    }

    /**
     * Two names are prefix-compatible when, after dropping initials,
     * the shorter first-name prefix is a prefix of the longer one.
     * Examples:
     *   "Susan Bandecchi" + "S. Bandecchi"            → compatible (S ⊂ Susan)
     *   "Juan Manuel Cerundolo" + "J. M. Cerundolo"   → compatible (J ⊂ Juan)
     *   "Juan Manuel Cerundolo" + "F. Cerundolo"      → NOT compatible (F ≠ J)
     *   "Lloyd Harris" + "Billy Harris"               → NOT compatible
     */
    private function arePrefixCompatible(string $aName, string $bName): bool
    {
        $a = $this->firstNamePrefix($aName);
        $b = $this->firstNamePrefix($bName);
        if ($a === '' || $b === '') return false;
        return str_starts_with($a, $b) || str_starts_with($b, $a);
    }

    private function firstNamePrefix(string $name): string
    {
        $name = trim($name);
        if ($name === '') return '';
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name) ?: $name;
        $ascii = preg_replace('/[^a-zA-Z\s.-]/', '', $ascii);
        $first = strtolower(trim(preg_split('/\s+/', ltrim($ascii))[0] ?? ''));
        return rtrim($first, '.');
    }

    /**
     * Choose which row survives. Whichever has more dependent data wins;
     * ties broken by the lower id (preserve the older row).
     */
    private function pickCanonical(Player $a, Player $b): array
    {
        $weight = function (Player $p) {
            return DB::table('matches')
                    ->where('player1_id', $p->id)->orWhere('player2_id', $p->id)
                    ->count()
                + DB::table('bracket_predictions')
                    ->where('predicted_winner_id', $p->id)->count()
                + DB::table('predictions')
                    ->where('predicted_winner_id', $p->id)->count();
        };

        $wa = $weight($a);
        $wb = $weight($b);
        if ($wa > $wb)  return [$a, $b];
        if ($wb > $wa)  return [$b, $a];
        return $a->id < $b->id ? [$a, $b] : [$b, $a];
    }

    /**
     * Reassign every foreign key from $orphanId → $canonicalId, then delete
     * the orphan. Wrapped in a transaction so a partial failure rolls back.
     */
    private function mergeInto(int $canonicalId, int $orphanId): void
    {
        DB::transaction(function () use ($canonicalId, $orphanId) {
            DB::table('matches')->where('player1_id', $orphanId)
                ->update(['player1_id' => $canonicalId]);
            DB::table('matches')->where('player2_id', $orphanId)
                ->update(['player2_id' => $canonicalId]);
            DB::table('matches')->where('winner_id', $orphanId)
                ->update(['winner_id' => $canonicalId]);

            DB::table('bracket_predictions')->where('predicted_winner_id', $orphanId)
                ->update(['predicted_winner_id' => $canonicalId]);

            if (Schema::hasTable('predictions')) {
                DB::table('predictions')->where('predicted_winner_id', $orphanId)
                    ->update(['predicted_winner_id' => $canonicalId]);
            }

            if (Schema::hasTable('player_seed_overrides')) {
                // If the canonical already has an override in the same
                // tournament, drop the orphan's override instead of moving
                // it (would violate the unique constraint).
                $orphanOverrides = DB::table('player_seed_overrides')
                    ->where('player_id', $orphanId)->get();
                foreach ($orphanOverrides as $ov) {
                    $exists = DB::table('player_seed_overrides')
                        ->where('player_id', $canonicalId)
                        ->where('tournament_id', $ov->tournament_id)
                        ->exists();
                    if ($exists) {
                        DB::table('player_seed_overrides')->where('id', $ov->id)->delete();
                    } else {
                        DB::table('player_seed_overrides')->where('id', $ov->id)
                            ->update(['player_id' => $canonicalId]);
                    }
                }
            }

            Player::where('id', $orphanId)->delete();
        });
    }
}
