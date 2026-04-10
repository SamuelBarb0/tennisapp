<?php

namespace App\Services\Sportradar;

class TournamentRegistry
{
    /**
     * Map Sportradar round names to our short codes.
     */
    const ROUND_MAP = [
        'qualification_round_1' => 'Q1',
        'qualification_round_2' => 'Q2',
        'qualification_round_3' => 'Q3',
        'round_of_128'          => 'R128',
        'round_of_64'           => 'R64',
        'round_of_32'           => 'R32',
        'round_of_16'           => 'R16',
        'quarterfinal'          => 'QF',
        'semifinal'             => 'SF',
        'final'                 => 'F',
    ];

    /**
     * Round labels in Spanish.
     */
    const ROUND_LABELS = [
        'Q1'   => 'Clasificación R1',
        'Q2'   => 'Clasificación R2',
        'Q3'   => 'Clasificación R3',
        'R128' => 'Ronda de 128',
        'R64'  => 'Ronda de 64',
        'R32'  => 'Ronda de 32',
        'R16'  => 'Octavos de Final',
        'QF'   => 'Cuartos de Final',
        'SF'   => 'Semifinal',
        'F'    => 'Final',
    ];

    /**
     * The 23 target tournaments (Singles only).
     * Keys are Sportradar competition IDs.
     */
    const TARGETS = [
        // ============ GRAND SLAMS ============
        // Australian Open
        'sr:competition:2567' => [
            'name' => 'Australian Open',
            'type' => 'GrandSlam',
            'surface' => 'Hard',
            'city' => 'Melbourne',
            'country' => 'Australia',
            'gender' => 'men',
            'category' => 'ATP',
        ],
        'sr:competition:2571' => [
            'name' => 'Australian Open',
            'type' => 'GrandSlam',
            'surface' => 'Hard',
            'city' => 'Melbourne',
            'country' => 'Australia',
            'gender' => 'women',
            'category' => 'WTA',
        ],
        // Roland Garros (French Open)
        'sr:competition:2579' => [
            'name' => 'Roland Garros',
            'type' => 'GrandSlam',
            'surface' => 'Clay',
            'city' => 'París',
            'country' => 'Francia',
            'gender' => 'men',
            'category' => 'ATP',
        ],
        'sr:competition:2583' => [
            'name' => 'Roland Garros',
            'type' => 'GrandSlam',
            'surface' => 'Clay',
            'city' => 'París',
            'country' => 'Francia',
            'gender' => 'women',
            'category' => 'WTA',
        ],
        // Wimbledon
        'sr:competition:2555' => [
            'name' => 'Wimbledon',
            'type' => 'GrandSlam',
            'surface' => 'Grass',
            'city' => 'Londres',
            'country' => 'Reino Unido',
            'gender' => 'men',
            'category' => 'ATP',
        ],
        'sr:competition:2559' => [
            'name' => 'Wimbledon',
            'type' => 'GrandSlam',
            'surface' => 'Grass',
            'city' => 'Londres',
            'country' => 'Reino Unido',
            'gender' => 'women',
            'category' => 'WTA',
        ],
        // US Open
        'sr:competition:2591' => [
            'name' => 'US Open',
            'type' => 'GrandSlam',
            'surface' => 'Hard',
            'city' => 'Nueva York',
            'country' => 'Estados Unidos',
            'gender' => 'men',
            'category' => 'ATP',
        ],
        'sr:competition:2595' => [
            'name' => 'US Open',
            'type' => 'GrandSlam',
            'surface' => 'Hard',
            'city' => 'Nueva York',
            'country' => 'Estados Unidos',
            'gender' => 'women',
            'category' => 'WTA',
        ],

        // ============ ATP MASTERS 1000 ============
        'sr:competition:2739' => [
            'name' => 'ATP Indian Wells',
            'type' => 'ATP_1000',
            'surface' => 'Hard',
            'city' => 'Indian Wells',
            'country' => 'Estados Unidos',
            'gender' => 'men',
            'category' => 'ATP',
        ],
        'sr:competition:2745' => [
            'name' => 'ATP Miami',
            'type' => 'ATP_1000',
            'surface' => 'Hard',
            'city' => 'Miami',
            'country' => 'Estados Unidos',
            'gender' => 'men',
            'category' => 'ATP',
        ],
        'sr:competition:3121' => [
            'name' => 'ATP Monte Carlo',
            'type' => 'ATP_1000',
            'surface' => 'Clay',
            'city' => 'Monte Carlo',
            'country' => 'Mónaco',
            'gender' => 'men',
            'category' => 'ATP',
        ],
        'sr:competition:2787' => [
            'name' => 'ATP Madrid',
            'type' => 'ATP_1000',
            'surface' => 'Clay',
            'city' => 'Madrid',
            'country' => 'España',
            'gender' => 'men',
            'category' => 'ATP',
        ],
        'sr:competition:2781' => [
            'name' => 'ATP Roma',
            'type' => 'ATP_1000',
            'surface' => 'Clay',
            'city' => 'Roma',
            'country' => 'Italia',
            'gender' => 'men',
            'category' => 'ATP',
        ],
        'sr:competition:2995' => [
            'name' => 'ATP Toronto',
            'type' => 'ATP_1000',
            'surface' => 'Hard',
            'city' => 'Toronto',
            'country' => 'Canadá',
            'gender' => 'men',
            'category' => 'ATP',
        ],
        'sr:competition:8285' => [
            'name' => 'ATP Montreal',
            'type' => 'ATP_1000',
            'surface' => 'Hard',
            'city' => 'Montreal',
            'country' => 'Canadá',
            'gender' => 'men',
            'category' => 'ATP',
        ],
        'sr:competition:2983' => [
            'name' => 'ATP Cincinnati',
            'type' => 'ATP_1000',
            'surface' => 'Hard',
            'city' => 'Cincinnati',
            'country' => 'Estados Unidos',
            'gender' => 'men',
            'category' => 'ATP',
        ],
        'sr:competition:3085' => [
            'name' => 'ATP Shanghai',
            'type' => 'ATP_1000',
            'surface' => 'Hard',
            'city' => 'Shanghái',
            'country' => 'China',
            'gender' => 'men',
            'category' => 'ATP',
        ],
        'sr:competition:2661' => [
            'name' => 'ATP Paris',
            'type' => 'ATP_1000',
            'surface' => 'Hard',
            'city' => 'París',
            'country' => 'Francia',
            'gender' => 'men',
            'category' => 'ATP',
        ],

        // ============ WTA 1000 ============
        'sr:competition:4221' => [
            'name' => 'WTA Doha',
            'type' => 'WTA_1000',
            'surface' => 'Hard',
            'city' => 'Doha',
            'country' => 'Qatar',
            'gender' => 'women',
            'category' => 'WTA',
        ],
        'sr:competition:4489' => [
            'name' => 'WTA Dubai',
            'type' => 'WTA_1000',
            'surface' => 'Hard',
            'city' => 'Dubái',
            'country' => 'Emiratos Árabes',
            'gender' => 'women',
            'category' => 'WTA',
        ],
        'sr:competition:4589' => [
            'name' => 'WTA Indian Wells',
            'type' => 'WTA_1000',
            'surface' => 'Hard',
            'city' => 'Indian Wells',
            'country' => 'Estados Unidos',
            'gender' => 'women',
            'category' => 'WTA',
        ],
        'sr:competition:4691' => [
            'name' => 'WTA Miami',
            'type' => 'WTA_1000',
            'surface' => 'Hard',
            'city' => 'Miami',
            'country' => 'Estados Unidos',
            'gender' => 'women',
            'category' => 'WTA',
        ],
        'sr:competition:4925' => [
            'name' => 'WTA Madrid',
            'type' => 'WTA_1000',
            'surface' => 'Clay',
            'city' => 'Madrid',
            'country' => 'España',
            'gender' => 'women',
            'category' => 'WTA',
        ],
        'sr:competition:4775' => [
            'name' => 'WTA Roma',
            'type' => 'WTA_1000',
            'surface' => 'Clay',
            'city' => 'Roma',
            'country' => 'Italia',
            'gender' => 'women',
            'category' => 'WTA',
        ],
        'sr:competition:8279' => [
            'name' => 'WTA Toronto',
            'type' => 'WTA_1000',
            'surface' => 'Hard',
            'city' => 'Toronto',
            'country' => 'Canadá',
            'gender' => 'women',
            'category' => 'WTA',
        ],
        'sr:competition:2977' => [
            'name' => 'WTA Montreal',
            'type' => 'WTA_1000',
            'surface' => 'Hard',
            'city' => 'Montreal',
            'country' => 'Canadá',
            'gender' => 'women',
            'category' => 'WTA',
        ],
        'sr:competition:8363' => [
            'name' => 'WTA Cincinnati',
            'type' => 'WTA_1000',
            'surface' => 'Hard',
            'city' => 'Cincinnati',
            'country' => 'Estados Unidos',
            'gender' => 'women',
            'category' => 'WTA',
        ],
        'sr:competition:8843' => [
            'name' => 'WTA Beijing',
            'type' => 'WTA_1000',
            'surface' => 'Hard',
            'city' => 'Beijing',
            'country' => 'China',
            'gender' => 'women',
            'category' => 'WTA',
        ],
        'sr:competition:11117' => [
            'name' => 'WTA Wuhan',
            'type' => 'WTA_1000',
            'surface' => 'Hard',
            'city' => 'Wuhan',
            'country' => 'China',
            'gender' => 'women',
            'category' => 'WTA',
        ],
    ];

    public static function isTarget(string $competitionId): bool
    {
        return isset(self::TARGETS[$competitionId]);
    }

    public static function getInfo(string $competitionId): ?array
    {
        return self::TARGETS[$competitionId] ?? null;
    }

    public static function mapRound(string $sportradarRound): string
    {
        return self::ROUND_MAP[$sportradarRound] ?? $sportradarRound;
    }

    public static function getRoundLabel(string $roundCode): string
    {
        return self::ROUND_LABELS[$roundCode] ?? $roundCode;
    }

    public static function getAllCompetitionIds(): array
    {
        return array_keys(self::TARGETS);
    }
}
