<?php

namespace Database\Seeders;

use App\Models\Player;
use Illuminate\Database\Seeder;

class PlayerSeeder extends Seeder
{
    public function run(): void
    {
        $atpPlayers = [
            ['name' => 'Jannik Sinner', 'country' => 'Italia', 'nationality_code' => 'ITA', 'ranking' => 1],
            ['name' => 'Alexander Zverev', 'country' => 'Alemania', 'nationality_code' => 'GER', 'ranking' => 2],
            ['name' => 'Carlos Alcaraz', 'country' => 'España', 'nationality_code' => 'ESP', 'ranking' => 3],
            ['name' => 'Novak Djokovic', 'country' => 'Serbia', 'nationality_code' => 'SRB', 'ranking' => 4],
            ['name' => 'Daniil Medvedev', 'country' => 'Rusia', 'nationality_code' => 'RUS', 'ranking' => 5],
            ['name' => 'Taylor Fritz', 'country' => 'Estados Unidos', 'nationality_code' => 'USA', 'ranking' => 6],
            ['name' => 'Casper Ruud', 'country' => 'Noruega', 'nationality_code' => 'NOR', 'ranking' => 7],
            ['name' => 'Alex de Minaur', 'country' => 'Australia', 'nationality_code' => 'AUS', 'ranking' => 8],
            ['name' => 'Andrey Rublev', 'country' => 'Rusia', 'nationality_code' => 'RUS', 'ranking' => 9],
            ['name' => 'Grigor Dimitrov', 'country' => 'Bulgaria', 'nationality_code' => 'BUL', 'ranking' => 10],
            ['name' => 'Stefanos Tsitsipas', 'country' => 'Grecia', 'nationality_code' => 'GRE', 'ranking' => 11],
            ['name' => 'Tommy Paul', 'country' => 'Estados Unidos', 'nationality_code' => 'USA', 'ranking' => 12],
            ['name' => 'Holger Rune', 'country' => 'Dinamarca', 'nationality_code' => 'DEN', 'ranking' => 13],
            ['name' => 'Ben Shelton', 'country' => 'Estados Unidos', 'nationality_code' => 'USA', 'ranking' => 14],
            ['name' => 'Hubert Hurkacz', 'country' => 'Polonia', 'nationality_code' => 'POL', 'ranking' => 15],
            ['name' => 'Frances Tiafoe', 'country' => 'Estados Unidos', 'nationality_code' => 'USA', 'ranking' => 16],
            ['name' => 'Lorenzo Musetti', 'country' => 'Italia', 'nationality_code' => 'ITA', 'ranking' => 17],
            ['name' => 'Ugo Humbert', 'country' => 'Francia', 'nationality_code' => 'FRA', 'ranking' => 18],
            ['name' => 'Sebastian Korda', 'country' => 'Estados Unidos', 'nationality_code' => 'USA', 'ranking' => 19],
            ['name' => 'Felix Auger-Aliassime', 'country' => 'Canadá', 'nationality_code' => 'CAN', 'ranking' => 20],
        ];

        foreach ($atpPlayers as $player) {
            Player::create(array_merge($player, ['category' => 'ATP']));
        }

        $wtaPlayers = [
            ['name' => 'Aryna Sabalenka', 'country' => 'Bielorrusia', 'nationality_code' => 'BLR', 'ranking' => 1],
            ['name' => 'Iga Swiatek', 'country' => 'Polonia', 'nationality_code' => 'POL', 'ranking' => 2],
            ['name' => 'Coco Gauff', 'country' => 'Estados Unidos', 'nationality_code' => 'USA', 'ranking' => 3],
            ['name' => 'Jasmine Paolini', 'country' => 'Italia', 'nationality_code' => 'ITA', 'ranking' => 4],
            ['name' => 'Elena Rybakina', 'country' => 'Kazajistán', 'nationality_code' => 'KAZ', 'ranking' => 5],
            ['name' => 'Qinwen Zheng', 'country' => 'China', 'nationality_code' => 'CHN', 'ranking' => 6],
            ['name' => 'Jessica Pegula', 'country' => 'Estados Unidos', 'nationality_code' => 'USA', 'ranking' => 7],
            ['name' => 'Emma Navarro', 'country' => 'Estados Unidos', 'nationality_code' => 'USA', 'ranking' => 8],
            ['name' => 'Daria Kasatkina', 'country' => 'Rusia', 'nationality_code' => 'RUS', 'ranking' => 9],
            ['name' => 'Barbora Krejcikova', 'country' => 'Chequia', 'nationality_code' => 'CZE', 'ranking' => 10],
            ['name' => 'Danielle Collins', 'country' => 'Estados Unidos', 'nationality_code' => 'USA', 'ranking' => 11],
            ['name' => 'Madison Keys', 'country' => 'Estados Unidos', 'nationality_code' => 'USA', 'ranking' => 12],
            ['name' => 'Beatriz Haddad Maia', 'country' => 'Brasil', 'nationality_code' => 'BRA', 'ranking' => 13],
            ['name' => 'Mirra Andreeva', 'country' => 'Rusia', 'nationality_code' => 'RUS', 'ranking' => 14],
            ['name' => 'Anna Kalinskaya', 'country' => 'Rusia', 'nationality_code' => 'RUS', 'ranking' => 15],
            ['name' => 'Marta Kostyuk', 'country' => 'Ucrania', 'nationality_code' => 'UKR', 'ranking' => 16],
            ['name' => 'Liudmila Samsonova', 'country' => 'Rusia', 'nationality_code' => 'RUS', 'ranking' => 17],
            ['name' => 'Diana Shnaider', 'country' => 'Rusia', 'nationality_code' => 'RUS', 'ranking' => 18],
            ['name' => 'Donna Vekic', 'country' => 'Croacia', 'nationality_code' => 'CRO', 'ranking' => 19],
            ['name' => 'Karolina Muchova', 'country' => 'Chequia', 'nationality_code' => 'CZE', 'ranking' => 20],
        ];

        foreach ($wtaPlayers as $player) {
            Player::create(array_merge($player, ['category' => 'WTA']));
        }
    }
}
