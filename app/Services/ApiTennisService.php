<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ApiTennisService
{
    protected string $baseUrl;
    protected string $apiKey;

    public function __construct()
    {
        $this->baseUrl = config('services.api_tennis.base_url');
        $this->apiKey = config('services.api_tennis.key');
    }

    protected function request(string $method, array $params = []): ?array
    {
        try {
            $query = array_merge([
                'method' => $method,
                'APIkey' => $this->apiKey,
            ], $params);

            $response = Http::timeout(30)->get($this->baseUrl, $query);

            if ($response->failed()) {
                Log::error("API Tennis request failed: {$method}", [
                    'status' => $response->status(),
                    'params' => $params,
                ]);
                return null;
            }

            $data = $response->json();

            if (!isset($data['success']) || $data['success'] != 1) {
                Log::warning("API Tennis returned error: {$method}", ['response' => $data]);
                return null;
            }

            return $data['result'] ?? [];
        } catch (\Exception $e) {
            Log::error("API Tennis exception: {$method}", [
                'message' => $e->getMessage(),
                'params' => $params,
            ]);
            return null;
        }
    }

    public function getEvents(): ?array
    {
        return Cache::remember('api_tennis_events', 3600, function () {
            return $this->request('get_events');
        });
    }

    public function getTournaments(): ?array
    {
        // No cache - response is too large for DB cache driver
        return $this->request('get_tournaments');
    }

    public function getFixtures(string $dateStart, string $dateStop, ?int $tournamentKey = null, ?int $eventTypeKey = null): ?array
    {
        $params = [
            'date_start' => $dateStart,
            'date_stop' => $dateStop,
        ];

        if ($tournamentKey) {
            $params['tournament_key'] = $tournamentKey;
        }

        if ($eventTypeKey) {
            $params['event_type_key'] = $eventTypeKey;
        }

        return $this->request('get_fixtures', $params);
    }

    public function getLivescores(): ?array
    {
        return $this->request('get_livescore');
    }

    public function getStandings(string $eventType = 'ATP'): ?array
    {
        return Cache::remember("api_tennis_standings_{$eventType}", 3600, function () use ($eventType) {
            return $this->request('get_standings', ['event_type' => $eventType]);
        });
    }

    public function getPlayer(int $playerKey): ?array
    {
        return $this->request('get_players', ['player_key' => $playerKey]);
    }

    public function getH2H(int $firstPlayerKey, int $secondPlayerKey): ?array
    {
        return $this->request('get_H2H', [
            'first_player_key' => $firstPlayerKey,
            'second_player_key' => $secondPlayerKey,
        ]);
    }

    public function getOdds(string $dateStart, string $dateStop, ?int $matchKey = null): ?array
    {
        $params = [
            'date_start' => $dateStart,
            'date_stop' => $dateStop,
        ];

        if ($matchKey) {
            $params['match_key'] = $matchKey;
        }

        return $this->request('get_odds', $params);
    }
}
