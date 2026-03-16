<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\ApiTennisService;
use App\Services\Sync\TournamentSync;
use App\Services\Sync\PlayerSync;
use App\Services\Sync\MatchSync;
use Illuminate\Http\Request;

class ApiSyncController extends Controller
{
    public function index()
    {
        $lastSync = [
            'tournaments' => Setting::get('last_sync_tournaments'),
            'players' => Setting::get('last_sync_players'),
            'fixtures' => Setting::get('last_sync_fixtures'),
            'livescores' => Setting::get('last_sync_livescores'),
        ];

        return view('admin.api-sync.index', compact('lastSync'));
    }

    public function syncTournaments()
    {
        $sync = new TournamentSync(app(ApiTennisService::class));
        $result = $sync->sync();

        if (isset($result['error'])) {
            return back()->with('error', $result['error']);
        }

        Setting::set('last_sync_tournaments', now()->toDateTimeString());

        return back()->with('success', "Torneos sincronizados: {$result['created']} creados, {$result['updated']} actualizados, {$result['skipped']} omitidos.");
    }

    public function syncPlayers()
    {
        $sync = new PlayerSync(app(ApiTennisService::class));
        $result = $sync->sync('all');

        Setting::set('last_sync_players', now()->toDateTimeString());

        return back()->with('success', "Jugadores sincronizados: {$result['created']} creados, {$result['updated']} actualizados.");
    }

    public function syncFixtures(Request $request)
    {
        $from = $request->input('date_from', now()->format('Y-m-d'));
        $to = $request->input('date_to', now()->addDays(7)->format('Y-m-d'));

        $sync = new MatchSync(app(ApiTennisService::class));
        $result = $sync->syncFixtures($from, $to);

        if (isset($result['error'])) {
            return back()->with('error', $result['error']);
        }

        Setting::set('last_sync_fixtures', now()->toDateTimeString());

        return back()->with('success', "Partidos sincronizados: {$result['created']} creados, {$result['updated']} actualizados, {$result['skipped']} omitidos. Predicciones evaluadas: {$result['predictionsScored']}.");
    }

    public function syncLivescores()
    {
        $sync = new MatchSync(app(ApiTennisService::class));
        $result = $sync->syncLivescores();

        if (isset($result['error'])) {
            return back()->with('error', $result['error']);
        }

        Setting::set('last_sync_livescores', now()->toDateTimeString());

        return back()->with('success', "Livescores sincronizados: {$result['created']} creados, {$result['updated']} actualizados. Predicciones evaluadas: {$result['predictionsScored']}.");
    }

    public function syncAll()
    {
        $api = app(ApiTennisService::class);
        $messages = [];

        // Tournaments
        $tSync = new TournamentSync($api);
        $tResult = $tSync->sync();
        if (!isset($tResult['error'])) {
            Setting::set('last_sync_tournaments', now()->toDateTimeString());
            $messages[] = "Torneos: {$tResult['created']} creados, {$tResult['updated']} actualizados";
        }

        // Players
        $pSync = new PlayerSync($api);
        $pResult = $pSync->sync('all');
        Setting::set('last_sync_players', now()->toDateTimeString());
        $messages[] = "Jugadores: {$pResult['created']} creados, {$pResult['updated']} actualizados";

        // Fixtures (next 7 days)
        $mSync = new MatchSync($api);
        $mResult = $mSync->syncFixtures(now()->format('Y-m-d'), now()->addDays(7)->format('Y-m-d'));
        if (!isset($mResult['error'])) {
            Setting::set('last_sync_fixtures', now()->toDateTimeString());
            $messages[] = "Partidos: {$mResult['created']} creados, {$mResult['updated']} actualizados";
        }

        return back()->with('success', implode(' | ', $messages));
    }
}
