<?php

namespace App\Services\Tennis;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin wrapper over the api-tennis.com REST endpoint.
 *
 * Differences vs the old Matchstat client:
 *   - Single GET base URL with `?method=<name>&APIkey=<key>` style auth
 *   - No pagination needed for `get_tournaments` — returns the full list
 *   - Returns full ATP/WTA rankings (2,000+ players) and player photo URLs
 */
class ApiTennisClient
{
    /** Event type ids — discovered via `?method=get_events`. */
    public const EVENT_ATP_SINGLES = 265;
    public const EVENT_WTA_SINGLES = 266;

    /** Cache TTLs (seconds). */
    public const TTL_TOURNAMENTS = 86400; // 24h — tournament catalog barely changes
    public const TTL_RANKINGS    = 21600; // 6h
    public const TTL_FIXTURES    = 60;    // 1 min — live data
    public const TTL_PLAYER      = 86400; // 24h

    private function client(): PendingRequest
    {
        $cfg = config('services.api_tennis');
        return Http::baseUrl(rtrim($cfg['base'], '/') . '/')
            // 90s timeout because get_fixtures with full pointbypoint can exceed
            // 1MB on Grand Slams + Masters 1000 and the API streams slowly.
            ->timeout(90)
            ->retry(2, 500, throw: false);
    }

    /**
     * Throttled, cached GET. The api-tennis.com endpoint is a single URL with a
     * `method` query param, so we pass the method here.
     */
    private function get(string $method, array $query = [], int $ttl = self::TTL_FIXTURES): ?array
    {
        $query['method'] = $method;
        $query['APIkey'] = config('services.api_tennis.key');
        if (!$query['APIkey']) {
            Log::error('api-tennis.com: API_TENNIS_KEY not configured');
            return null;
        }

        ksort($query);
        $cacheKey = 'apitennis:' . md5(http_build_query($query));

        // Use the `file` cache driver explicitly — get_tournaments returns ~6MB
        // of payload that exceeds MySQL's default max_allowed_packet (4MB) and
        // can't fit in the `cache` table.
        return Cache::store('file')->remember($cacheKey, $ttl, function () use ($method, $query) {
            try {
                $response = $this->client()->get('', $query);
                if (!$response->successful()) {
                    Log::warning('api-tennis.com non-2xx', [
                        'method' => $method,
                        'code'   => $response->status(),
                        'body'   => substr($response->body(), 0, 500),
                    ]);
                    return null;
                }
                $json = $response->json();
                if (!is_array($json) || ($json['success'] ?? 0) !== 1) {
                    Log::warning('api-tennis.com error response', [
                        'method' => $method,
                        'body'   => $json,
                    ]);
                    return null;
                }
                return $json;
            } catch (\Throwable $e) {
                Log::error('api-tennis.com exception', [
                    'method' => $method,
                    'error'  => $e->getMessage(),
                ]);
                return null;
            }
        });
    }

    /** Master tournament catalog — every event known to the API. ~10,000 entries. */
    public function tournaments(): ?array
    {
        return $this->get('get_tournaments', [], self::TTL_TOURNAMENTS);
    }

    /** ATP or WTA singles ranking. $event = 'ATP' | 'WTA' */
    public function standings(string $event): ?array
    {
        return $this->get('get_standings', ['event_type' => strtoupper($event)], self::TTL_RANKINGS);
    }

    /**
     * Fixtures within a date range. Pass `tournament_key` to scope to one event,
     * `event_type_key` to scope to ATP/WTA.
     */
    public function fixtures(string $dateStart, string $dateStop, array $extra = []): ?array
    {
        return $this->get('get_fixtures', array_merge([
            'date_start' => $dateStart,
            'date_stop'  => $dateStop,
        ], $extra), self::TTL_FIXTURES);
    }

    /** Currently playing matches. */
    public function livescore(array $extra = []): ?array
    {
        return $this->get('get_livescore', $extra, self::TTL_FIXTURES);
    }

    /** Detailed player profile by `player_key`. */
    public function player(int $playerKey): ?array
    {
        return $this->get('get_players', ['player_key' => $playerKey], self::TTL_PLAYER);
    }
}
