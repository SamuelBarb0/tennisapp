<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use App\Services\Payments\MercadoPagoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    /**
     * Start the Mercado Pago Checkout Pro flow for a tournament.
     * Redirects the user to MP's hosted checkout page.
     */
    public function checkout(Tournament $tournament, MercadoPagoService $mp)
    {
        $user = auth()->user();
        if (!$user) abort(401);

        if (!$tournament->requiresPayment()) {
            return redirect()->route('tournaments.show', $tournament)
                ->with('info', 'Este torneo es gratis — ¡pasa directo a predecir!');
        }

        if ($tournament->hasUserPaid($user->id)) {
            return redirect()->route('tournaments.show', $tournament)
                ->with('success', 'Ya tienes acceso a este torneo.');
        }

        // Block checkout if the tournament has already started — there's no
        // way for the user to fill the bracket after the first match begins,
        // so charging them would be a guaranteed refund request.
        //
        // Deadline strategy:
        //   1. First match's scheduled_at - 1 min (the canonical cutoff).
        //   2. Fallback to tournament.start_date - 1 min when there are no
        //      matches yet (bracket not published — Wimbledon a month before
        //      its first day, etc.).
        //   3. If neither exists, allow the checkout: the tournament is
        //      genuinely upcoming and the bracket will appear later.
        $firstMatch = $tournament->matches()
            ->whereNotIn('status', ['cancelled'])
            ->whereNotNull('scheduled_at')
            ->orderBy('scheduled_at')
            ->first();
        $deadline = $firstMatch?->scheduled_at?->copy()->subMinute()
            ?? $tournament->start_date?->copy()->subMinute();
        $alreadyStarted = $tournament->status === 'finished'
            || ($deadline && now()->gte($deadline));
        if ($alreadyStarted) {
            return redirect()->route('tournaments.show', $tournament)
                ->with('error', 'Este torneo ya cerró las predicciones — no es posible pagar para participar ahora.');
        }

        try {
            ['init_point' => $url] = $mp->createPreferenceForTournament($user, $tournament);
        } catch (\Throwable $e) {
            Log::error('Checkout failed', ['user' => $user->id, 'tournament' => $tournament->id, 'error' => $e->getMessage()]);
            return redirect()->route('tournaments.show', $tournament)
                ->with('error', 'No pudimos iniciar el pago. Intenta de nuevo en unos minutos.');
        }

        return redirect()->away($url);
    }

    /**
     * MP redirects the user back here after the checkout. We bounce them
     * straight to the tournament they were paying for with a flash message
     * that explains the result — no intermediate "pago en proceso" page.
     */
    public function returnFromMp(Request $request, MercadoPagoService $mp)
    {
        $status      = $request->query('status', 'pending');
        $mpPaymentId = $request->query('payment_id');
        $externalRef = $request->query('external_reference');

        // Best-effort sync — MP usually has the payment status ready a few
        // hundred ms after the redirect, so we ask directly instead of
        // waiting for the webhook.
        if ($mpPaymentId) {
            $mp->syncPayment($mpPaymentId);
        }

        $payment = null;
        if ($externalRef) {
            $payment = \App\Models\TournamentPayment::with('tournament')->find($externalRef);
        }

        // Secondary fallback when MP redirected without a payment_id (typical
        // for closed-tab returns landing at status=pending or status=failure).
        // Search MP by our preference id to find out what really happened.
        //
        // Three outcomes from MP's side:
        //   - 'approved':   sync the payment and unlock the bracket.
        //   - 'abandoned':  no payment record on MP — user clicked "Volver a
        //                   la tienda" without paying. Cancel our local row
        //                   immediately so the UI doesn't show "en proceso".
        //   - 'pending':    real pending (PSE/Nequi waiting confirmation) —
        //                   leave alone, the webhook will fire later.
        // Decisión rápida sin esperar a MP: si MP nos redirige a `status=failure`
        // o el usuario llega SIN payment_id (señal típica de "Volver a la
        // tienda"), cancelamos inmediatamente. La consulta a MP es solo para
        // pillar el caso raro de un pago aprobado cuyo webhook llegó tarde.
        if ($payment && $payment->status === 'pending') {
            $userBailed = $status === 'failure'
                || ($status === 'pending' && !$mpPaymentId);

            if ($userBailed) {
                // Confirmar con MP por si el usuario sí pagó pero MP redirigió mal.
                $resolution = $payment->preference_id
                    ? $mp->checkPreferenceStatus($payment->preference_id)
                    : 'abandoned';
                if ($resolution !== 'approved' && $resolution !== 'pending') {
                    $payment->update(['status' => 'cancelled']);
                    $payment = $payment->fresh(['tournament']);
                }
            }
        }

        // Si tras lo anterior el row sigue pending y la URL trae payment_id,
        // hacemos la segunda verificación clásica buscando un pago aprobado
        // con webhook lento.
        if ($payment && $payment->status === 'pending' && $payment->preference_id) {
            $resolution = $mp->checkPreferenceStatus($payment->preference_id);

            if ($resolution === 'approved') {
                try {
                    $client = new \MercadoPago\Client\Payment\PaymentClient();
                    $filters = ['preference_id' => $payment->preference_id];
                    $searchRequest = new \MercadoPago\Net\MPSearchRequest(5, 0, $filters);
                    $results = $client->search($searchRequest);
                    $items = is_object($results) && isset($results->results) ? $results->results : [];
                    foreach ($items as $p) {
                        $pStatus = is_array($p) ? ($p['status'] ?? null) : ($p->status ?? null);
                        $pId     = is_array($p) ? ($p['id'] ?? null)     : ($p->id ?? null);
                        if ($pStatus === 'approved' && $pId) {
                            $mp->syncPayment((string) $pId);
                            break;
                        }
                    }
                    $payment = $payment->fresh(['tournament']);
                } catch (\Throwable $e) {
                    Log::warning('Could not resolve approved payment on return', [
                        'payment_id' => $payment->id,
                        'error'      => $e->getMessage(),
                    ]);
                }
            }
        }

        // No payment row → user came in via a stale URL or stripped query
        // params. Send them home, nothing else we can do.
        if (!$payment || !$payment->tournament) {
            return redirect()->route('home')
                ->with('info', 'No encontramos el pago. Si te cobraron, revisa tu correo o intenta de nuevo desde el torneo.');
        }

        // Decide the flash message based on the truth from our DB (set by
        // the webhook or the immediate syncPayment above), falling back to
        // the query string from MP.
        $effective = match (true) {
            $payment->status === 'approved'  => 'approved',
            $payment->status === 'rejected'  => 'rejected',
            $payment->status === 'cancelled' => 'cancelled',
            $status === 'success'            => 'approved',
            $status === 'failure'            => 'rejected',
            default                          => 'pending',
        };

        $redirect = redirect()->route('tournaments.show', $payment->tournament);
        return match ($effective) {
            'approved'  => $redirect->with('success', '¡Pago aprobado! Ya puedes llenar tu bracket.'),
            'rejected'  => $redirect->with('error',   'El pago fue rechazado. Intenta con otro medio de pago.'),
            'cancelled' => $redirect->with('info',    'El pago fue cancelado. Cuando quieras reintentarlo, vuelve a darle "Pagar".'),
            default     => $redirect->with('info',    'Tu pago está en proceso. Cuando Mercado Pago lo confirme (suele ser cuestión de minutos) podrás llenar tu bracket.'),
        };
    }

    /**
     * Mercado Pago notification webhook (IPN). Returns 200 quickly so MP doesn't retry.
     */
    public function webhook(Request $request, MercadoPagoService $mp)
    {
        $payload = $request->all();
        Log::info('MP webhook received', $payload);

        try {
            $mp->handleWebhook($payload);
        } catch (\Throwable $e) {
            Log::error('MP webhook handler crashed', ['error' => $e->getMessage(), 'payload' => $payload]);
        }

        return response()->json(['ok' => true]);
    }
}
