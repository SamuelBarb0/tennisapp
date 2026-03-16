<?php

namespace App\Console\Commands;

use App\Services\ApiTennisService;
use App\Services\Sync\MatchSync;
use Illuminate\Console\Command;

class SyncLivescores extends Command
{
    protected $signature = 'tennis:sync-livescores';
    protected $description = 'Sincronizar marcadores en vivo desde API Tennis';

    public function handle(): int
    {
        $this->info('Sincronizando livescores...');

        $sync = new MatchSync(app(ApiTennisService::class));
        $result = $sync->syncLivescores();

        if (isset($result['error'])) {
            $this->error($result['error']);
            return self::FAILURE;
        }

        $this->info("Creados: {$result['created']} | Actualizados: {$result['updated']} | Omitidos: {$result['skipped']} | Predicciones evaluadas: {$result['predictionsScored']}");
        return self::SUCCESS;
    }
}
