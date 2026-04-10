<?php

namespace App\Console\Commands;

use App\Services\SportradarService;
use App\Services\Sync\TournamentSync;
use Illuminate\Console\Command;

class SyncTournaments extends Command
{
    protected $signature = 'tennis:sync-tournaments';
    protected $description = 'Sincronizar torneos desde Sportradar';

    public function handle(): int
    {
        $this->info('Sincronizando torneos desde Sportradar...');

        $sync = new TournamentSync(app(SportradarService::class));
        $result = $sync->sync();

        if (isset($result['error'])) {
            $this->error($result['error']);
            return self::FAILURE;
        }

        $this->info("Creados: {$result['created']} | Actualizados: {$result['updated']} | Errores: {$result['errors']}");
        return self::SUCCESS;
    }
}
