@extends('layouts.app')
@section('title', 'Premios')

@push('styles')
<style>
    /* Hero premios */
    .prizes-hero {
        background:
            radial-gradient(ellipse at 15% 60%, rgba(238,229,57,0.10) 0%, transparent 55%),
            radial-gradient(ellipse at 85% 20%, rgba(238,229,57,0.06) 0%, transparent 50%),
            linear-gradient(160deg, #0e1f30 0%, #1b3d5d 50%, #0e1f30 100%);
        position: relative;
        overflow: hidden;
    }
    .prizes-hero::before {
        content: '';
        position: absolute; inset: 0; opacity: 0.02;
        background-image: url("data:image/svg+xml,%3Csvg width='32' height='32' viewBox='0 0 32 32' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M16 2L30 16L16 30L2 16Z' fill='none' stroke='%23EEE539' stroke-width='0.6'/%3E%3C/svg%3E");
        background-size: 32px 32px;
    }

    /* Card hover 3D suave */
    .prize-card {
        transition: transform 0.35s ease, box-shadow 0.35s ease;
        transform-style: preserve-3d;
    }
    .prize-card:hover {
        transform: translateY(-6px) rotateX(1.5deg);
        box-shadow: 0 20px 50px rgba(0,0,0,0.13);
    }

    /* Star shimmer en puntos */
    .points-badge {
        position: relative;
        overflow: hidden;
    }
    .points-badge::after {
        content: '';
        position: absolute; inset: 0;
        background: linear-gradient(90deg, transparent 20%, rgba(238,229,57,0.25) 50%, transparent 80%);
        background-size: 200% 100%;
        animation: pointsShimmer 2.8s ease-in-out infinite;
    }
    @keyframes pointsShimmer {
        0%   { background-position: -200% 0; }
        100% { background-position:  200% 0; }
    }

    /* Trophy float */
    .trophy-float { animation: floatY 5s ease-in-out infinite; }
    @keyframes floatY {
        0%,100% { transform: translateY(0); }
        50%      { transform: translateY(-12px); }
    }
</style>
@endpush

@section('content')

{{-- Hero Premios --}}
<div class="prizes-hero relative overflow-hidden">
    <div class="relative max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-10 md:py-12 text-center fade-in">
        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-tc-accent/10 border border-tc-accent/20 mb-4">
            <svg class="w-3.5 h-3.5 text-tc-accent" fill="currentColor" viewBox="0 0 20 20"><path d="M10 15l-5.878 3.09 1.123-6.545L.489 6.91l6.572-.955L10 0l2.939 5.955 6.572.955-4.756 4.635 1.123 6.545z"/></svg>
            <span class="text-tc-accent text-[10px] font-bold uppercase tracking-widest">Tienda de premios</span>
        </div>
        <h1 class="text-3xl md:text-5xl font-black text-white tracking-tight mb-2">Canjea tus puntos</h1>
        <p class="text-white/40 text-sm">Gana prediciendo torneos y canjea tus puntos por premios exclusivos</p>
        @auth
        <div class="mt-5 inline-flex items-center gap-3 px-5 py-2.5 bg-tc-accent/10 border border-tc-accent/25 rounded-full glow-gold">
            <svg class="w-4 h-4 text-tc-accent" fill="currentColor" viewBox="0 0 20 20"><path d="M10 15l-5.878 3.09 1.123-6.545L.489 6.91l6.572-.955L10 0l2.939 5.955 6.572.955-4.756 4.635 1.123 6.545z"/></svg>
            <span class="font-black text-tc-accent text-lg count-up">{{ auth()->user()->points }}</span>
            <span class="text-sm text-white/50">puntos disponibles</span>
        </div>
        @endauth
    </div>
</div>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
        @forelse($prizes as $prize)
        <div class="prize-card bg-white rounded-3xl overflow-hidden shadow-sm border border-gray-100 flex flex-col reveal-scale" data-delay="{{ $loop->index * 70 }}">
            <div class="h-48 bg-gradient-to-br from-gray-100 to-gray-200 flex items-center justify-center">
                @if($prize->image)
                    <img src="{{ Storage::url($prize->image) }}" alt="{{ $prize->name }}" class="w-full h-full object-cover">
                @else
                    <svg class="w-16 h-16 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7"/></svg>
                @endif
            </div>
            <div class="p-5 flex-1 flex flex-col">
                <h3 class="font-semibold text-base mb-2">{{ $prize->name }}</h3>
                <p class="text-sm text-gray-500 mb-4 flex-1">{{ Str::limit($prize->description, 80) }}</p>
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-1.5 points-badge px-2 py-1 rounded-lg bg-yellow-50">
                        <svg class="w-4 h-4 text-yellow-500" fill="currentColor" viewBox="0 0 20 20"><path d="M10 15l-5.878 3.09 1.123-6.545L.489 6.91l6.572-.955L10 0l2.939 5.955 6.572.955-4.756 4.635 1.123 6.545z"/></svg>
                        <span class="font-bold text-sm">{{ number_format($prize->points_required) }}</span>
                        <span class="text-xs text-gray-400">pts</span>
                    </div>
                    <span class="text-xs text-gray-400">{{ $prize->stock }} disponibles</span>
                </div>
                @auth
                    @if(auth()->user()->points >= $prize->points_required)
                        <form action="{{ route('prizes.redeem', $prize) }}" method="POST"
                              x-data="{ confirming: false }">
                            @csrf
                            <button type="button" x-show="!confirming" @click="confirming = true"
                                    class="w-full py-2.5 bg-tc-primary text-white rounded-2xl text-sm font-medium hover:bg-tc-primary-hover transition-all">
                                Canjear
                            </button>
                            <div x-show="confirming" x-cloak class="flex gap-2">
                                <button type="submit" class="flex-1 py-2.5 bg-green-500 text-white rounded-2xl text-sm font-medium hover:bg-green-600 transition-all">Confirmar</button>
                                <button type="button" @click="confirming = false" class="flex-1 py-2.5 bg-gray-200 text-gray-600 rounded-2xl text-sm font-medium hover:bg-gray-300 transition-all">Cancelar</button>
                            </div>
                        </form>
                    @else
                        <div class="w-full py-2.5 bg-gray-100 text-gray-400 rounded-2xl text-sm font-medium text-center">
                            Necesitas {{ number_format($prize->points_required - auth()->user()->points) }} pts más
                        </div>
                    @endif
                @else
                    <a href="{{ route('login') }}" class="w-full py-2.5 bg-gray-100 text-gray-600 rounded-2xl text-sm font-medium text-center hover:bg-gray-200 transition-all block">
                        Inicia sesión para canjear
                    </a>
                @endauth
            </div>
        </div>
        @empty
        <div class="col-span-full text-center py-16">
            <p class="text-gray-500">No hay premios disponibles en este momento.</p>
        </div>
        @endforelse
    </div>
</div>
@endsection
