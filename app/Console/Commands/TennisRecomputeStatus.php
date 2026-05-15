<?php

namespace App\Console\Commands;

use App\Models\Tournament;
use Illuminate\Console\Command;

/**
 * Recompute the `status` of every tournament from its start/end dates.
 *
 *   - now < start_date → 'upcoming'
 *   - start <= now <= end → 'in_progress'
 *   - now > end_date → 'finished'
 *
 * Necessary because the live-sync only touches `status` when fixtures arrive
 * from api-tennis. Once a tournament ends, no more fixtures come in and the
 * status would stay 'in_progress' forever otherwise.
 *
 * Idempotent — safe to run every day from the scheduler.
 */
class TennisRecomputeStatus extends Command
{
    protected $signature = 'tennis:recompute-status';
    protected $description = 'Recompute tournament status based on start/end dates';

    public function handle(): int
    {
        $now = now()->startOfDay();
        $updated = 0;

        $tournaments = Tournament::whereNotNull('start_date')
            ->whereNotNull('end_date')
            ->get();

        foreach ($tournaments as $t) {
            $newStatus = match (true) {
                $now->lt($t->start_date) => 'upcoming',
                $now->gt($t->end_date)   => 'finished',
                default                  => 'in_progress',
            };
            if ($t->status !== $newStatus) {
                $t->update(['status' => $newStatus]);
                $updated++;
                $this->line("  · {$t->name} ({$t->type}): {$t->status} → {$newStatus}");
            }
        }

        $this->newLine();
        $this->info("✓ Recomputed {$tournaments->count()} tournaments, {$updated} updated.");
        return self::SUCCESS;
    }
}
