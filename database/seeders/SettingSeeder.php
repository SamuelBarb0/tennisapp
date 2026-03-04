<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            'site_name' => 'TennisApp',
            'site_description' => 'La mejor plataforma de pronósticos de tenis profesional',
            'primary_color' => '#0071E3',
            'secondary_color' => '#34C759',
            'contact_email' => 'contacto@tennisapp.com',
            'instagram' => 'https://instagram.com/tennisapp',
            'twitter' => 'https://twitter.com/tennisapp',
            'facebook' => 'https://facebook.com/tennisapp',
            'points_per_correct' => '10',
            'bonus_champion' => '50',
        ];

        foreach ($settings as $key => $value) {
            Setting::set($key, $value);
        }
    }
}
