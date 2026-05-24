<?php

namespace App\Services\Tennis;

use App\Http\Controllers\BracketPredictionController;
use App\Models\Player;
use App\Models\TennisMatch;
use App\Models\Tournament;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Orchestrates sync between api-tennis.com and our local DB. Replaces the
 * older MatchstatSyncService.
 *
 * Three sync flows:
 *   - syncRankings()       — refresh ATP & WTA rankings (top-N players)
 *   - syncCalendar()       — upsert the 23 covered tournaments by name match
 *   - syncTournamentLive() — refresh one tournament's fixtures + scores
 *   - syncAllActive()      — loop syncTournamentLive over every active tournament
 *
 * Idempotent — re-running just refreshes data, never duplicates rows.
 */
class ApiTennisSyncService
{
    public function __construct(
        private ApiTennisClient $client,
        private BracketTennisScraper $scraper,
    ) {}

    /**
     * The 23 tournaments the customer wants covered. Maps a search needle to
     * the tier label we store, plus which tours apply. The needle is matched
     * case-insensitively against the API's `tournament_name` and the event
     * name MUST be `Atp Singles` or `Wta Singles` — that filters out doubles,
     * juniors, ITF events that share city names.
     *
     * The order also acts as priority: when multiple API tournaments match the
     * same needle (e.g. "Madrid" and "Madrid 2"), we keep the one with the
     * fewest extra tokens — the canonical edition.
     */
    private const COVERED = [
        // Grand Slams
        ['needle' => 'Australian Open', 'tier' => 'Grand Slam',       'tours' => ['ATP', 'WTA'], 'city' => 'Melbourne',  'country' => 'Australia',      'surface' => 'Dura'],
        ['needle' => 'French Open',     'tier' => 'Grand Slam',       'tours' => ['ATP', 'WTA'], 'city' => 'Paris',      'country' => 'Francia',        'surface' => 'Arcilla'],
        ['needle' => 'Wimbledon',       'tier' => 'Grand Slam',       'tours' => ['ATP', 'WTA'], 'city' => 'Londres',    'country' => 'Reino Unido',    'surface' => 'Hierba'],
        ['needle' => 'US Open',         'tier' => 'Grand Slam',       'tours' => ['ATP', 'WTA'], 'city' => 'Nueva York', 'country' => 'Estados Unidos', 'surface' => 'Dura'],
        // ATP Masters 1000 (9)
        ['needle' => 'Indian Wells',    'tier' => 'ATP Masters 1000', 'tours' => ['ATP'], 'city' => 'Indian Wells', 'country' => 'Estados Unidos', 'surface' => 'Dura'],
        ['needle' => 'Miami',           'tier' => 'ATP Masters 1000', 'tours' => ['ATP'], 'city' => 'Miami',        'country' => 'Estados Unidos', 'surface' => 'Dura'],
        ['needle' => 'Monte Carlo',     'tier' => 'ATP Masters 1000', 'tours' => ['ATP'], 'city' => 'Monte Carlo',  'country' => 'Mónaco',         'surface' => 'Arcilla'],
        ['needle' => 'Madrid',          'tier' => 'ATP Masters 1000', 'tours' => ['ATP'], 'city' => 'Madrid',       'country' => 'España',         'surface' => 'Arcilla'],
        ['needle' => 'Rome',            'tier' => 'ATP Masters 1000', 'tours' => ['ATP'], 'city' => 'Roma',         'country' => 'Italia',         'surface' => 'Arcilla'],
        ['needle' => 'Montreal',        'tier' => 'ATP Masters 1000', 'tours' => ['ATP'], 'city' => 'Montreal',     'country' => 'Canadá',         'surface' => 'Dura'],
        ['needle' => 'Cincinnati',      'tier' => 'ATP Masters 1000', 'tours' => ['ATP'], 'city' => 'Cincinnati',   'country' => 'Estados Unidos', 'surface' => 'Dura'],
        ['needle' => 'Shanghai',        'tier' => 'ATP Masters 1000', 'tours' => ['ATP'], 'city' => 'Shanghái',     'country' => 'China',          'surface' => 'Dura'],
        ['needle' => 'Paris',           'tier' => 'ATP Masters 1000', 'tours' => ['ATP'], 'city' => 'París',        'country' => 'Francia',        'surface' => 'Dura (indoor)'],
        // WTA 1000 (10) — Miami/Madrid/Rome/Cincinnati already covered above as Masters,
        // here we add the WTA versions plus the 5 WTA-only events
        ['needle' => 'Miami',           'tier' => 'WTA 1000',         'tours' => ['WTA'], 'city' => 'Miami',        'country' => 'Estados Unidos', 'surface' => 'Dura'],
        ['needle' => 'Madrid',          'tier' => 'WTA 1000',         'tours' => ['WTA'], 'city' => 'Madrid',       'country' => 'España',         'surface' => 'Arcilla'],
        ['needle' => 'Rome',            'tier' => 'WTA 1000',         'tours' => ['WTA'], 'city' => 'Roma',         'country' => 'Italia',         'surface' => 'Arcilla'],
        ['needle' => 'Cincinnati',      'tier' => 'WTA 1000',         'tours' => ['WTA'], 'city' => 'Cincinnati',   'country' => 'Estados Unidos', 'surface' => 'Dura'],
        ['needle' => 'Doha',            'tier' => 'WTA 1000',         'tours' => ['WTA'], 'city' => 'Doha',         'country' => 'Catar',          'surface' => 'Dura'],
        ['needle' => 'Dubai',           'tier' => 'WTA 1000',         'tours' => ['WTA'], 'city' => 'Dubái',        'country' => 'Emiratos Árabes Unidos', 'surface' => 'Dura'],
        ['needle' => 'Toronto',         'tier' => 'WTA 1000',         'tours' => ['WTA'], 'city' => 'Toronto',      'country' => 'Canadá',         'surface' => 'Dura'],
        ['needle' => 'Beijing',         'tier' => 'WTA 1000',         'tours' => ['WTA'], 'city' => 'Pekín',        'country' => 'China',          'surface' => 'Dura'],
        ['needle' => 'Wuhan',           'tier' => 'WTA 1000',         'tours' => ['WTA'], 'city' => 'Wuhan',        'country' => 'China',          'surface' => 'Dura'],
    ];

    // ───────────────────────────────────────────────────────────────────────────
    // Rankings
    // ───────────────────────────────────────────────────────────────────────────

    /**
     * Pull ATP and WTA rankings, upserting Player rows. Resolves existing rows
     * by api_player_key first, then by slug — slug is UNIQUE in the schema.
     *
     * Returns: ['atp'=>n, 'wta'=>n, 'created'=>n, 'matched'=>n, 'errors'=>[...]]
     */
    public function syncRankings(int $top = 200): array
    {
        $stats = ['atp' => 0, 'wta' => 0, 'matched' => 0, 'created' => 0, 'errors' => []];

        foreach (['ATP', 'WTA'] as $tour) {
            $resp = $this->client->standings($tour);
            if (!$resp || empty($resp['result'])) {
                $stats['errors'][] = "{$tour}: empty response";
                continue;
            }

            foreach (array_slice($resp['result'], 0, $top) as $row) {
                $playerKey = $row['player_key'] ?? null;
                $name      = trim($row['player'] ?? '');
                if (!$playerKey || $name === '') continue;

                $slug = Str::slug($name) ?: ('player-' . $playerKey);

                $player = Player::where('api_player_key', $playerKey)->first()
                    ?? Player::where('slug', $slug)->first()
                    ?? new Player();

                $player->fill([
                    'api_player_key'   => (string) $playerKey,
                    'name'             => $name,
                    'slug'             => $slug,
                    'category'         => $tour,
                    'ranking'          => is_numeric($row['place'] ?? null) ? (int) $row['place'] : null,
                    'country'          => $row['country'] ?? $player->country ?? 'Unknown',
                    'nationality_code' => $this->countryToIso2($row['country'] ?? null) ?? $player->nationality_code,
                ])->save();

                if ($player->wasRecentlyCreated) $stats['created']++;
                else                              $stats['matched']++;
                $stats[strtolower($tour)]++;
            }
        }

        return $stats;
    }

    // ───────────────────────────────────────────────────────────────────────────
    // Calendar discovery
    // ───────────────────────────────────────────────────────────────────────────

    /**
     * Pull the master tournament catalog, find the 23 covered events by name,
     * and upsert Tournament rows with their api_tournament_key. Idempotent.
     *
     * Returns: ['imported'=>n, 'updated'=>n, 'skipped'=>n, 'errors'=>[...]]
     */
    public function syncCalendar(): array
    {
        $stats = ['imported' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];

        $resp = $this->client->tournaments();
        if (!$resp || empty($resp['result'])) {
            $stats['errors'][] = 'empty tournament catalog';
            return $stats;
        }

        // Index API tournaments by event_type for fast lookup
        $byEvent = [
            ApiTennisClient::EVENT_ATP_SINGLES => [],
            ApiTennisClient::EVENT_WTA_SINGLES => [],
        ];
        foreach ($resp['result'] as $t) {
            $ek = (int) ($t['event_type_key'] ?? 0);
            if (isset($byEvent[$ek])) {
                $byEvent[$ek][] = $t;
            }
        }

        foreach (self::COVERED as $entry) {
            foreach ($entry['tours'] as $tour) {
                $eventKey = $tour === 'ATP' ? ApiTennisClient::EVENT_ATP_SINGLES : ApiTennisClient::EVENT_WTA_SINGLES;
                $best = $this->bestTournamentMatch($byEvent[$eventKey], $entry['needle']);

                if (!$best) {
                    $stats['skipped']++;
                    continue;
                }

                $tier = $entry['tier'] === 'Grand Slam' ? "{$tour} Grand Slam" : $entry['tier'];
                $name = $this->canonicalDisplayName($entry['needle'], $tour);
                $slug = Str::slug($name) . '-' . strtolower($tour);
                // Family slug groups ATP+WTA of the same event into one card.
                // We derive it from the canonical name (no tour suffix) + year.
                $year = now()->year;
                $familySlug = Str::slug($this->canonicalDisplayName($entry['needle'], 'ATP')) . '-' . $year;

                $existing = Tournament::where('api_tournament_key', (string) $best['tournament_key'])->first()
                    ?? Tournament::where('slug', $slug)->first();

                $attrs = [
                    'api_tournament_key' => (string) $best['tournament_key'],
                    'name'               => $name,
                    'slug'               => $slug,
                    'family_slug'        => $familySlug,
                    'type'               => $tier,
                    'season'             => $year,
                    'is_active'          => true,
                    // Static location/surface data per needle (api-tennis doesn't expose this).
                    'city'               => $entry['city']    ?? null,
                    'country'            => $entry['country'] ?? null,
                    'surface'            => $entry['surface'] ?? null,
                ];

                if ($existing) {
                    $existing->fill($attrs)->save();
                    $stats['updated']++;
                } else {
                    Tournament::create($attrs);
                    $stats['imported']++;
                }
            }
        }

        return $stats;
    }

