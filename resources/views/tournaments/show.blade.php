@extends('layouts.app')
@section('title', $tournament->name)

@section('content')
<div class="bg-gradient-to-br {{ $tournament->type === 'GrandSlam' ? 'from-yellow-400 to-orange-500' : ($tournament->type === 'ATP' ? 'from-[#0071E3] to-blue-700' : 'from-purple-500 to-pink-500') }} py-16">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="fade-in">
            <div class="flex items-center gap-3 mb-4">
                <a href="{{ route('tournaments.index') }}" class="text-white/70 hover:text-white text-sm transition-colors">&larr; Torneos</a>
                @php $status = $tournament->status; @endphp
                <span class="px-3 py-1 {{ $status === 'live' ? 'bg-red-500' : ($status === 'upcoming' ? 'bg-white/20' : 'bg-gray-700') }} text-white text-xs font-medium rounded-full">
                    {{ $status === 'live' ? 'En curso' : ($status === 'upcoming' ? 'Próximamente' : 'Finalizado') }}
                </span>
                @if($tournament->is_premium)
                    <span class="px-3 py-1 bg-yellow-400 text-yellow-900 text-xs font-bold rounded-full">PREMIUM</span>
                @endif
            </div>
            <h1 class="text-3xl md:text-5xl font-bold text-white mb-3">{{ $tournament->name }}</h1>
            <div class="flex flex-wrap items-center gap-4 text-white/80 text-sm">
                <span class="flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>
                    {{ $tournament->city }}, {{ $tournament->country }}
                </span>
                <span class="flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    {{ $tournament->start_date->format('d M') }} - {{ $tournament->end_date->format('d M, Y') }}
                </span>
                @if($tournament->surface)
                <span>{{ $tournament->surface }}</span>
                @endif
                <span class="px-2 py-0.5 bg-white/20 rounded-full text-xs">x{{ $tournament->points_multiplier }} puntos</span>
            </div>
        </div>
    </div>
</div>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
    @auth
        @if($tournament->status !== 'finished')
        <div class="mb-8">
            <a href="{{ route('tournaments.predict', $tournament) }}" class="inline-flex items-center gap-2 px-6 py-3 bg-[#0071E3] text-white rounded-2xl font-medium hover:bg-[#0062CC] transition-all shadow-sm hover:shadow-md">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                Hacer pronósticos
            </a>
        </div>
        @endif
    @endauth

    {{-- Matches by Round --}}
    @forelse($matches as $round => $roundMatches)
    <div class="mb-10">
        <h2 class="text-xl font-bold mb-4 flex items-center gap-3">
            <span class="w-10 h-10 bg-gray-100 rounded-xl flex items-center justify-center text-sm font-bold text-gray-500">{{ $round }}</span>
            @switch($round)
                @case('R128') Ronda de 128 @break
                @case('R64') Ronda de 64 @break
                @case('R32') Ronda de 32 @break
                @case('R16') Octavos de Final @break
                @case('QF') Cuartos de Final @break
                @case('SF') Semifinal @break
                @case('F') Final @break
                @default {{ $round }}
            @endswitch
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @foreach($roundMatches as $match)
            <div class="bg-white rounded-2xl p-5 border border-gray-100 hover-lift">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-xs text-gray-400">{{ $match->scheduled_at->format('d M, H:i') }}</span>
                    <span class="px-2 py-0.5 rounded-full text-xs font-medium
                        {{ $match->status === 'live' ? 'bg-red-100 text-red-600' : ($match->status === 'finished' ? 'bg-green-100 text-green-600' : 'bg-gray-100 text-gray-500') }}">
                        {{ $match->status === 'live' ? 'En vivo' : ($match->status === 'finished' ? 'Finalizado' : 'Pendiente') }}
                    </span>
                </div>
                <div class="space-y-3">
                    <div class="flex items-center justify-between {{ $match->winner_id === $match->player1_id ? 'font-bold' : '' }}">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 bg-blue-50 rounded-full flex items-center justify-center text-xs font-bold text-[#0071E3]">
                                {{ strtoupper(substr($match->player1->nationality_code, 0, 3)) }}
                            </div>
                            <span class="text-sm">{{ $match->player1->name }}</span>
                            @if($match->winner_id === $match->player1_id)
                                <svg class="w-4 h-4 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                            @endif
                        </div>
                        <span class="text-xs text-gray-400">#{{ $match->player1->ranking }}</span>
                    </div>
                    <div class="flex items-center justify-between {{ $match->winner_id === $match->player2_id ? 'font-bold' : '' }}">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 bg-green-50 rounded-full flex items-center justify-center text-xs font-bold text-green-600">
                                {{ strtoupper(substr($match->player2->nationality_code, 0, 3)) }}
                            </div>
                            <span class="text-sm">{{ $match->player2->name }}</span>
                            @if($match->winner_id === $match->player2_id)
                                <svg class="w-4 h-4 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                            @endif
                        </div>
                        <span class="text-xs text-gray-400">#{{ $match->player2->ranking }}</span>
                    </div>
                </div>
                @if($match->score)
                <div class="mt-3 pt-3 border-t border-gray-50 text-center">
                    <span class="text-sm font-mono font-medium text-gray-600">{{ $match->score }}</span>
                </div>
                @endif
            </div>
            @endforeach
        </div>
    </div>
    @empty
    <div class="text-center py-16">
        <p class="text-gray-500">No hay partidos programados aún para este torneo.</p>
    </div>
    @endforelse
</div>
@endsection
