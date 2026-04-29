<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\Tournament;
use App\Services\Tennis\MatchstatSyncService;
use Illuminate\Http\Request;

class ApiSyncController extends Controller
{
    public function index()
    {
        $lastSync = [
            'rankings'  => Setting::get('last_sync_rankings'),
            'live'      => Setting::get('last_sync_live'),
        ];

        // Cron health: heartbeat should be <2 min old if cron is running.
        $heartbeat = Setting::get('cron_heartbeat');
        $cronStatus = [
            'last' => $heartbeat,
            'ok'   => $heartbeat
                && \Illuminate\Support\Carbon::parse($heartbeat)->diffInMinutes(now()) <= 2,
        ];

        // Tournaments that have matchstat_tournament_id set (i.e. ready to auto-sync)
        $linkedTournaments = Tournament::whereNotNull('matchstat_tournament_id')
            ->orderByDesc('start_date')
            ->take(10)
            ->get();

        $unlinkedCount = Tournament::where('is_active', true)
            ->whereNull('matchstat_tournament_id')
            ->count();

        return view('admin.api-sync.index', compact('lastSync', 'linkedTournaments', 'unlinkedCount', 'cronStatus'));
    }

    public function syncRankings(MatchstatSyncService $sync)
    {
        try {
            $stats = $sync->syncRankings(200);
            Setting::set('last_sync_rankings', now()->toDateTimeString());
            return back()->with('success', "Rankings actualizados: ATP {$stats['atp']}, WTA {$stats['wta']}.");
        } catch (\Throwable $e) {
            return back()->with('error', 'Error al sincronizar rankings: ' . $e->getMessage());
        }
    }

    public function syncLive(MatchstatSyncService $sync, Request $request)
    {
        try {
            if ($request->filled('tournament_id')) {
                $t = Tournament::find($request->tournament_id);
                if (!$t) return back()->with('error', 'Torneo no encontrado.');
                $result = $sync->syncTournamentLive($t);
                Setting::set('last_sync_live', now()->toDateTimeString());
                return back()->with('success', "Sincronizado {$t->name}: " . json_encode($result));
            }

            $results = $sync->syncAllActive();
            Setting::set('last_sync_live', now()->toDateTimeString());
            return back()->with('success', 'Sync completo. ' . count($results) . ' torneo(s) procesado(s).');
        } catch (\Throwable $e) {
            return back()->with('error', 'Error al sincronizar: ' . $e->getMessage());
        }
    }
}
