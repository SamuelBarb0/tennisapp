<?php
// Uso (en producción): php inspect_predictions.php
// Diagnostica predicciones rotas para Roland Garros WTA (tournament_id=62).

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\BracketPrediction;
use App\Models\Player;
use App\Models\TennisMatch;
use App\Models\Tournament;

$t = Tournament::where('id', 62)->first();
$matchesByPos = $t->matches()->where('round', 'R128')->get()->keyBy('bracket_position');

$broken = 0; $ok = 0; $missing = 0;
foreach (BracketPrediction::where('tournament_id', 62)->where('round', 'R128')->get() as $p) {
    $m = $matchesByPos[$p->position] ?? null;
    if (!$m) { $missing++; continue; }
    if ($p->predicted_winner_id === $m->player1_id || $p->predicted_winner_id === $m->player2_id) {
        $ok++;
    } else {
        $broken++;
    }
}
echo "R128 predictions — OK: $ok / BROKEN: $broken / MISSING: $missing" . PHP_EOL;
echo PHP_EOL . 'Cómo se movieron las 10 primeras predicciones rotas del user 25:' . PHP_EOL;

$shown = 0;
foreach (BracketPrediction::where('tournament_id', 62)->where('round', 'R128')->where('user_id', 25)->orderBy('position')->get() as $p) {
    $m = $matchesByPos[$p->position] ?? null;
    if (!$m) continue;
    $valid = ($p->predicted_winner_id === $m->player1_id || $p->predicted_winner_id === $m->player2_id);
    if ($valid) continue;

    $nowAt = $t->matches()
        ->where('round', 'R128')
        ->where(function ($q) use ($p) {
            $q->where('player1_id', $p->predicted_winner_id)
              ->orWhere('player2_id', $p->predicted_winner_id);
        })
        ->first();

    $predicted = Player::find($p->predicted_winner_id)?->name ?? '?';
    $nowPos = $nowAt?->bracket_position ?? 'NO ESTÁ EN R128';
    echo "  pos=" . $p->position . " predicted=" . $predicted . " → ahora está en pos=" . $nowPos . PHP_EOL;

    if (++$shown >= 10) break;
}
