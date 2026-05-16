<?php

namespace App\Console\Commands;

use App\Models\Tournament;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Backfill `family_slug` on existing tournaments.
 *
 * For tournaments that already have `tennisexplorer_slug`, we copy that value
 * (since bracket.tennis uses the same slug for both ATP and WTA editions of
 * the same event). For tournaments without it, we derive a family slug from
 * the canonical short name + year so siblings still group correctly.
 *
 *   php artisan tennis:backfill-family-slugs
 */
class TennisBackfillFamilySlugs extends Command
{
    protected $signature = 'tennis:backfill-family-slugs';
    protected $description = 'Populate the family_slug column on existing tournaments';

    public function handle(): int
    {
        $tournaments = Tournament::whereNull('family_slug')->get();
        $updated = 0;

        foreach ($tournaments as $t) {
            $family = $this->resolveFamilySlug($t);
            if (!$family) continue;
            $t->update(['family_slug' => $family]);
            $this->line("  · {$t->name} ({$t->type}) → {$family}");
            $updated++;
        }

        $this->newLine();
        $this->info("✓ Backfilled {$updated} of {$tournaments->count()} tournaments.");
        return self::SUCCESS;
    }

    /**
     * Derive the family slug from whatever stable identifier the tournament has:
     *   1. tennisexplorer_slug (always shared between ATP/WTA when set)
     *   2. The short canonical name + season ("rome-2026", "roland-garros-2026")
     */
    private function resolveFamilySlug(Tournament $t): ?string
    {
        if ($t->tennisexplorer_slug) {
            return $t->tennisexplorer_slug;
        }

        // Try to extract the canonical name (drop the tour suffix from slug)
        $slug = $t->slug;
        if (!$slug) return null;
        $clean = preg_replace('/-(atp|wta)$/i', '', $slug);
        if ($t->season) {
            return $clean . '-' . $t->season;
        }
        return $clean;
    }
}
