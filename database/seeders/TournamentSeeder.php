<?php

namespace Database\Seeders;

use App\Models\Tournament;
use Illuminate\Database\Seeder;

class TournamentSeeder extends Seeder
{
    public function run(): void
    {
        $tournaments = [
            // Grand Slams
            ['name' => 'Australian Open 2025', 'type' => 'GrandSlam', 'location' => 'Melbourne Park', 'city' => 'Melbourne', 'country' => 'Australia', 'surface' => 'Duro', 'start_date' => '2025-01-13', 'end_date' => '2025-01-26', 'points_multiplier' => 2.0],
            ['name' => 'Roland Garros 2025', 'type' => 'GrandSlam', 'location' => 'Stade Roland Garros', 'city' => 'París', 'country' => 'Francia', 'surface' => 'Arcilla', 'start_date' => '2025-05-25', 'end_date' => '2025-06-08', 'points_multiplier' => 2.0],
            ['name' => 'Wimbledon 2025', 'type' => 'GrandSlam', 'location' => 'All England Club', 'city' => 'Londres', 'country' => 'Reino Unido', 'surface' => 'Hierba', 'start_date' => '2025-06-30', 'end_date' => '2025-07-13', 'points_multiplier' => 2.0],
            ['name' => 'US Open 2025', 'type' => 'GrandSlam', 'location' => 'USTA Billie Jean King National Tennis Center', 'city' => 'Nueva York', 'country' => 'Estados Unidos', 'surface' => 'Duro', 'start_date' => '2025-08-25', 'end_date' => '2025-09-07', 'points_multiplier' => 2.0],

            // ATP Masters 1000
            ['name' => 'BNP Paribas Open', 'type' => 'ATP', 'location' => 'Indian Wells Tennis Garden', 'city' => 'Indian Wells', 'country' => 'Estados Unidos', 'surface' => 'Duro', 'start_date' => '2025-03-04', 'end_date' => '2025-03-15', 'points_multiplier' => 1.5],
            ['name' => 'Miami Open', 'type' => 'ATP', 'location' => 'Hard Rock Stadium', 'city' => 'Miami', 'country' => 'Estados Unidos', 'surface' => 'Duro', 'start_date' => '2025-03-18', 'end_date' => '2025-03-29', 'points_multiplier' => 1.5],
            ['name' => 'Rolex Monte-Carlo Masters', 'type' => 'ATP', 'location' => 'Monte-Carlo Country Club', 'city' => 'Mónaco', 'country' => 'Mónaco', 'surface' => 'Arcilla', 'start_date' => '2025-04-05', 'end_date' => '2025-04-12', 'points_multiplier' => 1.5],
            ['name' => 'Mutua Madrid Open', 'type' => 'ATP', 'location' => 'Caja Mágica', 'city' => 'Madrid', 'country' => 'España', 'surface' => 'Arcilla', 'start_date' => '2025-04-22', 'end_date' => '2025-05-03', 'points_multiplier' => 1.5],
            ['name' => 'Internazionali BNL d\'Italia', 'type' => 'ATP', 'location' => 'Foro Itálico', 'city' => 'Roma', 'country' => 'Italia', 'surface' => 'Arcilla', 'start_date' => '2025-05-06', 'end_date' => '2025-05-17', 'points_multiplier' => 1.5],
            ['name' => 'Canadian Open', 'type' => 'ATP', 'location' => 'IGA Stadium', 'city' => 'Montreal', 'country' => 'Canadá', 'surface' => 'Duro', 'start_date' => '2025-08-02', 'end_date' => '2025-08-12', 'points_multiplier' => 1.5],
            ['name' => 'Cincinnati Open', 'type' => 'ATP', 'location' => 'Lindner Family Tennis Center', 'city' => 'Cincinnati', 'country' => 'Estados Unidos', 'surface' => 'Duro', 'start_date' => '2025-08-13', 'end_date' => '2025-08-23', 'points_multiplier' => 1.5],
            ['name' => 'Rolex Shanghai Masters', 'type' => 'ATP', 'location' => 'Qizhong Forest Sports City Arena', 'city' => 'Shanghái', 'country' => 'China', 'surface' => 'Duro', 'start_date' => '2025-10-07', 'end_date' => '2025-10-18', 'points_multiplier' => 1.5],
            ['name' => 'Rolex Paris Masters', 'type' => 'ATP', 'location' => 'Accor Arena', 'city' => 'París', 'country' => 'Francia', 'surface' => 'Duro (Indoor)', 'start_date' => '2025-11-02', 'end_date' => '2025-11-08', 'points_multiplier' => 1.5],

            // WTA 1000
            ['name' => 'Qatar TotalEnergies Open', 'type' => 'WTA', 'location' => 'Khalifa International Tennis Complex', 'city' => 'Doha', 'country' => 'Catar', 'surface' => 'Duro', 'start_date' => '2025-02-09', 'end_date' => '2025-02-15', 'points_multiplier' => 1.5],
            ['name' => 'Dubai Duty Free Tennis Championships', 'type' => 'WTA', 'location' => 'Dubai Tennis Stadium', 'city' => 'Dubái', 'country' => 'EAU', 'surface' => 'Duro', 'start_date' => '2025-02-16', 'end_date' => '2025-02-22', 'points_multiplier' => 1.5],
            ['name' => 'BNP Paribas Open WTA', 'type' => 'WTA', 'location' => 'Indian Wells Tennis Garden', 'city' => 'Indian Wells', 'country' => 'Estados Unidos', 'surface' => 'Duro', 'start_date' => '2025-03-04', 'end_date' => '2025-03-15', 'points_multiplier' => 1.5],
            ['name' => 'Miami Open WTA', 'type' => 'WTA', 'location' => 'Hard Rock Stadium', 'city' => 'Miami', 'country' => 'Estados Unidos', 'surface' => 'Duro', 'start_date' => '2025-03-18', 'end_date' => '2025-03-29', 'points_multiplier' => 1.5],
            ['name' => 'Madrid Open WTA', 'type' => 'WTA', 'location' => 'Caja Mágica', 'city' => 'Madrid', 'country' => 'España', 'surface' => 'Arcilla', 'start_date' => '2025-04-22', 'end_date' => '2025-05-03', 'points_multiplier' => 1.5],
            ['name' => 'Italian Open WTA', 'type' => 'WTA', 'location' => 'Foro Itálico', 'city' => 'Roma', 'country' => 'Italia', 'surface' => 'Arcilla', 'start_date' => '2025-05-06', 'end_date' => '2025-05-17', 'points_multiplier' => 1.5],
            ['name' => 'Canadian Open WTA', 'type' => 'WTA', 'location' => 'Sobeys Stadium', 'city' => 'Toronto', 'country' => 'Canadá', 'surface' => 'Duro', 'start_date' => '2025-08-02', 'end_date' => '2025-08-12', 'points_multiplier' => 1.5],
            ['name' => 'Cincinnati Open WTA', 'type' => 'WTA', 'location' => 'Lindner Family Tennis Center', 'city' => 'Cincinnati', 'country' => 'Estados Unidos', 'surface' => 'Duro', 'start_date' => '2025-08-13', 'end_date' => '2025-08-23', 'points_multiplier' => 1.5],
            ['name' => 'China Open', 'type' => 'WTA', 'location' => 'National Tennis Center', 'city' => 'Beijing', 'country' => 'China', 'surface' => 'Duro', 'start_date' => '2025-10-01', 'end_date' => '2025-10-12', 'points_multiplier' => 1.5],
            ['name' => 'Wuhan Open', 'type' => 'WTA', 'location' => 'Optics Valley International Tennis Center', 'city' => 'Wuhan', 'country' => 'China', 'surface' => 'Duro', 'start_date' => '2025-10-15', 'end_date' => '2025-10-26', 'points_multiplier' => 1.5],
        ];

        foreach ($tournaments as $tournament) {
            Tournament::create($tournament);
        }
    }
}
