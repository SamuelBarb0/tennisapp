<?php

namespace App\Services\Sync;

use App\Models\Tournament;
use App\Services\SportradarService;
use App\Services\Sportradar\TournamentRegistry;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class TournamentSync
{
    protected SportradarService $api;

    public function __construct(SportradarService $api)
    {
        $this->api = $api;
    }

    public function sync(): array
    {
        $created = 0;
        $updated = 0;
        $errors = 0;

        foreach (TournamentRegistry::TARGETS as $competitionId => $info) {
            try {
                $seasons = $this->api->getSeasons($competitionId);

                if (!$seasons || empty($seasons)) {
                    Log::warning("No seasons found for {$info['name']}", ['competition_id' => $competitionId]);
                    $errors++;
                    continue;
                }

                // Find the 2026 season, or the most recent one
                $season = $this->findCurrentSeason($seasons);

                if (!$season) {
                    Log::warning("No current season for {$info['name']}");
                    $errors++;
                    continue;
                }

                // Build name: strip existing prefix, then add gender suffix
                $baseName = preg_replace('/^(ATP|WTA)\s+/', '', $info['name']);
                $genderLabel = $info['gender'] === 'women' ? 'Femenino' : 'Masculino';
                $fullName = "{$baseName} {$genderLabel}";

                $tournament = Tournament::where('api_tournament_key', $competitionId)->first();

                $data = [
                    'name' => $fullName,
                    'slug' => Str::slug($fullName . '-' . $info['gender']),
                    'type' => $info['type'],
                    'surface' => $info['surface'],
                    'city' => $info['city'],
                    'country' => $info['country'],
                    'location' => $info['city'] . ', ' . $info['country'],
                    'start_date' => $season['start_date'],
                    'end_date' => $season['end_date'],
                    'is_active' => true,
                    'api_event_type_key' => $season['id'], // Store season ID here
                ];

                if ($tournament) {
                    $tournament->update($data);
                    $updated++;
                } else {
                    $data['api_tournament_key'] = $competitionId;
                    $data['is_premium'] = false;
                    Tournament::create($data);
                    $created++;
                }
            } catch (\Exception $e) {
                Log::error("Error syncing tournament {$info['name']}: {$e->getMessage()}");
                $errors++;
            }
        }

        Log::info("Tournament sync completed", compact('created', 'updated', 'errors'));
        return compact('created', 'updated', 'errors');
    }

    protected function findCurrentSeason(array $seasons): ?array
    {
        $currentYear = (int) date('Y');

        // Prefer current year
        foreach ($seasons as $season) {
            $year = (int) ($season['year'] ?? 0);
            if ($year === $currentYear) {
                return $season;
            }
        }

        // Fallback to most recent
        usort($seasons, fn($a, $b) => ($b['year'] ?? 0) <=> ($a['year'] ?? 0));
        return $seasons[0] ?? null;
    }
}
