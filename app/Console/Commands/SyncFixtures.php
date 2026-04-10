<?php

namespace App\Console\Commands;

use App\Models\Tournament;
use App\Services\SportradarService;
use App\Services\Sync\MatchSync;
use Illuminate\Console\Command;

class SyncFixtures extends Command
{
    protected $signature = 'tennis:sync-fixtures {--tournament= : ID del torneo específico}';
    protected $description = 'Sincronizar partidos de torneos activos desde Sportradar';

    public function handle(): int
    {
        $sync = new MatchSync(app(SportradarService::class));

        if ($tournamentId = $this->option('tournament')) {
            $tournament = Tournament::find($tournamentId);
            if (!$tournament) {
                $this->error("Torneo no encontrado: {$tournamentId}");
                return self::FAILURE;
            }

            $this->info("Sincronizando partidos de {$tournament->name}...");
            $result = $sync->syncTournament($tournament);
        } else {
            $this->info('Sincronizando partidos de todos los torneos activos...');
            $result = $sync->syncAll();
        }

        if (isset($result['error'])) {
            $this->error($result['error']);
            return self::FAILURE;
        }

        $this->info("Creados: {$result['created']} | Actualizados: {$result['updated']} | Omitidos: {$result['skipped']} | Predicciones: {$result['predictionsScored']}");
        return self::SUCCESS;
    }
}
