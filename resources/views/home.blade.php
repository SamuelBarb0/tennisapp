@extends('layouts.app')
@section('title', 'Inicio')

@section('content')
{{-- Hero Section --}}
<section class="relative bg-gradient-to-br from-[#0071E3] via-[#0062CC] to-[#004BA0] overflow-hidden">
    <div class="absolute inset-0 opacity-10">
        <div class="absolute top-20 left-10 w-72 h-72 bg-white rounded-full blur-3xl"></div>
        <div class="absolute bottom-10 right-20 w-96 h-96 bg-white rounded-full blur-3xl"></div>
    </div>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 md:py-28 relative">
        <div class="text-center fade-in">
            <h1 class="text-4xl md:text-6xl font-bold text-white tracking-tight mb-6">
                Predice. Compite. <span class="text-green-300">Gana.</span>
            </h1>
            <p class="text-lg md:text-xl text-blue-100 max-w-2xl mx-auto mb-10">
                Haz tus pronósticos en los mejores torneos de tenis del mundo y gana premios increíbles.
            </p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                @guest
                    <a href="{{ route('register') }}" class="px-8 py-3.5 bg-white text-[#0071E3] rounded-full text-base font-semibold hover:bg-gray-100 transition-all shadow-lg hover:shadow-xl hover:-translate-y-0.5">
                        Comenzar gratis
                    </a>
                    <a href="{{ route('tournaments.index') }}" class="px-8 py-3.5 bg-white/10 text-white border border-white/30 rounded-full text-base font-semibold hover:bg-white/20 transition-all backdrop-blur">
                        Ver torneos
                    </a>
                @else
                    <a href="{{ route('tournaments.index') }}" class="px-8 py-3.5 bg-white text-[#0071E3] rounded-full text-base font-semibold hover:bg-gray-100 transition-all shadow-lg hover:shadow-xl hover:-translate-y-0.5">
                        Hacer pronósticos
                    </a>
                @endguest
            </div>
        </div>
        {{-- Stats --}}
        <div class="mt-16 grid grid-cols-2 md:grid-cols-4 gap-4 md:gap-8 max-w-3xl mx-auto slide-up">
            <div class="text-center">
                <div class="text-3xl md:text-4xl font-bold text-white">23</div>
                <div class="text-sm text-blue-200 mt-1">Torneos</div>
            </div>
            <div class="text-center">
                <div class="text-3xl md:text-4xl font-bold text-white">40+</div>
                <div class="text-sm text-blue-200 mt-1">Jugadores</div>
            </div>
            <div class="text-center">
                <div class="text-3xl md:text-4xl font-bold text-white">{{ $topUsers->count() > 0 ? number_format($topUsers->sum('points')) : '0' }}</div>
                <div class="text-sm text-blue-200 mt-1">Puntos repartidos</div>
            </div>
            <div class="text-center">
                <div class="text-3xl md:text-4xl font-bold text-white">8</div>
                <div class="text-sm text-blue-200 mt-1">Premios</div>
            </div>
        </div>
    </div>
</section>

{{-- Live Matches --}}
@if($liveMatches->count() > 0)
<section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 -mt-8 relative z-10">
    <div class="bg-white rounded-3xl shadow-xl p-6 md:p-8 border border-gray-100">
        <div class="flex items-center gap-3 mb-6">
            <span class="relative flex h-3 w-3">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-3 w-3 bg-red-500"></span>
            </span>
            <h2 class="text-xl font-bold">En Vivo</h2>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @foreach($liveMatches as $match)
            <div class="bg-gray-50 rounded-2xl p-5 hover-lift">
                <div class="text-xs text-gray-500 mb-3 flex items-center gap-2">
                    <span class="px-2 py-0.5 bg-red-100 text-red-600 rounded-full font-medium">LIVE</span>
                    {{ $match->tournament->name }} &middot; {{ $match->round }}
                </div>
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3 flex-1">
                        <div class="w-10 h-10 bg-[#0071E3]/10 rounded-full flex items-center justify-center text-sm font-bold text-[#0071E3]">
                            {{ strtoupper(substr($match->player1->nationality_code, 0, 3)) }}
                        </div>
                        <div>
                            <div class="font-semibold text-sm">{{ $match->player1->name }}</div>
                            <div class="text-xs text-gray-400">#{{ $match->player1->ranking }}</div>
                        </div>
                    </div>
                    <div class="px-4 py-2 bg-white rounded-xl text-center min-w-[80px] shadow-sm">
                        <div class="text-xs text-gray-400">VS</div>
                    </div>
                    <div class="flex items-center gap-3 flex-1 justify-end text-right">
                        <div>
                            <div class="font-semibold text-sm">{{ $match->player2->name }}</div>
                            <div class="text-xs text-gray-400">#{{ $match->player2->ranking }}</div>
                        </div>
                        <div class="w-10 h-10 bg-green-50 rounded-full flex items-center justify-center text-sm font-bold text-green-600">
                            {{ strtoupper(substr($match->player2->nationality_code, 0, 3)) }}
                        </div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- Upcoming Tournaments --}}
