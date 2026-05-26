<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\TennisMatch;
use App\Models\Player;
use App\Models\Tournament;
use App\Services\Tennis\ApiTennisClient;
use App\Services\Tennis\ApiTennisSyncService;

$client = app(ApiTennisClient::class);
$svc = app(ApiTennisSyncService::class);

$resp = $client->fixtures("2026-03-06", "2026-03-08", ['tournament_key' => 1898]);
$fixtures = $resp['result'] ?? [];

$refUpsert = new ReflectionMethod($svc, 'upsertPlayerFromFixture');
$refUpsert->setAccessible(true);
$refMapRound = new ReflectionMethod($svc, 'mapRound');
$refMapRound->setAccessible(true);

$t = Tournament::find(85);

$stats = ['total' => 0, 'qualy' => 0, 'no_round' => 0, 'cancelled' => 0, 'no_player' => 0, 'found_existing' => 0, 'found_candidate' => 0, 'orphan' => 0, 'updated' => 0];

foreach ($fixtures as $f) {
    $stats['total']++;
    $eventKey = $f['event_key'] ?? null;
    if (!$eventKey) continue;

    $isQualy = ($f['event_qualification'] ?? null) === 'True' || ($f['event_qualification'] ?? null) === true;
    if ($isQualy) { $stats['qualy']++; continue; }

    $roundLabel = trim((string) ($f['tournament_round'] ?? ''));
    if ($roundLabel === '') { $stats['no_round']++; continue; }

    $apiStatus = mb_strtolower((string) ($f['event_status'] ?? ''));
    if (str_contains($apiStatus, 'cancelled')) { $stats['cancelled']++; continue; }

    $player1 = $refUpsert->invoke($svc, $f['first_player_key'] ?? null, $f['event_first_player'] ?? null, null, $t);
    $player2 = $refUpsert->invoke($svc, $f['second_player_key'] ?? null, $f['event_second_player'] ?? null, null, $t);
    $round = $refMapRound->invoke($svc, $f['tournament_round'] ?? '');

    $existing = TennisMatch::where('api_event_key', (string) $eventKey)->first();

    if ($existing) {
        $stats['found_existing']++;
        continue;
    }

    if (!$player1 || !$player2) { $stats['no_player']++; continue; }

    $candidates = TennisMatch::where('tournament_id', $t->id)
        ->where('round', $round)
        ->where(function ($q) {
            $q->where('api_event_key', 'LIKE', 'placeholder-%')
              ->orWhere('api_event_key', 'LIKE', 'bt-bootstrap-%')
              ->orWhereIn('status', ['pending', 'cancelled']);
        })
        ->orderBy('bracket_position')
        ->get();

    $matched = null;
    foreach ($candidates as $c) {
        $matchesP1 = $player1 && ($c->player1_id === $player1->id || $c->player2_id === $player1->id);
        $matchesP2 = $player2 && ($c->player1_id === $player2->id || $c->player2_id === $player2->id);
        if ($matchesP1 || $matchesP2) { $matched = $c; break; }
    }

    if ($matched) {
        $stats['found_candidate']++;
        echo "MATCHED key=$eventKey $round → existing pos={$matched->bracket_position}" . PHP_EOL;
    } else {
        $stats['orphan']++;
        echo "ORPHAN key=$eventKey $round {$f['event_first_player']} vs {$f['event_second_player']} (p1={$player1->id}, p2={$player2->id}, candidates_count=" . $candidates->count() . ")" . PHP_EOL;
        if ($candidates->count() > 0) {
            foreach ($candidates->take(3) as $c) {
                echo "  candidate pos={$c->bracket_position} p1={$c->player1_id} p2={$c->player2_id} status={$c->status}" . PHP_EOL;
            }
        }
    }
}

echo PHP_EOL . "STATS: " . json_encode($stats, JSON_PRETTY_PRINT) . PHP_EOL;
