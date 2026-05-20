<?php

namespace App\Console\Commands;

use App\Models\Tournament;
use App\Services\Tennis\BracketTennisScraper;
use Illuminate\Console\Command;

/**
 * Fill missing start_date / end_date on tournaments using bracket.tennis as
 * source. api-tennis.com only exposes dates inside the fixtures payload (2-3
 * days before each tournament), while bracket.tennis publishes them in the
 * page JSON weeks in advance.
 *
 * Idempotent — only updates rows where start_date OR end_date is NULL.
 *
 *   php artisan tennis:fill-tournament-dates
 *   php artisan tennis:fill-tournament-dates --force   (re-fetch even if set)
 */
class TennisFillTournamentDates extends Command
{
    protected $signature = 'tennis:fill-tournament-dates {--force : Overwrite existing dates}';
    protected $description = 'Populate tournament start/end dates from bracket.tennis';

    public function handle(BracketTennisScraper $scraper): int
    {
        $query = Tournament::whereNotNull('tennisexplorer_slug');
        if (!$this->option('force')) {
            $query->where(function ($q) {
                $q->whereNull('start_date')->orWhereNull('end_date');
            });
        }
        $tournaments = $query->get();

        if ($tournaments->isEmpty()) {
            $this->info('No tournaments need date filling. Use --force to refresh.');
            return self::SUCCESS;
        }

        $this->info("Filling dates for {$tournaments->count()} tournaments…");
        $this->newLine();

        $updated = 0;
        foreach ($tournaments as $t) {
            $tour = str_starts_with($t->type, 'WTA') ? 'wta' : 'atp';
            $dates = $scraper->dates($t->tennisexplorer_slug, $tour);

            if (!($dates['start'] ?? null) || !($dates['end'] ?? null)) {
                $this->line(sprintf('  <fg=red>--</>  %-32s [%s]  →  (no dates on BT)', $t->name, strtoupper($tour)));
                continue;
            }

            $t->update([
                'start_date' => $dates['start'],
                'end_date'   => $dates['end'],
            ]);
            $this->line(sprintf('  <fg=green>OK</>  %-32s [%s]  →  %s → %s',
                $t->name, strtoupper($tour), $dates['start'], $dates['end']));
            $updated++;
        }

        $this->newLine();
        $this->info("✓ Filled dates on {$updated} tournaments.");
        return self::SUCCESS;
    }
}
