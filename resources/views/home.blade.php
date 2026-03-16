@extends('layouts.app')
@section('title', 'Inicio')

@section('content')
{{-- Hero Section --}}
<section class="relative bg-gradient-to-br from-tc-primary via-tc-primary-hover to-tc-primary-dark overflow-hidden">
    <div class="absolute inset-0 opacity-10">
        <div class="absolute top-20 left-10 w-72 h-72 bg-white rounded-full blur-3xl"></div>
        <div class="absolute bottom-10 right-20 w-96 h-96 bg-white rounded-full blur-3xl"></div>
    </div>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 md:py-24 relative">
        <div class="text-center fade-in">
            <h1 class="text-4xl md:text-6xl font-bold text-white tracking-tight mb-6">
                Predice. Compite. <span class="text-tc-accent">Gana.</span>
            </h1>
            <p class="text-lg md:text-xl text-blue-100 max-w-2xl mx-auto mb-10">
                Haz tus pronósticos en los mejores torneos de tenis del mundo y gana premios increíbles.
            </p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                @guest
                    <a href="{{ route('register') }}" class="px-8 py-3.5 bg-tc-accent text-tc-primary-dark rounded-full text-base font-semibold hover:brightness-110 transition-all shadow-lg hover:shadow-xl hover:-translate-y-0.5">
                        Comenzar gratis
                    </a>
                    <a href="{{ route('tournaments.index') }}" class="px-8 py-3.5 bg-white/10 text-white border border-white/30 rounded-full text-base font-semibold hover:bg-white/20 transition-all backdrop-blur">
                        Ver torneos
                    </a>
                @else
                    <a href="{{ route('tournaments.index') }}" class="px-8 py-3.5 bg-tc-accent text-tc-primary-dark rounded-full text-base font-semibold hover:brightness-110 transition-all shadow-lg hover:shadow-xl hover:-translate-y-0.5">
                        Hacer pronósticos
                    </a>
                    <a href="{{ route('rules') }}" class="px-8 py-3.5 bg-white/10 text-white border border-white/30 rounded-full text-base font-semibold hover:bg-white/20 transition-all backdrop-blur">
                        ¿Cómo funciona?
                    </a>
                @endguest
            </div>
        </div>
        {{-- Stats dinámicos --}}
        <div class="mt-14 grid grid-cols-2 md:grid-cols-4 gap-4 md:gap-8 max-w-3xl mx-auto slide-up">
            <div class="text-center">
                <div class="text-3xl md:text-4xl font-bold text-white">{{ $stats['tournaments'] }}</div>
                <div class="text-sm text-blue-200 mt-1">Torneos</div>
            </div>
            <div class="text-center">
                <div class="text-3xl md:text-4xl font-bold text-white">{{ number_format($stats['players']) }}</div>
                <div class="text-sm text-blue-200 mt-1">Jugadores</div>
            </div>
            <div class="text-center">
                <div class="text-3xl md:text-4xl font-bold text-white">{{ number_format($stats['total_points']) }}</div>
                <div class="text-sm text-blue-200 mt-1">Puntos repartidos</div>
            </div>
            <div class="text-center">
                <div class="text-3xl md:text-4xl font-bold text-white">{{ number_format($stats['users']) }}</div>
                <div class="text-sm text-blue-200 mt-1">Participantes</div>
            </div>
        </div>
    </div>
</section>

{{-- ========== PRÓXIMO TORNEO PARA PREDECIR (Protagonista) ========== --}}
@if($nextTournament)
<section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 -mt-8 relative z-10">
    <div class="bg-white rounded-3xl shadow-xl border border-gray-100 overflow-hidden">
        <div class="md:flex">
            {{-- Lado izquierdo: info del torneo --}}
            <div class="md:w-1/2 p-6 md:p-8">
                <div class="flex items-center gap-3 mb-4">
                    <span class="px-3 py-1 bg-tc-accent text-tc-primary-dark text-xs font-bold rounded-full">PRÓXIMO PARA PREDECIR</span>
                    @if($nextTournament->is_premium)
                        <span class="px-3 py-1 bg-yellow-100 text-yellow-700 text-xs font-bold rounded-full">PREMIUM</span>
                    @else
                        <span class="px-3 py-1 bg-green-100 text-green-700 text-xs font-bold rounded-full">GRATIS</span>
                    @endif
                </div>
                <h2 class="text-2xl md:text-3xl font-bold mb-2">{{ $nextTournament->name }}</h2>
                @if($nextTournament->city || $nextTournament->country)
                <p class="text-gray-500 flex items-center gap-1 mb-3">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    {{ $nextTournament->city }}{{ $nextTournament->country ? ', ' . $nextTournament->country : '' }}
                </p>
                @endif
                <div class="flex flex-wrap gap-3 mb-5">
                    <span class="text-sm text-gray-500 bg-gray-100 px-3 py-1 rounded-lg">{{ $nextTournament->start_date->format('d M') }} - {{ $nextTournament->end_date->format('d M, Y') }}</span>
                    @if($nextTournament->surface)
                    <span class="text-sm text-gray-500 bg-gray-100 px-3 py-1 rounded-lg">{{ $nextTournament->surface }}</span>
                    @endif
                </div>
                <div class="flex items-center gap-2 text-sm text-gray-500 mb-6">
                    <svg class="w-5 h-5 text-tc-accent" fill="currentColor" viewBox="0 0 20 20"><path d="M10 15l-5.878 3.09 1.123-6.545L.489 6.91l6.572-.955L10 0l2.939 5.955 6.572.955-4.756 4.635 1.123 6.545z"/></svg>
                    <span><strong>{{ $nextTournament->pending_matches_count }}</strong> partidos disponibles para predecir</span>
                </div>
                <a href="{{ route('tournaments.show', $nextTournament) }}" class="inline-flex items-center gap-2 px-6 py-3 bg-tc-primary text-white rounded-full font-semibold hover:bg-tc-primary-hover transition-all shadow-md hover:shadow-lg hover:-translate-y-0.5">
                    Predecir ahora
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </a>
            </div>
            {{-- Lado derecho: visual del torneo --}}
            <div class="md:w-1/2 bg-gradient-to-br {{ $nextTournament->type === 'GrandSlam' ? 'from-yellow-400 to-orange-500' : ($nextTournament->type === 'ATP' ? 'from-tc-primary to-blue-700' : 'from-purple-500 to-pink-500') }} flex items-center justify-center p-8 min-h-[200px]">
                <div class="text-center text-white">
                    <span class="text-white/30 text-9xl font-black block leading-none">{{ substr($nextTournament->type, 0, 1) }}</span>
                    <span class="text-white/80 text-lg font-semibold mt-2 block">{{ $nextTournament->type }}</span>
                </div>
            </div>
        </div>
    </div>
