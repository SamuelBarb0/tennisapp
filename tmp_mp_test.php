$svc = app(\App\Services\Payments\MercadoPagoService::class);
$user = \App\Models\User::find(2);
$t = \App\Models\Tournament::find(38);
echo "APP_URL: " . config("app.url") . PHP_EOL;
echo "User: " . ($user ? $user->email : "NOT FOUND") . PHP_EOL;
echo "Tournament: " . ($t ? $t->name . " (price=" . $t->price . ")" : "NOT FOUND") . PHP_EOL;
if (!$user || !$t) exit;
try {
    $result = $svc->createPreferenceForTournament($user, $t);
    echo "OK init_point=" . $result["init_point"] . PHP_EOL;
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . PHP_EOL;
    if (method_exists($e, "getApiResponse") && $e->getApiResponse()) {
        echo "API response: " . $e->getApiResponse()->getContent() . PHP_EOL;
    }
}
