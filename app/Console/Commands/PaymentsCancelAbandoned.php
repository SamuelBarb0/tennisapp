<?php

namespace App\Console\Commands;

use App\Models\TournamentPayment;
use App\Services\Payments\MercadoPagoService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Sweep stale "pending" TournamentPayments. The Checkout Pro flow creates a
 * pending row when the user clicks "Pagar", and Mercado Pago does NOT send any
 * webhook if the user abandons the checkout — so the row would stay pending
 * forever, blocking the UI ("pago en proceso").
 *
 * Strategy: for each pending payment older than the cutoff, ask MP whether the
 * preference has any associated payment. If MP confirms the user never paid,
 * mark our row as cancelled. If MP says the payment is actually approved (rare,
 * webhook lag), sync it instead. We never blind-cancel without consulting MP.
 */
class PaymentsCancelAbandoned extends Command
{
    protected $signature = 'payments:cancel-abandoned
                            {--minutes=30 : Cancel pending payments older than this many minutes}
                            {--dry-run : Show what would change without writing anything}';

    protected $description = 'Cancel pending Mercado Pago payments the user abandoned at checkout';

    public function handle(MercadoPagoService $mp): int
    {
        $cutoffMinutes = (int) $this->option('minutes');
        $dryRun        = (bool) $this->option('dry-run');

        $cutoff = now()->subMinutes($cutoffMinutes);

        $stale = TournamentPayment::where('status', 'pending')
            ->where('created_at', '<', $cutoff)
            ->whereNotNull('preference_id')
            ->get();

        if ($stale->isEmpty()) {
            $this->info('No abandoned payments to clean up.');
            return self::SUCCESS;
        }

        $this->info(($dryRun ? '[DRY RUN] ' : '') . "Checking {$stale->count()} stale pending payments…");

        $totals = ['approved' => 0, 'cancelled' => 0, 'kept' => 0, 'unknown' => 0];

        foreach ($stale as $payment) {
            $status = $mp->checkPreferenceStatus($payment->preference_id);
            $ageMin = (int) $payment->created_at->diffInMinutes(now());
            $tag    = "[{$payment->id}] u={$payment->user_id} t={$payment->tournament_id} age={$ageMin}m";

            switch ($status) {
                case 'approved':
                    // The user actually paid — webhook just hasn't fired yet.
                    // syncPayment() pulls the real payment from MP and updates us.
                    $this->line("· {$tag} → MP says APPROVED, syncing…");
                    if (!$dryRun) {
                        // We don't know the mp_payment_id here, so we sync via
                        // preference search inside checkPreferenceStatus would
                        // require an extra lookup. Easiest: re-query MP for the
                        // payment and then call syncPayment with its id.
                        $this->resolveApprovedPayment($mp, $payment);
                    }
                    $totals['approved']++;
                    break;

                case 'abandoned':
                    $this->line("· {$tag} → abandoned, cancelling");
                    if (!$dryRun) {
                        $payment->update(['status' => 'cancelled']);
                    }
                    $totals['cancelled']++;
                    break;

                case 'pending':
                    // MP shows an in-process payment (e.g. PSE pending bank confirmation).
                    // Leave it alone; the webhook will eventually fire.
                    $this->line("· {$tag} → MP says still pending, keeping");
                    $totals['kept']++;
                    break;

                case 'unknown':
                default:
                    $this->warn("· {$tag} → MP unreachable, keeping for now");
                    $totals['unknown']++;
                    break;
            }
        }

        $this->newLine();
        $this->info('Summary:');
        $this->table(array_keys($totals), [array_values($totals)]);

        Log::info('payments:cancel-abandoned completed', $totals);

        return self::SUCCESS;
    }

    /**
     * When the sweeper finds an approved preference, fetch the real MP payment
     * via the SDK and run syncPayment so our row reflects the real state.
     */
    private function resolveApprovedPayment(MercadoPagoService $mp, TournamentPayment $payment): void
    {
        try {
            $client = new \MercadoPago\Client\Payment\PaymentClient();
            $results = $client->search([
                'criteria'      => 'desc',
                'limit'         => 5,
                'preference_id' => $payment->preference_id,
            ]);
            $items = is_object($results) && isset($results->results) ? $results->results : [];
            foreach ($items as $p) {
                if (($p->status ?? null) === 'approved' && isset($p->id)) {
                    $mp->syncPayment((string) $p->id);
                    return;
                }
            }
        } catch (\Throwable $e) {
            Log::error('Could not resolve approved payment from preference', [
                'payment_id'    => $payment->id,
                'preference_id' => $payment->preference_id,
                'error'         => $e->getMessage(),
            ]);
        }
    }
}
