<?php

namespace App\Console\Commands;

use App\Services\ApiTennisService;
use App\Services\Sync\PlayerSync;
use Illuminate\Console\Command;

class SyncPlayers extends Command
{
    protected $signature = 'tennis:sync-players {--category=all : ATP, WTA o all}';
    protected $description = 'Sincronizar jugadores y rankings desde API Tennis';

    public function handle(): int
    {
        $category = $this->option('category');
        $this->info("Sincronizando jugadores ({$category})...");

        $sync = new PlayerSync(app(ApiTennisService::class));
        $result = $sync->sync($category);

        $this->info("Creados: {$result['created']} | Actualizados: {$result['updated']}");
        return self::SUCCESS;
    }
}
