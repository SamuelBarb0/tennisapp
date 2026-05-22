<?php

namespace App\Console\Commands;

use App\Models\Tournament;
use App\Services\Tennis\PredictionRealigner;
use Illuminate\Console\Command;

class TennisRealignPredictions extends Command
{
    protected $signature = 'tennis:realign-predictions
                            {--tournament= : Tournament id to process (otherwise all active)}';

    protected $description = 'Repara predicciones de usuarios cuando un sync movió jugadores entre posiciones del cuadro.';

    public function handle(PredictionRealigner $realigner): int
    {
        $tournaments = $this->option('tournament')
            ? Tournament::where('id', $this->option('tournament'))->get()
            : Tournament::whereIn('status', ['upcoming', 'in_progress', 'live'])->get();

        foreach ($tournaments as $t) {
            $r = $realigner->realign($t);
            $this->info("→ {$t->name} [{$t->type}] (id={$t->id})  promovidas={$r['promoted']} migradas={$r['moved']} huérfanas={$r['orphaned']}");
        }

        return self::SUCCESS;
    }
}
