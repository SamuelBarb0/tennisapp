@extends('layouts.app')
@section('title', $tournament->name)

@push('styles')
<style>
    .bracket-scroll {
        scrollbar-width: thin;
        scrollbar-color: #1b3d5d33 transparent;
    }
    .bracket-scroll::-webkit-scrollbar { height: 6px; }
    .bracket-scroll::-webkit-scrollbar-track { background: transparent; }
    .bracket-scroll::-webkit-scrollbar-thumb { background: #1b3d5d33; border-radius: 3px; }
    .connector-line { stroke: #1b3d5d; stroke-width: 1.5; opacity: 0.25; }
    .match-card-pending { cursor: pointer; transition: all 0.15s; }
    .match-card-pending:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(27,61,93,0.15); }
</style>
@endpush

@section('content')
{{-- Hero Header --}}
<div class="bg-gradient-to-br {{ $tournament->type === 'GrandSlam' ? 'from-yellow-400 to-orange-500' : ($tournament->type === 'ATP' ? 'from-tc-primary to-blue-700' : 'from-purple-500 to-pink-500') }} py-12 md:py-16">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="fade-in text-center md:text-left">
            <div class="flex items-center justify-center md:justify-start gap-3 mb-4">
                <a href="{{ route('tournaments.index') }}" class="text-white/70 hover:text-white text-sm transition-colors">&larr; Torneos</a>
                @php $status = $tournament->status; @endphp
                <span class="px-3 py-1 {{ $status === 'live' ? 'bg-red-500' : ($status === 'upcoming' ? 'bg-white/20' : 'bg-white/30') }} text-white text-xs font-medium rounded-full">
                    {{ $status === 'live' ? 'En curso' : ($status === 'upcoming' ? 'Próximamente' : 'Finalizado') }}
                </span>
                @if($tournament->is_premium)
                    <span class="px-3 py-1 bg-tc-accent text-tc-primary text-xs font-bold rounded-full">PREMIUM</span>
                @endif
            </div>
            <h1 class="text-3xl md:text-5xl font-bold text-white mb-3">{{ $tournament->name }}</h1>
            <div class="flex flex-wrap items-center justify-center md:justify-start gap-4 text-white/80 text-sm">
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
                <span class="px-2 py-0.5 bg-white/20 rounded-full text-xs">{{ $tournament->type }}</span>
            </div>
        </div>
    </div>
</div>

@php
    $roundOrder = ['R128', 'R64', 'R32', 'R16', 'QF', 'SF', 'F'];
    $roundLabels = [
        'R128' => '1RA RONDA', 'R64' => '2DA RONDA', 'R32' => '3RA RONDA',
        'R16' => 'OCTAVOS', 'QF' => 'CUARTOS', 'SF' => 'SEMI', 'F' => 'FINAL'
    ];
    $roundPointsMap = $tournament->roundPoints->pluck('points', 'round');
    $orderedRounds = collect($roundOrder)->filter(fn($r) => isset($matches[$r]))->values();
    $extraRounds = collect($matches->keys())->diff($roundOrder)->values();
    $allRounds = $orderedRounds->merge($extraRounds);
    $totalRounds = $allRounds->count();
@endphp

{{-- Info bar --}}
<div class="bg-tc-primary border-b border-white/10">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3">
        <div class="flex flex-col sm:flex-row gap-3 items-start sm:items-center">
            {{-- Puntos por ronda --}}
            @if($tournament->roundPoints->count() > 0)
            <div class="flex items-center gap-2 flex-wrap flex-1">
                <span class="text-[10px] font-bold uppercase tracking-widest text-white/50">Puntos</span>
                @foreach($allRounds as $round)
                    @if(isset($roundPointsMap[$round]))
                    <div class="flex items-center gap-1 px-2.5 py-1 bg-white/10 rounded-lg">
                        <span class="text-[10px] font-medium text-white/70">{{ $roundLabels[$round] ?? $round }}</span>
                        <span class="text-[10px] font-bold text-tc-accent">{{ $roundPointsMap[$round] }}</span>
                    </div>
                    @endif
                @endforeach
            </div>
            @endif

            @auth
            <div class="flex items-center gap-2 text-[10px] text-white/60">
                <svg class="w-3.5 h-3.5 text-tc-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>Haz click en un partido pendiente para predecir</span>
            </div>
            @endauth

            {{-- Ranking dropdown --}}
            <div x-data="{ open: false }" class="relative">
                <button x-on:click="open = !open" class="flex items-center gap-2 px-3 py-1.5 bg-white/10 hover:bg-white/20 rounded-lg transition-colors">
                    <svg class="w-3.5 h-3.5 text-tc-accent" fill="currentColor" viewBox="0 0 20 20"><path d="M10 15l-5.878 3.09 1.123-6.545L.489 6.91l6.572-.955L10 0l2.939 5.955 6.572.955-4.756 4.635 1.123 6.545z"/></svg>
                    <span class="text-xs font-medium text-white">Ranking</span>
                    <svg class="w-3 h-3 text-white/50 transition-transform" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div x-show="open" x-on:click.outside="open = false" x-transition class="absolute right-0 top-full mt-2 w-72 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 overflow-hidden">
                    <div class="bg-tc-primary px-4 py-3">
                        <h3 class="text-white font-bold text-sm">Ranking del Torneo</h3>
                        <p class="text-white/60 text-[10px]">Mejores pronosticadores</p>
                    </div>
                    @if($tournamentRanking->count() > 0)
                        @foreach($tournamentRanking as $index => $rankedUser)
                        <div class="flex items-center gap-3 px-4 py-2.5 {{ !$loop->last ? 'border-b border-gray-50' : '' }}">
                            <div class="w-6 h-6 flex items-center justify-center rounded-full text-[10px] font-bold {{ $index === 0 ? 'bg-tc-accent text-tc-primary' : ($index === 1 ? 'bg-gray-200 text-gray-600' : ($index === 2 ? 'bg-orange-100 text-orange-600' : 'bg-gray-100 text-gray-400')) }}">
                                {{ $index + 1 }}
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="text-xs font-medium truncate">{{ $rankedUser->name }}</div>
                                <div class="text-[10px] text-gray-400">{{ $rankedUser->correct_predictions }}/{{ $rankedUser->tournament_predictions }} aciertos</div>
                            </div>
                            <div class="text-sm font-bold text-tc-primary">{{ number_format($rankedUser->tournament_points) }}</div>
                        </div>
                        @endforeach
                    @else
                        <div class="px-4 py-6 text-center">
                            <p class="text-xs text-gray-400">Aún no hay pronósticos</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Bracket Area --}}