</section>
@endif

{{-- ========== TORNEO EN VIVO (si hay) ========== --}}
@if($liveTournament)
<section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-10">
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
        <a href="{{ route('tournaments.show', $tournament) }}" class="group block bg-white rounded-3xl overflow-hidden shadow-sm hover-lift border border-gray-100">
            <div class="h-32 bg-gradient-to-br {{ $tournament->type === 'GrandSlam' ? 'from-yellow-400 to-orange-500' : ($tournament->type === 'ATP' ? 'from-tc-primary to-blue-700' : 'from-purple-500 to-pink-500') }} flex items-center justify-center relative">
                <span class="text-white/20 text-7xl font-black">{{ substr($tournament->type, 0, 1) }}</span>
                @if($tournament->is_premium)
                    <span class="absolute top-3 right-3 px-2.5 py-0.5 bg-yellow-400 text-yellow-900 text-xs font-bold rounded-full">PREMIUM</span>
                @else
                    <span class="absolute top-3 right-3 px-2.5 py-0.5 bg-green-400 text-green-900 text-xs font-bold rounded-full">GRATIS</span>
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

{{-- ========== TOP RANKING ========== --}}
@if($topUsers->count() > 0)
<section class="bg-white py-14">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h2 class="text-2xl md:text-3xl font-bold">Ranking de Jugadores</h2>
                <p class="text-sm text-gray-500 mt-1">Los mejores pronosticadores de Tennis Challenge</p>
            </div>
            <a href="{{ route('rankings.index') }}" class="text-tc-primary text-sm font-medium hover:underline">Ver completo &rarr;</a>
        </div>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Podio: Top 3 --}}
            <div class="lg:col-span-1 bg-gradient-to-br from-tc-primary to-tc-primary-hover rounded-3xl p-6 text-white">
                <h3 class="text-sm font-medium text-white/70 mb-6 uppercase tracking-wider">Podio</h3>
                @foreach($topUsers->take(3) as $index => $user)
                <div class="flex items-center gap-4 {{ !$loop->last ? 'mb-5' : '' }}">
                    <div class="w-10 h-10 rounded-full flex items-center justify-center text-lg font-bold {{ $index === 0 ? 'bg-tc-accent text-tc-primary-dark' : ($index === 1 ? 'bg-white/20 text-white' : 'bg-orange-400/80 text-white') }}">
                        {{ $index + 1 }}
                    </div>
                    <div class="flex-1">
                        <div class="font-semibold text-sm">{{ $user->name }}</div>
                        <div class="text-xs text-white/60">{{ $user->predictions_count ?? 0 }} pronósticos</div>
                    </div>
                    <div class="text-right">
                        <div class="font-bold text-tc-accent">{{ number_format($user->points) }}</div>
                        <div class="text-xs text-white/50">pts</div>
                    </div>
                </div>
                @endforeach
            </div>

            {{-- Tabla: Posiciones 4-10 --}}
            <div class="lg:col-span-2 bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-50">
                    <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider">Clasificación General</h3>
                </div>
                @foreach($topUsers as $index => $user)
                @if($index >= 3)
                <div class="flex items-center gap-4 px-6 py-3.5 {{ !$loop->last ? 'border-b border-gray-50' : '' }} hover:bg-gray-50 transition-colors">
                    <div class="w-7 h-7 flex items-center justify-center rounded-full bg-gray-100 text-gray-500 text-xs font-bold">
                        {{ $index + 1 }}
                    </div>
                    <div class="w-8 h-8 bg-tc-primary rounded-full flex items-center justify-center text-white text-xs font-bold">
                        {{ strtoupper(substr($user->name, 0, 1)) }}
                    </div>
                    <div class="flex-1">
                        <div class="font-medium text-sm">{{ $user->name }}</div>
                    </div>
                    <div class="text-right">
                        <div class="font-bold text-tc-primary text-sm">{{ number_format($user->points) }} pts</div>
                    </div>
                </div>
                @endif
                @endforeach
                @if($topUsers->count() <= 3)
                <div class="px-6 py-8 text-center text-sm text-gray-400">
                    Más participantes se unirán pronto
                </div>
                @endif
            </div>
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
        <div class="bg-white rounded-2xl p-5 hover-lift border border-gray-100 shadow-sm">
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
