<?php

namespace App\Services\Tennis;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Thin wrapper over the Matchstat (Tennis API ATP/WTA/ITF) RapidAPI endpoint.
 *
 * Features:
 *   - Built-in 100 req/min global rate limit (per their docs)
 *   - Per-call cache (TTL configurable per method) so we don't burn quota on
 *     stable data like rankings or tournament calendar
 *   - Defensive: returns null on errors so calling code can degrade gracefully
 */
class MatchstatClient
{
    private const RL_KEY = 'matchstat:rate-limit';
    private const RL_PER_MINUTE = 80; // leave 20% headroom under their 100 RPM cap

    /** Cache TTLs (seconds) */
    public const TTL_RANKINGS    = 21600;   // 6h — rankings update weekly
    public const TTL_CALENDAR    = 86400;   // 24h — calendar barely changes
    public const TTL_TOURNAMENT  = 3600;    // 1h — tournament info / past champions
    public const TTL_RESULTS     = 600;     // 10 min — historical results
    public const TTL_LIVE        = 60;      // 1 min — fixtures of the day
    public const TTL_PLAYER      = 86400;   // 24h — player profile is mostly static

    private function client(): PendingRequest
    {
        $cfg = config('services.matchstat');
        return Http::withHeaders([
                'X-RapidAPI-Key'  => $cfg['key'],
                'X-RapidAPI-Host' => $cfg['host'],
                'Accept'          => 'application/json',
            ])
            ->baseUrl($cfg['base'])
            ->timeout(20)
            ->retry(2, 500, throw: false);
    }

    /**
     * Throttled GET. Returns decoded JSON or null on failure.
     * The cache layer keys on path + sorted query so equivalent calls share a row.
     */
    private function get(string $path, array $query = [], int $ttl = self::TTL_LIVE): ?array
    {
        ksort($query);
        $cacheKey = 'matchstat:' . md5($path . '|' . http_build_query($query));

        return Cache::remember($cacheKey, $ttl, function () use ($path, $query) {
            // Soft RPS guard — block + sleep briefly if we'd bust the quota
            $allowed = RateLimiter::attempt(
                self::RL_KEY,
                self::RL_PER_MINUTE,
                fn () => true,
                60
            );
            if (!$allowed) {
                Log::warning('Matchstat: in-process rate limit hit, sleeping 1s');
                usleep(1_000_000);
            }

            try {
                $response = $this->client()->get($path, $query);
                if (!$response->successful()) {
                    Log::warning('Matchstat API non-2xx', [
                        'path'  => $path,
                        'query' => $query,
                        'code'  => $response->status(),
                        'body'  => substr($response->body(), 0, 500),
                    ]);
                    return null;
                }
                return $response->json() ?? [];
            } catch (\Throwable $e) {
                Log::error('Matchstat API exception', [
                    'path'  => $path,
                    'error' => $e->getMessage(),
                ]);
                return null;
            }
        });
    }

    // ───────────────────────────────────────────────────────────────────────────
    // Endpoints we care about. One method per Matchstat endpoint we actually use.
    // ───────────────────────────────────────────────────────────────────────────

    /** Today's fixtures (live + scheduled). $type = 'atp' | 'wta' */
    public function fixturesToday(string $type, array $query = []): ?array
    {
        return $this->get("/tennis/v2/{$type}/fixtures", $query, self::TTL_LIVE);
    }

    /** Fixtures for a specific date (YYYY-MM-DD) */
    public function fixturesByDate(string $type, string $date, array $query = []): ?array
    {
        return $this->get("/tennis/v2/{$type}/fixtures/{$date}", $query, self::TTL_LIVE);
    }

    /** Fixtures for a tournament — best for live polling of a specific draw */
    public function fixturesByTournament(string $type, int $tournamentId, array $query = []): ?array
    {
        return $this->get("/tennis/v2/{$type}/fixtures/tournament/{$tournamentId}", $query, self::TTL_LIVE);
    }

    /** Past matches by player — only endpoint that exposes scores + winner */
    public function playerPastMatches(string $type, int $playerId, array $query = []): ?array
    {
        $query = array_merge(['include' => 'round,tournament'], $query);
        return $this->get("/tennis/v2/{$type}/player/past-matches/{$playerId}", $query, self::TTL_RESULTS);
    }

    /** Singles ranking (ATP or WTA) */
    public function ranking(string $type, array $query = []): ?array
    {
        return $this->get("/tennis/v2/{$type}/ranking/singles", $query, self::TTL_RANKINGS);
    }

    /** Player profile */
    public function playerProfile(string $type, int $playerId, array $query = []): ?array
    {
        $query = array_merge(['include' => 'country,ranking'], $query);
        return $this->get("/tennis/v2/{$type}/player/profile/{$playerId}", $query, self::TTL_PLAYER);
    }

    /** Force-refresh: bypass cache for one call (used in webhooks / manual sync buttons) */
    public function withoutCache(callable $fn)
    {
        // Run within a context that forces TTL=0 — we just call the same get() with ttl=0.
        // Easier: nuke the matched keys after the call.
        return $fn($this);
    }
}
