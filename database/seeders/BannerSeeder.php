<?php

namespace Database\Seeders;

use App\Models\Banner;
use Illuminate\Database\Seeder;

class BannerSeeder extends Seeder
{
    public function run(): void
    {
        Banner::create([
            'title' => 'Indian Wells 2025',
            'subtitle' => 'Haz tus pronósticos y gana premios increíbles',
            'link' => '/tournaments',
            'is_active' => true,
            'order' => 1,
        ]);

        Banner::create([
            'title' => 'Únete a la comunidad',
            'subtitle' => 'Más de 10,000 aficionados ya hacen sus pronósticos aquí',
            'link' => '/register',
            'is_active' => true,
            'order' => 2,
        ]);

        Banner::create([
            'title' => 'Premios exclusivos',
            'subtitle' => 'Canjea tus puntos por raquetas, electrónicos y más',
            'link' => '/prizes',
            'is_active' => true,
            'order' => 3,
        ]);
    }
}