    /**
     * Pick the best match from a list of API tournaments for a given needle.
     * Prefers shorter/canonical names ("Madrid" beats "Madrid 2", "ATP Madrid").
     */
    private function bestTournamentMatch(array $candidates, string $needle): ?array
    {
        $needleLower = mb_strtolower($needle);
        $matches = [];
        foreach ($candidates as $t) {
            $name = mb_strtolower($t['tournament_name'] ?? '');
            if (str_contains($name, $needleLower)) {
                $matches[] = $t;
            }
        }
        if (empty($matches)) return null;

        // Sort by name length ascending — "Madrid" (6) wins over "ATP Madrid" (10).
        usort($matches, fn($a, $b) => strlen($a['tournament_name'] ?? '') - strlen($b['tournament_name'] ?? ''));
        return $matches[0];
    }

    /**
     * Customer-friendly commercial display names for each tournament. We use
     * the official sponsor/branded name the customer specified — matches what
     * users see on TV broadcasts and ATP/WTA homepages.
     */
    private function canonicalDisplayName(string $needle, string $tour): string
    {
        return match ($needle) {
            // Grand Slams
            'Australian Open' => 'Australian Open',
            'French Open'     => 'Roland Garros',
            'Wimbledon'       => 'Wimbledon',
            'US Open'         => 'US Open',
            // ATP Masters 1000
            'Indian Wells'    => 'BNP Paribas Open',
            'Miami'           => $tour === 'WTA' ? 'Miami Open' : 'Miami Open',
            'Monte Carlo'     => 'Rolex Monte-Carlo Masters',
            'Madrid'          => 'Mutua Madrid Open',
            'Rome'            => "Internazionali BNL d'Italia",
            'Montreal'        => 'National Bank Open (Montreal)',
            'Cincinnati'      => 'Cincinnati Open',
            'Shanghai'        => 'Rolex Shanghai Masters',
            'Paris'           => 'Rolex Paris Masters',
            // WTA 1000 extras
            'Doha'            => 'Qatar TotalEnergies Open',
            'Dubai'           => 'Dubai Duty Free Tennis Championships',
            'Toronto'         => 'National Bank Open (Toronto)',
            'Beijing'         => 'China Open',
            'Wuhan'           => 'Wuhan Open',
            default           => $needle,
        };
    }

    // ───────────────────────────────────────────────────────────────────────────
    // Tournament live sync
    // ───────────────────────────────────────────────────────────────────────────

