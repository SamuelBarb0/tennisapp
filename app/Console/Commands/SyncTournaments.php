<?php

namespace App\Console\Commands;

use App\Services\ApiTennisService;
use App\Services\Sync\TournamentSync;
use Illuminate\Console\Command;

class SyncTournaments extends Command
{
    protected $signature = 'tennis:sync-tournaments';
    protected $description = 'Sincronizar torneos desde API Tennis';

    public function handle(): int
    {
        $this->info('Sincronizando torneos...');

        $sync = new TournamentSync(app(ApiTennisService::class));
        $result = $sync->sync();

        if (isset($result['error'])) {
            $this->error($result['error']);
            return self::FAILURE;
        }

        $this->info("Creados: {$result['created']} | Actualizados: {$result['updated']} | Omitidos: {$result['skipped']}");
        return self::SUCCESS;
    }
}
