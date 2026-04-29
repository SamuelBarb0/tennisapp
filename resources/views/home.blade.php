@extends('layouts.app')
@section('title', 'Inicio')

@section('content')

{{-- ========== CARRUSEL HOME (hero default + banners admin) ========== --}}
@php
    // Total slides = 1 hero default + N banners. Hero is always slide 0.
    $totalSlides = 1 + $banners->count();
@endphp
<section class="bg-tc-primary"
         x-data="{
            current: 0,
            count: {{ $totalSlides }},
            autoTimer: null,
            init() {
                if (this.count > 1) this.autoTimer = setInterval(() => this.next(), 7000);
            },
            next() { this.current = (this.current + 1) % this.count; },
            prev() { this.current = (this.current - 1 + this.count) % this.count; },
            go(i)  { this.current = i; }
         }">
    <div class="relative">
        <div class="relative overflow-hidden" style="min-height: 480px;">

            {{-- ★ Slide 0: Hero default "Predice. Compite. Gana." ★ --}}
            <div x-show="current === 0"
                 x-transition:enter="transition ease-out duration-500"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 class="absolute inset-0 bg-gradient-to-br from-tc-primary via-tc-primary-hover to-tc-primary-dark overflow-hidden">
                {{-- Orbes decorativos --}}
                <div class="absolute top-10 left-10 w-72 h-72 bg-white/10 rounded-full blur-3xl float-y-slow pointer-events-none"></div>
                <div class="absolute bottom-0 right-10 w-96 h-96 bg-white/10 rounded-full blur-3xl float-y pointer-events-none" style="animation-delay:1.5s"></div>

                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-14 md:py-20 relative">
                    <div class="text-center fade-in">
                        <h1 class="text-4xl md:text-6xl font-bold text-white tracking-tight mb-6">
                            Predice. Compite. <span class="text-tc-accent">Gana.</span>
                        </h1>
                        <p class="text-lg md:text-xl text-blue-100 max-w-2xl mx-auto mb-10">
                            Haz tus pronósticos en los mejores torneos de tenis del mundo y gana premios increíbles.
                        </p>
                        <div class="flex flex-col sm:flex-row gap-4 justify-center">
                            @guest
                                <a href="{{ route('register') }}" class="px-8 py-3.5 bg-tc-accent text-tc-primary-dark rounded-full text-base font-semibold hover:brightness-110 transition-all shadow-lg hover:shadow-xl hover:-translate-y-1 active:scale-95">
                                    Comenzar gratis
                                </a>
                                <a href="{{ route('tournaments.index') }}" class="px-8 py-3.5 bg-white/10 text-white border border-white/30 rounded-full text-base font-semibold hover:bg-white/20 transition-all backdrop-blur hover:-translate-y-1">
                                    Ver torneos
                                </a>
                            @else
                                <a href="{{ route('tournaments.index') }}" class="px-8 py-3.5 bg-tc-accent text-tc-primary-dark rounded-full text-base font-semibold hover:brightness-110 transition-all shadow-lg hover:shadow-xl hover:-translate-y-1 active:scale-95">
                                    Hacer pronósticos
                                </a>
                                <a href="{{ route('rules') }}" class="px-8 py-3.5 bg-white/10 text-white border border-white/30 rounded-full text-base font-semibold hover:bg-white/20 transition-all backdrop-blur hover:-translate-y-1">
                                    ¿Cómo funciona?
                                </a>
                            @endguest
                        </div>
                        {{-- Stats --}}
                        <div class="mt-12 grid grid-cols-2 md:grid-cols-4 gap-4 md:gap-8 max-w-3xl mx-auto">
                            <div class="text-center">
                                <div class="text-2xl md:text-3xl font-bold text-white">{{ $stats['tournaments'] }}</div>
                                <div class="text-xs text-blue-200 mt-1">Torneos</div>
                            </div>
                            <div class="text-center">
                                <div class="text-2xl md:text-3xl font-bold text-white">{{ $stats['players'] }}</div>
                                <div class="text-xs text-blue-200 mt-1">Jugadores</div>
                            </div>
                            <div class="text-center">
                                <div class="text-2xl md:text-3xl font-bold text-white">{{ $stats['total_points'] }}</div>
                                <div class="text-xs text-blue-200 mt-1">Puntos repartidos</div>
                            </div>
                            <div class="text-center">
                                <div class="text-2xl md:text-3xl font-bold text-white">{{ $stats['users'] }}</div>
                                <div class="text-xs text-blue-200 mt-1">Participantes</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ★ Slides 1+: Admin banners ★ --}}
            @foreach($banners as $i => $banner)
            @php $slideIndex = $i + 1; @endphp
            <div x-show="current === {{ $slideIndex }}"
                 x-transition:enter="transition ease-out duration-500"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 class="absolute inset-0">
                @php $src = $banner->media_src; @endphp
                @if($banner->link)<a href="{{ $banner->link }}" class="block h-full w-full">@endif
                @if($banner->media_type === 'video' && $src)
                    <video src="{{ $src }}" autoplay muted loop playsinline class="absolute inset-0 w-full h-full object-cover"></video>
                @elseif($src)
                    <img src="{{ $src }}" alt="{{ $banner->title }}" class="absolute inset-0 w-full h-full object-cover">
                @else
                    <div class="absolute inset-0 bg-gradient-to-br from-tc-primary via-tc-primary-hover to-tc-primary-dark"></div>
                @endif
                {{-- overlay con texto si hay título/subtítulo --}}
                @if($banner->title || $banner->subtitle)
                <div class="absolute inset-0 bg-gradient-to-r from-black/65 via-black/35 to-transparent flex items-center">
                    <div class="max-w-7xl mx-auto px-6 sm:px-12 md:px-20 w-full">
                        <div class="max-w-2xl">
                            @if($banner->title)
                            <h2 class="text-3xl md:text-5xl font-black text-white tracking-tight mb-2 drop-shadow-lg">{{ $banner->title }}</h2>
                            @endif
                            @if($banner->subtitle)
                            <p class="text-base md:text-lg text-white/90 drop-shadow max-w-xl">{{ $banner->subtitle }}</p>
                            @endif
                        </div>
                    </div>
                </div>
                @endif
                @if($banner->link)</a>@endif
            </div>
            @endforeach

        </div>

        @if($totalSlides > 1)
        {{-- Arrows --}}
        <button x-on:click="prev()" class="absolute left-3 sm:left-6 top-1/2 -translate-y-1/2 w-10 h-10 rounded-full bg-black/30 hover:bg-black/50 text-white flex items-center justify-center backdrop-blur transition z-10">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/></svg>
        </button>
        <button x-on:click="next()" class="absolute right-3 sm:right-6 top-1/2 -translate-y-1/2 w-10 h-10 rounded-full bg-black/30 hover:bg-black/50 text-white flex items-center justify-center backdrop-blur transition z-10">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
        </button>
        {{-- Dots --}}
        <div class="absolute bottom-5 left-1/2 -translate-x-1/2 flex gap-2 z-10">
            @for($i = 0; $i < $totalSlides; $i++)
            <button x-on:click="go({{ $i }})"
                    :class="current === {{ $i }} ? 'bg-tc-accent w-8' : 'bg-white/50 hover:bg-white/70 w-2'"
                    class="h-2 rounded-full transition-all"></button>
            @endfor
        </div>
        @endif
    </div>
