<?php

namespace App\Console\Commands;

use App\Services\SportradarService;
use App\Services\Sync\PlayerSync;
use Illuminate\Console\Command;

class SyncPlayers extends Command
{
    protected $signature = 'tennis:sync-players {--category=all : ATP, WTA o all}';
    protected $description = 'Sincronizar jugadores y rankings desde Sportradar';

    public function handle(): int
    {
        $category = $this->option('category');
        $this->info("Sincronizando jugadores ({$category}) desde Sportradar...");

        $sync = new PlayerSync(app(SportradarService::class));
        $result = $sync->sync($category);

        if (isset($result['error'])) {
            $this->error($result['error']);
            return self::FAILURE;
        }

        $this->info("Creados: {$result['created']} | Actualizados: {$result['updated']}");
        return self::SUCCESS;
    }
}