    /**
     * Pull current fixtures for one tournament and upsert matches + scores.
     * Triggers scoring of bracket predictions for any match that just finished.
     */
    public function syncTournamentLive(Tournament $tournament): array
    {
        if (!$tournament->api_tournament_key) {
            return ['skipped' => 'no api_tournament_key'];
        }

        // Window split into 3-day chunks. Pulling the whole tournament window
        // at once times out on Grand Slams + Masters because the API streams
        // pointbypoint payloads slowly (1MB+ responses). Day-by-day stays well
        // under the 90s timeout per call.
        //
        // We anchor the window to the tournament's own dates (when known) — a
        // simple "now() ± 21 days" misses Australian Open in May, French Open
        // in September, etc. Falls back to a now-centered window for tournaments
        // without dates yet (those still in discovery).
        if ($tournament->start_date && $tournament->end_date) {
            // Pad by 5 days on each side to absorb timezone drift and qualifying
            // pre-events that sometimes carry over a calendar day.
            $windowStart = $tournament->start_date->copy()->subDays(5);
            $windowEnd   = $tournament->end_date->copy()->addDays(5);
        } else {
            $windowStart = now()->subDays(21);
            $windowEnd   = now()->addDays(21);
        }

        $allFixtures = [];
        for ($cursor = $windowStart->copy(); $cursor->lte($windowEnd); $cursor->addDays(3)) {
            $chunkStart = $cursor->format('Y-m-d');
            $chunkEnd   = $cursor->copy()->addDays(2)->min($windowEnd)->format('Y-m-d');
            $resp = $this->client->fixtures($chunkStart, $chunkEnd, [
                'tournament_key' => (int) $tournament->api_tournament_key,
            ]);
            if ($resp && !empty($resp['result'])) {
                foreach ($resp['result'] as $f) {
                    $allFixtures[$f['event_key']] = $f;
                }
            }
        }

        // bracket.tennis is the source of truth for bracket structure +
        // players (including qualifier confirmations as TBDs get filled in).
        // api-tennis only supplies match results. So we ALWAYS run the BT
        // bootstrap before processing api-tennis fixtures — the bootstrap is
        // idempotent: it fills TBD slots with newly-confirmed players and
        // updates seeds, but never overwrites a real player or a result.
        $bootstrapped = $this->bootstrapFromBracketTennis($tournament);

        // Detect whether api-tennis has published main-draw fixtures yet.
        // Grand Slams publish qualifying days before the main draw, so we
        // explicitly filter qualy out — an empty()-only guard kept us stuck on
        // qualy in past seasons.
        $mainDrawFixtures = array_filter($allFixtures, function ($f) {
            $isQualy = ($f['event_qualification'] ?? null) === 'True'
                || ($f['event_qualification'] ?? null) === true;
            return !$isQualy && trim((string) ($f['tournament_round'] ?? '')) !== '';
        });

        // If api-tennis has nothing useful yet, we're done — the bootstrap
        // above already published the bracket from BT for users to predict on.
        if (empty($mainDrawFixtures)) {
            return [
                'fixtures'       => count($allFixtures),
                'main_draw'      => 0,
                'qualy'          => count($allFixtures),
                'finished'       => 0,
                'scored'         => 0,
                'placeholders'   => 0,
                'bootstrapped'   => $bootstrapped,
            ];
        }

        // Derive the tournament's real start datetime from the earliest
        // main-draw fixture api-tennis published. This is how the "Cierra:
        // HH:MM" deadline gets a precise value — api-tennis usually publishes
        // event_time (HH:MM in the tournament's local timezone) for day-1
        // matches 24-72h before the tournament starts. The bootstrap rows we
        // created earlier all point to tournament.start_date for their
        // scheduled_at, so updating start_date cascades to every R128 slot
        // that doesn't have its own api-tennis fixture yet.
        $this->refreshTournamentStartDate($tournament, $mainDrawFixtures);

        // Wrap into the shape the rest of the loop expects.
        $resp = ['result' => array_values($allFixtures)];

        $newlyFinished = [];
        $updated = 0;
        $skippedQualy = 0;
        foreach ($resp['result'] as $f) {
            $eventKey = $f['event_key'] ?? null;
            if (!$eventKey) continue;

            // Skip qualifying rounds — the bracket predictions cover the main
            // draw only. The API marks them with event_qualification = "True"
            // and reuses round names like "Semi-finals" / "Final" for the
            // qualifying ladder, which would corrupt our bracket structure.
            $isQualy = ($f['event_qualification'] ?? null) === 'True'
                || ($f['event_qualification'] ?? null) === true;
            if ($isQualy) {
                $skippedQualy++;
                continue;
            }

            // Skip fixtures with no round label — those are usually exhibition
            // / next-gen / extras the API mixes into the tournament feed.
            $roundLabel = trim((string) ($f['tournament_round'] ?? ''));
            if ($roundLabel === '') {
                $skippedQualy++;
                continue;
            }

            // Skip cancelled fixtures — the API leaves the original scheduled
            // pairing as "Cancelled" when a player withdraws and adds a NEW
            // fixture with the replacement opponent. Keeping both creates
            // duplicate matches in the same round.
            $apiStatus = mb_strtolower((string) ($f['event_status'] ?? ''));
            if (str_contains($apiStatus, 'cancelled')) {
                $skippedQualy++;
                continue;
            }

            $player1 = $this->upsertPlayerFromFixture(
                $f['first_player_key'] ?? null,
                $f['event_first_player'] ?? null,
                $f['event_first_player_logo'] ?? null,
                $tournament,
            );
            $player2 = $this->upsertPlayerFromFixture(
                $f['second_player_key'] ?? null,
                $f['event_second_player'] ?? null,
                $f['event_second_player_logo'] ?? null,
                $tournament,
            );

            $status = $this->mapStatus($f['event_status'] ?? null);
            $winnerSide = $f['event_winner'] ?? null;
            $winnerId = null;
            if ($status === 'finished') {
                if ($winnerSide === 'First Player')  $winnerId = $player1?->id;
                if ($winnerSide === 'Second Player') $winnerId = $player2?->id;
            }

            $round = $this->mapRound($f['tournament_round'] ?? '');

            // Compute status note for retirements / walkovers / suspensions.
            // The LOSING player gets the tag (ret./wo/etc.) next to their name.
            $statusNote = $this->computeStatusNote($f, $winnerSide);

            // Results-only payload. api-tennis ONLY contributes scores, winners,
            // status and scheduling — never the player_id, seed, round, or
            // bracket_position. Those belong to bracket.tennis (the structural
            // authority) and changing them here is what historically scrambled
            // the bracket (sibling player confusion, ghost matches, etc.).
            $resultAttrs = [
                'status'       => $status,
                'status_note'  => $statusNote,
                'winner_id'    => $winnerId,
                'score'        => $this->formatScore($f),
                'scheduled_at' => $this->parseDateTime($f['event_date'] ?? null, $f['event_time'] ?? null, $tournament->timezone ?: 'America/Bogota'),
            ];

            // For "qualifier upgrade" cases — when bracket.tennis had this slot
            // as "real seed vs Qualifier/LL" and api-tennis now confirms a
            // specific qualifier — we DO allow filling in the missing player.
            // Only fills NULLs / TBD; never overwrites a real player.
            $tbdId = Player::where('name', 'TBD')->value('id');
            $playerFillAttrs = [
                'player1_id'   => $player1?->id,
                'player2_id'   => $player2?->id,
                'round'        => $round,
                'tournament_id'=> $tournament->id,
            ];

            // ─── bracket.tennis is the source of truth for bracket structure ───
            // api-tennis only ENRICHES existing matches (scores, winners, status).
            // We never let it create new slots or move bracket_position, because:
            //   - api-tennis sometimes returns extra fixtures (exhibition matches,
            //     mixed doubles bleeding into the singles feed, etc.) which would
            //     create ghost slots that desorganize the visual bracket.
            //   - surname-based matching is fragile across name spellings, so a
            //     misspelled player would land in the wrong slot.
            //
            // Match lookup order:
            //   1. Exact match by api_event_key (already-synced fixture).
            //   2. A bracket.tennis bootstrap slot or placeholder where at least
            //      one player matches → upgrade that slot.
            //   3. If neither matches, SKIP this fixture entirely — we don't
            //      create a new slot.
            $existing = TennisMatch::where('api_event_key', (string) $eventKey)->first();

            if (!$existing) {
                $candidates = TennisMatch::where('tournament_id', $tournament->id)
                    ->where('round', $round)
                    ->where(function ($q) {
                        $q->where('api_event_key', 'LIKE', 'placeholder-%')
                          ->orWhere('api_event_key', 'LIKE', 'bt-bootstrap-%');
                    })
                    ->orderBy('bracket_position')
                    ->get();

                // Pass 1: candidate where at least one player matches. Handles
                // the qualifier case (real seed vs TBD on bracket.tennis, then
                // api-tennis fills in the qualifier later) AND the case where
                // a winner from a previous round already populated one side.
                foreach ($candidates as $c) {
                    $matchesP1 = $player1 && ($c->player1_id === $player1->id || $c->player2_id === $player1->id);
                    $matchesP2 = $player2 && ($c->player1_id === $player2->id || $c->player2_id === $player2->id);
                    if ($matchesP1 || $matchesP2) {
                        $existing = $c;
                        break;
                    }
                }

                // Pass 2: synthetic 'placeholder-%' slot with TBD-vs-TBD. These
                // are the rows ensureBracketPlaceholders() seeded for later
                // rounds (R64+ when starting from R128, R32+ when starting
                // from R64). Player matching can't work because both sides are
                // TBD, so we fill them in bracket_position order.
                //
                // Restricted to 'placeholder-%' on purpose: never overwrite a
                // 'bt-bootstrap-%' slot from bracket.tennis with a mismatched
                // fixture — bracket.tennis is the structural authority and a
                // mismatched player would scramble the bracket again.
                // Pass 2 (synthetic TBD-vs-TBD slots) is deliberately
                // SKIPPED here. It used to grab the first TBD-vs-TBD slot
                // in bracket_position order, which produced the Khachanov-
                // vs-Trungelliti-in-R64-pos=1 bug: api-tennis publishes
                // future-round fixtures with both players named (because
                // they're already scheduled with a date+time), but we have
                // no way to know which structural slot they belong to —
                // it depends on who wins the upstream R128 matches. The
                // ONLY safe source for R64+ players is the winner
                // propagation that runs after the upstream R128 finishes.
                // For R128 the bootstrap fills players directly from
                // bracket.tennis, so this fallback isn't needed there
                // either.
                //
                // No "first non-empty free slot" fallback for non-TBD
                // candidates: overwriting a bracket.tennis row whose
                // players don't match would scramble the bracket.
            }

            if (!$existing) {
                // Skip orphan fixtures rather than inventing slots. The user-visible
                // bracket structure stays anchored to bracket.tennis.
                $skippedQualy++; // reuse counter to avoid adding a new return key
                continue;
            }

            $wasFinished = $existing->status === 'finished';
            $isPlaceholder = str_starts_with($existing->api_event_key ?? '', 'placeholder-')
                || str_starts_with($existing->api_event_key ?? '', 'bt-bootstrap-');

            // Build the actual update payload. Start with results-only fields.
            $updateAttrs = $resultAttrs;

            // Adopt the real api_event_key when upgrading a placeholder slot,
            // so future syncs match by event_key instead of falling back to
            // player matching.
            if ($isPlaceholder) {
                $updateAttrs['api_event_key'] = (string) $eventKey;
            }

            // PLAYER FILL — api-tennis NEVER changes players. The only
            // exception is filling a TBD placeholder in R128 (or whatever
            // the starting round is): those are slots bracket.tennis
            // published as "Qualifier/LL/TBD" because they weren't
            // confirmed yet at scrape time. Once the slot has a real
            // player from BT, it stays untouched here.
            //
            // For R64 and beyond: TBD slots are NEVER filled from
            // api-tennis fixtures, because we can't trust the position
            // mapping. api-tennis sometimes publishes a future-round
            // fixture (e.g. Khachanov vs Trungelliti for R64) and the
            // fallback "first TBD-vs-TBD slot" matcher would drop those
            // players in the WRONG bracket_position (pos=1 instead of
            // pos=29). Players for R64+ slots come ONLY from the
            // structural winner propagation that runs after a R128 match
            // is marked finished — see inferUnreportedWalkovers and the
            // score-driven advancement code.
            //
            // All other player changes (Lucky Loser substitutions, late
            // withdrawals, name corrections) come from bracket.tennis via
            // bootstrapFromBracketTennis(). Single source of truth = no
            // possibility of api-tennis and BT showing different players
            // in the same slot.
            // Only fill TBDs in rows created by the bootstrap (starting
            // round = R128 for full draws, R64 for 56-draws, etc.). Rows
            // created by ensureBracketPlaceholders for later rounds keep
            // their TBDs until the winner propagation fills them.
            $cameFromBootstrap = str_starts_with($existing->api_event_key ?? '', 'bt-bootstrap-');
            if ($cameFromBootstrap) {
                if ($tbdId && $existing->player1_id === $tbdId && $player1) {
                    $updateAttrs['player1_id'] = $player1->id;
                }
                if ($tbdId && $existing->player2_id === $tbdId && $player2) {
                    $updateAttrs['player2_id'] = $player2->id;
                }
            }

            $existing->update($updateAttrs);
            if (!$wasFinished && $status === 'finished') $newlyFinished[] = $existing->id;
            $updated++;
        }

        $scored = 0;
        if (!empty($newlyFinished)) {
            $scored = BracketPredictionController::scoreTournament($tournament);
        }

        // Detect walkovers the API didn't report cleanly. If a match is still
        // pending with score="0-0" or null while the next-round match it feeds
        // into already has a real winner, the winner of the pending match must
        // be whoever shows up in the next round.
        $this->inferUnreportedWalkovers($tournament);

        // Bracket structure is owned by the bootstrap (bracket.tennis) and
        // the one-shot `tennis:repair-bracket-positions` admin command. The
        // live sync deliberately does NOT call reorderBracketFromScraper
        // anymore — it was undoing the canonical order on every run by
        // re-mapping rows to whatever (often wrong) positions its fuzzy
        // matcher produced. See the Roland Garros 2026 incident: Sinner
        // would land on pos=22 instead of pos=1 every 15 minutes.
        //
        // For tournaments without a tennisexplorer_slug (rare path) we still
        // need to derive positions from player progression — there's no
        // canonical source to anchor against, so the inference fallback
        // remains.
        if (!$tournament->tennisexplorer_slug) {
            $this->rebuildBracketPositions($tournament);
        }

        // Apply any manual badge overrides on top of what BT gave us. This
        // covers cases where the official tournament site shows a badge that
        // bracket.tennis left out (e.g. RG 2026 showing T. Rakotomanga as WC
        // while BT left her badge blank).
        $this->applySeedOverrides($tournament);

        // Repair user predictions that got out of sync with the (now updated)
        // bracket. Two things this fixes automatically:
        //   - "Qualifier / LL" picks get promoted to the real player once BT
        //     confirms them.
        //   - Picks that were anchored to a position get migrated when the
        //     scraper reorders the bracket (Roland Garros 2026 incident:
        //     ~half of one user's R128 picks moved one slot when the
        //     scraped order replaced the bootstrap order).
        app(PredictionRealigner::class)->realign($tournament);

        // Fill in placeholder matches for later rounds so the bracket renders
        // complete (Quarter-finals / Semis / Final) even before the API
        // publishes those fixtures.
        $placeholders = $this->ensureBracketPlaceholders($tournament);

        // Backfill start/end dates from the main-draw fixtures, since the
        // tournament catalog endpoint doesn't expose dates. Status follows
        // start/end relative to today. We only consider REAL matches (not the
        // far-future placeholders or bootstrap rows) so the dates reflect
        // actual scheduled play.
        $dates = $tournament->matches()
            ->whereNotNull('scheduled_at')
            ->where(function ($q) {
                $q->whereNull('api_event_key')
                  ->orWhere(function ($q2) {
                      $q2->where('api_event_key', 'NOT LIKE', 'placeholder-%')
                         ->where('api_event_key', 'NOT LIKE', 'bt-bootstrap-%');
                  });
            })
            ->selectRaw('MIN(scheduled_at) as first_at, MAX(scheduled_at) as last_at')
            ->first();

        $update = ['last_synced_at' => now()];
        if ($dates && $dates->first_at) {
            $startDate = Carbon::parse($dates->first_at);

            // Preserve the existing start_date if it already carries an hour
            // component AND lands on the same calendar day: that means
            // refreshTournamentStartDate() ran earlier in this sync and
            // wrote a precise first-match time we don't want to drop back
            // to 00:00. We only overwrite if the calendar day differs
            // (which happens when the schedule moves earlier/later).
            $existingStart = $tournament->start_date;
            $sameDay = $existingStart && $existingStart->isSameDay($startDate);
            $existingHasTime = $existingStart
                && ($existingStart->hour !== 0 || $existingStart->minute !== 0);
            if (!$sameDay || !$existingHasTime) {
                $update['start_date'] = $startDate;
            }

            // Extend end_date to match the tournament's NATURAL duration.
            // The observed last_at only covers rounds already scheduled; the
            // final usually plays a few more days later. Cap by the tier:
            //   Grand Slam = 14 days, Masters 1000 = 12 days, others = 7 days.
            $observedEnd = Carbon::parse($dates->last_at ?? $dates->first_at);
            $naturalDays = str_contains($tournament->type, 'Grand Slam') ? 14
                : (str_contains($tournament->type, 'Masters 1000') || str_contains($tournament->type, 'WTA 1000') ? 12 : 7);
            $naturalEnd = $startDate->copy()->addDays($naturalDays);
            $update['end_date'] = $observedEnd->gt($naturalEnd)
                ? $observedEnd->toDateString()
                : $naturalEnd->toDateString();
        }
        if (isset($update['end_date'])) {
            $effectiveStart = $update['start_date'] ?? $tournament->start_date;
            if ($effectiveStart) {
                $update['status'] = $this->statusFromDates(
                    Carbon::parse($effectiveStart),
                    Carbon::parse($update['end_date']),
                );
            }
        }
        $tournament->update($update);

        return [
            'fixtures'    => count($resp['result']),
            'main_draw'   => $updated,
            'qualy'       => $skippedQualy,
            'finished'    => count($newlyFinished),
            'scored'      => $scored,
            'placeholders'=> $placeholders,
        ];
    }

    /**
     * Reorder bracket_position using bracket.tennis as source of truth.
     *
     * bracket.tennis publishes the official R128 draw with `data-match-id`
     * slots (round-slot pairs). From the 64 R128 matches we propagate the
     * full tree deterministically: R64 slot N = ceil(R128 slot 2N or 2N+1 / 2).
     *
     * Strategy:
     *   1. Scrape R128 slot map: surname → slot (each player has exactly 1 slot)
     *   2. For each match in DB starting at R128, find each player's slot.
     *      Pair up: a match between players in slots 2K and 2K+1 of R128
     *      sits at R128 slot K (the lower of the two).
     *      For R64 and beyond, a match is positioned at slot K of round R
     *      where K = floor(min_player_slot_in_prev_round / 2).
     *
     * Falls back to the player-progression inference if the scrape fails.
     */

