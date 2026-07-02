<?php

namespace App\Services\Tennis;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Scrapes bracket.tennis to obtain the canonical bracket order.
 *
 * Why this site (vs Tennis Explorer):
 *   - Renders the FULL 7-round bracket including empty/TBD slots
 *   - Every match has data-match-id="round-slot" — official tree position
 *   - Includes byes explicitly (player2 = "Bye") so seed placement is unambiguous
 *
 * Public contract:
 *   draw(string $slug, string $tour): array
 *     Returns the round-0 (first round) matches with their official slot:
 *     [
 *       ['slot' => 0, 'p1' => 'Jannik Sinner', 'p2' => 'Bye',          'p1_country' => 'ita', 'p2_country' => 'null'],
 *       ['slot' => 1, 'p1' => 'Sebastian Ofner', 'p2' => 'Alex Michelsen', 'p1_country' => 'aut', 'p2_country' => 'usa'],
 *       ...
 *     ]
 *   We only need round 0 — later rounds are derived deterministically from
 *   the binary tree (R64 slot N = ceil((R128 slot 2N + slot 2N+1)/2)).
 */
class BracketTennisScraper
{
    public const TTL_DRAW       = 3600; // 1h — draws don't change once published
    public const TTL_DRAW_EMPTY = 300;  // 5min — keep retrying when the draw is still pending

    private const URL_TEMPLATE = 'https://bracket.tennis/tournaments/{slug}/{tour}';

    public function draw(string $slug, string $tour): array
    {
        return $this->fetchAndParse($slug, $tour, fn($html) => $this->parse($html, true));
    }

    /**
     * Like draw(), but returns EVERY round's matches — each entry tagged with
     * its 'round' index (0 = first round, 1 = next, …). bracket.tennis renders
     * the full tree, so the later-round columns already carry the players it
     * has propagated (e.g. Wimbledon R32 shows Sinner vs Brooksby once the R64
     * is played). We use this to fill our R64+ slots directly from BT instead
     * of relying solely on internal propagation of api-tennis results — which
     * leaves those slots empty whenever a score fails to sync.
     */
    public function fullDraw(string $slug, string $tour): array
    {
        return $this->fetchAndParse($slug, $tour, fn($html) => $this->parse($html, false), cacheSuffix: 'fulldraw');
    }

    /**
     * Extract tournament start/end dates from the page JSON.
     * Returns ['start' => 'YYYY-MM-DD'|null, 'end' => 'YYYY-MM-DD'|null].
     */
    public function dates(string $slug, string $tour): array
    {
        return $this->fetchAndParse($slug, $tour, function ($html) {
            $out = ['start' => null, 'end' => null];
            if (preg_match('~"startDate":"([0-9-]{10})"~', $html, $m))  $out['start'] = $m[1];
            if (preg_match('~"endDate":"([0-9-]{10})"~', $html, $m))    $out['end']   = $m[1];
            return $out;
        }, cacheSuffix: 'dates');
    }

