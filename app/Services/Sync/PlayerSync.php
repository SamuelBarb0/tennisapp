<?php

namespace App\Services\Sync;

use App\Models\Player;
use App\Services\SportradarService;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class PlayerSync
{
    protected SportradarService $api;

    public function __construct(SportradarService $api)
    {
        $this->api = $api;
    }

    public function sync(string $category = 'all'): array
    {
        $rankings = $this->api->getRankings();

        if (!$rankings) {
            return ['error' => 'No se pudo obtener rankings de Sportradar'];
        }

        $totalCreated = 0;
        $totalUpdated = 0;

        foreach ($rankings as $ranking) {
            $rankingType = $ranking['type_id'] ?? null;
            $cat = $this->resolveCategory($ranking['name'] ?? '', $rankingType);

            if ($category !== 'all' && strtoupper($category) !== $cat) {
                continue;
            }

            $competitors = $ranking['competitor_rankings'] ?? [];

            foreach ($competitors as $cr) {
                $competitor = $cr['competitor'] ?? null;
                if (!$competitor) continue;

                $competitorId = $competitor['id'];
                $name = $this->formatName($competitor['name'] ?? '');
                $countryCode = $competitor['country_code'] ?? 'UNK';
                $country = $competitor['country'] ?? 'Unknown';
                $rank = $cr['rank'] ?? null;

                $player = Player::where('api_player_key', $competitorId)->first();

                $data = [
                    'name' => $name,
                    'slug' => Str::slug($name),
                    'country' => $country,
                    'nationality_code' => $countryCode,
                    'ranking' => $rank,
                    'category' => $cat,
                    'api_player_key' => $competitorId,
                ];

                if ($player) {
                    $player->update($data);
                    $totalUpdated++;
                } else {
                    Player::create($data);
                    $totalCreated++;
                }
            }
        }

        Log::info("Player sync completed", ['created' => $totalCreated, 'updated' => $totalUpdated]);
        return ['created' => $totalCreated, 'updated' => $totalUpdated];
    }

    /**
     * Sportradar names come as "Last, First" — convert to "First Last".
     */
    protected function formatName(string $name): string
    {
        if (str_contains($name, ',')) {
            $parts = explode(',', $name, 2);
            return trim($parts[1]) . ' ' . trim($parts[0]);
        }
        return $name;
    }

    protected function resolveCategory(string $rankingName, ?int $typeId): string
    {
        if (stripos($rankingName, 'WTA') !== false || $typeId === 22) {
            return 'WTA';
        }
        return 'ATP';
    }
}
