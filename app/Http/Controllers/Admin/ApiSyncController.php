<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\SportradarService;
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
        $sync = new TournamentSync(app(SportradarService::class));
        $result = $sync->sync();

        if (isset($result['error'])) {
            return back()->with('error', $result['error']);
        }

        Setting::set('last_sync_tournaments', now()->toDateTimeString());

        return back()->with('success', "Torneos sincronizados: {$result['created']} creados, {$result['updated']} actualizados.");
    }

    public function syncPlayers()
    {
        $sync = new PlayerSync(app(SportradarService::class));
        $result = $sync->sync('all');

        if (isset($result['error'])) {
            return back()->with('error', $result['error']);
        }

        Setting::set('last_sync_players', now()->toDateTimeString());

        return back()->with('success', "Jugadores sincronizados: {$result['created']} creados, {$result['updated']} actualizados.");
    }

    public function syncFixtures(Request $request)
    {
        $sync = new MatchSync(app(SportradarService::class));
        $result = $sync->syncAll();

        if (isset($result['error'])) {
            return back()->with('error', $result['error']);
        }

        Setting::set('last_sync_fixtures', now()->toDateTimeString());

        return back()->with('success', "Partidos sincronizados: {$result['created']} creados, {$result['updated']} actualizados, {$result['skipped']} omitidos. Predicciones: {$result['predictionsScored']}.");
    }

    public function syncLivescores()
    {
        $sync = new MatchSync(app(SportradarService::class));
        $result = $sync->syncLive();

        if (isset($result['error'])) {
            return back()->with('error', $result['error']);
        }

        Setting::set('last_sync_livescores', now()->toDateTimeString());

        return back()->with('success', "Livescores sincronizados: {$result['created']} creados, {$result['updated']} actualizados. Predicciones: {$result['predictionsScored']}.");
    }

    public function syncAll()
    {
        $api = app(SportradarService::class);
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
        if (!isset($pResult['error'])) {
            Setting::set('last_sync_players', now()->toDateTimeString());
            $messages[] = "Jugadores: {$pResult['created']} creados, {$pResult['updated']} actualizados";
        }

        // Fixtures (all active tournaments)
        $mSync = new MatchSync($api);
        $mResult = $mSync->syncAll();
        if (!isset($mResult['error'])) {
            Setting::set('last_sync_fixtures', now()->toDateTimeString());
            $messages[] = "Partidos: {$mResult['created']} creados, {$mResult['updated']} actualizados";
        }

        return back()->with('success', implode(' | ', $messages));
    }
}
