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
     * MP redirects the user back here after the checkout. We use this only for the UX
     * (showing a "thanks" page); the source of truth is the webhook.
     */
    public function returnFromMp(Request $request, MercadoPagoService $mp)
    {
        $status = $request->query('status', 'pending');
        $mpPaymentId = $request->query('payment_id');
        $externalRef = $request->query('external_reference');

        // Best-effort sync — webhook may already have processed this, that's fine.
        if ($mpPaymentId) {
            $mp->syncPayment($mpPaymentId);
        }

        $payment = null;
        if ($externalRef) {
            $payment = \App\Models\TournamentPayment::with('tournament')->find($externalRef);
        }

        return view('payments.return', compact('status', 'payment'));
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
