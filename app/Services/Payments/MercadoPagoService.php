<?php

namespace App\Services\Payments;

use App\Models\Tournament;
use App\Models\TournamentPayment;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\Exceptions\MPApiException;
use MercadoPago\MercadoPagoConfig;

/**
 * Wraps the Mercado Pago SDK so the rest of the app doesn't need to know about it.
 *
 * Two flows are supported:
 *   1) Checkout Pro — createPreferenceForTournament() returns init_point URL
 *   2) Webhook IPN — handleWebhook() looks up the payment by ID and syncs status
 */
class MercadoPagoService
{
    public function __construct()
    {
        $token = config('services.mercadopago.access_token');
        if (!$token) {
            throw new \RuntimeException('MERCADOPAGO_ACCESS_TOKEN no configurado.');
        }
        MercadoPagoConfig::setAccessToken($token);
    }

    /**
     * Create a Checkout Pro preference for a user paying for a tournament.
     * Returns ['init_point' => string, 'payment' => TournamentPayment].
     */
    public function createPreferenceForTournament(User $user, Tournament $tournament): array
    {
        if (!$tournament->requiresPayment()) {
            throw new \DomainException('Este torneo no requiere pago.');
        }

        // Reuse a pending row if there is one — avoids creating duplicates when the
        // user clicks "pagar" multiple times before completing checkout.
        $payment = TournamentPayment::firstOrCreate(
            [
                'user_id'       => $user->id,
                'tournament_id' => $tournament->id,
                'status'        => 'pending',
            ],
            [
                'amount'   => $tournament->price,
                'currency' => config('services.mercadopago.currency', 'COP'),
            ]
        );

        $client = new PreferenceClient();
        $request = [
            'items' => [
                [
                    'id'          => 'tournament-' . $tournament->id,
                    'title'       => 'Acceso a ' . $tournament->name,
                    'description' => 'Predicción del torneo ' . $tournament->name . ' en Tennis Challenge',
                    'quantity'    => 1,
                    'currency_id' => config('services.mercadopago.currency', 'COP'),
                    'unit_price'  => (float) $tournament->price,
                ],
            ],
            'payer' => [
                'email' => $user->email,
                'name'  => $user->name,
            ],
            'back_urls' => [
                'success' => route('payments.mp.return', ['status' => 'success']),
                'failure' => route('payments.mp.return', ['status' => 'failure']),
                'pending' => route('payments.mp.return', ['status' => 'pending']),
            ],
            'auto_return'         => 'approved',
            'notification_url'    => route('payments.mp.webhook'),
            'external_reference'  => (string) $payment->id,
            'statement_descriptor' => 'TennisChallenge',
            'metadata' => [
                'payment_id'    => $payment->id,
                'user_id'       => $user->id,
                'tournament_id' => $tournament->id,
            ],
        ];

        try {
            $preference = $client->create($request);
        } catch (MPApiException $e) {
            Log::error('MP preference create failed', [
                'user' => $user->id,
                'tournament' => $tournament->id,
                'message' => $e->getMessage(),
                'api' => $e->getApiResponse() ? $e->getApiResponse()->getContent() : null,
            ]);
            throw $e;
        }

        $payment->update([
            'preference_id' => $preference->id,
        ]);

        return [
            // Use sandbox_init_point when on TEST credentials, production otherwise.
            'init_point' => $this->isSandbox()
                ? ($preference->sandbox_init_point ?? $preference->init_point)
                : $preference->init_point,
            'payment' => $payment->fresh(),
        ];
    }

    /**
     * Webhook handler. MP sends notifications with type=payment and data.id=<mp_payment_id>.
     * We fetch the payment from MP's API to confirm and update our row.
     *
     * Returns true if a payment was processed, false otherwise.
     */
    public function handleWebhook(array $payload): bool
    {
        $type = $payload['type'] ?? $payload['topic'] ?? null;
        $paymentId = $payload['data']['id'] ?? $payload['id'] ?? null;

        if ($type !== 'payment' || !$paymentId) {
            Log::info('MP webhook ignored', ['type' => $type, 'payload' => $payload]);
            return false;
        }

        try {
            $client = new PaymentClient();
            // SDK requires int, but webhook payloads carry the id as a string.
            $mpPayment = $client->get((int) $paymentId);
        } catch (MPApiException $e) {
            Log::error('MP webhook lookup failed', [
                'mp_payment_id' => $paymentId,
                'message' => $e->getMessage(),
            ]);
            return false;
        }

        // Identify which TournamentPayment row this belongs to.
        // Prefer external_reference (our payment id), fall back to metadata.
        $localPaymentId = $mpPayment->external_reference
            ?? ($mpPayment->metadata->payment_id ?? null);

        if (!$localPaymentId) {
            Log::warning('MP webhook: no external_reference', ['mp_payment_id' => $paymentId]);
            return false;
        }

        $payment = TournamentPayment::find($localPaymentId);
        if (!$payment) {
            Log::warning('MP webhook: TournamentPayment not found', ['local_id' => $localPaymentId]);
            return false;
        }

        $newStatus = $this->mapStatus($mpPayment->status);
        $payment->update([
            'mp_payment_id' => (string) $mpPayment->id,
            'status'        => $newStatus,
            'mp_response'   => json_decode(json_encode($mpPayment), true),
            'paid_at'       => $newStatus === 'approved' ? now() : $payment->paid_at,
        ]);

        // If a duplicate "approved" already exists for this user+tournament, mark this
        // one as refunded-pending so the unique business rule holds.
        if ($newStatus === 'approved') {
            TournamentPayment::where('user_id', $payment->user_id)
                ->where('tournament_id', $payment->tournament_id)
                ->where('id', '!=', $payment->id)
                ->where('status', 'pending')
                ->update(['status' => 'cancelled']);
        }

        return true;
    }

    /**
     * Sync a single payment by its MP id (used by success callback as a fallback when
     * the webhook hasn't fired yet).
     */
    public function syncPayment(string $mpPaymentId): ?TournamentPayment
    {
        try {
            $client = new PaymentClient();
            $mpPayment = $client->get((int) $mpPaymentId);
        } catch (MPApiException) {
            return null;
        }

        $localPaymentId = $mpPayment->external_reference
            ?? ($mpPayment->metadata->payment_id ?? null);

        if (!$localPaymentId) return null;
        $payment = TournamentPayment::find($localPaymentId);
        if (!$payment) return null;

        $newStatus = $this->mapStatus($mpPayment->status);
        $payment->update([
            'mp_payment_id' => (string) $mpPayment->id,
            'status'        => $newStatus,
            'mp_response'   => json_decode(json_encode($mpPayment), true),
            'paid_at'       => $newStatus === 'approved' ? ($payment->paid_at ?? now()) : $payment->paid_at,
        ]);

        return $payment->fresh();
    }

    private function mapStatus(?string $mpStatus): string
    {
        return match ($mpStatus) {
            'approved'   => 'approved',
            'rejected'   => 'rejected',
            'cancelled'  => 'cancelled',
            'refunded', 'charged_back' => 'refunded',
            default      => 'pending', // in_process, pending, authorized
        };
    }

    private function isSandbox(): bool
    {
        $token = config('services.mercadopago.access_token', '');
        return str_starts_with($token, 'TEST-');
    }
}