    /**
     * Pick the earliest main-draw fixture from api-tennis and write its
     * date+time to tournament.start_date. This is the source of truth for
     * "when does the tournament start" — and by extension for the
     * predictions deadline shown in the UI ("Cierra: HH:MM").
     *
     * api-tennis publishes event_time (HH:MM, local tournament time) for
     * day-1 matches 24-72h before the tournament starts. Until then, we keep
     * whatever start_date the catalog sync wrote (date with 00:00 time).
     *
     * Idempotent: re-runs every sync; only writes when the derived value
     * actually changes.
     */
    private function refreshTournamentStartDate(Tournament $tournament, array $mainDrawFixtures): void
    {
        if (empty($mainDrawFixtures)) return;

        // Find the earliest fixture that has both a date AND a non-empty
        // time. Fixtures with only a date and no time get filtered out — we
        // only commit a start_date update when api-tennis is actually giving
        // us hour-level precision.
        $earliest = null;
        foreach ($mainDrawFixtures as $f) {
            $date = trim((string) ($f['event_date'] ?? ''));
            $time = trim((string) ($f['event_time'] ?? ''));
            if ($date === '' || $time === '') continue;
            $combined = $date . ' ' . $time;
            if ($earliest === null || strcmp($combined, $earliest) < 0) {
                $earliest = $combined;
            }
        }

        if ($earliest === null) return;

        // api-tennis returns event_time as a wall-clock in the tournament's
        // local timezone (e.g. "11:00" for Roland Garros means 11:00 Paris).
        // We convert to UTC using the tournament's timezone column so that
        // when the frontend renders ->bogota() it lands at the right hour.
        // Tournaments default to America/Bogota if no venue timezone is set,
        // which is correct for the no-conversion case.
        $tz = $tournament->timezone ?: 'America/Bogota';
        try {
            $newStart = \Carbon\Carbon::parse($earliest, $tz)->setTimezone('UTC');
        } catch (\Throwable $e) {
            return;
        }

        if ($tournament->start_date && $tournament->start_date->equalTo($newStart)) {
            return;
        }

        $oldStart = $tournament->start_date;
        $tournament->update(['start_date' => $newStart]);

        // Cascade the change to bootstrap rows that were anchored to the
        // OLD start_date. We deliberately don't touch rows with their own
        // api_event_key from api-tennis — those carry per-match times that
        // are already correct.
        if ($oldStart) {
            $tournament->matches()
                ->where('scheduled_at', $oldStart)
                ->where('api_event_key', 'like', 'bt-bootstrap-%')
                ->update(['scheduled_at' => $newStart]);
        }
    }

    /**
     * Apply any (tournament_id, player_id, badge) overrides on top of the
     * seeds the scraper assigned. Used when bracket.tennis omits a badge
     * the official tournament site confirms (WC, Q, LL, PR, SE) or to
     * correct a wrong seed.
     */
    private function applySeedOverrides(Tournament $tournament): void
    {
        $overrides = \App\Models\PlayerSeedOverride::where('tournament_id', $tournament->id)->get();
        if ($overrides->isEmpty()) return;

        foreach ($overrides as $ov) {
            $matches = $tournament->matches()
                ->where(function ($q) use ($ov) {
                    $q->where('player1_id', $ov->player_id)
                      ->orWhere('player2_id', $ov->player_id);
                })
                ->get();

            foreach ($matches as $m) {
                $updates = [];
                if ($m->player1_id === $ov->player_id && $m->player1_seed !== $ov->badge) {
                    $updates['player1_seed'] = $ov->badge;
                }
                if ($m->player2_id === $ov->player_id && $m->player2_seed !== $ov->badge) {
                    $updates['player2_seed'] = $ov->badge;
                }
                if ($updates) $m->update($updates);
            }
        }
    }

    private function reorderBracketFromScraper(Tournament $tournament): void
    {
        $slug = $tournament->tennisexplorer_slug;
        if (!$slug) {
            $this->rebuildBracketPositions($tournament);
            return;
        }

        // Slug format: "rome/2026/atp-men" → bracket.tennis tournament + tour
        // For bracket.tennis it's "<tournament-slug>/<tour>", we accept both
        // formats and derive tour automatically from the tournament type.
        [$btSlug, $btTour] = $this->parseBracketTennisSlug($slug, $tournament);

        $draw = $this->scraper->draw($btSlug, $btTour);
        if (empty($draw)) {
            Log::warning('bracket.tennis returned empty draw — falling back to inference', [
                'tournament' => $tournament->slug,
                'bt_slug'    => $btSlug,
                'bt_tour'    => $btTour,
            ]);
            $this->rebuildBracketPositions($tournament);
            return;
        }

        // 1) Index every player BT showed in R128 by (surname, full-first-name-from-BT).
        //    Later we'll look up DB players by surname and compare the DB's
        //    first-name prefix against the BT entries' full first names —
        //    this handles all three problem cases uniformly:
        //      - "F. Cerundolo"  (DB prefix "f")    matches "Francisco"  not "Juan"
        //      - "J.M. Cerundolo" (DB prefix "j")   matches "Juan Manuel"
        //      - "Xin. Wang"     (DB prefix "xin")  matches "Xinyu"      not "Xiyu"
        //      - "Xiy. Wang"     (DB prefix "xiy")  matches "Xiyu"       not "Xinyu"
        //    Key is (surname → list of [first_name_lower, seed, country, slot]).
        $btIndex = [];
        foreach ($draw as $entry) {
            foreach (['p1', 'p2'] as $side) {
                $name = $entry[$side];
                if (!$name || strcasecmp($name, 'Bye') === 0) continue;
                $surname = BracketTennisScraper::surnameKey($name);
                $first   = $this->firstNamePrefix($name);
                if ($surname === '' || $first === '') continue;
                $btIndex[$surname][] = [
                    'first'   => $first,
                    'seed'    => $entry[$side . '_seed'] ?? null,
                    'country' => $entry[$side . '_country'] ?? null,
                    'slot'    => ($entry['slot'] * 2) + ($side === 'p2' ? 1 : 0),
                ];
            }
        }

        // Find the BT row that matches a DB player name. Compares the DB's
        // first-name prefix against each BT entry sharing the surname: the
        // BT first name must start with the DB prefix (e.g. "francisco"
        // starts with "f", "xinyu" starts with "xin"). If exactly one matches,
        // return it; ambiguous matches return null so we don't apply the
        // wrong seed.
        $resolveBtRow = function (string $dbName) use (&$btIndex): ?array {
            $surname = BracketTennisScraper::surnameKey($dbName);
            if ($surname === '' || !isset($btIndex[$surname])) return null;
            $dbPrefix = $this->firstNamePrefix($dbName);
            if ($dbPrefix === '') return null;
            $hits = array_values(array_filter(
                $btIndex[$surname],
                fn($row) => str_starts_with($row['first'], $dbPrefix),
            ));
            return count($hits) === 1 ? $hits[0] : null;
        };

        // Apply seeds to every match (winners keep their seed throughout the
        // bracket). Also patch missing player country/flag from bracket.tennis
        // when the api-tennis data didn't provide it (e.g. Townsend, Kessler).
        //
        // CRITICAL: when the scraper showed this player in a R128 match but
        // WITHOUT a seed, we also CLEAR any stale seed the DB has — that
        // way a seed accidentally inherited from a sibling (or from a previous
        // tournament) gets wiped on every sync, not just additively merged.
        foreach ($tournament->matches()->with(['player1', 'player2'])->get() as $m) {
            $updates = [];
            foreach (['player1' => 'player1_seed', 'player2' => 'player2_seed'] as $rel => $col) {
                $p = $m->{$rel};
                if (!$p || $p->name === 'TBD') continue;
                $row = $resolveBtRow($p->name);
                if ($row === null) continue;

                if ($row['seed'] !== null) {
                    // Scraper has a seed for this exact player → apply it.
                    if ($m->$col !== $row['seed']) {
                        $updates[$col] = $row['seed'];
                    }
                } elseif ($m->round === 'R128' && $m->$col !== null) {
                    // Scraper saw this player in R128 but WITHOUT a seed →
                    // wipe any stale seed in our DB (sibling confusion, etc.).
                    $updates[$col] = null;
                }

                // Backfill country from BT if missing or "Unknown"
                if ($row['country'] && (!$p->nationality_code || $p->country === 'Unknown' || $p->iso2 === 'un')) {
                    $iso3 = strtoupper($row['country']);
                    $p->update(['nationality_code' => $iso3, 'country' => $p->country === 'Unknown' || !$p->country ? $iso3 : $p->country]);
                }
            }
            if ($updates) $m->update($updates);
        }

        // For seeded players who got a bye in R128 (no real match there), make
        // sure their seed gets exposed on the BYE-status row so views fall
        // back correctly. We walk the BT index and look up each (surname,
        // first-name) pair against the DB.
        foreach ($btIndex as $surname => $rows) {
            foreach ($rows as $row) {
                if (!is_numeric($row['seed'])) continue; // Q/WC/LL handled per-match
                // Find the unique DB player matching this BT entry.
                $candidates = Player::where('name', 'like', '%' . substr($surname, 0, 4) . '%')
                    ->get()
                    ->filter(fn($p) => BracketTennisScraper::surnameKey($p->name) === $surname);
                $player = $candidates->first(function ($p) use ($row) {
                    $pref = $this->firstNamePrefix($p->name);
                    return $pref !== '' && str_starts_with($row['first'], $pref);
                });
                if (!$player) continue;

                $hasR128 = $tournament->matches()
                    ->where('round', 'R128')
                    ->where(function ($q) use ($player) {
                        $q->where('player1_id', $player->id)
                          ->orWhere('player2_id', $player->id);
                    })
                    ->exists();
                if (!$hasR128) {
                    $tournament->matches()
                        ->where('status', 'bye')
                        ->where('player1_id', $player->id)
                        ->update(['player1_seed' => $row['seed']]);
                }
            }
        }

        // 2) Place R128 matches at their canonical bracket.tennis slot, then
        //    derive every following round STRUCTURALLY (match at pos K of round
        //    R is fed by positions 2K-1 and 2K of round R-1). This is purely
        //    tree-arithmetic — we never re-derive positions from player names
        //    after R128, so once the bracket is laid out it stays put across
        //    syncs even when retirees / walkovers / partial fixtures appear.
        $rounds = ['R128', 'R64', 'R32', 'R16', 'QF', 'SF', 'F'];

        // ─── R128: anchor positions from the scraped slot map ───────────────
        // We respect existing positions when the players match the scraper's
        // expected slot — that way a sync doesn't overwrite a valid position
        // with one inferred from possibly-stale data.
        $r128 = $tournament->matches()
            ->where('round', 'R128')
            ->with(['player1', 'player2'])
            ->get();

        if ($r128->isNotEmpty()) {
            $used = [];
            $unmatched = [];

            foreach ($r128 as $m) {
                $slot = null;
                foreach ([$m->player1?->name, $m->player2?->name] as $name) {
                    if (!$name) continue;
                    $key = BracketTennisScraper::surnameKey($name);
                    if ($key && isset($surnameToFirstSlot[$key])) {
                        // bracket_position is 1-indexed match number (1..64);
                        // surnameToFirstSlot returns the *expanded* slot of the
                        // player (0..127), so we floor-divide by 2 to map back
                        // to the match number, then +1 to 1-index.
                        $slot = intdiv($surnameToFirstSlot[$key], 2) + 1;
                        break;
                    }
                }
                if ($slot === null) {
                    $unmatched[] = $m;
                    continue;
                }
                // If another match already claimed this slot, keep the first
                // and queue the second to fill a hole later. (Should be rare.)
                if (isset($used[$slot])) {
                    $unmatched[] = $m;
                    continue;
                }
                $used[$slot] = $m->id;
                if ($m->bracket_position !== $slot) {
                    $m->update(['bracket_position' => $slot]);
                }
            }

            // Place unmatched / collided matches in the still-free slots so
            // the tree stays contiguous (1..64). We sort by current position
            // first so existing layouts are disturbed as little as possible.
            $unmatched = collect($unmatched)->sortBy('bracket_position')->values();
            $next = 1;
            foreach ($unmatched as $m) {
                while (isset($used[$next]) && $next <= 64) $next++;
                $used[$next] = $m->id;
                if ($m->bracket_position !== $next) {
                    $m->update(['bracket_position' => $next]);
                }
                $next++;
            }
        }

        // ─── R64 and beyond: derive positions purely from the previous round ─
        // Match at position K of round R is fed by positions 2K-1 and 2K of
        // round R-1. We figure out which player came from which feeder pair,
        // and assign each round-R match accordingly. This eliminates the
        // surname-propagation drift that used to shuffle the bracket every sync.
        for ($i = 1; $i < count($rounds); $i++) {
            $round     = $rounds[$i];
            $prevRound = $rounds[$i - 1];

            $current = $tournament->matches()
                ->where('round', $round)
                ->with(['player1', 'player2'])
                ->get();
            if ($current->isEmpty()) continue;

            $prev = $tournament->matches()
                ->where('round', $prevRound)
                ->with(['player1', 'player2', 'winner'])
                ->get()
                ->keyBy('bracket_position');

            $expectedTotal = match ($round) {
                'R64' => 32, 'R32' => 16, 'R16' => 8,
                'QF'  => 4,  'SF'  => 2,  'F'   => 1,
                default => $current->count(),
            };

            // For each current-round match, find the highest-information feeder
            // pair (any of its players already won a prev-round match). The
            // match takes position ceil(feeder_pos / 2).
            $assignments = [];   // bracket_position => $match
            $unresolved  = [];   // matches we couldn't place by feeder lookup

            foreach ($current as $m) {
                $playerIds = array_filter([$m->player1_id, $m->player2_id]);
                $bestFeederPos = null;
                foreach ($prev as $pos => $pm) {
                    if (in_array($pm->winner_id, $playerIds, true)
                        || in_array($pm->player1_id, $playerIds, true)
                        || in_array($pm->player2_id, $playerIds, true)) {
                        $bestFeederPos = $bestFeederPos === null ? $pos : min($bestFeederPos, $pos);
                    }
                }
                if ($bestFeederPos === null) {
                    $unresolved[] = $m;
                    continue;
                }
                $slot = intdiv($bestFeederPos + 1, 2); // ceil(feederPos / 2)
                // Collision: keep the one with the lower id (older row).
                if (isset($assignments[$slot])) {
                    if ($assignments[$slot]->id < $m->id) {
                        $unresolved[] = $m;
                        continue;
                    }
                    $unresolved[] = $assignments[$slot];
                }
                $assignments[$slot] = $m;
            }

            // Write the resolved positions.
            $used = [];
            foreach ($assignments as $slot => $m) {
                $used[$slot] = true;
                if ($m->bracket_position !== $slot) {
                    $m->update(['bracket_position' => $slot]);
                }
            }

            // Fill the remaining slots with unresolved matches in their
            // existing-position order — minimises movement when we have a
            // partial bracket and the structural lookup couldn't help.
            $unresolved = collect($unresolved)->sortBy('bracket_position')->values();
            $next = 1;
            foreach ($unresolved as $m) {
                while (isset($used[$next]) && $next <= $expectedTotal) $next++;
                $used[$next] = true;
                if ($m->bracket_position !== $next) {
                    $m->update(['bracket_position' => $next]);
                }
                $next++;
            }
        }
    }

