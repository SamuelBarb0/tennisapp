<?php

namespace App\Console\Commands;

use App\Models\Tournament;
use Illuminate\Console\Command;

/**
 * Bulk-set the timezone column on tournaments by matching against name /
 * family_slug. Run once after the migration, and re-run after adding new
 * tournaments to the schedule.
 *
 * Tournaments not in this list keep their default (America/Bogota), which
 * means times from api-tennis get stored verbatim — correct for tournaments
 * whose venue is unknown or that genuinely play in Bogotá time.
 */
class TennisSetTimezones extends Command
{
    protected $signature = 'tennis:set-timezones';
    protected $description = 'Asigna la zona horaria del recinto a cada torneo conocido (Grand Slams + Masters 1000 + WTA 1000).';

    /**
     * Venue timezones for the tournaments we cover. Keyed by a substring of
     * the tournament name OR family_slug — match is case-insensitive.
     *
     * Sources: official tournament sites + Wikipedia "host city" timezones.
     */
    private const TZ_MAP = [
        // Grand Slams
        'roland-garros'   => 'Europe/Paris',
        'french-open'     => 'Europe/Paris',
        'wimbledon'       => 'Europe/London',
        'us-open'         => 'America/New_York',
        'australian-open' => 'Australia/Melbourne',

        // ATP Masters 1000
        'indian-wells'    => 'America/Los_Angeles',
        'miami'           => 'America/New_York',
        'monte-carlo'     => 'Europe/Monaco',
        'madrid'          => 'Europe/Madrid',
        'rome'            => 'Europe/Rome',
        'italian-open'    => 'Europe/Rome',
        'national-bank-open-montreal' => 'America/Toronto',
        'national-bank-open-toronto'  => 'America/Toronto',
        'cincinnati'      => 'America/New_York',
        'shanghai'        => 'Asia/Shanghai',
        'paris'           => 'Europe/Paris',     // Rolex Paris Masters

        // WTA 1000
        'dubai'           => 'Asia/Dubai',
        'doha'            => 'Asia/Qatar',
        'china-open'      => 'Asia/Shanghai',
        'beijing'         => 'Asia/Shanghai',
        'wuhan'           => 'Asia/Shanghai',
        'guadalajara'     => 'America/Mexico_City',
        'berlin'          => 'Europe/Berlin',
        'eastbourne'      => 'Europe/London',
    ];

    public function handle(): int
    {
        $updated = 0;
        $unchanged = 0;
        $unknown = [];

        foreach (Tournament::all() as $t) {
            $tz = $this->resolveTimezone($t);
            if (!$tz) {
                $unknown[] = $t->name . ' [' . $t->type . ']';
                continue;
            }
            if ($t->timezone === $tz) {
                $unchanged++;
                continue;
            }
            $t->update(['timezone' => $tz]);
            $this->line("  → {$t->name} [{$t->type}]: {$tz}");
            $updated++;
        }

        $this->info(PHP_EOL . "Updated: {$updated}. Unchanged: {$unchanged}.");
        if ($unknown) {
            $this->warn('Sin timezone conocido (se queda con default America/Bogota):');
            foreach ($unknown as $n) $this->line("  - {$n}");
        }
        return self::SUCCESS;
    }

    private function resolveTimezone(Tournament $t): ?string
    {
        $haystacks = array_filter([
            strtolower($t->family_slug ?? ''),
            strtolower($t->slug ?? ''),
            strtolower($t->name ?? ''),
        ]);

        foreach (self::TZ_MAP as $needle => $tz) {
            foreach ($haystacks as $h) {
                if (str_contains($h, $needle)) return $tz;
            }
        }
        return null;
    }
}
