@extends('layouts.app')
@section('title', 'Resultado del pago')

@section('content')
<div class="min-h-[60vh] bg-gradient-to-b from-gray-50 to-gray-100 flex items-center justify-center px-4 py-16">
    <div class="max-w-md w-full bg-white rounded-3xl shadow-xl border border-gray-100 overflow-hidden">

        {{-- Header --}}
        @php
            $cfg = match ($status) {
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

            @if($status === 'success' || $payment->status === 'approved')
            <a href="{{ route('tournaments.show', $payment->tournament) }}"
               class="block w-full px-6 py-3 bg-tc-primary text-white rounded-xl font-bold hover:bg-tc-primary-hover transition shadow-md">
                Ir a llenar mi bracket →
            </a>
            @elseif($status === 'failure')
            <a href="{{ route('tournaments.show', $payment->tournament) }}"
               class="block w-full px-6 py-3 bg-tc-primary text-white rounded-xl font-bold hover:bg-tc-primary-hover transition shadow-md">
                Volver al torneo
            </a>
            @else
            <p class="text-xs text-gray-500 mb-4">Recibirás acceso automáticamente cuando el pago se confirme.</p>
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