<div class="bg-gradient-to-b from-gray-50 to-white min-h-[60vh]">
    @if($allRounds->count() > 0)
    <div class="bracket-scroll overflow-x-auto py-10">
        <div class="inline-flex items-stretch mx-auto" style="min-width: max-content; padding: 0 max(2rem, calc((100vw - {{ $totalRounds * 260 + ($totalRounds - 1) * 40 }}px) / 2));">
            @foreach($allRounds as $roundIndex => $round)
            @php
                $roundMatches = $matches[$round];
                $matchCount = $roundMatches->count();
                $isLast = $roundIndex === $allRounds->count() - 1;
                $isFirst = $roundIndex === 0;
            @endphp
            <div class="flex flex-col" style="width: 250px;">
                {{-- Round header --}}
                <div class="text-center mb-6">
                    <div class="inline-block px-4 py-1.5 rounded-full {{ $isLast ? 'bg-tc-primary text-white' : 'bg-tc-primary/10 text-tc-primary' }}">
                        <span class="text-[10px] font-bold uppercase tracking-widest">{{ $roundLabels[$round] ?? $round }}</span>
                    </div>
                    @if(isset($roundPointsMap[$round]))
                        <div class="text-[10px] font-bold text-tc-accent mt-1">{{ $roundPointsMap[$round] }} pts</div>
                    @endif
                </div>

                {{-- Matches column --}}
                <div class="flex flex-col justify-around flex-1" style="gap: {{ $isFirst ? '8' : max(8, (pow(2, $roundIndex) - 1) * 52) }}px;">
                    @foreach($roundMatches as $matchIndex => $match)
                    @php
                        $userPick = $userPredictions[$match->id] ?? null;
                        $isPending = $match->status === 'pending';
                        $p1Won = $match->winner_id === $match->player1_id;
                        $p2Won = $match->winner_id === $match->player2_id;
                        $p1Predicted = $userPick === $match->player1_id;
                        $p2Predicted = $userPick === $match->player2_id;
                    @endphp
                    <div class="px-2" x-data="{ showPredict: false, saving: false, saved: {{ $userPick ? 'true' : 'false' }}, pickedId: {{ $userPick ?? 'null' }} }">
                        {{-- Match card --}}
                        <div
                            {!! $isPending && auth()->check() ? 'x-on:click="showPredict = !showPredict"' : '' !!}
                            class="rounded-xl overflow-hidden shadow-sm {{ $isPending && auth()->check() ? 'match-card-pending' : '' }} {{ $match->status === 'live' ? 'ring-2 ring-red-400 shadow-red-100' : '' }} {{ $isLast && !$isPending ? 'ring-2 ring-tc-accent shadow-lg' : '' }}"
                            :class="showPredict ? 'ring-2 ring-tc-accent shadow-lg' : ''"
                            style="width: 234px;"
                        >
                            {{-- Status indicators --}}
                            @if($match->status === 'live')
                            <div class="bg-red-500 text-white text-[9px] font-bold text-center py-0.5 tracking-wider flex items-center justify-center gap-1">
                                <span class="w-1.5 h-1.5 bg-white rounded-full animate-pulse"></span> EN VIVO
                            </div>
                            @endif

                            {{-- Prediction badge --}}
                            <template x-if="saved && !showPredict">
                                <div class="bg-tc-accent text-tc-primary text-[9px] font-bold text-center py-0.5 tracking-wider">
                                    TU PRONÓSTICO
                                </div>
                            </template>

                            {{-- Player 1 --}}
                            <div class="flex items-center gap-1.5 px-3 py-2.5 border-b transition-colors"
                                :class="{
                                    'bg-tc-primary text-white border-tc-primary': {{ $p1Won ? 'true' : 'false' }},
                                    'bg-gray-50 text-gray-400 border-gray-100': {{ ($match->status === 'finished' && !$p1Won) ? 'true' : 'false' }},
                                    'bg-tc-accent/10 border-tc-accent/30': !{{ $p1Won ? 'true' : 'false' }} && !{{ ($match->status === 'finished' && !$p1Won) ? 'true' : 'false' }} && pickedId === {{ $match->player1_id }},
                                    'bg-white text-gray-800 border-gray-100': !{{ $p1Won ? 'true' : 'false' }} && !{{ ($match->status === 'finished' && !$p1Won) ? 'true' : 'false' }} && pickedId !== {{ $match->player1_id }}
                                }"
                            >
                                <span class="text-[9px] font-mono w-5 text-right shrink-0 {{ $p1Won ? 'text-white/60' : 'text-gray-400' }}">
                                    {{ $match->player1->ranking ?? '' }}
                                </span>
                                <img src="{{ $match->player1->flag_url }}" alt="{{ $match->player1->nationality_code }}" class="w-5 h-3.5 rounded-sm object-cover shrink-0" loading="lazy">
                                <span class="text-[11px] font-semibold truncate flex-1 {{ $p1Won ? 'text-white' : '' }}">
                                    {{ strtoupper($match->player1->name) }}
                                </span>
                                {{-- Prediction indicator --}}
                                <template x-if="pickedId === {{ $match->player1_id }} && !showPredict && !{{ $p1Won ? 'true' : 'false' }}">
                                    <svg class="w-3.5 h-3.5 text-tc-accent shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                </template>
                                @if($match->score)
                                    @php $sets = explode(' ', $match->score); @endphp
                                    @foreach($sets as $set)
                                        @php $parts = explode('-', $set); @endphp
                                        <span class="text-[10px] font-mono font-bold w-3.5 text-center {{ $p1Won ? 'text-tc-accent' : 'text-gray-400' }}">{{ $parts[0] ?? '' }}</span>
                                    @endforeach
                                @endif
                            </div>

                            {{-- Player 2 --}}
                            <div class="flex items-center gap-1.5 px-3 py-2.5 transition-colors"
                                :class="{
                                    'bg-tc-primary text-white': {{ $p2Won ? 'true' : 'false' }},
                                    'bg-gray-50 text-gray-400': {{ ($match->status === 'finished' && !$p2Won) ? 'true' : 'false' }},
                                    'bg-tc-accent/10': !{{ $p2Won ? 'true' : 'false' }} && !{{ ($match->status === 'finished' && !$p2Won) ? 'true' : 'false' }} && pickedId === {{ $match->player2_id }},
                                    'bg-white text-gray-800': !{{ $p2Won ? 'true' : 'false' }} && !{{ ($match->status === 'finished' && !$p2Won) ? 'true' : 'false' }} && pickedId !== {{ $match->player2_id }}
                                }"
                            >
                                <span class="text-[9px] font-mono w-5 text-right shrink-0 {{ $p2Won ? 'text-white/60' : 'text-gray-400' }}">
                                    {{ $match->player2->ranking ?? '' }}
                                </span>
                                <img src="{{ $match->player2->flag_url }}" alt="{{ $match->player2->nationality_code }}" class="w-5 h-3.5 rounded-sm object-cover shrink-0" loading="lazy">
                                <span class="text-[11px] font-semibold truncate flex-1 {{ $p2Won ? 'text-white' : '' }}">
                                    {{ strtoupper($match->player2->name) }}
                                </span>
                                {{-- Prediction indicator --}}
                                <template x-if="pickedId === {{ $match->player2_id }} && !showPredict && !{{ $p2Won ? 'true' : 'false' }}">
                                    <svg class="w-3.5 h-3.5 text-tc-accent shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                </template>
                                @if($match->score)
                                    @php $sets = explode(' ', $match->score); @endphp
                                    @foreach($sets as $set)
                                        @php $parts = explode('-', $set); @endphp
                                        <span class="text-[10px] font-mono font-bold w-3.5 text-center {{ $p2Won ? 'text-tc-accent' : 'text-gray-400' }}">{{ $parts[1] ?? '' }}</span>
                                    @endforeach
                                @endif
                            </div>

                            {{-- Pending match footer --}}
                            @if($isPending)
                            <div class="bg-tc-primary/5 text-center py-1 border-t border-gray-100">
                                <span class="text-[9px] font-medium text-tc-primary/60">{{ $match->scheduled_at->format('d M, H:i') }}</span>
                            </div>
                            @endif
                        </div>

                        {{-- Prediction panel (slides open below match) --}}
                        @if($isPending && auth()->check())
                        <div x-show="showPredict" x-collapse x-on:click.outside="showPredict = false" class="mt-1">
                            <div class="bg-white rounded-xl border-2 border-tc-accent shadow-lg overflow-hidden" style="width: 234px;">
                                <div class="bg-tc-accent px-3 py-2">
                                    <span class="text-[10px] font-bold text-tc-primary uppercase tracking-wider">¿Quién gana?</span>
                                </div>
                                <div class="p-2 space-y-1.5">
                                    {{-- Pick Player 1 --}}
                                    <button
                                        x-on:click.stop="saving = true; fetch('{{ route('predictions.store') }}', {
                                            method: 'POST',
                                            headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json'},
                                            body: JSON.stringify({match_id: {{ $match->id }}, predicted_winner_id: {{ $match->player1_id }}})
                                        }).then(r => r.json()).then(d => { if(d.success) { pickedId = {{ $match->player1_id }}; saved = true; } saving = false; showPredict = false; }).catch(() => { saving = false; })"
                                        :disabled="saving"
                                        class="w-full flex items-center gap-2 px-3 py-2.5 rounded-lg transition-all text-left"
                                        :class="pickedId === {{ $match->player1_id }} ? 'bg-tc-primary text-white' : 'bg-gray-50 hover:bg-tc-primary/10 text-gray-800'"
                                    >
                                        <img src="{{ $match->player1->flag_url }}" alt="{{ $match->player1->nationality_code }}" class="w-5 h-3.5 rounded-sm object-cover shrink-0">
                                        <span class="text-xs font-semibold flex-1 truncate">{{ $match->player1->name }}</span>
                                        <span class="text-[9px] opacity-60">{{ $match->player1->ranking ? '#'.$match->player1->ranking : '' }}</span>
                                        <template x-if="pickedId === {{ $match->player1_id }}">
                                            <svg class="w-4 h-4 text-tc-accent shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                        </template>
                                    </button>

                                    {{-- Pick Player 2 --}}
                                    <button
                                        x-on:click.stop="saving = true; fetch('{{ route('predictions.store') }}', {
                                            method: 'POST',
                                            headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json'},
                                            body: JSON.stringify({match_id: {{ $match->id }}, predicted_winner_id: {{ $match->player2_id }}})
                                        }).then(r => r.json()).then(d => { if(d.success) { pickedId = {{ $match->player2_id }}; saved = true; } saving = false; showPredict = false; }).catch(() => { saving = false; })"
                                        :disabled="saving"
                                        class="w-full flex items-center gap-2 px-3 py-2.5 rounded-lg transition-all text-left"
                                        :class="pickedId === {{ $match->player2_id }} ? 'bg-tc-primary text-white' : 'bg-gray-50 hover:bg-tc-primary/10 text-gray-800'"
                                    >
                                        <img src="{{ $match->player2->flag_url }}" alt="{{ $match->player2->nationality_code }}" class="w-5 h-3.5 rounded-sm object-cover shrink-0">
                                        <span class="text-xs font-semibold flex-1 truncate">{{ $match->player2->name }}</span>
                                        <span class="text-[9px] opacity-60">{{ $match->player2->ranking ? '#'.$match->player2->ranking : '' }}</span>
                                        <template x-if="pickedId === {{ $match->player2_id }}">
                                            <svg class="w-4 h-4 text-tc-accent shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                        </template>
                                    </button>
                                </div>
                                <div class="px-3 pb-2 flex items-center justify-between">
                                    <span class="text-[9px] text-gray-400">{{ $match->scheduled_at->format('d M, H:i') }}</span>
                                    <template x-if="saving">
                                        <span class="text-[9px] text-tc-primary font-medium">Guardando...</span>
                                    </template>
                                </div>
                            </div>
                        </div>
                        @endif
                    </div>
                    @endforeach
                </div>
            </div>

            {{-- Connector SVGs between rounds --}}
            @if(!$isLast)
            <div class="flex flex-col justify-around flex-shrink-0" style="width: 40px; padding-top: 50px;">
                @for($i = 0; $i < ceil($matchCount / 2); $i++)
                <div class="flex-1 flex items-center" style="min-height: 80px;">
                    <svg width="40" height="100%" viewBox="0 0 40 100" preserveAspectRatio="none" class="w-full h-full">
                        <line x1="0" y1="25" x2="20" y2="25" class="connector-line"/>
                        <line x1="0" y1="75" x2="20" y2="75" class="connector-line"/>
                        <line x1="20" y1="25" x2="20" y2="75" class="connector-line"/>
                        <line x1="20" y1="50" x2="40" y2="50" class="connector-line"/>
                    </svg>
                </div>
                @endfor
            </div>
            @endif
            @endforeach
        </div>
    </div>
    @else
    <div class="text-center py-20">
        <svg class="w-16 h-16 text-gray-200 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
        <p class="text-gray-400 font-medium">No hay partidos programados aún</p>
        <p class="text-gray-300 text-sm mt-1">Los partidos aparecerán aquí cuando se publique el cuadro</p>
    </div>
    @endif
</div>

{{-- Guest prompt --}}
@guest
<div class="bg-tc-primary/5 border-t border-tc-primary/10">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 text-center">
        <p class="text-sm text-gray-600 mb-3">Inicia sesión para hacer tus pronósticos y ganar puntos</p>
        <a href="{{ route('login') }}" class="inline-flex items-center gap-2 px-6 py-2.5 bg-tc-primary text-white rounded-xl text-sm font-medium hover:bg-tc-primary-hover transition-colors">Ingresar</a>
    </div>
</div>
@endguest
@endsection