</section>

{{-- ========== PRÓXIMOS TORNEOS A PREDECIR (configurable por admin) ========== --}}
@if($featuredTournaments->count() > 0)
<section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-10 reveal">
    <div class="text-center mb-8">
        <h2 class="text-2xl md:text-3xl font-black tracking-tight uppercase text-tc-primary">Próximos Torneos a Predecir</h2>
        <p class="text-sm text-gray-500 mt-1">Los torneos seleccionados por el administrador para esta temporada</p>
    </div>

    <div class="grid grid-cols-1 {{ $featuredTournaments->count() > 1 ? 'md:grid-cols-2' : '' }} gap-6">
        @foreach($featuredTournaments as $ft)
        @php $state = $ft->bracket_state; @endphp
        <div class="bg-white rounded-3xl shadow-xl border border-gray-100 overflow-hidden">
            <div class="md:flex h-full">
                {{-- Lado izquierdo: info del torneo --}}
                <div class="md:w-1/2 p-6 md:p-8 flex flex-col">
                    <div class="flex items-center gap-2 mb-4 flex-wrap">
                        @if($state === 'live')
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-red-100 text-red-700 text-xs font-bold rounded-full">
                                <span class="w-1.5 h-1.5 bg-red-500 rounded-full animate-pulse"></span>EN VIVO
                            </span>
                        @elseif($state === 'open')
                            <span class="px-3 py-1 bg-tc-accent text-tc-primary-dark text-xs font-bold rounded-full">PRÓXIMO PARA PREDECIR</span>
                        @else
                            <span class="px-3 py-1 bg-gray-200 text-gray-600 text-xs font-bold rounded-full">PRÓXIMAMENTE</span>
                        @endif
                        @if($ft->requiresPayment())
                            <span class="px-3 py-1 bg-yellow-100 text-yellow-800 text-xs font-bold rounded-full">${{ number_format($ft->price, 0, ',', '.') }} COP</span>
                        @else
                            <span class="px-3 py-1 bg-green-100 text-green-700 text-xs font-bold rounded-full">GRATIS</span>
                        @endif
                    </div>
                    <h2 class="text-2xl md:text-3xl font-bold mb-2">{{ $ft->name }}</h2>
                    @if($ft->city || $ft->country)
                    <p class="text-gray-500 flex items-center gap-1 mb-3">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        {{ $ft->city }}{{ $ft->country ? ', ' . $ft->country : '' }}
                    </p>
                    @endif
                    <div class="flex flex-wrap gap-3 mb-5">
                        <span class="text-sm text-gray-500 bg-gray-100 px-3 py-1 rounded-lg">{{ $ft->start_date->format('d M') }} - {{ $ft->end_date->format('d M, Y') }}</span>
                        @if($ft->surface)
                        <span class="text-sm text-gray-500 bg-gray-100 px-3 py-1 rounded-lg">{{ $ft->surface }}</span>
                        @endif
                    </div>
                    @if($state === 'open')
                    <div class="flex items-center gap-2 text-sm text-gray-500 mb-6">
                        <svg class="w-5 h-5 text-tc-accent" fill="currentColor" viewBox="0 0 20 20"><path d="M10 15l-5.878 3.09 1.123-6.545L.489 6.91l6.572-.955L10 0l2.939 5.955 6.572.955-4.756 4.635 1.123 6.545z"/></svg>
                        <span><strong>{{ $ft->pending_matches_count }}</strong> partidos disponibles para predecir</span>
                    </div>
                    @endif
                    <div class="mt-auto pt-2">
                        @if($state === 'live')
                            <a href="{{ route('tournaments.show', $ft) }}" class="inline-flex items-center gap-2 px-6 py-3 bg-red-500 text-white rounded-full font-semibold hover:bg-red-600 transition-all shadow-md">
                                Torneo en vivo
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            </a>
                            <p class="text-xs text-gray-400 mt-2">Predicciones finalizadas — revisa tu bracket guardado</p>
                        @elseif($state === 'open')
                            <a href="{{ route('tournaments.show', $ft) }}" class="inline-flex items-center gap-2 px-6 py-3 bg-tc-primary text-white rounded-full font-semibold hover:bg-tc-primary-hover transition-all shadow-md hover:shadow-lg hover:-translate-y-0.5">
                                Predecir ahora
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            </a>
                        @else
                            <button disabled class="inline-flex items-center gap-2 px-6 py-3 bg-gray-200 text-gray-400 rounded-full font-semibold cursor-not-allowed">
                                Predicciones próximamente
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/></svg>
                            </button>
                        @endif
                    </div>
                </div>
                {{-- Lado derecho: imagen del torneo (con fallback al gradiente por tipo) --}}
                <div class="md:w-1/2 relative min-h-[200px] flex items-center justify-center overflow-hidden bg-gradient-to-br {{ $ft->type === 'GrandSlam' ? 'from-yellow-400 to-orange-500' : (str_starts_with($ft->type, 'ATP') ? 'from-tc-primary to-blue-700' : 'from-purple-500 to-pink-500') }}">
                    @if($ft->image)
                        <img src="{{ asset('storage/' . $ft->image) }}" alt="{{ $ft->name }}" class="absolute inset-0 w-full h-full object-cover">
                        <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent"></div>
                        <div class="relative text-white text-center px-6 self-end pb-6 w-full">
                            <span class="inline-block px-3 py-1 rounded-full bg-white/20 backdrop-blur text-[10px] font-bold uppercase tracking-widest">{{ $ft->type }}</span>
                        </div>
                    @else
                        <div class="text-center text-white">
                            <span class="text-white/30 text-9xl font-black block leading-none">{{ substr($ft->type, 0, 1) }}</span>
                            <span class="text-white/80 text-lg font-semibold mt-2 block">{{ $ft->type }}</span>
                        </div>
                    @endif
                </div>
            </div>
        </div>
        @endforeach
    </div>
