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

$resp = $client->fixtures("2026-05-29", "2026-05-31", ['tournament_key' => 2155]);
$fixture = null;
foreach ($resp['result'] ?? [] as $f) {
    if (($f['event_key'] ?? null) == 12132000) { $fixture = $f; break; }
}
if (!$fixture) { echo "Fixture 12132000 no encontrado\n"; exit; }

echo "Fixture: {$fixture['event_first_player']} vs {$fixture['event_second_player']}\n";
echo "Round: {$fixture['tournament_round']}\n";
echo "Status: {$fixture['event_status']}\n";
echo "Qualy: " . ($fixture['event_qualification'] ?? '?') . "\n\n";

$refUpsert = new ReflectionMethod($svc, 'upsertPlayerFromFixture');
$refUpsert->setAccessible(true);
$refMapRound = new ReflectionMethod($svc, 'mapRound');
$refMapRound->setAccessible(true);
$refCompactTokens = new ReflectionMethod($svc, 'compactTokens');
$refCompactTokens->setAccessible(true);
$refFirstNamePrefix = new ReflectionMethod($svc, 'firstNamePrefix');
$refFirstNamePrefix->setAccessible(true);

$t = Tournament::find(61);

$player1 = $refUpsert->invoke($svc, $fixture['first_player_key'] ?? null, $fixture['event_first_player'] ?? null, null, $t);
$player2 = $refUpsert->invoke($svc, $fixture['second_player_key'] ?? null, $fixture['event_second_player'] ?? null, null, $t);
$round = $refMapRound->invoke($svc, $fixture['tournament_round'] ?? '');

echo "Resolved: player1=" . ($player1?->id ?? 'NULL') . " ({$player1?->name}), player2=" . ($player2?->id ?? 'NULL') . " ({$player2?->name})\n";
echo "Round: $round\n\n";

// Pass 0
$existing = TennisMatch::where('api_event_key', '12132000')->first();
echo "Pass 0 (api_event_key): " . ($existing ? "FOUND id={$existing->id}" : "NOT FOUND") . "\n";

if (!$existing) {
    $candidates = TennisMatch::where('tournament_id', $t->id)
        ->where('round', $round)
        ->where(function ($q) {
            $q->where('api_event_key', 'LIKE', 'placeholder-%')
              ->orWhere('api_event_key', 'LIKE', 'bt-bootstrap-%')
              ->orWhereIn('status', ['pending', 'cancelled']);
        })
        ->orderBy('bracket_position')
        ->get();
    echo "Candidates count: " . $candidates->count() . "\n";

    // Pass 1
    foreach ($candidates as $c) {
        $matchesP1 = $player1 && ($c->player1_id === $player1->id || $c->player2_id === $player1->id);
        $matchesP2 = $player2 && ($c->player1_id === $player2->id || $c->player2_id === $player2->id);
        if ($matchesP1 || $matchesP2) {
            echo "Pass 1: MATCH at pos={$c->bracket_position}\n";
            $existing = $c;
            break;
        }
    }
    if (!$existing) echo "Pass 1: no match\n";
}

// Pass 1.5 manual simulation
if (!$existing) {
    echo "\nSimulating Pass 1.5:\n";
    foreach ($candidates as $c) {
        echo "  Trying candidate pos={$c->bracket_position}: p1={$c->player1_id}({$c->player1?->name}) p2={$c->player2_id}({$c->player2?->name})\n";

        foreach ([['p1', $player1], ['p2', $player2]] as [$ourLabel, $ourPlayer]) {
            if (!$ourPlayer) continue;
            foreach (['player1', 'player2'] as $candSide) {
                $candPlayer = $c->{$candSide};
                if (!$candPlayer) continue;
                if ($candPlayer->id === $ourPlayer->id) continue;

                $aTokens = $refCompactTokens->invoke($svc, $candPlayer->name ?? '');
                $bTokens = $refCompactTokens->invoke($svc, $ourPlayer->name ?? '');
                $shared = array_intersect($aTokens, $bTokens);
                $aFirst = $refFirstNamePrefix->invoke($svc, $candPlayer->name ?? '');
                $bFirst = $refFirstNamePrefix->invoke($svc, $ourPlayer->name ?? '');
                $prefixOK = $aFirst === '' || $bFirst === '' || str_starts_with($aFirst, $bFirst) || str_starts_with($bFirst, $aFirst);

                echo "    Compare our $ourLabel ({$ourPlayer->name}) with cand $candSide ({$candPlayer->name}):\n";
                echo "      aTokens=" . json_encode($aTokens) . "\n";
                echo "      bTokens=" . json_encode($bTokens) . "\n";
                echo "      shared=" . json_encode(array_values($shared)) . "\n";
                echo "      firstNames: a={$aFirst}, b={$bFirst}, compat=" . ($prefixOK ? 'YES' : 'NO') . "\n";
                echo "      RESULT: " . (!empty($shared) && $prefixOK ? 'MATCH' : 'no match') . "\n";
            }
        }
    }
}
