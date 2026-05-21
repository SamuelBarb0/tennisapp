<?php

namespace App\Console\Commands;

use App\Models\Player;
use App\Models\TennisMatch;
use App\Models\BracketPrediction;
use App\Services\Tennis\BracketTennisScraper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Find Player rows that represent the same person (e.g. "A. Zverev" and
 * "Alexander Zverev") and merge them into the one with a real ranking.
 *
 * Two Players are duplicates when:
 *   - Same surnameKey() (last name, ASCII-normalised, lowercase)
 *   - Same category (ATP / WTA)
 *
 * Strategy: keep the one with a real ranking (the "canonical"), reassign all
 * its match references (player1_id / player2_id / winner_id), then delete
 * the duplicate. Bracket predictions are also remapped.
 */
class TennisMergeDuplicatePlayers extends Command
{
    protected $signature = 'tennis:merge-duplicate-players
                            {--dry-run : Show what would be merged without writing anything}';

    protected $description = 'Merge duplicate Player rows created by the bracket.tennis bootstrap (e.g. "A. Zverev" + "Alexander Zverev")';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        // Group ALL players by (category, surnameKey, firstInitial). Adding the
        // first initial of the FIRST given name prevents merging siblings or
        // unrelated players who happen to share a surname:
        //   - "F. Cerundolo" + "Juan Manuel Cerundolo" → different initials, skip
        //   - "A. Zverev"   + "Alexander Zverev"      → both 'A', merge
        $groups = [];
        foreach (Player::all() as $p) {
            $cat = $p->category ?: 'UNKNOWN';
            $key = BracketTennisScraper::surnameKey($p->name ?? '');
            if ($key === '') continue;
            $initial = $this->firstInitial($p->name ?? '');
            if ($initial === '') continue;
            $gid = $cat . '|' . $key . '|' . $initial;
            $groups[$gid][] = $p;
        }

        $totals = ['groups_processed' => 0, 'players_merged' => 0, 'matches_repointed' => 0, 'predictions_repointed' => 0];

        foreach ($groups as $gid => $players) {
            if (count($players) < 2) continue;

            // Pick the canonical: the one with a real ranking. If multiple have
            // a ranking, pick the one with the lowest id (oldest, most likely
            // the api-tennis one that the rankings sync keeps fresh).
            usort($players, function ($a, $b) {
                $aHasRank = !empty($a->ranking);
                $bHasRank = !empty($b->ranking);
                if ($aHasRank && !$bHasRank) return -1;
                if (!$aHasRank && $bHasRank) return 1;
                return $a->id <=> $b->id;
            });
            $canonical = array_shift($players);
            $dups      = $players;

            $this->line("· {$canonical->category} '{$canonical->name}' (id={$canonical->id}, rank=" . ($canonical->ranking ?? 'NULL') . ")");
            foreach ($dups as $d) {
                $this->line("    └─ merging duplicate '{$d->name}' (id={$d->id}, rank=" . ($d->ranking ?? 'NULL') . ")");
                if ($dryRun) {
                    $totals['players_merged']++;
                    continue;
                }

                DB::transaction(function () use ($canonical, $d, &$totals) {
                    // Repoint matches.
                    $totals['matches_repointed'] += TennisMatch::where('player1_id', $d->id)->update(['player1_id' => $canonical->id]);
                    $totals['matches_repointed'] += TennisMatch::where('player2_id', $d->id)->update(['player2_id' => $canonical->id]);
                    $totals['matches_repointed'] += TennisMatch::where('winner_id',  $d->id)->update(['winner_id'  => $canonical->id]);

                    // Repoint bracket predictions if the table exists.
                    if (class_exists(\App\Models\BracketPrediction::class)) {
                        $totals['predictions_repointed'] += BracketPrediction::where('predicted_winner_id', $d->id)
                            ->update(['predicted_winner_id' => $canonical->id]);
                    }

                    // Backfill canonical fields from the duplicate when the
                    // canonical's are empty/poor — e.g. duplicate has a full
                    // name and the canonical has the initial-form.
                    $updates = [];
                    if (mb_strlen($d->name ?? '') > mb_strlen($canonical->name ?? '')) {
                        $updates['name'] = $d->name;
                    }
                    if (empty($canonical->country) || $canonical->country === 'Unknown') {
                        if (!empty($d->country) && $d->country !== 'Unknown') $updates['country'] = $d->country;
                    }
                    if (empty($canonical->nationality_code) && !empty($d->nationality_code)) {
                        $updates['nationality_code'] = $d->nationality_code;
                    }
                    if (!empty($updates)) $canonical->update($updates);

                    $d->delete();
                    $totals['players_merged']++;
                });
            }
            $totals['groups_processed']++;
        }

        $this->newLine();
        $this->info(($dryRun ? '[DRY RUN] ' : '') . 'Done.');
        $this->table(array_keys($totals), [array_values($totals)]);

        return self::SUCCESS;
    }

    /**
     * Return the lowercase first letter of the player's first given name.
     * Handles both initialled forms ("A. Zverev" → 'a') and full forms
     * ("Alexander Zverev" → 'a'). Returns '' when the name is malformed.
     */
    private function firstInitial(string $name): string
    {
        $name = trim($name);
        if ($name === '') return '';
        // ASCII-normalise so accents don't split groups.
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name) ?: $name;
        $ascii = preg_replace('/[^a-zA-Z\s.-]/', '', $ascii);
        $ch = mb_substr(ltrim($ascii), 0, 1);
        return strtolower($ch);
    }
}