    /**
     * Resolve the bracket.tennis URL components from a tournament's stored
     * slug. We accept two formats for backwards compatibility:
     *   - "rome/2026/atp-men"      (legacy Tennis Explorer format)
     *   - "internazionali-bnl-d-italia-2026|atp" (bracket.tennis-native)
     * Returns [slug, tour].
     */
    private function parseBracketTennisSlug(string $stored, Tournament $tournament): array
    {
        // Bracket.tennis native format
        if (str_contains($stored, '|')) {
            [$slug, $tour] = explode('|', $stored, 2);
            return [$slug, $tour];
        }

        // Otherwise derive tour from tournament type
        $tour = str_starts_with($tournament->type, 'WTA') ? 'wta' : 'atp';
        return [$stored, $tour];
    }

    /**
     * Recompute bracket_position so the visualization renders a clean tree.
     *
     * Strategy: walk forward, but for each round we GUARANTEE the invariant
     * "R_n match at position K is fed by R_{n-1} matches at positions 2K-1
     * and 2K". We achieve this by:
     *
     *   1. First round: number chronologically (1..N).
     *   2. For each next round: find the feeders (prev-round matches whose
     *      winner appears in this match) and ASSIGN this match to position
     *      ceil(min(feeder)/2). If two next-round matches collide on the same
     *      slot, the second one is the BYE side — we shift the colliding
     *      feeder's siblings instead of shifting this match up.
     *   3. Byes (players entering without a previous match): we INSERT a
     *      virtual feeder slot in the previous round so the tree stays binary.
     *      The previous round can have "gaps" in bracket_position, which the
     *      visualization handles as empty/bye boxes.
     *
     * Net effect: the final positions form a binary tree where every position
     * K in round R is fed by positions 2K-1 and 2K in round R-1, which is
     * exactly what the bracket renderer expects.
     */
    private function rebuildBracketPositions(Tournament $tournament): void
    {
        $rounds = ['R128', 'R64', 'R32', 'R16', 'QF', 'SF', 'F'];

        // Find the first round with at least one match
        $firstRound = null;
        foreach ($rounds as $r) {
            if ($tournament->matches()->where('round', $r)->exists()) {
                $firstRound = $r;
                break;
            }
        }
        if (!$firstRound) return;
        $startIdx = array_search($firstRound, $rounds, true);

        // 1) First round: assign chronological positions 1..N
        $firstMatches = $tournament->matches()
            ->where('round', $firstRound)
            ->orderBy('scheduled_at')
            ->orderBy('id')
            ->get();
        $pos = 0;
        $playerToPrevPos = []; // player_id => position in prev round
        foreach ($firstMatches as $m) {
            $pos++;
            $m->update(['bracket_position' => $pos]);
            if ($m->player1_id && $m->player1_id !== 1057) $playerToPrevPos[$m->player1_id] = $pos;
            if ($m->player2_id && $m->player2_id !== 1057) $playerToPrevPos[$m->player2_id] = $pos;
        }
        $prevRoundSize = $firstMatches->count();

        // 2) Walk forward, anchoring each round to the previous one
        for ($i = $startIdx + 1; $i < count($rounds); $i++) {
            $round = $rounds[$i];
            $matches = $tournament->matches()
                ->where('round', $round)
                ->orderBy('scheduled_at')
                ->orderBy('id')
                ->get();
            if ($matches->isEmpty()) continue;

            // For each match, determine its target slot.
            // Anchor = position of the feeder in prev round (the one that's
            // smaller, i.e. the odd-side feeder 2K-1).
            $matchAnchor = []; // match_id => prev-round position (smallest feeder)
            $unanchored  = []; // matches whose players didn't play in prev round (byes)
            foreach ($matches as $m) {
                $feederPositions = [];
                foreach ([$m->player1_id, $m->player2_id] as $pid) {
                    if (!$pid || $pid === 1057) continue;
                    if (isset($playerToPrevPos[$pid])) {
                        $feederPositions[] = $playerToPrevPos[$pid];
                    }
                }
                if (!empty($feederPositions)) {
                    $matchAnchor[$m->id] = min($feederPositions);
                } else {
                    $unanchored[] = $m;
                }
            }

            // Pad prevRoundSize so byes get virtual slots. A round naturally
            // has 2 * (next-round size) feeder slots. If we have fewer real
            // matches, the missing slots are byes.
            $expectedSize = max($prevRoundSize, $matches->count() * 2);

            // Decide each match's slot in the new round.
            // Slot K in this round corresponds to feeder pair (2K-1, 2K) in prev round.
            // So matchAnchor[m] = P → slot = ceil(P/2) = intdiv(P+1, 2).
            $slotByMatch = [];
            $usedSlots = [];
            foreach ($matchAnchor as $mid => $anchor) {
                $slot = intdiv($anchor + 1, 2);
                $slotByMatch[$mid] = $slot;
                $usedSlots[$slot] = true;
            }

            // Place unanchored matches (byes — players appearing in this round
            // for the first time) into still-free slots, preferring lower ones.
            $totalSlots = max(intdiv($expectedSize, 2), $matches->count());
            $next = 1;
            foreach ($unanchored as $m) {
                while (isset($usedSlots[$next])) $next++;
                $slotByMatch[$m->id] = $next;
                $usedSlots[$next] = true;
                $next++;
            }

            // Resolve collisions: two matches anchored to the same slot.
            // Push later (chronologically) match into the next free slot.
            $finalSlots = [];
            $taken = [];
            // Sort by current slot, then by chronological order, so earlier
            // matches keep their preferred slot.
            $matchesById = $matches->keyBy('id');
            $orderedIds = collect($slotByMatch)
                ->sortBy(fn($s, $id) => $s * 1000 + (int) $matchesById[$id]->id)
                ->keys()->all();
            foreach ($orderedIds as $mid) {
                $slot = $slotByMatch[$mid];
                while (isset($taken[$slot])) $slot++;
                $finalSlots[$mid] = $slot;
                $taken[$slot] = true;
            }

            // Persist and rebuild playerToPrevPos for the next iteration.
            $newPlayerToPrev = [];
            foreach ($matches as $m) {
                $newPos = $finalSlots[$m->id] ?? 0;
                $m->update(['bracket_position' => $newPos]);
                if ($m->player1_id && $m->player1_id !== 1057) $newPlayerToPrev[$m->player1_id] = $newPos;
                if ($m->player2_id && $m->player2_id !== 1057) $newPlayerToPrev[$m->player2_id] = $newPos;
            }
            $playerToPrevPos = $newPlayerToPrev;
            $prevRoundSize = $totalSlots;
        }
    }

