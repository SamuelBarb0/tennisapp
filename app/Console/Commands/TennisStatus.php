<?php

namespace App\Console\Commands;

use App\Models\Tournament;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Diagnostic snapshot for every customer tournament. Shows at a glance:
 *   - Days until start
 *   - Whether api-tennis.com has fixtures
 *   - Whether bracket.tennis has the draw published
 *   - Whether the local DB is ready (matches loaded + bracket reordered)
 *
 *   php artisan tennis:status
 *   php artisan tennis:status --upcoming   (skip past tournaments)
 *   php artisan tennis:status --slug=rome  (single tournament)
 */
class TennisStatus extends Command
{
    protected $signature = 'tennis:status
                            {--upcoming : Only show tournaments that haven\'t finished}
                            {--slug= : Inspect one specific tournament by slug}';

    protected $description = 'Diagnose the data pipeline status of every tournament';

    public function handle(): int
    {
        $query = Tournament::whereNotNull('api_tournament_key')
            ->where('api_tournament_key', 'NOT LIKE', 'test-%');

        if ($this->option('slug')) {
            $query->where('slug', $this->option('slug'));
        } elseif ($this->option('upcoming')) {
            $query->where(function ($q) {
                $q->whereNull('end_date')
                  ->orWhere('end_date', '>=', now()->subDays(3));
            });
        }

        $tournaments = $query->orderByRaw('COALESCE(start_date, "9999-12-31") ASC')->get();

        if ($tournaments->isEmpty()) {
            $this->warn('No tournaments matched.');
            return self::SUCCESS;
        }

        $rows = [];
        foreach ($tournaments as $t) {
            $rows[] = $this->inspectTournament($t);
        }

        $this->table(
            ['Torneo', 'Tour', 'Fecha', 'Días', 'API', 'BT', 'Matches', 'Estado'],
            $rows,
        );

        return self::SUCCESS;
    }

    private function inspectTournament(Tournament $t): array
    {
        $tour = str_starts_with($t->type, 'WTA') ? 'WTA' : 'ATP';
        $start = $t->start_date?->format('d M') ?? '—';
        $daysOut = $t->start_date ? (int) now()->startOfDay()->diffInDays($t->start_date, false) : null;

        // api-tennis: do we have any non-placeholder matches?
        $matchCount = $t->matches()
            ->where('api_event_key', 'NOT LIKE', 'placeholder-%')
            ->count();
        $apiOk = $matchCount > 0 ? '✓' : '—';

        // bracket.tennis: is the slug set AND the page responds with a real draw?
        $btOk = '—';
        if ($t->tennisexplorer_slug) {
            $btOk = $this->bracketTennisHasDraw($t->tennisexplorer_slug, strtolower($tour)) ? '✓' : '○';
        }

        $estado = match (true) {
            $matchCount === 0 && $btOk === '✓'      => '⏳ Esperando fixtures (BT listo)',
            $matchCount > 0 && $btOk === '✓'        => '✅ Habilitado',
            $matchCount > 0 && $btOk !== '✓'        => '⚠ Bracket sin orden oficial',
            $daysOut !== null && $daysOut > 7        => '🗓 Próximamente',
            $daysOut !== null && $daysOut < -7       => '✓ Finalizado',
            default                                  => '⏳ Sin datos',
        };

        return [
            $t->name,
            $tour,
            $start,
            $daysOut === null ? '?' : ($daysOut > 0 ? "+{$daysOut}" : (string) $daysOut),
            $apiOk,
            $btOk,
            $matchCount,
            $estado,
        ];
    }

    /**
     * Cheap check: fetch the bracket.tennis page and look for the
     * "Tournament Not Found" marker. We don't parse the draw here — that's
     * the BracketTennisScraper's job during the real sync.
     */
    private function bracketTennisHasDraw(string $slug, string $tour): bool
    {
        try {
            $resp = Http::timeout(8)->withHeaders([
                'User-Agent' => 'Mozilla/5.0',
            ])->get("https://bracket.tennis/tournaments/{$slug}/{$tour}");
        } catch (\Throwable) {
            return false;
        }
        if (!$resp->successful()) return false;
        $body = $resp->body();
        if (str_contains($body, 'Tournament Not Found')) return false;
        // Real draws contain data-match-id attributes
        return str_contains($body, 'data-match-id="0-');
    }
}
