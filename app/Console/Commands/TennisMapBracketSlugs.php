<?php

namespace App\Console\Commands;

use App\Models\Tournament;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Auto-discovers the bracket.tennis URL slug for each tournament in the DB.
 *
 * For every tournament with an api_tournament_key (the customer's 23+
 * tournaments), tries a list of candidate slugs derived from the tournament
 * name and saves the first one that returns HTTP 200 from bracket.tennis.
 *
 *   php artisan tennis:map-bracket-slugs
 *   php artisan tennis:map-bracket-slugs --force   (re-run even if already set)
 *   php artisan tennis:map-bracket-slugs --year=2027
 *
 * Designed to run once per season — when a new year starts, the same
 * tournaments get fresh slugs (e.g. roland-garros-2027 instead of 2026).
 */
class TennisMapBracketSlugs extends Command
{
    protected $signature = 'tennis:map-bracket-slugs
                            {--force : Overwrite existing slugs}
                            {--year= : Override the year suffix (defaults to current)}';

    protected $description = 'Auto-discover bracket.tennis slugs for the customer\'s tournaments';

    /**
     * Manual overrides for tournaments whose canonical name doesn't match the
     * naive slug derivation. Keyed by `Tournament.name` — we accept BOTH the
     * canonical short names (used right after discovery) AND the commercial
     * names (after `tennis:rename-tournaments`), so this command is idempotent
     * regardless of which order the customer runs them in.
     *
     * Each value is a list of candidate slug bases to try (in priority order),
     * without the `-{year}` suffix.
     */
    private const NAME_OVERRIDES = [
        // Canonical short names (as discovered from api-tennis.com)
        'Roland Garros' => ['roland-garros'],
        'US Open'       => ['us-open'],
        'Miami'         => ['miami-open', 'miami'],
        'Monte Carlo'   => ['rolex-monte-carlo-masters', 'monte-carlo-masters'],
        'Madrid'        => ['madrid-open', 'mutua-madrid-open'],
        'Rome'          => ['internazionali-bnl-d-italia', 'internazionali-bnl-ditalia'],
        'Montreal'      => ['national-bank-open'],
        'Toronto'       => ['national-bank-open'],
        'Cincinnati'    => ['cincinnati-open', 'cincinnati'],
        'Shanghai'      => ['shanghai-masters', 'shanghai-rolex-masters'],
        'Paris'         => ['paris-masters', 'rolex-paris-masters'],
        'Doha'          => ['qatar-open', 'qatar-totalenergies-open'],
        'Dubai'         => ['dubai-duty-free-tennis-championships', 'dubai-tennis-championships'],
        'Beijing'       => ['china-open'],
        'Wuhan'         => ['wuhan-open'],
        'Indian Wells'  => ['indian-wells', 'bnp-paribas-open'],
        'Wimbledon'     => ['wimbledon'],
        'Australian Open' => ['australian-open'],

        // Commercial names (after rename-tournaments)
        'BNP Paribas Open'                       => ['indian-wells', 'bnp-paribas-open'],
        'Miami Open'                             => ['miami-open'],
        'Rolex Monte-Carlo Masters'              => ['rolex-monte-carlo-masters', 'monte-carlo-masters'],
        'Mutua Madrid Open'                      => ['madrid-open', 'mutua-madrid-open'],
        "Internazionali BNL d'Italia"            => ['internazionali-bnl-d-italia', 'internazionali-bnl-ditalia'],
        'National Bank Open (Montreal)'          => ['national-bank-open'],
        'National Bank Open (Toronto)'           => ['national-bank-open'],
        'Cincinnati Open'                        => ['cincinnati-open'],
        'Rolex Shanghai Masters'                 => ['shanghai-masters', 'shanghai-rolex-masters'],
        'Rolex Paris Masters'                    => ['paris-masters', 'rolex-paris-masters'],
        'Qatar TotalEnergies Open'               => ['qatar-open', 'qatar-totalenergies-open'],
        'Dubai Duty Free Tennis Championships'   => ['dubai-duty-free-tennis-championships'],
        'China Open'                             => ['china-open'],
        'Wuhan Open'                             => ['wuhan-open'],
    ];

    public function handle(): int
    {
        $year  = (int) ($this->option('year') ?: now()->year);
        $force = (bool) $this->option('force');

        $tournaments = Tournament::whereNotNull('api_tournament_key')
            ->where('api_tournament_key', 'NOT LIKE', 'test-%')
            ->when(!$force, fn($q) => $q->whereNull('tennisexplorer_slug'))
            ->orderBy('type')
            ->orderBy('name')
            ->get();

        if ($tournaments->isEmpty()) {
            $this->info('No tournaments need mapping. Use --force to re-check.');
            return self::SUCCESS;
        }

        $this->info("Mapping bracket.tennis slugs for {$tournaments->count()} tournaments (year={$year})…");
        $this->newLine();

        $found = $missing = 0;
        foreach ($tournaments as $t) {
            $tour = str_starts_with($t->type, 'WTA') ? 'wta' : 'atp';
            $candidates = $this->candidatesFor($t->name, $year);
            $hit = null;
            foreach ($candidates as $slug) {
                if ($this->urlExists($slug, $tour)) {
                    $hit = $slug;
                    break;
                }
            }

            if ($hit) {
                $t->update(['tennisexplorer_slug' => $hit]);
                $this->line(sprintf('  <fg=green>OK </>  %-32s [%s]  →  %s', $t->name, strtoupper($tour), $hit));
                $found++;
            } else {
                $this->line(sprintf('  <fg=red>--</>  %-32s [%s]  →  (no match, tried: %s)', $t->name, strtoupper($tour), implode(', ', $candidates)));
                $missing++;
            }
        }

        $this->newLine();
        $this->table(
            ['Found', 'Missing'],
            [[$found, $missing]],
        );

        return self::SUCCESS;
    }

    /**
     * Build the ordered list of candidate slugs to probe for one tournament.
     * Starts with manual overrides (most reliable), then falls back to a
     * naive slugified version of the name.
     */
    private function candidatesFor(string $name, int $year): array
    {
        $bases = self::NAME_OVERRIDES[$name] ?? [];
        // Always include the auto-slugified name as last resort
        $auto = \Illuminate\Support\Str::slug($name);
        if ($auto && !in_array($auto, $bases, true)) {
            $bases[] = $auto;
        }
        return array_map(fn($b) => "{$b}-{$year}", $bases);
    }

    /**
     * Quick HEAD-style check that bracket.tennis serves a real page for this
     * tournament slug. We use a GET because bracket.tennis returns the same
     * status for HEAD as for the SPA shell — instead we look for the
     * "Tournament Not Found" marker in the body.
     */
    private function urlExists(string $slug, string $tour): bool
    {
        try {
            $resp = Http::withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                ])
                ->timeout(10)
                ->get("https://bracket.tennis/tournaments/{$slug}/{$tour}");
        } catch (\Throwable) {
            return false;
        }
        if (!$resp->successful()) return false;
        $body = $resp->body();
        // The SPA renders this when the slug doesn't exist
        if (str_contains($body, 'Tournament Not Found')) return false;
        if (str_contains($body, 'No tournament with the id')) return false;
        return true;
    }
}
