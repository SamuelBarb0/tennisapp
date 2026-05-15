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
        ['needle' => 'Australian Open', 'tier' => 'Grand Slam',       'tours' => ['ATP', 'WTA']],
        ['needle' => 'French Open',     'tier' => 'Grand Slam',       'tours' => ['ATP', 'WTA']],
        ['needle' => 'Wimbledon',       'tier' => 'Grand Slam',       'tours' => ['ATP', 'WTA']],
        ['needle' => 'US Open',         'tier' => 'Grand Slam',       'tours' => ['ATP', 'WTA']],
        // ATP Masters 1000 (9)
        ['needle' => 'Indian Wells',    'tier' => 'ATP Masters 1000', 'tours' => ['ATP']],
        ['needle' => 'Miami',           'tier' => 'ATP Masters 1000', 'tours' => ['ATP']],
        ['needle' => 'Monte Carlo',     'tier' => 'ATP Masters 1000', 'tours' => ['ATP']],
        ['needle' => 'Madrid',          'tier' => 'ATP Masters 1000', 'tours' => ['ATP']],
        ['needle' => 'Rome',            'tier' => 'ATP Masters 1000', 'tours' => ['ATP']],
        ['needle' => 'Montreal',        'tier' => 'ATP Masters 1000', 'tours' => ['ATP']],
        ['needle' => 'Cincinnati',      'tier' => 'ATP Masters 1000', 'tours' => ['ATP']],
        ['needle' => 'Shanghai',        'tier' => 'ATP Masters 1000', 'tours' => ['ATP']],
        ['needle' => 'Paris',           'tier' => 'ATP Masters 1000', 'tours' => ['ATP']],
        // WTA 1000 (10) — Miami/Madrid/Rome/Cincinnati already covered above as Masters,
        // here we add the WTA versions plus the 5 WTA-only events
        ['needle' => 'Miami',           'tier' => 'WTA 1000',         'tours' => ['WTA']],
        ['needle' => 'Madrid',          'tier' => 'WTA 1000',         'tours' => ['WTA']],
        ['needle' => 'Rome',            'tier' => 'WTA 1000',         'tours' => ['WTA']],
        ['needle' => 'Cincinnati',      'tier' => 'WTA 1000',         'tours' => ['WTA']],
        ['needle' => 'Doha',            'tier' => 'WTA 1000',         'tours' => ['WTA']],
        ['needle' => 'Dubai',           'tier' => 'WTA 1000',         'tours' => ['WTA']],
        ['needle' => 'Toronto',         'tier' => 'WTA 1000',         'tours' => ['WTA']],
        ['needle' => 'Beijing',         'tier' => 'WTA 1000',         'tours' => ['WTA']],
        ['needle' => 'Wuhan',           'tier' => 'WTA 1000',         'tours' => ['WTA']],
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

                $existing = Tournament::where('api_tournament_key', (string) $best['tournament_key'])->first()
                    ?? Tournament::where('slug', $slug)->first();

                $attrs = [
                    'api_tournament_key' => (string) $best['tournament_key'],
                    'name'               => $name,
                    'slug'               => $slug,
                    'type'               => $tier,
                    'season'             => now()->year,
                    'is_active'          => true,
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
        $allFixtures = [];
        for ($i = -21; $i <= 21; $i += 3) {
            $chunkStart = now()->addDays($i)->format('Y-m-d');
            $chunkEnd   = now()->addDays(min($i + 2, 21))->format('Y-m-d');
            $resp = $this->client->fixtures($chunkStart, $chunkEnd, [
                'tournament_key' => (int) $tournament->api_tournament_key,
            ]);
            if ($resp && !empty($resp['result'])) {
                foreach ($resp['result'] as $f) {
                    $allFixtures[$f['event_key']] = $f;
                }
            }
        }

        // If api-tennis hasn't published fixtures yet but bracket.tennis already
        // has the draw, bootstrap the bracket from BT alone so users can start
        // predicting ~5-7 days earlier. The api-tennis scores/winners will
        // overlay later when fixtures appear.
        if (empty($allFixtures)) {
            $bootstrapped = $this->bootstrapFromBracketTennis($tournament);
            if ($bootstrapped > 0) {
                return [
                    'fixtures'       => 0,
                    'main_draw'      => 0,
                    'qualy'          => 0,
                    'finished'       => 0,
                    'scored'         => 0,
                    'placeholders'   => 0,
                    'bootstrapped'   => $bootstrapped,
                ];
            }
            return ['fixtures' => 0, 'updated' => 0, 'scored' => 0];
        }

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

            $attrs = [
                'tournament_id'  => $tournament->id,
                'player1_id'     => $player1?->id,
                'player2_id'     => $player2?->id,
                'round'          => $round,
                'status'         => $status,
                'status_note'    => $statusNote,
                'winner_id'      => $winnerId,
                'score'          => $this->formatScore($f),
                'scheduled_at'   => $this->parseDateTime($f['event_date'] ?? null, $f['event_time'] ?? null),
            ];

            // Prefer to replace a placeholder in the same round if one exists
            // (so we don't end up with duplicate matches per slot).
            $existing = TennisMatch::where('api_event_key', (string) $eventKey)->first()
                ?? TennisMatch::where('tournament_id', $tournament->id)
                    ->where('round', $round)
                    ->where('api_event_key', 'LIKE', 'placeholder-%')
                    ->orderBy('bracket_position')
                    ->first();

            if ($existing) {
                $wasFinished = $existing->status === 'finished';
                // If this is a placeholder being upgraded, also adopt the real api_event_key
                if (str_starts_with($existing->api_event_key ?? '', 'placeholder-')) {
                    $attrs['api_event_key'] = (string) $eventKey;
                }
                $existing->update($attrs);
                if (!$wasFinished && $status === 'finished') $newlyFinished[] = $existing->id;
            } else {
                $attrs['api_event_key']    = (string) $eventKey;
                $attrs['bracket_position'] = $this->nextBracketPosition($tournament, $round);
                TennisMatch::create($attrs);
            }
            $updated++;
        }

        $scored = 0;
        if (!empty($newlyFinished)) {
            $scored = BracketPredictionController::scoreTournament($tournament);
        }

        // If the tournament has a tennisexplorer_slug we use the OFFICIAL draw
        // order scraped from tennisexplorer.com — this is the canonical source.
        // Otherwise we fall back to inferring the tree from player progression
        // (less reliable but works for any tournament).
        if ($tournament->tennisexplorer_slug) {
            $this->reorderBracketFromScraper($tournament);
        } else {
            $this->rebuildBracketPositions($tournament);
        }

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
            $update['start_date'] = $startDate->toDateString();

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
        if (isset($update['start_date']) && isset($update['end_date'])) {
            $update['status'] = $this->statusFromDates(
                Carbon::parse($update['start_date']),
                Carbon::parse($update['end_date']),
            );
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

        // 1) Build surname → R128 slot map from the scraped draw.
        //    Also keep surname → seed/Q/WC tag AND surname → country (ISO-3)
        //    so we can patch missing flags from bracket.tennis as fallback.
        $surnameToFirstSlot = [];
        $surnameToSeed = [];
        $surnameToCountry = [];
        foreach ($draw as $entry) {
            foreach (['p1', 'p2'] as $side) {
                $name = $entry[$side];
                if (!$name || strcasecmp($name, 'Bye') === 0) continue;
                $key = BracketTennisScraper::surnameKey($name);
                if ($key === '') continue;
                if (!isset($surnameToFirstSlot[$key])) {
                    $expandedPos = ($entry['slot'] * 2) + ($side === 'p2' ? 1 : 0);
                    $surnameToFirstSlot[$key] = $expandedPos;
                }
                $seedTag = $entry[$side . '_seed'] ?? null;
                if ($seedTag !== null && !isset($surnameToSeed[$key])) {
                    $surnameToSeed[$key] = $seedTag;
                }
                $countryTag = $entry[$side . '_country'] ?? null;
                if ($countryTag && !isset($surnameToCountry[$key])) {
                    $surnameToCountry[$key] = $countryTag;
                }
            }
        }

        // Apply seeds to every match (winners keep their seed throughout the
        // bracket). Also patch missing player country/flag from bracket.tennis
        // when the api-tennis data didn't provide it (e.g. Townsend, Kessler).
        foreach ($tournament->matches()->with(['player1', 'player2'])->get() as $m) {
            $updates = [];
            foreach (['player1' => 'player1_seed', 'player2' => 'player2_seed'] as $rel => $col) {
                $p = $m->{$rel};
                if (!$p || $p->name === 'TBD') continue;
                $sk = BracketTennisScraper::surnameKey($p->name);
                if (isset($surnameToSeed[$sk]) && $m->$col !== $surnameToSeed[$sk]) {
                    $updates[$col] = $surnameToSeed[$sk];
                }
                // Backfill country from BT if missing or "Unknown"
                if (isset($surnameToCountry[$sk]) && (!$p->nationality_code || $p->country === 'Unknown' || $p->iso2 === 'un')) {
                    $iso3 = strtoupper($surnameToCountry[$sk]);
                    $p->update(['nationality_code' => $iso3, 'country' => $p->country === 'Unknown' || !$p->country ? $iso3 : $p->country]);
                }
            }
            if ($updates) $m->update($updates);
        }

        // 2) For each round in DB, place matches based on the player's
        //    expanded position from bracket.tennis. After we know the OFFICIAL
        //    slot, we COMPACT positions to be sequential 1..N — that way the
        //    bracket renderer (which expects sorted contiguous positions) draws
        //    the tree cleanly while preserving the canonical pair order.
        $rounds = ['R128', 'R64', 'R32', 'R16', 'QF', 'SF', 'F'];
        $playerToExpandedPos = $surnameToFirstSlot;

        foreach ($rounds as $round) {
            $dbMatches = $tournament->matches()
                ->where('round', $round)
                ->with(['player1', 'player2'])
                ->get();
            if ($dbMatches->isEmpty()) continue;

            // Step A: compute the OFFICIAL slot for each match
            $officialSlot = [];   // match_id => slot
            $playersOf    = [];   // match_id => [surname1, surname2]
            $unmatched    = [];   // matches with no scraper info
            foreach ($dbMatches as $m) {
                $surnames = [];
                foreach ([$m->player1?->name, $m->player2?->name] as $name) {
                    if (!$name) continue;
                    $surnames[] = BracketTennisScraper::surnameKey($name);
                }
                $playersOf[$m->id] = $surnames;
                $expandedPositions = [];
                foreach ($surnames as $s) {
                    if ($s && isset($playerToExpandedPos[$s])) {
                        $expandedPositions[] = $playerToExpandedPos[$s];
                    }
                }
                if (empty($expandedPositions)) {
                    $unmatched[] = $m->id;
                } else {
                    $officialSlot[$m->id] = min($expandedPositions);
                }
            }

            // Step B: assign each match to its TREE slot (1..N) where N is the
            // expected number of matches in this round.
            // Each player's expanded position halves once per round, so the
            // tree slot is: floor(expanded_pos / 2) + 1.
            //
            // Real matches keep their canonical position. Placeholders fill the
            // remaining slots so the bracket tree stays contiguous and the
            // R128→QF→F lineage stays correct.
            $expectedTotal = match ($round) {
                'R128' => 64, 'R64' => 32, 'R32' => 16, 'R16' => 8,
                'QF'   => 4,  'SF'  => 2,  'F'   => 1, default => count($dbMatches),
            };

            // Sort real matches by their (halved) tree slot, NOT by raw expanded position
            $treeSlot = [];
            foreach ($officialSlot as $matchId => $expandedPos) {
                $treeSlot[$matchId] = intdiv($expandedPos, 2) + 1;
            }

            // Resolve collisions: if two real matches collide on the same slot,
            // the second one moves to the next free slot. (Should be rare with
            // bracket.tennis as source — only happens if API put extra fixtures.)
            asort($treeSlot);
            $used = [];
            foreach ($treeSlot as $matchId => $slot) {
                while (isset($used[$slot]) && $slot <= $expectedTotal) $slot++;
                $used[$slot] = true;
                $dbMatches->firstWhere('id', $matchId)->update(['bracket_position' => $slot]);
            }

            // Place placeholders in the still-free slots so the tree is contiguous.
            $next = 1;
            foreach ($unmatched as $matchId) {
                while (isset($used[$next])) $next++;
                $used[$next] = true;
                $dbMatches->firstWhere('id', $matchId)->update(['bracket_position' => $next]);
                $next++;
            }

            // Step C: propagate ALL players' expanded positions to the next
            // round by halving. We do this for everyone — including players
            // whose match didn't appear in the current round — so positions
            // stay consistent across the entire tree even when only part of
            // the bracket has real fixtures yet.
            foreach ($playerToExpandedPos as $s => $v) {
                $playerToExpandedPos[$s] = intdiv($v, 2);
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
     * Bootstrap the bracket directly from bracket.tennis when api-tennis hasn't
     * published fixtures yet. Creates 64 R128 matches with players + seeds,
     * leaving status='pending' and no scores. The next sync (when api-tennis
     * fixtures arrive) overlays scores and winners on top.
     *
     * Returns the number of matches created.
     */
    private function bootstrapFromBracketTennis(Tournament $tournament): int
    {
        if (!$tournament->tennisexplorer_slug) return 0;
        [$btSlug, $btTour] = $this->parseBracketTennisSlug($tournament->tennisexplorer_slug, $tournament);

        $draw = $this->scraper->draw($btSlug, $btTour);
        if (empty($draw)) return 0;

        // Skip if matches already exist (idempotency)
        if ($tournament->matches()->where('round', 'R128')->exists()) return 0;

        $tbd = Player::where('name', 'TBD')->first();
        if (!$tbd) return 0;

        $created = 0;
        $tour = str_starts_with($tournament->type, 'WTA') ? 'WTA' : 'ATP';
        foreach ($draw as $entry) {
            $p1 = $this->resolveBootstrapPlayer($entry['p1'], $entry['p1_country'], $tbd, $tour);
            $p2 = $this->resolveBootstrapPlayer($entry['p2'], $entry['p2_country'], $tbd, $tour);
            if (!$p1 || !$p2) continue;

            TennisMatch::create([
                'tournament_id'    => $tournament->id,
                'player1_id'       => $p1->id,
                'player2_id'       => $p2->id,
                'player1_seed'     => $entry['p1_seed'] ?? null,
                'player2_seed'     => $entry['p2_seed'] ?? null,
                'round'            => 'R128',
                'bracket_position' => $entry['slot'] + 1, // 1-indexed
                'status'           => 'pending',
                'scheduled_at'     => $tournament->start_date ?? now()->addDays(7),
                'api_event_key'    => 'bt-bootstrap-r128-' . $entry['slot'] . '-' . $tournament->id,
            ]);
            $created++;
        }

        // Generate TBD placeholders for R64..F so the bracket renders complete.
        $this->ensureBracketPlaceholders($tournament);

        $tournament->update(['last_synced_at' => now()]);
        return $created;
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

        $player = Player::where('slug', $slug)->first();
        if ($player) return $player;

        // Create a new player record. Country comes as ISO-3 (e.g. "ita") from
        // bracket.tennis flags — Player::getIso2Attribute already handles 3-letter codes.
        return Player::create([
            'name'             => $name,
            'slug'             => $slug,
            'category'         => $tour,
            'country'          => $country ? strtoupper($country) : 'Unknown',
            'nationality_code' => $country ?: null,
        ]);
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

        $player = Player::where('api_player_key', (string) $key)->first()
            ?? Player::where('slug', $slug)->first()
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
            'slug'             => $slug,
            'category'         => $player->category ?? $tour,
            'country'          => $country ?: 'Unknown',
            'nationality_code' => $iso2,
            'photo'            => $photo ?: $player->photo,
        ])->save();

        return $player;
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
        $sets = $fixture['scores'] ?? null;
        if (is_array($sets) && !empty($sets)) {
            $parts = [];
            foreach ($sets as $s) {
                $first  = $this->stripTiebreak($s['score_first']  ?? null);
                $second = $this->stripTiebreak($s['score_second'] ?? null);
                if ($first === null || $second === null) continue;
                $parts[] = $first . '-' . $second;
            }
            if (!empty($parts)) return implode(' ', $parts);
        }
        return $fixture['event_final_result'] ?? null;
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

    private function parseDateTime(?string $date, ?string $time): ?Carbon
    {
        if (!$date) return null;
        try {
            $stamp = $time ? "{$date} {$time}" : $date;
            return Carbon::parse($stamp);
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
