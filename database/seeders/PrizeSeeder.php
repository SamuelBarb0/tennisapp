<?php

namespace Database\Seeders;

use App\Models\Prize;
use Illuminate\Database\Seeder;

class PrizeSeeder extends Seeder
{
    public function run(): void
    {
        $prizes = [
            ['name' => 'Raqueta Wilson Pro Staff', 'description' => 'Raqueta profesional Wilson Pro Staff 97, la misma que usa Roger Federer.', 'points_required' => 5000, 'stock' => 3],
            ['name' => 'Smart TV 55" Samsung', 'description' => 'Televisor Samsung Crystal UHD 55 pulgadas con Smart TV.', 'points_required' => 8000, 'stock' => 1],
            ['name' => 'Audifonos Sony WH-1000XM5', 'description' => 'Auriculares inalámbricos con cancelación de ruido premium.', 'points_required' => 2500, 'stock' => 5],
            ['name' => 'Camiseta Nike Rafa Nadal', 'description' => 'Camiseta oficial Nike de la colección Rafa Nadal, talla a elegir.', 'points_required' => 800, 'stock' => 10],
            ['name' => 'Bolso Wilson Tour', 'description' => 'Bolso para raquetas Wilson Tour con capacidad para 6 raquetas.', 'points_required' => 1500, 'stock' => 4],
            ['name' => 'Pelota de tenis firmada', 'description' => 'Pelota de tenis oficial con certificado de autenticidad.', 'points_required' => 500, 'stock' => 15],
            ['name' => 'Gift Card Amazon $50', 'description' => 'Tarjeta de regalo Amazon por valor de $50 USD.', 'points_required' => 3000, 'stock' => 8],
            ['name' => 'Licuadora Ninja Professional', 'description' => 'Licuadora profesional Ninja de 1000W con jarra de 72oz.', 'points_required' => 2000, 'stock' => 3],
        ];

        foreach ($prizes as $prize) {
            Prize::create($prize);
        }
    }
}
