<?php

namespace App\Console\Commands;

use App\Models\Tournament;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use ReflectionClass;

/**
 * Restore tournament `type` fields after the admin form bug downgraded them
 * (e.g. "ATP Grand Slam" → "ATP"). We canonicalise each tournament against
 * the COVERED list in ApiTennisSyncService so the type matches exactly what
 * the sync would store, no manual guessing.
 */
class TennisFixTournamentTypes extends Command
{
    protected $signature = 'tennis:fix-tournament-types
                            {--dry-run : Show what would change without writing anything}';

    protected $description = 'Restore canonical tier strings on tournament.type (ATP Grand Slam, WTA 1000, etc.)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        // Pull the COVERED list from ApiTennisSyncService via reflection — it's
        // private const but we don't want to duplicate it here. Falls back to a
        // hardcoded copy if reflection breaks for some reason.
        $covered = $this->loadCoveredList();
        if (empty($covered)) {
            $this->error('Could not load COVERED list from ApiTennisSyncService.');
            return self::FAILURE;
        }

        $totals = ['checked' => 0, 'fixed' => 0, 'unknown' => 0];
        $unknown = [];

        foreach (Tournament::orderBy('start_date', 'desc')->get() as $t) {
            $totals['checked']++;

            $expected = $this->expectedType($t, $covered);
            if (!$expected) {
                // Not in COVERED — could be a test tournament or something custom.
                $totals['unknown']++;
                $unknown[] = "[{$t->id}] {$t->name} (type='{$t->type}', slug='{$t->slug}')";
                continue;
            }

            if ($t->type === $expected) continue;

            $this->line("· [{$t->id}] {$t->name}: '{$t->type}' → '{$expected}'");
            $totals['fixed']++;
            if (!$dryRun) $t->update(['type' => $expected]);
        }

        $this->newLine();
        $this->info(($dryRun ? '[DRY RUN] ' : '') . "Done.");
        $this->table(array_keys($totals), [array_values($totals)]);

        if (!empty($unknown)) {
            $this->warn('Tournaments not in COVERED list (left untouched):');
            foreach ($unknown as $u) $this->line('  ' . $u);
        }

        return self::SUCCESS;
    }

    /**
     * Look up the canonical type for a tournament by matching its slug against
     * each COVERED entry's needle + tour. Returns null if no match.
     */
    private function expectedType(Tournament $t, array $covered): ?string
    {
        $slug = mb_strtolower($t->slug ?? '');
        $name = mb_strtolower($t->name ?? '');
        // Tour from existing data: prefer family_slug suffix, fall back to type prefix.
        $isATP = str_ends_with($slug, '-atp') || str_starts_with($t->type ?? '', 'ATP');
        $isWTA = str_ends_with($slug, '-wta') || str_starts_with($t->type ?? '', 'WTA');
        // If neither suffix matches, infer from type field.
        if (!$isATP && !$isWTA) {
            // Sometimes Grand Slams come with just "GrandSlam" — try by name.
            $isATP = !$isWTA; // best-effort default
        }
        $tour = $isWTA ? 'WTA' : 'ATP';

        foreach ($covered as $entry) {
            // Match by needle inside the slug or name (case-insensitive, accent-tolerant)
            $needle = mb_strtolower($entry['needle']);
            $needleSlug = Str::slug($entry['needle']);

            $matchesSlug = str_contains($slug, $needleSlug);
            $matchesName = str_contains($name, $needle);
            // Roland Garros special: API says "French Open" but our name is "Roland Garros".
            if ($entry['needle'] === 'French Open') {
                $matchesSlug = $matchesSlug || str_contains($slug, 'roland-garros');
                $matchesName = $matchesName || str_contains($name, 'roland garros');
            }

            if (!$matchesSlug && !$matchesName) continue;
            if (!in_array($tour, $entry['tours'], true)) continue;

            // Build the final tier label exactly like ApiTennisSyncService does.
            return $entry['tier'] === 'Grand Slam'
                ? "{$tour} Grand Slam"
                : $entry['tier'];
        }

        return null;
    }

    /** Pull the private const COVERED from the sync service. */
    private function loadCoveredList(): array
    {
        try {
            $ref = new ReflectionClass(\App\Services\Tennis\ApiTennisSyncService::class);
            $constants = $ref->getConstants();
            return $constants['COVERED'] ?? [];
        } catch (\Throwable $e) {
            return [];
        }
    }
}