    /**
     * After syncing real fixtures, fill in TBD placeholder matches for later
     * rounds so the bracket renders all the remaining columns.
     *
     * We DON'T overwrite earlier rounds that may simply have fewer matches
     * because of byes (e.g. a 96-draw plays only 32 R128 matches — the other
     * 32 seeds enter at R64). Instead we count how many slots each round
     * naturally needs based on the matches already present in the immediately
     * preceding round (winners advance), and create TBDs for the diff.
     *
     * Returns the number of placeholders newly created.
     */
    private function ensureBracketPlaceholders(Tournament $tournament): int
    {
        $tbd = Player::where('name', 'TBD')->first();
        if (!$tbd) return 0;

        // Wipe any existing placeholders first — they'll be re-created below
        // matching the current state. This avoids accumulation across syncs.
        $tournament->matches()
            ->where('api_event_key', 'LIKE', 'placeholder-%')
            ->delete();

        $rounds = ['R128', 'R64', 'R32', 'R16', 'QF', 'SF', 'F'];
        $halvedSlots = [
            'R128' => 64, 'R64' => 32, 'R32' => 16, 'R16' => 8,
            'QF'   => 4,  'SF'  => 2,  'F'   => 1,
        ];

        // Count real matches per round (placeholders just got deleted above).
        $countsByRound = [];
        foreach ($rounds as $r) {
            $countsByRound[$r] = $tournament->matches()->where('round', $r)->count();
        }

        // The "starting round" is the earliest one with at least one real match.
        $firstWithData = null;
        foreach ($rounds as $r) {
            if ($countsByRound[$r] > 0) { $firstWithData = $r; break; }
        }
        if (!$firstWithData) return 0;

        // For rounds at or AFTER firstWithData: expected = (matches in prev round) / 2,
        // capped by the natural halvedSlots. For firstWithData itself we trust the
        // real count (byes mean the bracket may be smaller than max).
        $created = 0;
        $expectedForRound = [];
        $prevCount = $countsByRound[$firstWithData];

        // For the first-with-data round, expected matches = existing real count
        // (byes don't fill in; that's the natural starting size).
        $expectedForRound[$firstWithData] = $prevCount;

        $startIdx = array_search($firstWithData, $rounds, true);
        for ($i = $startIdx + 1; $i < count($rounds); $i++) {
            $round = $rounds[$i];
            // Each subsequent round halves the previous, but a 96-draw means
            // the second round adds back the byed seeds → so use the larger of
            // halvedSlots[$round] when first round was small.
            $half = intdiv($expectedForRound[$rounds[$i - 1]], 2);
            // If R128 has 32 matches (96-draw), R64 should have 32 + 32 byes = 32 matches.
            // halvedSlots[R64] = 32, so we take the max.
            $expected = max($half, $countsByRound[$round]);
            // Cap at the round's natural maximum (avoid huge brackets).
            $expected = min($expected, $halvedSlots[$round]);
            $expectedForRound[$round] = $expected;

            $missing = max(0, $expected - $countsByRound[$round]);
            if ($missing === 0) continue;

            $usedPositions = $tournament->matches()
                ->where('round', $round)
                ->pluck('bracket_position')
                ->all();
            $position = 0;
            for ($p = 0; $p < $missing; $p++) {
                while (in_array(++$position, $usedPositions, true)) {
                    // skip
                }
                // Use updateOrCreate so stale placeholders from a previous run
                // that survived the delete (e.g. race with the live-sync cron)
                // get overwritten instead of triggering a unique-constraint error.
                TennisMatch::updateOrCreate(
                    ['api_event_key' => 'placeholder-' . $round . '-' . $position . '-' . $tournament->id],
                    [
                        'tournament_id'    => $tournament->id,
                        'player1_id'       => $tbd->id,
                        'player2_id'       => $tbd->id,
                        'round'            => $round,
                        'bracket_position' => $position,
                        'status'           => 'pending',
                        'scheduled_at'     => now()->addYears(1),
                    ]
                );
                $created++;
            }
        }

        return $created;
    }

    private function statusFromDates(Carbon $start, Carbon $end): string
    {
        $now = now()->startOfDay();
        if ($now->lt($start)) return 'upcoming';
        if ($now->gt($end))   return 'finished';
        return 'in_progress';
    }

    /**
     * Bootstrap (and continuously refresh) the bracket from bracket.tennis.
     * bracket.tennis is the source of truth for structure + players —
     * including qualifier confirmations that replace TBD placeholders as the
     * tournament progresses. api-tennis only contributes results on top.
     *
     * Idempotent: re-running fills TBD slots with newly-confirmed players but
     * never overwrites an existing real player or a finished result.
     *
     * Returns the number of matches created OR updated.
     */
    private function bootstrapFromBracketTennis(Tournament $tournament): int
    {
        if (!$tournament->tennisexplorer_slug) return 0;
        [$btSlug, $btTour] = $this->parseBracketTennisSlug($tournament->tennisexplorer_slug, $tournament);

        $draw = $this->scraper->draw($btSlug, $btTour);
        if (empty($draw)) return 0;

        // Detect the bracket size from how many entries the scraper returned.
        // bracket.tennis emits one entry per first-round MATCH (not per player),
        // so:
        //   64 entries → R128 (full Grand Slam draw of 128 players)
        //   32 entries → R64  (56-draw tournament like Dubai/Qatar/Monte-Carlo,
        //                      with 8 BYEs taking the top seeds straight to R32)
        //   16 entries → R32  (ATP 500 tournaments)
        //    8 entries → R16  (small fields)
        // This used to hardcode 'R128' which left e.g. Dubai with 32 matches in
        // R128 while api-tennis put the real fixtures in R64 — the two never
        // matched up and the bracket rendered scrambled.
        $entryCount = count($draw);
        $startRound = match (true) {
            $entryCount > 32 => 'R128',
            $entryCount > 16 => 'R64',
            $entryCount > 8  => 'R32',
            $entryCount > 4  => 'R16',
            default          => 'QF',
        };

        $tbd = Player::where('name', 'TBD')->first();
        if (!$tbd) return 0;

        // Any player whose name reads like a placeholder ("Qualifier",
        // "Qualifier / LL", "LL", "Lucky Loser", "TBD", "Por definir") counts
        // as a refillable slot — bracket.tennis confirming a real qualifier
        // should replace those. We resolve the ids once up front so the inner
        // loop can do a fast in_array check.
        $placeholderIds = Player::where(function ($q) {
            $q->where('name', 'TBD')
              ->orWhere('name', 'like', '%Qualifier%')
              ->orWhere('name', 'like', '%Lucky Loser%')
              ->orWhere('name', 'like', '%Por definir%')
              ->orWhereRaw('LOWER(name) = ?', ['ll']);
        })->pluck('id')->all();

        $touched = 0;
        $tour = str_starts_with($tournament->type, 'WTA') ? 'WTA' : 'ATP';
        foreach ($draw as $entry) {
            // Detect BYE slots before resolving players. bracket.tennis emits
            // "Bye" as one of the two side labels for top seeds who skip the
            // first round. We model them as `status=bye` matches with the real
            // player as winner — this way the bracket renderer can show "BYE"
            // explicitly and inferUnreportedWalkovers won't mistake them for
            // walkovers (which was the bug that produced "Rybakina vs TBD,
            // note=wo_p1, winner=TBD" in Dubai).
            $p1IsBye = $entry['p1'] && strcasecmp($entry['p1'], 'Bye') === 0;
            $p2IsBye = $entry['p2'] && strcasecmp($entry['p2'], 'Bye') === 0;

            $p1 = $this->resolveBootstrapPlayer($entry['p1'], $entry['p1_country'], $tbd, $tour);
            $p2 = $this->resolveBootstrapPlayer($entry['p2'], $entry['p2_country'], $tbd, $tour);
            if (!$p1 || !$p2) continue;

            $bracketPosition = $entry['slot'] + 1; // 1-indexed

            // Look up any existing match at this slot. If one exists we'll
            // refresh TBD slots in-place instead of creating duplicates.
            $existing = $tournament->matches()
                ->where('round', $startRound)
                ->where('bracket_position', $bracketPosition)
                ->first();

            if ($existing) {
                $updates = [];

                // A slot can be refilled in two cases:
                //   (a) it holds a placeholder (TBD / Qualifier / LL / Lucky
                //       Loser) and BT has confirmed a real player,
                //   (b) it holds the WRONG sibling — same surname as BT, but
                //       a different first name. This happens when the old
                //       single-char fuzzy matcher picked the wrong sibling
                //       (Xin. Wang appearing in Xiyu Wang's slot, etc.).
                // Real players that genuinely match BT are never overwritten.
                $p1IsPlaceholder = in_array($existing->player1_id, $placeholderIds, true);
                $p2IsPlaceholder = in_array($existing->player2_id, $placeholderIds, true);

                $p1IsWrongSibling = !$p1IsPlaceholder
                    && $existing->player1
                    && $this->isWrongSiblingFor($existing->player1, $entry['p1']);
                $p2IsWrongSibling = !$p2IsPlaceholder
                    && $existing->player2
                    && $this->isWrongSiblingFor($existing->player2, $entry['p2']);

                if (($p1IsPlaceholder || $p1IsWrongSibling) && !in_array($p1->id, $placeholderIds, true)) {
                    $updates['player1_id'] = $p1->id;
                }
                if (($p2IsPlaceholder || $p2IsWrongSibling) && !in_array($p2->id, $placeholderIds, true)) {
                    $updates['player2_id'] = $p2->id;
                }

                // Seeds get refreshed whenever bracket.tennis has one, since
                // it's the authoritative source for seeding.
                if (array_key_exists('p1_seed', $entry) && $entry['p1_seed'] !== null
                    && (int) $existing->player1_seed !== (int) $entry['p1_seed']) {
                    $updates['player1_seed'] = $entry['p1_seed'];
                }
                if (array_key_exists('p2_seed', $entry) && $entry['p2_seed'] !== null
                    && (int) $existing->player2_seed !== (int) $entry['p2_seed']) {
                    $updates['player2_seed'] = $entry['p2_seed'];
                }

                if (!empty($updates)) {
                    $existing->update($updates);
                    $touched++;
                }
                continue;
            }

            // No existing match — create from scratch.
            // For BYE rows, the real player wins automatically and we mark it
            // as status='bye' so the UI shows "BYE" instead of a fake match.
            $matchAttrs = [
                'tournament_id'    => $tournament->id,
                'player1_id'       => $p1->id,
                'player2_id'       => $p2->id,
                'player1_seed'     => $entry['p1_seed'] ?? null,
                'player2_seed'     => $entry['p2_seed'] ?? null,
                'round'            => $startRound,
                'bracket_position' => $bracketPosition,
                'scheduled_at'     => $tournament->start_date ?? now()->addDays(7),
                'api_event_key'    => 'bt-bootstrap-' . strtolower($startRound) . '-' . $entry['slot'] . '-' . $tournament->id,
            ];

            if ($p1IsBye && !$p2IsBye) {
                $matchAttrs['status']    = 'bye';
                $matchAttrs['winner_id'] = $p2->id;
            } elseif ($p2IsBye && !$p1IsBye) {
                $matchAttrs['status']    = 'bye';
                $matchAttrs['winner_id'] = $p1->id;
            } else {
                $matchAttrs['status'] = 'pending';
            }

            TennisMatch::create($matchAttrs);
            $touched++;
        }

        // Generate TBD placeholders for R64..F so the bracket renders complete.
        $this->ensureBracketPlaceholders($tournament);

        $tournament->update(['last_synced_at' => now()]);
        return $touched;
    }

