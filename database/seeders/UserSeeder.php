<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'name' => 'Administrador',
            'email' => 'admin@tennisapp.com',
            'password' => bcrypt('password'),
            'is_admin' => true,
            'points' => 0,
            'email_verified_at' => now(),
        ]);

        $users = [
            ['name' => 'Carlos Rodríguez', 'email' => 'carlos@example.com', 'points' => 1250],
            ['name' => 'María López', 'email' => 'maria@example.com', 'points' => 980],
            ['name' => 'Andrés Gómez', 'email' => 'andres@example.com', 'points' => 870],
            ['name' => 'Valentina Díaz', 'email' => 'valentina@example.com', 'points' => 750],
            ['name' => 'Santiago Martínez', 'email' => 'santiago@example.com', 'points' => 650],
            ['name' => 'Camila Torres', 'email' => 'camila@example.com', 'points' => 540],
            ['name' => 'Sebastián Herrera', 'email' => 'sebastian@example.com', 'points' => 430],
            ['name' => 'Laura Ramírez', 'email' => 'laura@example.com', 'points' => 320],
            ['name' => 'Diego Morales', 'email' => 'diego@example.com', 'points' => 210],
            ['name' => 'Isabella Castro', 'email' => 'isabella@example.com', 'points' => 150],
        ];

        foreach ($users as $user) {
            User::create(array_merge($user, [
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
            ]));
        }
    }
}