</section>
@endif

{{-- ========== TORNEO EN VIVO (si hay) ========== --}}
@if($liveTournament)
<section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-10 reveal">
    <div class="bg-white rounded-3xl shadow-sm p-6 border border-gray-100">
        <div class="flex items-center gap-3 mb-4">
            <span class="relative flex h-3 w-3">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-3 w-3 bg-red-500"></span>
            </span>
            <h2 class="text-lg font-bold">En Vivo: {{ $liveTournament->name }}</h2>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            @foreach($liveTournament->matches as $match)
            <div class="bg-gray-50 rounded-2xl p-4">
                <div class="text-xs text-gray-500 mb-2 flex items-center gap-2">
                    <span class="px-2 py-0.5 bg-red-100 text-red-600 rounded-full font-medium text-[10px]">LIVE</span>
                    {{ $match->round }}
                </div>
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2 flex-1">
                        <img src="{{ $match->player1->flag_url }}" alt="{{ $match->player1->nationality_code }}" class="w-8 h-6 rounded object-cover">
                        <div>
                            <div class="font-semibold text-sm">{{ $match->player1->name }}</div>
                            <div class="text-xs text-gray-400">#{{ $match->player1->ranking }}</div>
                        </div>
                    </div>
                    <div class="px-3 py-1 bg-white rounded-xl text-xs text-gray-400 shadow-sm">
                        {{ $match->score ?? 'VS' }}
                    </div>
                    <div class="flex items-center gap-2 flex-1 justify-end text-right">
                        <div>
                            <div class="font-semibold text-sm">{{ $match->player2->name }}</div>
                            <div class="text-xs text-gray-400">#{{ $match->player2->ranking }}</div>
                        </div>
                        <img src="{{ $match->player2->flag_url }}" alt="{{ $match->player2->nationality_code }}" class="w-8 h-6 rounded object-cover">
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- ========== PRÓXIMOS TORNEOS (Prioridad alta) ========== --}}
@if($upcomingTournaments->count() > 0)
<section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-14">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-2xl md:text-3xl font-bold">Próximos Torneos</h2>
            <p class="text-sm text-gray-500 mt-1">Torneos disponibles para predecir</p>
        </div>
        <a href="{{ route('tournaments.index') }}" class="text-tc-primary text-sm font-medium hover:underline">Ver todos &rarr;</a>
    </div>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
        @foreach($upcomingTournaments as $tournament)
        <a href="{{ route('tournaments.show', $tournament) }}" class="group block bg-white rounded-3xl overflow-hidden shadow-sm hover-lift border border-gray-100 reveal-scale" data-delay="{{ $loop->index * 80 }}">
            <div class="h-32 relative overflow-hidden bg-gradient-to-br {{ $tournament->type === 'GrandSlam' ? 'from-yellow-400 to-orange-500' : (str_starts_with($tournament->type, 'ATP') ? 'from-tc-primary to-blue-700' : 'from-purple-500 to-pink-500') }}">
                @if($tournament->image)
                    <img src="{{ asset('storage/' . $tournament->image) }}" alt="{{ $tournament->name }}" class="absolute inset-0 w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                    <div class="absolute inset-0 bg-gradient-to-t from-black/50 to-transparent"></div>
                @else
                    <div class="absolute inset-0 flex items-center justify-center">
                        <span class="text-white/20 text-7xl font-black">{{ substr($tournament->type, 0, 1) }}</span>
                    </div>
                @endif
                @if($tournament->requiresPayment())
                    <span class="absolute top-3 right-3 px-2.5 py-0.5 bg-yellow-400 text-yellow-900 text-xs font-bold rounded-full z-10 shadow">${{ number_format($tournament->price, 0, ',', '.') }} COP</span>
                @else
                    <span class="absolute top-3 right-3 px-2.5 py-0.5 bg-green-400 text-green-900 text-xs font-bold rounded-full z-10">GRATIS</span>
                @endif
            </div>
            <div class="p-5">
                <h3 class="font-semibold text-base mb-1 group-hover:text-tc-primary transition-colors">{{ $tournament->name }}</h3>
                @if($tournament->city || $tournament->country)
                <p class="text-sm text-gray-500 flex items-center gap-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    {{ $tournament->city }}{{ $tournament->country ? ', ' . $tournament->country : '' }}
                </p>
                @endif
                <div class="flex items-center justify-between mt-3 pt-3 border-t border-gray-100">
                    <span class="text-xs text-gray-400">{{ $tournament->start_date->format('d M') }} - {{ $tournament->end_date->format('d M') }}</span>
                    <span class="text-xs font-medium px-2 py-0.5 bg-gray-100 rounded-lg">{{ $tournament->surface ?? $tournament->type }}</span>
                </div>
            </div>
        </a>
        @endforeach
    </div>
