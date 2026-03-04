<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            PlayerSeeder::class,
            TournamentSeeder::class,
            MatchSeeder::class,
            PrizeSeeder::class,
            BannerSeeder::class,
            SettingSeeder::class,
        ]);
    }
}
