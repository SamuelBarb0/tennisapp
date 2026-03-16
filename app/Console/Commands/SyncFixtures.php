<?php

namespace App\Console\Commands;

use App\Services\ApiTennisService;
use App\Services\Sync\MatchSync;
use Illuminate\Console\Command;

class SyncFixtures extends Command
{
    protected $signature = 'tennis:sync-fixtures {--from= : Fecha inicio (Y-m-d)} {--to= : Fecha fin (Y-m-d)}';
    protected $description = 'Sincronizar partidos/fixtures desde API Tennis';

    public function handle(): int
    {
        $from = $this->option('from') ?: now()->format('Y-m-d');
        $to = $this->option('to') ?: now()->addDays(7)->format('Y-m-d');

        $this->info("Sincronizando partidos del {$from} al {$to}...");

        $sync = new MatchSync(app(ApiTennisService::class));
        $result = $sync->syncFixtures($from, $to);

        if (isset($result['error'])) {
            $this->error($result['error']);
            return self::FAILURE;
        }

        $this->info("Creados: {$result['created']} | Actualizados: {$result['updated']} | Omitidos: {$result['skipped']} | Predicciones evaluadas: {$result['predictionsScored']}");
        return self::SUCCESS;
    }
}