</section>
@endif

{{-- ========== TOURNAMENT RANKINGS ========== --}}
@if(count($tournamentRankings) > 0)
<section class="bg-white py-14">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h2 class="text-2xl md:text-3xl font-bold">Rankings por Torneo</h2>
                <p class="text-sm text-gray-500 mt-1">Los mejores pronosticadores en los torneos activos</p>
            </div>
            <a href="{{ route('rankings.index') }}" class="text-tc-primary text-sm font-medium hover:underline">Ver todos &rarr;</a>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            @foreach($tournamentRankings as $tr)
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden reveal" data-delay="{{ $loop->index * 100 }}">
                <div class="bg-gradient-to-r {{ str_starts_with($tr['tournament']->type, 'ATP') ? 'from-tc-primary to-tc-primary/80' : 'from-purple-600 to-pink-500' }} px-5 py-3">
                    <a href="{{ route('tournaments.show', $tr['tournament']) }}" class="text-white font-bold text-sm hover:underline">{{ $tr['tournament']->name }}</a>
                    <div class="text-white/60 text-[10px] mt-0.5">{{ $tr['tournament']->city }}, {{ $tr['tournament']->country }}</div>
                </div>
                @foreach($tr['ranking'] as $i => $ru)
                <div class="flex items-center gap-3 px-5 py-2.5 {{ !$loop->last ? 'border-b border-gray-50' : '' }}">
                    <div class="w-6 h-6 flex items-center justify-center rounded-full text-[10px] font-bold {{ $i === 0 ? 'bg-tc-accent text-tc-primary' : 'bg-gray-100 text-gray-400' }}">
                        {{ $i + 1 }}
                    </div>
                    <div class="w-7 h-7 bg-tc-primary rounded-full flex items-center justify-center text-white text-[10px] font-bold">
                        {{ strtoupper(substr($ru->name, 0, 1)) }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="text-sm font-medium truncate">{{ $ru->name }}</div>
                        <div class="text-[10px] text-gray-400">{{ $ru->correct_count }} aciertos</div>
                    </div>
                    <div class="text-sm font-bold text-tc-primary">{{ number_format($ru->tournament_points) }} pts</div>
                </div>
                @endforeach
            </div>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- ========== RESULTADOS RECIENTES ========== --}}
