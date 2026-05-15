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
    public const TTL_DRAW = 3600; // 1h — draws don't change during a tournament

    private const URL_TEMPLATE = 'https://bracket.tennis/tournaments/{slug}/{tour}';

    public function draw(string $slug, string $tour): array
    {
        $slug = trim($slug, '/');
        $tour = strtolower($tour);
        $cacheKey = 'bt-draw:' . md5($slug . '|' . $tour);

        return Cache::store('file')->remember($cacheKey, self::TTL_DRAW, function () use ($slug, $tour) {
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
                return [];
            }

            if (!$resp->successful()) {
                Log::warning('bracket.tennis non-2xx', ['slug' => $slug, 'tour' => $tour, 'code' => $resp->status()]);
                return [];
            }

            return $this->parse($resp->body());
        });
    }

    /**
     * Parse the bracket.tennis HTML to extract round-0 matches with their
     * canonical slot. Each match container has `data-match-id="round-slot"`
     * and two player blocks identified by `#flag-{iso3}` SVG references.
     *
     * We split the HTML on every `data-match-id` boundary and parse the
     * following ~3KB for the two player blocks of that match.
     */
    private function parse(string $html): array
    {
        $parts = preg_split('#(data-match-id="\d+-\d+")#', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        $matches = [];

        for ($i = 1; $i < count($parts); $i += 2) {
            if (!preg_match('#(\d+)-(\d+)#', $parts[$i], $m)) continue;
            $round = (int) $m[1];
            $slot  = (int) $m[2];
            if ($round !== 0) continue; // we only need round 0

            $body = substr($parts[$i + 1] ?? '', 0, 3500);

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

            $matches[] = [
                'slot'        => $slot,
                'p1'          => $this->cleanName($players[0][2][0]),
                'p2'          => $this->cleanName($players[1][2][0]),
                'p1_country'  => $this->cleanFlag($players[0][1][0]),
                'p2_country'  => $this->cleanFlag($players[1][1][0]),
                'p1_seed'     => $this->cleanSeed($seeds[0] ?? ''),
                'p2_seed'     => $this->cleanSeed($seeds[1] ?? ''),
            ];
        }

        usort($matches, fn($a, $b) => $a['slot'] - $b['slot']);
        return $matches;
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
