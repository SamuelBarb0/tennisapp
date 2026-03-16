<?php

namespace App\Services\Sync;

use App\Models\Player;
use App\Services\ApiTennisService;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class PlayerSync
{
    protected ApiTennisService $api;

    public function __construct(ApiTennisService $api)
    {
        $this->api = $api;
    }

    public function sync(string $category = 'all'): array
    {
        $totalCreated = 0;
        $totalUpdated = 0;

        $categories = $category === 'all' ? ['ATP', 'WTA'] : [strtoupper($category)];

        foreach ($categories as $cat) {
            $standings = $this->api->getStandings($cat);

            if ($standings === null) {
                Log::warning("Could not fetch {$cat} standings");
                continue;
            }

            foreach ($standings as $s) {
                // Find by api_player_key first, then by slug (for pre-existing seeded players)
                $slug = Str::slug($s['player']);
                $player = Player::where('api_player_key', $s['player_key'])->first()
                    ?? Player::where('slug', $slug)->first();

                $countryName = $s['country'] ?? 'Unknown';
                $nationalityCode = $this->getCountryCode($countryName);

                $data = [
                    'name' => $s['player'],
                    'slug' => $slug,
                    'country' => $countryName,
                    'nationality_code' => $nationalityCode,
                    'ranking' => (int) $s['place'],
                    'category' => $cat,
                    'api_player_key' => $s['player_key'],
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

    protected function getCountryCode(string $country): string
    {
        $codes = [
            'Argentina' => 'ARG', 'Australia' => 'AUS', 'Austria' => 'AUT',
            'Belarus' => 'BLR', 'Belgium' => 'BEL', 'Bolivia' => 'BOL',
            'Bosnia and Herzegovina' => 'BIH', 'Brazil' => 'BRA', 'Bulgaria' => 'BUL',
            'Canada' => 'CAN', 'Chile' => 'CHI', 'China' => 'CHN',
            'Colombia' => 'COL', 'Croatia' => 'CRO', 'Czech Republic' => 'CZE',
            'Denmark' => 'DEN', 'Dominican Republic' => 'DOM', 'Ecuador' => 'ECU',
            'Egypt' => 'EGY', 'Estonia' => 'EST', 'Finland' => 'FIN',
            'France' => 'FRA', 'Georgia' => 'GEO', 'Germany' => 'GER',
            'Greece' => 'GRE', 'Hungary' => 'HUN', 'India' => 'IND',
            'Israel' => 'ISR', 'Italy' => 'ITA', 'Japan' => 'JPN',
            'Kazakhstan' => 'KAZ', 'Latvia' => 'LAT', 'Lithuania' => 'LTU',
            'Luxembourg' => 'LUX', 'Mexico' => 'MEX', 'Moldova' => 'MDA',
            'Montenegro' => 'MNE', 'Netherlands' => 'NED', 'New Zealand' => 'NZL',
            'Norway' => 'NOR', 'Peru' => 'PER', 'Poland' => 'POL',
            'Portugal' => 'POR', 'Romania' => 'ROU', 'Russia' => 'RUS',
            'Serbia' => 'SRB', 'Slovakia' => 'SVK', 'Slovenia' => 'SLO',
            'South Africa' => 'RSA', 'South Korea' => 'KOR', 'Spain' => 'ESP',
            'Sweden' => 'SWE', 'Switzerland' => 'SUI', 'Taiwan' => 'TPE',
            'Tunisia' => 'TUN', 'Turkey' => 'TUR', 'Ukraine' => 'UKR',
            'United Kingdom' => 'GBR', 'United States' => 'USA', 'USA' => 'USA',
            'Uruguay' => 'URU', 'Uzbekistan' => 'UZB', 'World' => 'WOR',
        ];

        return $codes[$country] ?? strtoupper(substr($country, 0, 3));
    }
}
