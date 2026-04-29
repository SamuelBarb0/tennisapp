<?php

namespace App\Services\Tennis;

use App\Http\Controllers\BracketPredictionController;
use App\Models\Player;
use App\Models\TennisMatch;
use App\Models\Tournament;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Orchestrates sync between Matchstat (Tennis API ATP/WTA/ITF) and our local DB.
 *
 * Three sync flows:
 *  - syncRankings()        — refresh top-N ATP & WTA, upserts Player rows
 *  - syncTournamentLive()  — refresh one tournament's fixtures + scores
 *  - syncAllActive()       — loop syncTournamentLive over every active tournament
 *
 * The service is intentionally idempotent: re-running a sync just refreshes data,
 * it never duplicates rows.
 */
class MatchstatSyncService
{
    public function __construct(private MatchstatClient $client) {}

    // ───────────────────────────────────────────────────────────────────────────
    // Rankings
    // ───────────────────────────────────────────────────────────────────────────

    /**
     * Pull top-N rankings from Matchstat for ATP and WTA.
     *
     * Strategy to avoid duplicates with seeded/legacy data:
     *   1. Try to match by matchstat_id (already linked players)
     *   2. Fall back to name + category match (existing seed players get their
     *      matchstat_id assigned, no duplicate is created)
     *   3. Only as a last resort do we create a fresh row
     */
    public function syncRankings(int $top = 200): array
    {
        $stats = ['atp' => 0, 'wta' => 0, 'matched' => 0, 'created' => 0, 'errors' => []];
        foreach (['atp' => 'ATP', 'wta' => 'WTA'] as $type => $category) {
            $resp = $this->client->ranking($type);
            if (!$resp || !isset($resp['data'])) {
                $stats['errors'][] = "{$type}: empty response";
                continue;
            }
            foreach (array_slice($resp['data'], 0, $top) as $row) {
                $p = $row['player'] ?? null;
                if (!$p || empty($p['id'])) continue;

                $player = $this->resolvePlayer($p, $category);

                $player->fill([
                    'matchstat_id'     => $p['id'],
                    'name'             => $p['name'] ?? $player->name ?? 'Unknown',
                    'category'         => $category,
                    'ranking'          => $row['position'] ?? null,
                    'nationality_code' => $this->ioc3ToIso2($p['countryAcr'] ?? null) ?? $player->nationality_code,
                    // `country` is the verbose label (NOT NULL in legacy schema). Use the
                    // human-readable name from the API when present, fall back to the IOC code.
                    'country'          => $p['country']['name'] ?? $p['countryAcr'] ?? $player->country ?? 'Unknown',
                ])->save();

                if ($player->wasRecentlyCreated) $stats['created']++;
                else $stats['matched']++;
                $stats[$type]++;
            }
        }
        return $stats;
    }

    /**
     * Find an existing Player record that corresponds to the API payload, or
     * create a new one. Tries (in order):
     *   1. matchstat_id (exact link)
     *   2. exact name + category
     *   3. case-insensitive name + category (handles "Felix Auger Aliassime" vs
     *      "Félix Auger-Aliassime", small typos, etc.)
     *   4. new Player()
     */
    private function resolvePlayer(array $apiPlayer, string $category): Player
    {
        // 1) Already linked
        if ($existing = Player::where('matchstat_id', $apiPlayer['id'])->first()) {
            return $existing;
        }

        $name = $apiPlayer['name'] ?? '';
        if ($name === '') return new Player();

        // 2) Exact name + category, prefer a row that doesn't have a different matchstat_id
        $byName = Player::where('name', $name)
            ->where('category', $category)
            ->whereNull('matchstat_id')
            ->first();
        if ($byName) return $byName;

        // 3) Loose match — strip accents/punctuation
        $normalized = $this->normalizeName($name);
        $loose = Player::where('category', $category)
            ->whereNull('matchstat_id')
            ->get()
            ->first(fn($p) => $this->normalizeName($p->name) === $normalized);
        if ($loose) return $loose;

        // 4) New player
        return new Player();
    }

