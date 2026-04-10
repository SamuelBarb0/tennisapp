<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SportradarService
{
    protected string $baseUrl;
    protected string $apiKey;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.sportradar.base_url'), '/') . '/';
        $this->apiKey = config('services.sportradar.key');
    }

    protected function get(string $endpoint): ?array
    {
        try {
            $url = $this->baseUrl . $endpoint . '.json';

            $response = Http::timeout(30)->get($url, [
                'api_key' => $this->apiKey,
            ]);

            if ($response->status() === 429) {
                Log::warning("Sportradar rate limited, waiting 2s...");
                sleep(2);
                $response = Http::timeout(30)->get($url, ['api_key' => $this->apiKey]);
            }

            if ($response->failed()) {
                Log::error("Sportradar request failed: {$endpoint}", [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return null;
            }

            // Rate limit: ~1 req/sec
            usleep(1100000);

            return $response->json();
        } catch (\Exception $e) {
            Log::error("Sportradar exception: {$endpoint}", ['message' => $e->getMessage()]);
            return null;
        }
    }

    public function getCompetitions(): ?array
    {
        return Cache::remember('sportradar_competitions', 86400, function () {
            $data = $this->get('competitions');
            return $data['competitions'] ?? null;
        });
    }

    public function getSeasons(string $competitionId): ?array
    {
        $data = $this->get("competitions/{$competitionId}/seasons");
        return $data['seasons'] ?? null;
    }

    public function getSeasonSummaries(string $seasonId): ?array
    {
        $data = $this->get("seasons/{$seasonId}/summaries");
        return $data['summaries'] ?? null;
    }

    public function getSeasonInfo(string $seasonId): ?array
    {
        return $this->get("seasons/{$seasonId}");
    }

    public function getLiveSummaries(): ?array
    {
        $data = $this->get('live_summaries');
        return $data['summaries'] ?? null;
    }

    public function getCompetitorProfile(string $competitorId): ?array
    {
        return $this->get("competitors/{$competitorId}/profile");
    }

    public function getRankings(): ?array
    {
        $data = $this->get('rankings');
        return $data['rankings'] ?? null;
    }

    public function getDailySummaries(string $date): ?array
    {
        $data = $this->get("daily_summaries/{$date}");
        return $data['summaries'] ?? null;
    }
}
