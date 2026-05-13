<?php

namespace App\Console\Commands;

use App\Models\Tournament;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * One-shot utility to rename tournaments to their official commercial names
 * after the canonical mapping in ApiTennisSyncService changes. Idempotent.
 *
 * Each tournament is matched by either its `api_tournament_key` or its
 * current name (so it works whether the tournament was imported by the old
 * "Rome" mapping or already has a fresh name).
 *
 *   php artisan tennis:rename-tournaments
 */
class TennisRenameTournaments extends Command
{
    protected $signature = 'tennis:rename-tournaments';
    protected $description = 'Apply commercial display names to existing tournaments';

    /**
     * Maps short canonical names → commercial names. Mirrors the
     * canonicalDisplayName() switch in ApiTennisSyncService.
     */
    private const RENAMES = [
        'Indian Wells'    => 'BNP Paribas Open',
        'Miami'           => 'Miami Open',
        'Monte Carlo'     => 'Rolex Monte-Carlo Masters',
        'Madrid'          => 'Mutua Madrid Open',
        'Rome'            => "Internazionali BNL d'Italia",
        'Montreal'        => 'National Bank Open (Montreal)',
        'Cincinnati'      => 'Cincinnati Open',
        'Shanghai'        => 'Rolex Shanghai Masters',
        'Paris'           => 'Rolex Paris Masters',
        'Doha'            => 'Qatar TotalEnergies Open',
        'Dubai'           => 'Dubai Duty Free Tennis Championships',
        'Toronto'         => 'National Bank Open (Toronto)',
        'Beijing'         => 'China Open',
        'Wuhan'           => 'Wuhan Open',
    ];

    public function handle(): int
    {
        $renamed = 0;
        foreach (self::RENAMES as $from => $to) {
            $tournaments = Tournament::where('name', $from)
                ->whereNotNull('api_tournament_key')
                ->where('api_tournament_key', 'NOT LIKE', 'test-%')
                ->get();
            foreach ($tournaments as $t) {
                $tour = str_starts_with($t->type, 'WTA') ? 'wta' : 'atp';
                // Keep slug stable but tour-suffixed: madrid-open-atp / miami-open-wta
                $newSlug = Str::slug($to) . '-' . $tour;
                $t->update(['name' => $to, 'slug' => $newSlug]);
                $this->line("  · {$from} ({$t->type}) → {$to}");
                $renamed++;
            }
        }
        $this->newLine();
        $this->info("✓ {$renamed} tournaments renamed.");
        return self::SUCCESS;
    }
}