<section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
    <div class="flex items-center justify-between mb-8">
        <h2 class="text-2xl md:text-3xl font-bold">Torneos</h2>
        <a href="{{ route('tournaments.index') }}" class="text-[#0071E3] text-sm font-medium hover:underline">Ver todos &rarr;</a>
    </div>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
        @foreach($tournaments as $tournament)
        <a href="{{ route('tournaments.show', $tournament) }}" class="group block bg-white rounded-3xl overflow-hidden shadow-sm hover-lift border border-gray-100">
            <div class="h-40 bg-gradient-to-br {{ $tournament->type === 'GrandSlam' ? 'from-yellow-400 to-orange-500' : ($tournament->type === 'ATP' ? 'from-[#0071E3] to-blue-700' : 'from-purple-500 to-pink-500') }} flex items-center justify-center relative">
                <span class="text-white/20 text-8xl font-black">{{ substr($tournament->type, 0, 1) }}</span>
                @if($tournament->is_premium)
                    <span class="absolute top-4 right-4 px-3 py-1 bg-yellow-400 text-yellow-900 text-xs font-bold rounded-full">PREMIUM</span>
                @endif
                @php $status = $tournament->status; @endphp
                <span class="absolute top-4 left-4 px-3 py-1 {{ $status === 'live' ? 'bg-red-500 text-white' : ($status === 'upcoming' ? 'bg-white/90 text-gray-700' : 'bg-gray-700 text-white') }} text-xs font-medium rounded-full">
                    {{ $status === 'live' ? 'En curso' : ($status === 'upcoming' ? 'Próximamente' : 'Finalizado') }}
                </span>
            </div>
            <div class="p-5">
                <h3 class="font-semibold text-base mb-1 group-hover:text-[#0071E3] transition-colors">{{ $tournament->name }}</h3>
                <p class="text-sm text-gray-500 flex items-center gap-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    {{ $tournament->city }}, {{ $tournament->country }}
                </p>
                <div class="flex items-center justify-between mt-3 pt-3 border-t border-gray-100">
                    <span class="text-xs text-gray-400">{{ $tournament->start_date->format('d M') }} - {{ $tournament->end_date->format('d M, Y') }}</span>
                    <span class="text-xs font-medium px-2 py-1 bg-gray-100 rounded-lg">{{ $tournament->surface ?? $tournament->type }}</span>
                </div>
            </div>
        </a>
        @endforeach
    </div>
</section>

{{-- Upcoming Matches --}}
@if($upcomingMatches->count() > 0)
<section class="bg-white py-16">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-2xl md:text-3xl font-bold mb-8">Próximos partidos</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @foreach($upcomingMatches as $match)
            <div class="bg-gray-50 rounded-2xl p-5 hover-lift">
                <div class="text-xs text-gray-500 mb-3">
                    {{ $match->tournament->name }} &middot; {{ $match->round }} &middot; {{ $match->scheduled_at->format('d M, H:i') }}
                </div>
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3 flex-1">
                        <div class="w-10 h-10 bg-[#0071E3]/10 rounded-full flex items-center justify-center text-sm font-bold text-[#0071E3]">
                            {{ strtoupper(substr($match->player1->nationality_code, 0, 3)) }}
                        </div>
                        <div>
                            <div class="font-semibold text-sm">{{ $match->player1->name }}</div>
                            <div class="text-xs text-gray-400">#{{ $match->player1->ranking }}</div>
                        </div>
                    </div>
                    <div class="px-3 py-1.5 bg-white rounded-xl text-xs text-gray-400 shadow-sm">VS</div>
                    <div class="flex items-center gap-3 flex-1 justify-end text-right">
                        <div>
                            <div class="font-semibold text-sm">{{ $match->player2->name }}</div>
                            <div class="text-xs text-gray-400">#{{ $match->player2->ranking }}</div>
                        </div>
                        <div class="w-10 h-10 bg-green-50 rounded-full flex items-center justify-center text-sm font-bold text-green-600">
                            {{ strtoupper(substr($match->player2->nationality_code, 0, 3)) }}
                        </div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- Top Rankings --}}
@if($topUsers->count() > 0)
<section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
    <div class="flex items-center justify-between mb-8">
        <h2 class="text-2xl md:text-3xl font-bold">Top Rankings</h2>
        <a href="{{ route('rankings.index') }}" class="text-[#0071E3] text-sm font-medium hover:underline">Ver ranking completo &rarr;</a>
    </div>
    <div class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden">
        @foreach($topUsers as $index => $user)
        <div class="flex items-center gap-4 px-6 py-4 {{ !$loop->last ? 'border-b border-gray-50' : '' }} hover:bg-gray-50 transition-colors">
            <div class="w-8 h-8 flex items-center justify-center rounded-full {{ $index === 0 ? 'bg-yellow-100 text-yellow-600' : ($index === 1 ? 'bg-gray-100 text-gray-500' : ($index === 2 ? 'bg-orange-100 text-orange-600' : 'bg-gray-50 text-gray-400')) }} text-sm font-bold">
                {{ $index + 1 }}
            </div>
            <div class="w-9 h-9 bg-[#0071E3] rounded-full flex items-center justify-center text-white text-sm font-bold">
                {{ strtoupper(substr($user->name, 0, 1)) }}
            </div>
            <div class="flex-1">
                <div class="font-medium text-sm">{{ $user->name }}</div>
            </div>
            <div class="text-right">
                <div class="font-bold text-[#0071E3]">{{ number_format($user->points) }}</div>
                <div class="text-xs text-gray-400">puntos</div>
            </div>
        </div>
        @endforeach
    </div>
</section>
@endif

{{-- CTA Section --}}
@guest
<section class="bg-gradient-to-r from-[#0071E3] to-[#004BA0] py-16">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h2 class="text-3xl md:text-4xl font-bold text-white mb-4">Empieza a ganar hoy</h2>
        <p class="text-blue-100 text-lg mb-8">Regístrate gratis y comienza a hacer tus pronósticos en los mejores torneos de tenis.</p>
        <a href="{{ route('register') }}" class="inline-block px-8 py-3.5 bg-white text-[#0071E3] rounded-full text-base font-semibold hover:bg-gray-100 transition-all shadow-lg">
            Crear cuenta gratis
        </a>
    </div>
</section>
@endguest
@endsection