@if($recentResults->count() > 0)
<section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-14">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-2xl md:text-3xl font-bold">Resultados Recientes</h2>
            <p class="text-sm text-gray-500 mt-1">Partidos finalizados y puntos otorgados</p>
        </div>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        @foreach($recentResults as $match)
        <div class="bg-white rounded-2xl p-5 hover-lift border border-gray-100 shadow-sm reveal" data-delay="{{ $loop->index * 60 }}">
            <div class="text-xs text-gray-500 mb-3 flex items-center justify-between">
                <span>{{ $match->tournament->name }} &middot; {{ $match->round }}</span>
                <span class="px-2 py-0.5 bg-green-100 text-green-700 rounded-full font-medium">FINAL</span>
            </div>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2 flex-1">
                    <div class="w-9 h-9 {{ $match->winner_id === $match->player1_id ? 'ring-2 ring-green-400' : '' }} rounded-full overflow-hidden flex items-center justify-center">
                        <img src="{{ $match->player1->flag_url }}" alt="{{ $match->player1->nationality_code }}" class="w-full h-full object-cover">
                    </div>
                    <div>
                        <div class="font-semibold text-sm {{ $match->winner_id === $match->player1_id ? 'text-green-700' : '' }}">
                            {{ $match->player1->name }}
                            @if($match->winner_id === $match->player1_id) <span class="text-green-500">✓</span> @endif
                        </div>
                    </div>
                </div>
                <div class="px-3 py-1.5 bg-gray-50 rounded-xl text-center min-w-[70px]">
                    <div class="text-xs font-bold text-gray-700">{{ $match->score ?? '-' }}</div>
                </div>
                <div class="flex items-center gap-2 flex-1 justify-end text-right">
                    <div>
                        <div class="font-semibold text-sm {{ $match->winner_id === $match->player2_id ? 'text-green-700' : '' }}">
                            @if($match->winner_id === $match->player2_id) <span class="text-green-500">✓</span> @endif
                            {{ $match->player2->name }}
                        </div>
                    </div>
                    <div class="w-9 h-9 {{ $match->winner_id === $match->player2_id ? 'ring-2 ring-green-400' : '' }} rounded-full overflow-hidden flex items-center justify-center">
                        <img src="{{ $match->player2->flag_url }}" alt="{{ $match->player2->nationality_code }}" class="w-full h-full object-cover">
                    </div>
                </div>
            </div>
        </div>
        @endforeach
    </div>
</section>
@endif

{{-- ========== CTA Section ========== --}}
@guest
<section class="bg-gradient-to-r from-tc-primary to-tc-primary-dark py-16">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h2 class="text-3xl md:text-4xl font-bold text-white mb-4">Empieza a ganar hoy</h2>
        <p class="text-blue-100 text-lg mb-8">Regístrate gratis y comienza a hacer tus pronósticos en los mejores torneos de tenis.</p>
        <div class="flex flex-col sm:flex-row gap-4 justify-center">
            <a href="{{ route('register') }}" class="inline-block px-8 py-3.5 bg-tc-accent text-tc-primary-dark rounded-full text-base font-semibold hover:brightness-110 transition-all shadow-lg">
                Crear cuenta gratis
            </a>
            <a href="{{ route('rules') }}" class="inline-block px-8 py-3.5 bg-white/10 text-white border border-white/30 rounded-full text-base font-semibold hover:bg-white/20 transition-all">
                Ver reglas del juego
            </a>
        </div>
    </div>
</section>
@endguest
@endsection