    /**
     * Find or create a Player from a bracket.tennis name + country.
     * For "Bye" entries we return the shared TBD placeholder so the slot is
     * visually empty (no opponent to predict against).
     */
    private function resolveBootstrapPlayer(?string $name, ?string $country, Player $tbd, string $tour): ?Player
    {
        if (!$name || strcasecmp($name, 'Bye') === 0) return $tbd;
        $name = trim($name);
        $slug = Str::slug($name);
        if (!$slug) return $tbd;

        // 1) Exact slug match first — fast path.
        $player = Player::where('slug', $slug)->first();
        if ($player) return $player;

        // 2) Fuzzy match by surname + first-name prefix + tour. bracket.tennis
        //    sends full names ("Alexander Zverev") while api-tennis stores
        //    them initialized ("A. Zverev" or sometimes "Xin. Wang"). We need
        //    to match those without confusing same-tour players who share a
        //    surname:
        //      - Cerundolo brothers (Francisco / Juan Manuel) → distinguished by initial
        //      - Jones sisters (Emerson / Francesca)         → distinguished by initial
        //      - Wang doubles (Xinyu / Xiyu)                 → SAME initial, need >=3 chars
        //    Strategy: progressively widen the first-name prefix until we hit a
        //    unique match. If the api-tennis name only has an initial available
        //    ("X. Wang"), we fall back to single-char matching.
        $surnameKey = BracketTennisScraper::surnameKey($name);
        $firstPrefix = $this->firstNamePrefix($name);
        if ($surnameKey !== '' && $firstPrefix !== '') {
            $candidates = Player::where('category', $tour)
                ->where('name', 'like', '%' . substr($surnameKey, 0, 4) . '%')
                ->get()
                ->filter(fn($c) => BracketTennisScraper::surnameKey($c->name) === $surnameKey);

            if ($candidates->count() === 1) {
                return $candidates->first();
            }

            if ($candidates->count() > 1) {
                // Multiple players share this surname. Try the longest common
                // prefix match: start wide and shrink. This handles "Xinyu"
                // matching "Xin. Wang" (3 chars common) while still letting
                // "Xiyu" match "Xiy. Wang".
                $btPrefix = strtolower($firstPrefix);
                for ($len = min(strlen($btPrefix), 5); $len >= 1; $len--) {
                    $needle = substr($btPrefix, 0, $len);
                    $hits = $candidates->filter(function ($c) use ($needle) {
                        $candidatePrefix = strtolower($this->firstNamePrefix($c->name ?? ''));
                        return $candidatePrefix !== '' && str_starts_with($needle, $candidatePrefix);
                    });
                    if ($hits->count() === 1) {
                        return $hits->first();
                    }
                    if ($hits->count() === 0) break;
                }
            }
        }

        // 3) Nothing matched — create a fresh Player row. Country comes as
        //    ISO-3 (e.g. "ita") from bracket.tennis flags; Player::getIso2Attribute
        //    already handles 3-letter codes downstream.
        return Player::create([
            'name'             => $name,
            'slug'             => $slug,
            'category'         => $tour,
            'country'          => $country ? strtoupper($country) : 'Unknown',
            'nationality_code' => $country ?: null,
        ]);
    }

    /**
     * Decide whether the Player in a given slot is the WRONG sibling
     * compared to what bracket.tennis says for that slot.
     *
     * Returns true only when:
     *   - both names share the same surname (so we know we're comparing
     *     siblings, not unrelated players), AND
     *   - the DB player's first-name prefix is NOT a prefix-of the BT
     *     full first name (so "Xin." vs "Xiyu" → wrong, but "F." vs
     *     "Francisco" → right).
     *
     * Returning false when surnames differ means we never overwrite a slot
     * just because BT shows a totally different person there — that case
     * is handled by other matching logic (e.g. seed swaps).
     */
    private function isWrongSiblingFor(Player $dbPlayer, ?string $btName): bool
    {
        if (!$btName || strcasecmp($btName, 'Bye') === 0) return false;
        $dbSurname = BracketTennisScraper::surnameKey($dbPlayer->name ?? '');
        $btSurname = BracketTennisScraper::surnameKey($btName);
        if ($dbSurname === '' || $btSurname === '' || $dbSurname !== $btSurname) {
            return false;
        }
        $dbPrefix = $this->firstNamePrefix($dbPlayer->name ?? '');
        $btFirst  = $this->firstNamePrefix($btName);
        if ($dbPrefix === '' || $btFirst === '') return false;
        return !str_starts_with($btFirst, $dbPrefix);
    }