    /**
     * Shared HTTP/cache wrapper. We split cache TTL into "got data" (1h) vs
     * "empty result" (5min) so a missed publish window doesn't lock us into
     * an empty cache for a whole hour — that was exactly the Roland Garros
     * incident where the draw published but our app kept returning [] for an
     * hour after the publish.
     */
    private function fetchAndParse(string $slug, string $tour, callable $parser, string $cacheSuffix = 'draw'): array
    {
        $slug = trim($slug, '/');
        $tour = strtolower($tour);
        $cacheKey = 'bt-' . $cacheSuffix . ':' . md5($slug . '|' . $tour);
        $cache = Cache::store('file');

        $hit = $cache->get($cacheKey);
        if ($hit !== null) return $hit;

        $url = strtr(self::URL_TEMPLATE, ['{slug}' => $slug, '{tour}' => $tour]);
        try {
            $resp = Http::withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0 Safari/537.36',
                    'Accept'     => 'text/html',
                ])
                ->timeout(20)
                ->get($url);
        } catch (\Throwable $e) {
            Log::error('bracket.tennis fetch failed', ['slug' => $slug, 'tour' => $tour, 'error' => $e->getMessage()]);
            $cache->put($cacheKey, [], self::TTL_DRAW_EMPTY);
            return [];
        }

        if (!$resp->successful()) {
            Log::warning('bracket.tennis non-2xx', ['slug' => $slug, 'tour' => $tour, 'code' => $resp->status()]);
            $cache->put($cacheKey, [], self::TTL_DRAW_EMPTY);
            return [];
        }

        $parsed = $parser($resp->body());
        // TTL strategy:
        //   - empty parse → 5min (draw not published yet, keep retrying)
        //   - has unresolved placeholders (Qualifier, Bye-less TBDs) → 5min
        //     so confirmations get picked up within one cron cycle instead
        //     of waiting an hour
        //   - fully resolved draw → 1h (it won't change anymore)
        // For fulldraw, later-round TBDs mean the tournament is still being
        // played → keep the short TTL so newly-propagated R32+ players are
        // picked up within a cron cycle. Only a fully-resolved tree (no TBDs
        // anywhere) caches for the full hour.
        $hasPlaceholders = in_array($cacheSuffix, ['draw', 'fulldraw'], true) && $this->drawHasPlaceholders($parsed);
        $ttl = (empty($parsed) || $hasPlaceholders) ? self::TTL_DRAW_EMPTY : self::TTL_DRAW;
        $cache->put($cacheKey, $parsed, $ttl);
        return $parsed;
    }

    /**
     * Parse the bracket.tennis HTML to extract round-0 matches with their
     * canonical slot. Each match container has `data-match-id="round-slot"`
     * and two player blocks identified by `#flag-{iso3}` SVG references.
     *
     * We split the HTML on every `data-match-id` boundary and parse the
     * following ~3KB for the two player blocks of that match.
     */
    private function parse(string $html, bool $onlyRound0 = true): array
    {
        $parts = preg_split('#(data-match-id="\d+-\d+")#', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        $matches = [];

        // bracket.tennis tags every match container with a stable player UUID
        // pair — data-player-ids="uuidA,uuidB" — in ALL rounds. But player
        // NAMES only render with a flag SVG in the FIRST round; later rounds
        // show the propagated player WITHOUT a flag. Since every later-round
        // player won a first-round match, we learn each UUID's identity from
        // round 0 and resolve later rounds by UUID. Later-round matches are
        // stashed and resolved after the loop so DOM order doesn't matter.
        $uuidMap  = []; // uuid => ['name'=>, 'country'=>, 'seed'=>]
        $laterRaw = []; // [ ['round'=>, 'slot'=>, 'ids'=>[uuidA,uuidB]] ]

        for ($i = 1; $i < count($parts); $i += 2) {
            if (!preg_match('#(\d+)-(\d+)#', $parts[$i], $m)) continue;
            $round = (int) $m[1];
            $slot  = (int) $m[2];
            if ($onlyRound0 && $round !== 0) continue;

            $body = substr($parts[$i + 1] ?? '', 0, 3500);

            // The two side UUIDs (either may be absent → that side is TBD).
            $ids = [];
            if (preg_match('~data-player-ids="([^"]*)"~', $body, $pm)) {
                foreach (explode(',', $pm[1]) as $uuid) {
                    $ids[] = trim($uuid);
                }
            }

            // Later rounds: no flag markup to parse — stash for UUID resolution.
            if ($round !== 0) {
                $laterRaw[] = ['round' => $round, 'slot' => $slot, 'ids' => $ids];
                continue;
            }

            // Each player block looks like:
            //   <use href="...#flag-XXX"></use></svg><div...>NAME...
            // followed (sometimes) by a <span...opacity-60>SEED|Q|WC</span>.
            //
            // We do this in two passes: first capture (flag, name), then look
            // BACKWARDS from each player's position for the optional seed span.
            preg_match_all(
                '~#flag-(\w+)[^>]*></use></svg><div[^>]*>(?:<a[^>]*>)?([^<]+?)(?:</a>)?(?:<|$)~',
                $body,
                $players,
                PREG_SET_ORDER | PREG_OFFSET_CAPTURE,
            );

            if (count($players) < 2) continue;

            // For each player, peek up to 200 chars ahead for a seed span
            // BEFORE the next #flag-... marker.
            $seeds = [];
            foreach ($players as $idx => $p) {
                $matchEnd = $p[0][1] + strlen($p[0][0]);
                $nextStart = $idx + 1 < count($players) ? $players[$idx + 1][0][1] : strlen($body);
                $window = substr($body, $matchEnd, min(300, $nextStart - $matchEnd));
                if (preg_match('~<span[^>]*opacity-60[^>]*>([^<]+)</span>~', $window, $sm)) {
                    $seeds[$idx] = trim($sm[1]);
                } else {
                    $seeds[$idx] = '';
                }
            }

            $entry = [
                'round'       => 0,
                'slot'        => $slot,
                'p1'          => $this->cleanName($players[0][2][0]),
                'p2'          => $this->cleanName($players[1][2][0]),
                'p1_country'  => $this->cleanFlag($players[0][1][0]),
                'p2_country'  => $this->cleanFlag($players[1][1][0]),
                'p1_seed'     => $this->cleanSeed($seeds[0] ?? ''),
                'p2_seed'     => $this->cleanSeed($seeds[1] ?? ''),
            ];
            $matches[] = $entry;

            // Learn UUID → identity so later rounds can resolve the same person.
            // Skip Bye sides (no real player) and unmapped/empty UUIDs.
            foreach ([0 => 'p1', 1 => 'p2'] as $k => $side) {
                if (isset($ids[$k]) && $ids[$k] !== '' && strcasecmp($entry[$side], 'Bye') !== 0) {
                    $uuidMap[$ids[$k]] = [
                        'name'    => $entry[$side],
                        'country' => $entry[$side . '_country'],
                        'seed'    => $entry[$side . '_seed'],
                    ];
                }
            }
        }

        // Resolve stashed later-round matches from the UUID map (round 0 fully
        // parsed now). A side whose UUID is missing or not-yet-known (e.g. an
        // unresolved qualifier) stays TBD; a slot with neither side known is
        // skipped entirely.
        foreach ($laterRaw as $lr) {
            $p1 = (isset($lr['ids'][0]) && $lr['ids'][0] !== '') ? ($uuidMap[$lr['ids'][0]] ?? null) : null;
            $p2 = (isset($lr['ids'][1]) && $lr['ids'][1] !== '') ? ($uuidMap[$lr['ids'][1]] ?? null) : null;
            if (!$p1 && !$p2) continue;

            $matches[] = [
                'round'       => $lr['round'],
                'slot'        => $lr['slot'],
                'p1'          => $p1['name'] ?? 'TBD',
                'p2'          => $p2['name'] ?? 'TBD',
                'p1_country'  => $p1['country'] ?? null,
                'p2_country'  => $p2['country'] ?? null,
                'p1_seed'     => $p1['seed'] ?? null,
                'p2_seed'     => $p2['seed'] ?? null,
            ];
        }

        usort($matches, fn($a, $b) => [$a['round'], $a['slot']] <=> [$b['round'], $b['slot']]);
        return $matches;
    }

    /**
     * Detect whether the parsed draw still contains unresolved placeholder
     * names ("Qualifier", "Qualifier / LL", "LL", "Lucky Loser", "TBD"). Used
     * to keep the cache TTL short so confirmations are picked up promptly.
     */
    private function drawHasPlaceholders(array $parsed): bool
    {
        foreach ($parsed as $entry) {
            foreach (['p1', 'p2'] as $key) {
                $name = strtolower($entry[$key] ?? '');
                if ($name === '') continue;
                if (str_contains($name, 'qualifier') || str_contains($name, 'lucky')
                    || $name === 'tbd' || $name === 'll') {
                    return true;
                }
            }
        }
        return false;
    }

    private function cleanName(string $name): string
    {
        $name = trim(html_entity_decode($name, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        // Strip seed number suffix sometimes embedded
        $name = preg_replace('/\s*\(\d+\)\s*$/', '', $name);
        return $name;
    }

    private function cleanFlag(string $flag): ?string
    {
        $flag = strtolower(trim($flag));
        return ($flag === '' || $flag === 'null') ? null : $flag;
    }

    /** Normalize seed/Q/WC marker. Keeps "1"-"32" as seed digits, "Q" or "WC" as-is. */
    private function cleanSeed(string $raw): ?string
    {
        $s = trim($raw);
        if ($s === '') return null;
        $u = strtoupper($s);
        if (in_array($u, ['Q', 'WC', 'LL', 'PR', 'SE'], true)) return $u;
        if (ctype_digit($s)) return $s;
        return null;
    }

    /**
     * Normalize a player name for matching against api-tennis names.
     * api-tennis returns "J. Sinner", bracket.tennis returns "Jannik Sinner".
     * We match by surname (last token).
     */
    public static function surnameKey(string $name): string
    {
        $name = preg_replace('/^[a-z]\.\s*/i', '', trim($name)); // drop "J. "
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name) ?: $name;
        $ascii = preg_replace('/[^a-z\s-]/i', '', $ascii);
        // Last whitespace-separated chunk is the surname
        $tokens = preg_split('/\s+/', strtolower(trim($ascii)));
        return end($tokens) ?: '';
    }
}