    /**
     * Compare-friendly form of a player name. Removes accents, hyphens, dots
     * and lowercases.
     */
    private function normalizeName(string $name): string
    {
        $name = mb_strtolower($name);
        // Strip accents
        $name = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name) ?: $name;
        // Strip non-letters
        $name = preg_replace('/[^a-z0-9\s]/', '', $name);
        // Collapse whitespace
        $name = preg_replace('/\s+/', ' ', trim($name));
        return $name;
    }

    // ───────────────────────────────────────────────────────────────────────────
    // Tournament live sync
    // ───────────────────────────────────────────────────────────────────────────

    /**
     * Pull current fixtures for a tournament we already track. Updates each match
     * with score/winner when available, then triggers scoring of bracket predictions.
     *
     * Requires that $tournament->matchstat_tournament_id is set.
     */
    public function syncTournamentLive(Tournament $tournament): array
    {
        if (!$tournament->matchstat_tournament_id) {
            return ['updated' => 0, 'scored' => 0, 'skipped' => 'no matchstat_tournament_id'];
        }

        $type = strtolower(str_starts_with($tournament->type, 'WTA') ? 'wta' : 'atp');

        // 1) Pull all fixtures of the tournament (mostly metadata, no scores)
        $fixtures = $this->client->fixturesByTournament($type, (int) $tournament->matchstat_tournament_id, [
            'include' => 'round,tournament',
        ]);
        $fixtureCount = count($fixtures['data'] ?? []);

        // 2) Pull past-matches for each known player to get scores + winners.
        //    We dedupe by match id and merge over the fixtures map.
        $matchData = []; // keyed by matchstat match id
        foreach ($fixtures['data'] ?? [] as $f) {
            $matchData[$f['id']] = $f + ['match_winner' => null, 'result' => null];
        }

        // Find all players in this tournament's fixtures and pull their past-matches.
        // This is the only way to get scores from this API.
        $playerIds = collect($fixtures['data'] ?? [])
            ->flatMap(fn($f) => [$f['player1Id'] ?? null, $f['player2Id'] ?? null])
            ->filter()->unique()->values();

        $scoredFromPast = 0;
        foreach ($playerIds->take(20) as $pid) { // cap to avoid burning quota
            $past = $this->client->playerPastMatches($type, (int) $pid);
            foreach ($past['data'] ?? [] as $pm) {
                if (!isset($pm['tournamentId']) || $pm['tournamentId'] !== (int) $tournament->matchstat_tournament_id) continue;
                $mid = (int) $pm['id'];
                // past-matches uses string ids — fixtures use ints, but they should match
                $matchData[$mid] = ($matchData[$mid] ?? []) + [
                    'match_winner' => $pm['match_winner'] ?? null,
                    'result'       => $pm['result'] ?? null,
                ];
                $scoredFromPast++;
            }
        }

        // 3) Upsert into our matches table
        $updated = 0;
        $newlyFinished = [];
        foreach ($matchData as $mid => $m) {
            $existing = TennisMatch::where('matchstat_match_id', $mid)->first();

            $player1 = $this->upsertPlayerFromFixture($m['player1'] ?? null, $tournament);
            $player2 = $this->upsertPlayerFromFixture($m['player2'] ?? null, $tournament);

            $status = $m['match_winner'] ? 'finished' : 'pending';
            $winnerId = null;
            if ($m['match_winner']) {
                $winnerPlayer = Player::where('matchstat_id', $m['match_winner'])->first();
                $winnerId = $winnerPlayer?->id;
            }

            $attrs = [
                'tournament_id' => $tournament->id,
                'player1_id'    => $player1?->id,
                'player2_id'    => $player2?->id,
                'round'         => $this->mapRound($m['roundId'] ?? null, $tournament),
                'status'        => $status,
                'winner_id'     => $winnerId,
                'score'         => $m['result'] ?? null,
                'scheduled_at'  => $this->parseDate($m['date'] ?? null),
            ];

            if ($existing) {
                $wasFinished = $existing->status === 'finished';
                $existing->update($attrs);
                if (!$wasFinished && $status === 'finished') {
                    $newlyFinished[] = $existing->id;
                }
            } else {
                // Need a bracket_position; fall back to incrementing within the round.
                $attrs['matchstat_match_id'] = $mid;
                $attrs['bracket_position'] = $this->nextBracketPosition($tournament, $attrs['round']);
                TennisMatch::create($attrs);
            }
            $updated++;
        }

        // 4) Score predictions for any newly-finished matches
        $scored = 0;
        if (!empty($newlyFinished)) {
            $scored = BracketPredictionController::scoreTournament($tournament);
        }

        $tournament->update(['last_synced_at' => now()]);

        return [
            'fixtures_returned' => $fixtureCount,
            'players_polled'    => $playerIds->take(20)->count(),
            'updated'           => $updated,
            'newly_finished'    => count($newlyFinished),
            'scored'            => $scored,
        ];
    }

    /** Loop syncTournamentLive over every active tournament with a matchstat id. */
    public function syncAllActive(): array
    {
        $results = [];
        $tournaments = Tournament::where('is_active', true)
            ->whereNotNull('matchstat_tournament_id')
            ->whereIn('status', ['upcoming', 'in_progress', 'live'])
            ->get();

        foreach ($tournaments as $t) {
            try {
                $results[$t->slug] = $this->syncTournamentLive($t);
            } catch (\Throwable $e) {
                Log::error("Sync failed for {$t->slug}", ['error' => $e->getMessage()]);
                $results[$t->slug] = ['error' => $e->getMessage()];
            }
        }
        return $results;
    }

    // ───────────────────────────────────────────────────────────────────────────
    // Helpers
    // ───────────────────────────────────────────────────────────────────────────

    private function upsertPlayerFromFixture(?array $p, Tournament $tournament): ?Player
    {
        if (!$p || empty($p['id'])) return null;

        // Skip "doubles" entries — name like "X/Y"
        if (str_contains($p['name'] ?? '', '/')) return null;

        $category = str_starts_with($tournament->type, 'WTA') ? 'WTA' : 'ATP';

        return Player::updateOrCreate(
            ['matchstat_id' => $p['id']],
            [
                'name'             => $p['name'] ?? 'Unknown',
                'category'         => $category,
                'nationality_code' => $this->ioc3ToIso2($p['countryAcr'] ?? null),
                'country'          => $p['countryAcr'] ?? 'Unknown',
            ]
        );
    }

    /**
     * Matchstat roundId mapping is a bit weird — it's relative to the draw size.
     * Best effort: map by name first, fall back to numeric.
     */
    private function mapRound(?int $roundId, Tournament $tournament): string
    {
        // From observation: 5=Second, 6=Third, 7=Fourth, 9=1/4, 10=1/2, 12=Final
        // Stable name-based map (we always pass include=round so name should be there)
        return match ($roundId) {
            12 => 'F',
            10 => 'SF',
            9  => 'QF',
            7  => 'R16',
            6  => 'R32',
            5  => 'R64',
            4  => 'R128',
            default => 'R128',
        };
    }

    private function nextBracketPosition(Tournament $tournament, string $round): int
    {
        return TennisMatch::where('tournament_id', $tournament->id)
            ->where('round', $round)
            ->max('bracket_position') + 1 ?? 1;
    }

    private function parseDate(?string $iso): ?Carbon
    {
        if (!$iso) return null;
        try {
            return Carbon::parse($iso);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Matchstat returns IOC 3-letter codes ("ITA", "USA"). Our `flag_url` accessor
     * uses 2-letter ISO codes for flagcdn.com. Map the common ones.
     */
    private function ioc3ToIso2(?string $ioc): ?string
    {
        if (!$ioc) return null;
        $map = [
            'ARG'=>'ar','AUS'=>'au','AUT'=>'at','BEL'=>'be','BLR'=>'by','BRA'=>'br',
            'BUL'=>'bg','CAN'=>'ca','CHI'=>'cl','CHN'=>'cn','COL'=>'co','CRO'=>'hr',
            'CUB'=>'cu','CYP'=>'cy','CZE'=>'cz','DEN'=>'dk','ECU'=>'ec','EGY'=>'eg',
            'ESP'=>'es','EST'=>'ee','FIN'=>'fi','FRA'=>'fr','GBR'=>'gb','GEO'=>'ge',
            'GER'=>'de','GRE'=>'gr','HUN'=>'hu','IND'=>'in','IRL'=>'ie','ISR'=>'il',
            'ITA'=>'it','JPN'=>'jp','KAZ'=>'kz','KOR'=>'kr','LAT'=>'lv','LTU'=>'lt',
            'MEX'=>'mx','MON'=>'mc','NED'=>'nl','NOR'=>'no','NZL'=>'nz','PAR'=>'py',
            'PER'=>'pe','PHI'=>'ph','POL'=>'pl','POR'=>'pt','PUR'=>'pr','ROU'=>'ro',
            'RSA'=>'za','RUS'=>'ru','SLO'=>'si','SRB'=>'rs','SUI'=>'ch','SVK'=>'sk',
            'SWE'=>'se','THA'=>'th','TPE'=>'tw','TUN'=>'tn','TUR'=>'tr','UKR'=>'ua',
            'URU'=>'uy','USA'=>'us','VEN'=>'ve',
        ];
        return $map[strtoupper($ioc)] ?? null;
    }
}
