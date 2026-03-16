<?php

namespace App\Services\Sync;

use App\Models\Tournament;
use App\Services\ApiTennisService;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class TournamentSync
{
    // Event type keys for singles (main categories)
    const ATP_SINGLES = 265;
    const WTA_SINGLES = 266;

    // Grand Slam tournament names
    const GRAND_SLAMS = [
        'Australian Open',
        'Roland Garros',
        'Wimbledon',
        'US Open',
    ];

    protected ApiTennisService $api;

    public function __construct(ApiTennisService $api)
    {
        $this->api = $api;
    }

    public function sync(): array
    {
        $tournaments = $this->api->getTournaments();

        if ($tournaments === null) {
            return ['error' => 'No se pudo conectar con la API'];
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;

        // Filter only ATP Singles and WTA Singles tournaments
        $relevantTypes = [self::ATP_SINGLES, self::WTA_SINGLES];

        foreach ($tournaments as $t) {
            $eventTypeKey = (int) $t['event_type_key'];

            if (!in_array($eventTypeKey, $relevantTypes)) {
                $skipped++;
                continue;
            }

            $type = $this->resolveType($t['tournament_name'], $eventTypeKey);
            $surface = $this->normalizeSurface($t['tournament_sourface'] ?? null);

            $tournament = Tournament::where('api_tournament_key', $t['tournament_key'])->first();

            // Prefix with category to avoid duplicate slugs (e.g., ATP Acapulco vs WTA Acapulco)
            $prefix = $eventTypeKey === self::WTA_SINGLES ? 'WTA' : 'ATP';
            $fullName = "{$prefix} {$t['tournament_name']}";

            $data = [
                'name' => $fullName,
                'slug' => Str::slug($fullName),
                'type' => $type,
                'surface' => $surface,
                'api_event_type_key' => $eventTypeKey,
                'is_active' => true,
            ];

            if ($tournament) {
                $tournament->update($data);
                $updated++;
            } else {
                $data['api_tournament_key'] = $t['tournament_key'];
                $data['start_date'] = now();
                $data['end_date'] = now()->addDays(7);
                $data['points_multiplier'] = $type === 'GrandSlam' ? 2.0 : 1.0;
                Tournament::create($data);
                $created++;
            }
        }

        Log::info("Tournament sync completed", compact('created', 'updated', 'skipped'));

        return compact('created', 'updated', 'skipped');
    }

    protected function resolveType(string $name, int $eventTypeKey): string
    {
        foreach (self::GRAND_SLAMS as $gs) {
            if (stripos($name, $gs) !== false) {
                return 'GrandSlam';
            }
        }

        return $eventTypeKey === self::WTA_SINGLES ? 'WTA' : 'ATP';
    }

    protected function normalizeSurface(?string $surface): ?string
    {
        if (!$surface) return null;

        $surface = strtolower($surface);
        if (str_contains($surface, 'hard')) return 'Hard';
        if (str_contains($surface, 'clay')) return 'Clay';
        if (str_contains($surface, 'grass')) return 'Grass';
        if (str_contains($surface, 'carpet')) return 'Carpet';

        return ucfirst($surface);
    }
}
