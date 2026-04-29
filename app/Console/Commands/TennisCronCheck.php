<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Models\Tournament;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Diagnostic command to verify the cron / scheduler is alive and working.
 *
 *   php artisan tennis:cron-check
 *
 * Reports:
 *   - last time the heartbeat scheduled task ran
 *   - last time each sync task succeeded
 *   - whether anything looks stale
 */
class TennisCronCheck extends Command
{
    protected $signature = 'tennis:cron-check';
    protected $description = 'Verify the Laravel scheduler/cron is running and tennis syncs are up to date';

    public function handle(): int
    {
        $this->newLine();
        $this->line('<fg=cyan;options=bold>Tennis cron health check</>');
        $this->line(str_repeat('─', 60));

        $checks = [
            $this->checkHeartbeat(),
            $this->checkSync('Rankings', 'last_sync_rankings', '7 days'),
            $this->checkSync('Live scores', 'last_sync_live', '15 minutes'),
            $this->checkLastSyncedTournament(),
        ];

        $this->newLine();
        $allOk = !in_array(false, array_column($checks, 'ok'), true);

        if ($allOk) {
            $this->info('✓ Todo en orden — el cron está funcionando.');
            return self::SUCCESS;
        }

        $this->warn('⚠ Algunas cosas se ven mal. Revisa el cron del servidor.');
        $this->line('  Línea esperada en crontab:');
        $this->line('  <fg=gray>* * * * * cd ' . base_path() . ' && php artisan schedule:run >> /dev/null 2>&1</>');
        return self::FAILURE;
    }

    private function checkHeartbeat(): array
    {
        $last = Setting::get('cron_heartbeat');
        if (!$last) {
            $this->row('Heartbeat', '✗', 'Nunca se ha registrado', 'red');
            return ['ok' => false];
        }
        $when = Carbon::parse($last);
        $minutes = $when->diffInMinutes(now());
        $ok = $minutes <= 5;
        $this->row(
            'Heartbeat',
            $ok ? '✓' : '✗',
            $when->diffForHumans() . " ({$minutes} min)",
            $ok ? 'green' : 'red'
        );
        return ['ok' => $ok];
    }

    private function checkSync(string $label, string $key, string $maxAge): array
    {
        $last = Setting::get($key);
        if (!$last) {
            $this->row($label, '–', 'Aún no se ha sincronizado', 'yellow');
            return ['ok' => true]; // not run yet is OK if just configured
        }
        $when = Carbon::parse($last);
        $maxAgeCarbon = now()->sub($maxAge);
        $ok = $when->gte($maxAgeCarbon);
        $this->row(
            $label,
            $ok ? '✓' : '⚠',
            $when->diffForHumans() . ' (max: ' . $maxAge . ')',
            $ok ? 'green' : 'yellow'
        );
        return ['ok' => $ok];
    }

    private function checkLastSyncedTournament(): array
    {
        $t = Tournament::whereNotNull('matchstat_tournament_id')
            ->whereNotNull('last_synced_at')
            ->orderByDesc('last_synced_at')
            ->first();
        if (!$t) {
            $this->row('Torneos enlazados', '–', 'Ninguno con matchstat_tournament_id sincronizado', 'yellow');
            return ['ok' => true];
        }
        $when = $t->last_synced_at;
        $minutes = $when->diffInMinutes(now());
        $this->row(
            'Último torneo sync',
            '✓',
            "{$t->name} · {$when->diffForHumans()} ({$minutes} min)",
            'green'
        );
        return ['ok' => true];
    }

    private function row(string $label, string $icon, string $value, string $color): void
    {
        $this->line(sprintf(
            '  <fg=%s>%s</> %-22s %s',
            $color,
            $icon,
            $label,
            $value
        ));
    }
}