    /**
     * Lowercase prefix of the player's first name. Handles three input shapes:
     *   - Full name        ("Alexander Zverev"  → 'alexander')
     *   - Standard initial ("A. Zverev"         → 'a')
     *   - Extended initial ("Xin. Wang"         → 'xin')
     *
     * Used to disambiguate same-surname players during fuzzy matching. The
     * caller progressively narrows the prefix when more than one candidate
     * remains, so longer prefixes only get compared when there is real
     * ambiguity to resolve.
     */
    private function firstNamePrefix(string $name): string
    {
        $name = trim($name);
        if ($name === '') return '';
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name) ?: $name;
        $ascii = preg_replace('/[^a-zA-Z\s.-]/', '', $ascii);
        $tokens = preg_split('/\s+/', ltrim($ascii));
        $first = strtolower($tokens[0] ?? '');
        // Drop the trailing "." from initials like "Xin." or "A.".
        return rtrim($first, '.');
    }

    /** Loop syncTournamentLive over every active tournament with an api_tournament_key. */
    public function syncAllActive(): array
    {
        $results = [];
        $tournaments = Tournament::where('is_active', true)
            ->whereNotNull('api_tournament_key')
            // Test tournaments use placeholder keys like 'test-roma-premium-wta-2026'
            // that don't exist in api-tennis.com — skip them so they don't break
            // the sync run.
            ->where('api_tournament_key', 'NOT LIKE', 'test-%')
            ->whereIn('status', ['upcoming', 'in_progress', 'live'])
            ->get();

        foreach ($tournaments as $t) {
            try {
                $results[$t->slug] = $this->syncTournamentLive($t);
            } catch (\Throwable $e) {
                Log::error("api-tennis sync failed for {$t->slug}", ['error' => $e->getMessage()]);
                $results[$t->slug] = ['error' => $e->getMessage()];
            }
        }
        return $results;
    }

    // ───────────────────────────────────────────────────────────────────────────
    // Helpers
    // ───────────────────────────────────────────────────────────────────────────

    private function upsertPlayerFromFixture(?int $key, ?string $name, ?string $photo, Tournament $tournament): ?Player
    {
        if (!$key || !$name) return null;
        if (str_contains($name, '/')) return null; // doubles entries

        $name = trim($name);
        $slug = Str::slug($name) ?: ('player-' . $key);
        $tour = str_starts_with($tournament->type, 'WTA') ? 'WTA' : 'ATP';

        // Three-tier lookup. The third tier is what prevents api-tennis from
        // creating "Z. Diyas" as a new row when bracket.tennis already added
        // "Zarina Diyas" earlier in the day. Without it we end up with two
        // Player rows for the same person — see the dedupe pass for cleanup.
        $player = Player::where('api_player_key', (string) $key)->first()
            ?? Player::where('slug', $slug)->first()
            ?? $this->findPlayerBySurnamePrefix($name, $tour)
            ?? new Player();

        // If the player has no country yet, fetch full profile from api-tennis.
        // The /get_players call is cached (24h) so repeated syncs don't re-hit
        // the API for the same player.
        $needsCountry = !$player->exists
            || !$player->nationality_code
            || $player->country === 'Unknown'
            || $player->country === null;

        $country = $player->country;
        $iso2    = $player->nationality_code;

        // If we have a country name but no iso2 code, try resolving locally first
        if (!$iso2 && $country && $country !== 'Unknown') {
            $iso2 = $this->countryToIso2($country);
        }

        // If we still don't have full data, fetch the API profile
        if ($needsCountry || !$iso2) {
            $profile = $this->client->player($key);
            $row = $profile['result'][0] ?? null;
            if ($row && !empty($row['player_country'])) {
                $country = $row['player_country'];
                $iso2    = $this->countryToIso2($country) ?? $iso2;
            }
        }

        $player->fill([
            'api_player_key'   => (string) $key,
            'name'             => $name,
            // Don't overwrite an existing slug with a different version of
            // the name — keeps "zarina-diyas" stable when api-tennis sends
            // "Z. Diyas" (which would otherwise become "z-diyas").
            'slug'             => $player->exists ? ($player->slug ?? $slug) : $slug,
            'category'         => $player->category ?? $tour,
            'country'          => $country ?: 'Unknown',
            'nationality_code' => $iso2,
            'photo'            => $photo ?: $player->photo,
        ])->save();

        return $player;
    }

    /**
     * Locate an existing Player by surname + first-name prefix within the
     * same tour. Used as the third-tier fallback in upsertPlayerFromFixture
     * to prevent creating "Z. Diyas" alongside "Zarina Diyas".
     *
     * Returns null on ambiguity (multiple candidates) — better to create a
     * new row than to merge two distinct people (Jones sisters, Cerundolo
     * brothers).
     */
    private function findPlayerBySurnamePrefix(string $name, string $tour): ?Player
    {
        $surname = BracketTennisScraper::surnameKey($name);
        if ($surname === '') return null;
        $prefix = $this->firstNamePrefix($name);
        if ($prefix === '') return null;

        $candidates = Player::where('category', $tour)
            ->where('name', 'like', '%' . substr($surname, 0, 4) . '%')
            ->get()
            ->filter(fn($p) => BracketTennisScraper::surnameKey($p->name) === $surname);

        if ($candidates->isEmpty()) return null;

        // Single candidate → trust it.
        if ($candidates->count() === 1) {
            $only = $candidates->first();
            $otherPrefix = $this->firstNamePrefix($only->name ?? '');
            // Same surname but incompatible first names → it's a different
            // person (e.g. Lloyd vs Billy Harris). Don't merge.
            if ($otherPrefix !== '' && !str_starts_with($prefix, $otherPrefix) && !str_starts_with($otherPrefix, $prefix)) {
                return null;
            }
            return $only;
        }

        // Multiple candidates → find the single one whose first-name prefix
        // is prefix-compatible with the incoming name. If more than one
        // matches, bail (ambiguity).
        $matches = $candidates->filter(function ($c) use ($prefix) {
            $cp = $this->firstNamePrefix($c->name ?? '');
            return $cp !== '' && (str_starts_with($prefix, $cp) || str_starts_with($cp, $prefix));
        });

        return $matches->count() === 1 ? $matches->first() : null;
    }

    /**
     * Map api-tennis round labels to our short codes. The API uses fraction
     * notation: "1/64-finals" = R128, "1/32-finals" = R64, etc. "Final" alone
     * (no "1/N" prefix) is the championship match.
     *
     * Examples seen: "ATP Rome - 1/64-finals", "ATP Rome - Semi-finals",
     * "ATP Rome - Final".
     */
    private function mapRound(string $round): string
    {
        $r = mb_strtolower($round);
        return match (true) {
            str_contains($r, '1/64-final') || str_contains($r, '1/64 final') => 'R128',
            str_contains($r, '1/32-final') || str_contains($r, '1/32 final') => 'R64',
            str_contains($r, '1/16-final') || str_contains($r, '1/16 final') => 'R32',
            str_contains($r, '1/8-final')  || str_contains($r, '1/8 final')  => 'R16',
            // Quarter-finals comes through as "Quarter-finals" or "1/4-finals"
            str_contains($r, '1/4-final')  || str_contains($r, '1/4 final')
                || str_contains($r, 'quarter')                                => 'QF',
            // Semi-finals — check BEFORE "final" to avoid misclassification
            str_contains($r, 'semi')                                          => 'SF',
            // Plain "Final" — only if neither semi nor quarter matched above
            str_contains($r, 'final')                                         => 'F',
            // Fallbacks (rarely needed)
            str_contains($r, 'round of 128') || str_contains($r, '1st round') => 'R128',
            str_contains($r, 'round of 64')  || str_contains($r, '2nd round') => 'R64',
            str_contains($r, 'round of 32')  || str_contains($r, '3rd round') => 'R32',
            str_contains($r, 'round of 16')  || str_contains($r, '4th round') => 'R16',
            default                                                            => 'R128',
        };
    }

    /**
     * Format the score for display. Returns per-set breakdown separated by
     * single spaces ("6-3 7-5") because the bracket view parses on whitespace.
     *
     * api-tennis encodes tiebreaks as decimals (e.g. "7.12" means a 7-game
     * set won via a 12-point tiebreak). We strip the decimal portion here.
     *
     * The status suffix ((RET)/(WO)) lives in a SEPARATE column (`status_note`)
     * — we no longer append it to the score so the bracket renderer can show
     * the tag next to the losing player's name instead of muddying the score.
     */
    private function formatScore(array $fixture): ?string
    {
        // Quick reject: if the match isn't actually in progress / finished,
        // any score api-tennis sends is noise — we return null so the bracket
        // card stays clean instead of rendering a phantom "0-0".
        $status = mb_strtolower((string) ($fixture['event_status'] ?? ''));
        $isInPlay = $status === 'finished'
            || $status === 'live'
            || $status === 'in progress'
            || $status === 'walkover'
            || $status === 'retired'
            || str_starts_with($status, 'set ')
            || str_contains($status, 'suspended');
        if (!$isInPlay) return null;

        $sets = $fixture['scores'] ?? null;
        if (is_array($sets) && !empty($sets)) {
            $parts = [];
            foreach ($sets as $s) {
                $first  = $this->stripTiebreak($s['score_first']  ?? null);
                $second = $this->stripTiebreak($s['score_second'] ?? null);
                if ($first === null || $second === null) continue;
                $parts[] = $first . '-' . $second;
            }
            if (!empty($parts)) {
                $candidate = implode(' ', $parts);
                // Reject when every set is "0-0" — happens when the API
                // pre-populates the scores array before play actually begins.
                if (preg_replace('/[\s0-]/', '', $candidate) === '') return null;
                return $candidate;
            }
        }
        // Fallback to the aggregate "X - Y" string the API emits. Strip whitespace
        // before comparing because the API uses " 0 - 0 " with surrounding spaces.
        // "0-0" / "-" / empty all mean the match hasn't been played yet, so we
        // return null instead of storing a literal "0-0" that the bracket card
        // would then render as a real scoreline.
        $final = $fixture['event_final_result'] ?? null;
        if ($final !== null) {
            $clean = preg_replace('/\s+/', '', $final);
            if ($clean === '' || $clean === '-' || $clean === '0-0') return null;
        }
        return $final;
    }

    /**
     * Compute status_note from the API status + winner side. Tags the LOSING
     * player so the bracket card can render "Player (ret.)" next to their name.
     *   - Retired → "ret_p1" or "ret_p2"
     *   - Walkover → "wo_p1" or "wo_p2"
     *   - Suspended → "suspended" (no winner yet)
     *   - Normal finish → null
     */
    private function computeStatusNote(array $fixture, ?string $winnerSide): ?string
    {
        $status = mb_strtolower((string) ($fixture['event_status'] ?? ''));
        if (str_contains($status, 'suspended')) return 'suspended';
        if (str_contains($status, 'retired')) {
            // Loser is the side opposite to the winner
            if ($winnerSide === 'First Player')  return 'ret_p2';
            if ($winnerSide === 'Second Player') return 'ret_p1';
            return 'ret_p2'; // fallback
        }
        if (str_contains($status, 'walkover')) {
            if ($winnerSide === 'First Player')  return 'wo_p2';
            if ($winnerSide === 'Second Player') return 'wo_p1';
            return 'wo_p2';
        }
        return null;
    }

    /** Strip the tiebreak suffix from an API score ("7.12" → "7"). */
    private function stripTiebreak(mixed $raw): ?string
    {
        if ($raw === null) return null;
        $s = trim((string) $raw);
        if ($s === '') return null;
        // Keep only digits before any "." (which is the tiebreak separator).
        return preg_replace('/\..*$/', '', $s);
    }

    /**
     * Mark pending matches as walkovers when their downstream slot already has
     * a real winner. The API sometimes emits walkovers with event_status that
     * doesn't include the literal "Walkover" string (e.g. left blank or marked
     * "Cancelled"), so we infer them from the bracket shape itself.
     *
     * For each pending match at round R, position P: the next round R+1 plays
     * one match at position ceil(P/2), and one of its two players must equal
     * the winner of our pending match. Whichever side that is wins by walkover.
     */
    private function inferUnreportedWalkovers(Tournament $tournament): void
    {
        $rounds = ['R128', 'R64', 'R32', 'R16', 'QF', 'SF', 'F'];

        foreach ($rounds as $i => $round) {
            if ($i === count($rounds) - 1) break; // final has no next round
            $nextRound = $rounds[$i + 1];

            // Look for matches that COULD be unreported walkovers: both players
            // are known, but the match has no real winner and no real score.
            // Covers pending matches AND matches the API marked finished but left
            // winner_id NULL (which is what we've seen in the wild for walkovers).
            //
            // Exclusions:
            //   - status='bye' rows (the bootstrap already set winner_id correctly).
            //   - matches whose other player is the TBD placeholder (we must never
            //     mark "RealPlayer vs TBD, winner=TBD" — that was the Dubai bug).
            $tbdIdForFilter = Player::where('name', 'TBD')->value('id');
            $pending = TennisMatch::where('tournament_id', $tournament->id)
                ->where('round', $round)
                ->where('status', '!=', 'bye')
                ->whereNotNull('player1_id')
                ->whereNotNull('player2_id')
                ->whereNull('winner_id')
                ->where(function ($q) {
                    $q->whereNull('score')->orWhere('score', '0-0')->orWhereRaw("REPLACE(score,' ','') = '0-0'");
                })
                ->when($tbdIdForFilter, function ($q) use ($tbdIdForFilter) {
                    $q->where('player1_id', '!=', $tbdIdForFilter)
                      ->where('player2_id', '!=', $tbdIdForFilter);
                })
                ->get();

            foreach ($pending as $m) {
                $nextPos = (int) ceil($m->bracket_position / 2);
                $nextMatch = TennisMatch::where('tournament_id', $tournament->id)
                    ->where('round', $nextRound)
                    ->where('bracket_position', $nextPos)
                    ->first();

                if (!$nextMatch) continue;

                // Whichever of our two players appears in the next match is the walkover winner.
                $players = [$m->player1_id, $m->player2_id];
                $advancingId = null;
                foreach ($players as $pid) {
                    if ($pid === $nextMatch->player1_id || $pid === $nextMatch->player2_id) {
                        $advancingId = $pid;
                        break;
                    }
                }
                if (!$advancingId) continue;

                // Mark the LOSING side: wo_p1 means player1 lost; wo_p2 means player2 lost.
                $losingSide = $advancingId === $m->player1_id ? 'wo_p2' : 'wo_p1';

                $m->update([
                    'status'      => 'finished',
                    'status_note' => $losingSide,
                    'winner_id'   => $advancingId,
                    'score'       => null,
                ]);
            }
        }
    }

    private function mapStatus(?string $apiStatus): string
    {
        $s = mb_strtolower((string) $apiStatus);
        return match (true) {
            $s === 'finished'                                  => 'finished',
            in_array($s, ['walkover', 'retired'], true)        => 'finished',
            str_contains($s, 'suspended')                      => 'live', // shown as live; status_note flags it
            in_array($s, ['live', 'in progress'], true)        => 'live',
            str_starts_with($s, 'set ')                        => 'live',
            default                                            => 'pending',
        };
    }

    private function nextBracketPosition(Tournament $tournament, string $round): int
    {
        return TennisMatch::where('tournament_id', $tournament->id)
            ->where('round', $round)
            ->max('bracket_position') + 1 ?? 1;
    }

    private function parseDateTime(?string $date, ?string $time, string $tz = 'UTC'): ?Carbon
    {
        if (!$date) return null;
        try {
            $stamp = $time ? "{$date} {$time}" : $date;
            // Interpret the wall-clock string in the venue timezone, then
            // store as UTC. The display layer converts to Bogotá downstream.
            return Carbon::parse($stamp, $tz)->setTimezone('UTC');
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Lightweight country-name → ISO 3166-1 alpha-2 map. api-tennis.com returns
     * country names in English ("United States", "Spain"). The full list is in
     * Player::getIso2Attribute(); here we only handle what `standings` returns.
     */
    private function countryToIso2(?string $name): ?string
    {
        if (!$name) return null;
        $map = [
            'argentina'=>'ar','australia'=>'au','austria'=>'at','belgium'=>'be','belarus'=>'by','bolivia'=>'bo',
            'brazil'=>'br','bulgaria'=>'bg','canada'=>'ca','chile'=>'cl','china'=>'cn','colombia'=>'co',
            'costa rica'=>'cr','croatia'=>'hr','cyprus'=>'cy','czech republic'=>'cz','denmark'=>'dk',
            'ecuador'=>'ec','egypt'=>'eg','estonia'=>'ee','finland'=>'fi','france'=>'fr','germany'=>'de',
            'great britain'=>'gb','greece'=>'gr','hungary'=>'hu','india'=>'in','ireland'=>'ie','israel'=>'il',
            'italy'=>'it','japan'=>'jp','kazakhstan'=>'kz','south korea'=>'kr','latvia'=>'lv','lithuania'=>'lt',
            'mexico'=>'mx','monaco'=>'mc','netherlands'=>'nl','new zealand'=>'nz','norway'=>'no','paraguay'=>'py',
            'peru'=>'pe','philippines'=>'ph','poland'=>'pl','portugal'=>'pt','romania'=>'ro','russia'=>'ru',
            'serbia'=>'rs','slovakia'=>'sk','slovenia'=>'si','south africa'=>'za','spain'=>'es','sweden'=>'se',
            'switzerland'=>'ch','taiwan'=>'tw','thailand'=>'th','tunisia'=>'tn','turkey'=>'tr','ukraine'=>'ua',
            'united kingdom'=>'gb','united states'=>'us','uruguay'=>'uy','venezuela'=>'ve',
        ];
        return $map[mb_strtolower(trim($name))] ?? null;
    }
}
