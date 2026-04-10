<?php

namespace App\Console\Commands;

use App\Services\SportradarService;
use App\Services\Sync\MatchSync;
use Illuminate\Console\Command;

class SyncLivescores extends Command
{
    protected $signature = 'tennis:sync-livescores';
    protected $description = 'Sincronizar marcadores en vivo desde Sportradar';

    public function handle(): int
    {
        $this->info('Sincronizando livescores...');

        $sync = new MatchSync(app(SportradarService::class));
        $result = $sync->syncLive();

        if (isset($result['error'])) {
            $this->error($result['error']);
            return self::FAILURE;
        }

        $this->info("Creados: {$result['created']} | Actualizados: {$result['updated']} | Predicciones: {$result['predictionsScored']}");
        return self::SUCCESS;
    }
}
