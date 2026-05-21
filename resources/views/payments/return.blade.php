@extends('layouts.app')
@section('title', 'Resultado del pago')

@section('content')
@php
    // Single source of truth for the displayed state. Priority:
    //   1. Our DB payment status (updated by the webhook from Mercado Pago).
    //   2. Mercado Pago's `?status=` query param (used by back_urls).
    //
    // We don't trust the query param alone because MP sometimes sends
    // `status=pending` even after the payment was already approved on its
    // side, which makes the user see "Pago en proceso" while the bracket is
    // already unlocked. Reading $payment->status fixes both screens at once.
    $effectiveStatus = match (true) {
        $payment && $payment->status === 'approved'  => 'success',
        $payment && $payment->status === 'rejected'  => 'failure',
        $payment && in_array($payment->status, ['cancelled', 'refunded']) => 'failure',
        $status === 'success'  => 'success',
        $status === 'failure'  => 'failure',
        default                => 'pending',
    };

    $cfg = match ($effectiveStatus) {
        'success' => [
            'bg' => 'from-green-500 to-emerald-600',
            'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>',
            'title' => '¡Pago exitoso!',
            'subtitle' => 'Ya tienes acceso al torneo',
        ],
        'failure' => [
            'bg' => 'from-red-500 to-rose-600',
            'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>',
            'title' => 'Pago rechazado',
            'subtitle' => 'No pudimos procesar el pago',
        ],
        default => [
            'bg' => 'from-amber-400 to-orange-500',
            'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>',
            'title' => 'Pago en proceso',
            'subtitle' => 'Estamos esperando confirmación de Mercado Pago',
        ],
    };
@endphp
<div class="min-h-[60vh] bg-gradient-to-b from-gray-50 to-gray-100 flex items-center justify-center px-4 py-16"
     {{-- When the webhook is still pending, refresh after a few seconds so
          the screen flips to "success" once Mercado Pago calls our webhook.
          We only auto-refresh on the pending state; success/failure are final. --}}
     @if($effectiveStatus === 'pending') x-data x-init="setTimeout(() => window.location.reload(), 5000)" @endif>
    <div class="max-w-md w-full bg-white rounded-3xl shadow-xl border border-gray-100 overflow-hidden">

        <div class="bg-gradient-to-br {{ $cfg['bg'] }} px-6 py-10 text-center text-white">
            <div class="w-20 h-20 rounded-full bg-white/20 backdrop-blur mx-auto flex items-center justify-center mb-4">
                <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">{!! $cfg['icon'] !!}</svg>
            </div>
            <h1 class="text-2xl font-black tracking-tight">{{ $cfg['title'] }}</h1>
            <p class="text-white/80 text-sm mt-1">{{ $cfg['subtitle'] }}</p>
        </div>

        {{-- Body --}}
        <div class="px-6 py-6 text-center">
            @if($payment && $payment->tournament)
            <div class="text-xs text-gray-400 uppercase tracking-widest mb-1">Torneo</div>
            <div class="text-lg font-bold text-gray-800 mb-3">{{ $payment->tournament->name }}</div>
            <div class="inline-block px-4 py-1.5 rounded-xl bg-gray-100 text-tc-primary font-mono font-black text-sm mb-6">
                {{ number_format($payment->amount, 0) }} {{ $payment->currency }}
            </div>

            @if($effectiveStatus === 'success')
            <a href="{{ route('tournaments.show', $payment->tournament) }}"
               class="block w-full px-6 py-3 bg-tc-primary text-white rounded-xl font-bold hover:bg-tc-primary-hover transition shadow-md">
                Ir a llenar mi bracket →
            </a>
            @elseif($effectiveStatus === 'failure')
            <a href="{{ route('tournaments.show', $payment->tournament) }}"
               class="block w-full px-6 py-3 bg-tc-primary text-white rounded-xl font-bold hover:bg-tc-primary-hover transition shadow-md">
                Volver al torneo
            </a>
            @else
            <p class="text-xs text-gray-500 mb-4">Esta página se actualizará automáticamente cuando recibamos la confirmación de Mercado Pago.</p>
            <a href="{{ route('tournaments.show', $payment->tournament) }}"
               class="block w-full px-6 py-3 border border-gray-200 text-gray-600 rounded-xl font-bold hover:bg-gray-50 transition">
                Volver al torneo
            </a>
            @endif

            @else
            <a href="{{ route('home') }}"
               class="inline-block px-6 py-3 bg-tc-primary text-white rounded-xl font-bold hover:bg-tc-primary-hover transition shadow-md">
                Ir al inicio
            </a>
            @endif
        </div>
    </div>
</div>
@endsection
