@extends('layouts.app')
@section('title', $tournament->name)

@push('styles')
<style>
    /* ─── Scrollbar ─── */
    .draw-scroll { scrollbar-width: thin; scrollbar-color: rgba(27,61,93,0.15) transparent; }
    .draw-scroll::-webkit-scrollbar { height: 6px; }
    .draw-scroll::-webkit-scrollbar-track { background: transparent; }
    .draw-scroll::-webkit-scrollbar-thumb { background: rgba(27,61,93,0.15); border-radius: 99px; }

    /* ─── Player row ─── */
    .pr {
        display: flex; align-items: center; gap: 5px;
        padding: 6px 9px; font-size: 10.5px; line-height: 1;
        border-left: 2.5px solid transparent;
        transition: background 0.13s ease, border-color 0.13s ease;
        min-height: 27px;
    }
    .pr.w  { background: #1b3d5d; color: #fff; border-left-color: #eee539; }
    .pr.w .ss { color: #eee539; font-weight: 800; }
    .pr.l  { background: #f8fafc; color: #94a3b8; }
    .pr.l .ss { color: #cbd5e1; }
    .pr.n  { background: #fff; color: #334155; border-left-color: #e2e8f0; }
    .pr.lv { background: #fff; color: #334155; border-left-color: #f97316; }

    .pr.pk {
        border-left-color: #eee539 !important;
        background: linear-gradient(90deg, #fefce8, #fffef7) !important;
        color: #334155 !important;
    }
    .pr.correct {
        border-left-color: #22c55e !important;
        background: linear-gradient(90deg, #dcfce7, #f0fdf5) !important;
        color: #166534 !important;
        font-weight: 700;
    }
    .pr.wrong {
        border-left-color: #ef4444 !important;
        background: linear-gradient(90deg, #fef2f2, #fff8f8) !important;
        color: #94a3b8 !important;
    }

    /* ─── Ghost pick ─── */
    .ghost-pick {
        display: flex; align-items: center; gap: 4px;
        padding: 3px 9px; font-size: 8.5px;
        background: repeating-linear-gradient(-45deg, #fff5f5, #fff5f5 4px, #fee2e2 4px, #fee2e2 8px);
        color: #ef4444; border-top: 1px dashed #fca5a5;
    }
    .ghost-name { text-decoration: line-through; opacity: 0.65; }

    /* ─── Match card ─── */
    .match-card {
        border-radius: 8px; overflow: hidden;
        border: 1px solid #e2e8f0;
        background: #fff;
        box-shadow: 0 1px 4px rgba(27,61,93,0.06), 0 0 0 0 transparent;
        transition: box-shadow 0.18s ease, border-color 0.18s ease, transform 0.18s ease;
    }
    .match-card:hover {
        box-shadow: 0 4px 16px rgba(27,61,93,0.11);
        border-color: #cbd5e1;
        transform: translateY(-1px);
    }
    .match-card.live-border  { border-color: #fdba74; box-shadow: 0 0 0 2px rgba(249,115,22,0.12); }
    .match-card.result-correct { border-color: #86efac; box-shadow: 0 0 0 2px rgba(34,197,94,0.1); }
    .match-card.result-wrong   { border-color: #fca5a5; box-shadow: 0 0 0 2px rgba(239,68,68,0.07); }
    .match-card.user-picked    { border-color: #eee539; box-shadow: 0 0 0 2px rgba(238,229,57,0.18); }

    /* ─── Round label ─── */
    .round-col-header {
        text-align: center; padding: 5px 4px 10px;
        font-size: 9.5px; font-weight: 800;
        text-transform: uppercase; letter-spacing: 0.10em;
        color: #64748b;
    }
    .round-col-header .rpts {
        display: block; font-size: 8.5px; font-weight: 700;
        color: #1b3d5d; margin-top: 2px; letter-spacing: 0.04em;
    }
    .round-col-header.is-final { color: #1b3d5d; }
    .round-col-header.is-final .rpts { color: #b8a800; }

    /* ─── Result badge ─── */
    .res-badge {
        display: inline-flex; align-items: center; gap: 3px;
        padding: 2.5px 7px; border-radius: 5px;
        font-size: 8.5px; font-weight: 800; letter-spacing: 0.06em;
        text-transform: uppercase;
    }
    .res-badge.ok  { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
    .res-badge.err { background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; }

    /* ─── Pick dot ─── */
    .pk-dot {
        width: 14px; height: 14px; border-radius: 50%;
        display: flex; align-items: center; justify-content: center; flex-shrink: 0;
    }
    .pk-dot.ok  { background: #dcfce7; border: 1px solid #86efac; }
    .pk-dot.err { background: #fee2e2; border: 1px solid #fca5a5; }
    .pk-dot.pnd { background: #fefce8; border: 1px solid #eee539; }

    /* ─── Surface badge ─── */
    .surface-hard   { background: #dbeafe; color: #1d4ed8; }
    .surface-clay   { background: #ffedd5; color: #c2410c; }
    .surface-grass  { background: #dcfce7; color: #15803d; }
    .surface-indoor { background: #ede9fe; color: #6d28d9; }

    /* ─── Hero pattern ─── */
    .hero-dots {
        background-image: radial-gradient(circle, rgba(255,255,255,0.07) 1px, transparent 1px);
        background-size: 24px 24px;
    }

    /* ─── Trophy glow ─── */
    .trophy-glow { animation: tglow 3s ease-in-out infinite; }
    @keyframes tglow {
        0%,100% { filter: drop-shadow(0 0 5px rgba(238,229,57,0.3)); }
        50%      { filter: drop-shadow(0 0 14px rgba(238,229,57,0.6)); }
    }

    /* ─── Champion card ─── */
    .champion-wrap {
        border: 1.5px solid #eee539;
        border-radius: 10px;
        background: linear-gradient(135deg, #fefce8 0%, #fffef0 100%);
        box-shadow: 0 2px 16px rgba(238,229,57,0.15);
        padding: 12px 8px;
        text-align: center;
    }

    /* ─── BYE card ─── */
    .bye-card {
        border-radius: 8px; overflow: hidden;
        border: 1px solid #f1f5f9;
        background: #f8fafc;
        opacity: 0.6;
    }

    /* ─── Ranking row ─── */
    .rank-row { transition: background 0.12s; }
    .rank-row:hover { background: #f8fafc; }

    /* ─── Modal ─── */
    .modal-surface {
        background: #fff;
        border-radius: 18px;
        overflow: hidden;
        box-shadow: 0 25px 60px rgba(0,0,0,0.18), 0 0 0 1px rgba(0,0,0,0.04);
    }

    /* ─── Action bar ─── */
    .bar-saved { background: linear-gradient(90deg,#f0fdf4,#f7fef9); border-bottom: 1px solid #bbf7d0; }
    .bar-open  { background: linear-gradient(90deg,#fefce8,#fffef5); border-bottom: 1px solid rgba(238,229,57,0.4); }

    /* ─── Animations ─── */
    @keyframes fadeUp { from { opacity:0; transform:translateY(10px); } to { opacity:1; transform:none; } }
    .fade-up { animation: fadeUp 0.35s ease both; }
    .fade-up-1 { animation: fadeUp 0.35s 0.07s ease both; }
    .fade-up-2 { animation: fadeUp 0.35s 0.14s ease both; }
</style>
@endpush

@section('content')

{{-- ═══════ HERO ═══════ --}}
@php
    $type = $tournament->type ?? '';
    $isGrandSlam = $type === 'GrandSlam';
    $isATP       = str_starts_with($type, 'ATP');
    $isWTA       = str_starts_with($type, 'WTA');

    // Per-category visual identity
    $heroCfg = match(true) {
        $isGrandSlam => [
            'bg'      => 'linear-gradient(135deg, #0d1f2d 0%, #1a3a2a 40%, #0f2a1a 70%, #0d1f2d 100%)',
            'orb1'    => 'radial-gradient(ellipse at 15% 50%, rgba(238,229,57,0.18) 0%, transparent 60%)',
            'orb2'    => 'radial-gradient(ellipse at 85% 20%, rgba(255,255,255,0.04) 0%, transparent 55%)',
            'accent'  => '#eee539',
            'tag_bg'  => 'rgba(238,229,57,0.15)',
            'tag_border' => 'rgba(238,229,57,0.4)',
            'tag_text'   => '#eee539',
            'label'   => 'GRAND SLAM',
            'label_icon' => '<path d="M10 15l-5.878 3.09 1.123-6.545L.489 6.91l6.572-.955L10 0l2.939 5.955 6.572.955-4.756 4.635 1.123 6.545z"/>',
        ],
        $isATP => [
            'bg'      => 'linear-gradient(135deg, #071829 0%, #0e2d4a 45%, #0a3060 100%)',
            'orb1'    => 'radial-gradient(ellipse at 10% 60%, rgba(41,128,222,0.25) 0%, transparent 60%)',
            'orb2'    => 'radial-gradient(ellipse at 90% 10%, rgba(100,180,255,0.08) 0%, transparent 50%)',
            'accent'  => '#60b4ff',
            'tag_bg'  => 'rgba(41,128,222,0.2)',
            'tag_border' => 'rgba(96,180,255,0.4)',
            'tag_text'   => '#93c9ff',
            'label'   => $type,
            'label_icon' => '<path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"/>',
        ],
        $isWTA => [
            'bg'      => 'linear-gradient(135deg, #1a0a2e 0%, #2d1155 45%, #1e0a3c 100%)',
            'orb1'    => 'radial-gradient(ellipse at 15% 50%, rgba(192,86,218,0.22) 0%, transparent 60%)',
            'orb2'    => 'radial-gradient(ellipse at 88% 15%, rgba(255,150,220,0.08) 0%, transparent 50%)',
            'accent'  => '#e879f9',
            'tag_bg'  => 'rgba(192,86,218,0.18)',
            'tag_border' => 'rgba(232,121,249,0.4)',
            'tag_text'   => '#f0abfc',
            'label'   => $type,
            'label_icon' => '<path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"/>',
        ],
        default => [
            'bg'      => 'linear-gradient(135deg, #0d1f2d 0%, #1b3d5d 50%, #0f2540 100%)',
            'orb1'    => 'radial-gradient(ellipse at 20% 60%, rgba(27,61,93,0.6) 0%, transparent 60%)',
            'orb2'    => 'radial-gradient(ellipse at 80% 15%, rgba(100,160,220,0.07) 0%, transparent 50%)',
            'accent'  => '#eee539',
            'tag_bg'  => 'rgba(238,229,57,0.12)',
            'tag_border' => 'rgba(238,229,57,0.3)',
            'tag_text'   => '#eee539',
            'label'   => $type ?: 'TORNEO',
            'label_icon' => '<path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"/>',
        ],
    };

    $surfaceLower = strtolower($tournament->surface ?? '');
    $surfaceClass = match(true) {
        str_contains($surfaceLower, 'clay')   => 'surface-clay',
        str_contains($surfaceLower, 'grass')  => 'surface-grass',
        str_contains($surfaceLower, 'indoor') => 'surface-indoor',
        default => 'surface-hard',
    };
@endphp

<div class="hero-tournament relative overflow-hidden" style="background: {{ $heroCfg['bg'] }};">

    {{-- Ambient orbs --}}
    <div class="absolute inset-0 pointer-events-none" style="background: {{ $heroCfg['orb1'] }};"></div>
    <div class="absolute inset-0 pointer-events-none" style="background: {{ $heroCfg['orb2'] }};"></div>

    {{-- Subtle grid texture --}}
    <div class="absolute inset-0 pointer-events-none" style="background-image: linear-gradient(rgba(255,255,255,0.025) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,0.025) 1px, transparent 1px); background-size: 40px 40px;"></div>

    {{-- Decorative accent line --}}
    <div class="absolute top-0 left-0 right-0 h-[2px]" style="background: linear-gradient(90deg, transparent 0%, {{ $heroCfg['accent'] }} 30%, {{ $heroCfg['accent'] }} 70%, transparent 100%); opacity: 0.7;"></div>
    <div class="absolute bottom-0 left-0 right-0 h-px" style="background: rgba(255,255,255,0.06);"></div>

    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 md:py-10">
        <div class="flex items-start justify-between gap-4">
            <div class="min-w-0">

                {{-- Back link --}}
                <a href="{{ route('tournaments.index') }}"
                   class="inline-flex items-center gap-1.5 text-[11px] font-semibold tracking-widest uppercase transition-all mb-4 group fade-up"
                   style="color: rgba(255,255,255,0.4);"
                   onmouseover="this.style.color='rgba(255,255,255,0.75)'" onmouseout="this.style.color='rgba(255,255,255,0.4)'">
                    <svg class="w-3 h-3 transition-transform group-hover:-translate-x-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/></svg>
                    Torneos
                </a>

                {{-- Title row --}}
                <div class="flex items-center gap-3 flex-wrap mb-2 fade-up">
                    <h1 class="text-2xl md:text-3xl font-black tracking-tight leading-tight" style="color: #ffffff; text-shadow: 0 1px 12px rgba(0,0,0,0.4);">{{ $tournament->name }}</h1>
                    {{-- Category badge --}}
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[9px] font-black uppercase tracking-widest"
                          style="background: {{ $heroCfg['tag_bg'] }}; border: 1px solid {{ $heroCfg['tag_border'] }}; color: {{ $heroCfg['tag_text'] }};">
                        <svg class="w-2.5 h-2.5" fill="currentColor" viewBox="0 0 20 20">{!! $heroCfg['label_icon'] !!}</svg>
                        {{ $heroCfg['label'] }}
                    </span>
                </div>

                {{-- Meta row --}}
                <div class="flex flex-wrap items-center gap-x-3 gap-y-1.5 fade-up-1">
                    <span class="text-[11px] font-medium" style="color: rgba(255,255,255,0.6);">{{ $tournament->city }}, {{ $tournament->country }}</span>
                    <span class="w-1 h-1 rounded-full" style="background: rgba(255,255,255,0.2);"></span>
                    <span class="text-[11px] font-medium" style="color: rgba(255,255,255,0.6);">
                        {{ $tournament->start_date->format('d M') }} – {{ $tournament->end_date->format('d M Y') }}
                    </span>
                    @if($tournament->surface)
                    <span class="w-1 h-1 rounded-full" style="background: rgba(255,255,255,0.2);"></span>
                    <span class="px-2 py-0.5 rounded-full text-[9px] font-bold uppercase tracking-wider {{ $surfaceClass }}">{{ $tournament->surface }}</span>
                    @endif
                    @if(($tournament->status ?? '') === 'in_progress')
                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[9px] font-bold" style="background: rgba(249,115,22,0.15); border: 1px solid rgba(249,115,22,0.35); color: #fb923c;">
                        <span class="w-1.5 h-1.5 rounded-full bg-orange-400 animate-pulse"></span>En vivo
                    </span>
                    @endif
                </div>

                @auth
                <div class="mt-3.5 fade-up-2">
                    @if($predictionsLocked)
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-[10px] font-semibold" style="background: rgba(0,0,0,0.25); color: rgba(255,255,255,0.5); border: 1px solid rgba(255,255,255,0.08);">
                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/></svg>
                        Predicciones cerradas
                    </span>
                    @else
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-[10px] font-bold"
                          style="background: {{ $heroCfg['tag_bg'] }}; color: {{ $heroCfg['tag_text'] }}; border: 1px solid {{ $heroCfg['tag_border'] }};">
                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.828a1 1 0 101.415-1.414L11 9.586V6z" clip-rule="evenodd"/></svg>
                        Cierra: {{ $lockDate?->format('d M, H:i') }}
                    </span>
                    @endif
                </div>
                @endauth
            </div>

            {{-- Ranking dropdown --}}
            <div x-data="{ open: false }" class="relative shrink-0 fade-up-1">
                <button x-on:click="open = !open"
                    class="flex items-center gap-2 px-4 py-2 rounded-xl text-[11px] font-bold transition-all backdrop-blur-sm"
                    style="background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.12); color: rgba(255,255,255,0.85);"
                    onmouseover="this.style.background='rgba(255,255,255,0.14)'" onmouseout="this.style.background='rgba(255,255,255,0.08)'">
                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20" style="color: {{ $heroCfg['accent'] }};"><path d="M10 15l-5.878 3.09 1.123-6.545L.489 6.91l6.572-.955L10 0l2.939 5.955 6.572.955-4.756 4.635 1.123 6.545z"/></svg>
                    <span class="hidden sm:inline">Ranking</span>
                    <svg class="w-3 h-3 transition-transform" :class="open && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color: rgba(255,255,255,0.3);"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>

                <div x-show="open" x-on:click.outside="open = false"
                     x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-2 scale-95" x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                     x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0 scale-95"
                     class="absolute right-0 top-full mt-2 w-80 bg-white rounded-2xl shadow-2xl z-50 overflow-hidden ring-1 ring-black/5" style="display:none;">
                    <div class="px-5 py-3.5" style="background: {{ $heroCfg['bg'] }};">
                        <h3 class="font-black text-sm tracking-tight" style="color: #fff;">Ranking del Torneo</h3>
                    </div>
                    <div class="max-h-72 overflow-y-auto">
                        @forelse($tournamentRanking as $i => $ru)
                        <div class="rank-row flex items-center gap-3 px-5 py-2.5 {{ !$loop->last ? 'border-b border-gray-50' : '' }}">
                            <div class="w-6 h-6 rounded-lg flex items-center justify-center text-[10px] font-black shrink-0 {{ $i===0 ? 'bg-tc-accent text-tc-primary' : ($i===1 ? 'bg-gray-100 text-gray-500' : ($i===2 ? 'bg-amber-100 text-amber-700' : 'bg-gray-50 text-gray-400')) }}">{{ $i+1 }}</div>
                            <div class="flex-1 min-w-0">
                                <div class="text-xs font-semibold text-gray-800 truncate">{{ $ru->name }}</div>
                            </div>
                            <div class="text-[10px] text-gray-400 font-mono">{{ $ru->correct_predictions }}/{{ $ru->tournament_predictions }}</div>
                            <div class="text-xs font-black text-tc-primary tabular-nums">{{ number_format($ru->tournament_points) }}<span class="text-[9px] font-medium text-gray-400 ml-0.5">pts</span></div>
                        </div>
                        @empty
                        <div class="px-5 py-8 text-center"><p class="text-xs text-gray-400">Aún no hay pronósticos</p></div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@php
    $roundOrder  = ['R128', 'R64', 'R32', 'R16', 'QF', 'SF', 'F'];
    $roundLabels = [
        'R128' => '1ra Ronda', 'R64' => '2da Ronda', 'R32' => '3ra Ronda',
        'R16'  => 'Octavos',   'QF'  => 'Cuartos',   'SF'  => 'Semifinal', 'F' => 'Final'
    ];
    $roundPointsMap = $tournament->roundPoints->pluck('points', 'round');
    $orderedRounds  = collect($roundOrder)->filter(fn($r) => isset($matches[$r]))->values();
    $bracketRounds  = $orderedRounds;

    $isPlaceholderName = fn($name) => !$name || preg_match('/^(Qf|SF|WSF|WQF|F|Ganador|TBD)\s?\d?/i', $name);
    $validPositionsMap = $bracketRounds->mapWithKeys(function($r) use ($matches, $isPlaceholderName) {
        $positions = [];
        foreach ($matches[$r]->sortBy('bracket_position')->values() as $i => $m) {
            if (!$isPlaceholderName($m->player1?->name) || !$isPlaceholderName($m->player2?->name)) {
                $positions[] = $i + 1;
            }
        }
        return [$r => $positions];
    })->all();
@endphp

<div class="bg-gradient-to-b from-gray-50 to-gray-100/60 min-h-[60vh]">

    @if($bracketRounds->count() > 0)
    <div x-data="bracketApp()" x-init="init()">

        {{-- Action bar --}}
        @auth
        @if($bracketSaved)
        <div class="bar-saved">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-2.5 flex items-center gap-3">
                <div class="w-6 h-6 rounded-full bg-green-500 flex items-center justify-center shrink-0">
                    <svg class="w-3.5 h-3.5 text-white" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                </div>
                <span class="text-sm font-bold text-green-800">Bracket guardado</span>
                <span class="text-xs text-green-600 hidden sm:inline">— Los resultados se actualizan conforme avanza el torneo</span>
            </div>
        </div>
        @elseif(!$predictionsLocked)
        <div class="bar-open">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-2.5 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-6 h-6 rounded-lg bg-tc-accent flex items-center justify-center shrink-0">
                        <svg class="w-3.5 h-3.5 text-tc-primary" fill="currentColor" viewBox="0 0 20 20"><path d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z"/></svg>
                    </div>
                    <span class="text-sm font-bold text-tc-primary">Llena tu bracket</span>
                    <span class="text-xs text-gray-400 hidden sm:inline">— Haz click en el jugador que crees que ganará</span>
                </div>
                <div class="flex items-center gap-3">
                    <span class="text-[10px] font-mono text-gray-400 hidden sm:inline" x-text="totalPicks + '/' + totalRequired + ' picks'"></span>
                    <button x-on:click="showConfirm = true" :disabled="saving || !isComplete"
                        class="px-5 py-2 rounded-xl text-xs font-bold transition-all shadow-sm disabled:shadow-none"
                        :class="isComplete
                            ? 'bg-tc-primary text-white hover:bg-tc-primary-hover hover:shadow-md'
                            : 'bg-gray-200 text-gray-400 cursor-not-allowed'">
                        <span x-show="!saving" x-text="isComplete ? 'Guardar Bracket' : 'Bracket incompleto'"></span>
                        <span x-show="saving">Guardando...</span>
                    </button>
                </div>
            </div>
        </div>
        @endif
        @endauth

        {{-- ═══════ BRACKET ═══════ --}}
        <div class="draw-scroll overflow-x-auto px-4 sm:px-6 py-8">
            <div style="width: max-content; margin: 0 auto;">

                {{-- Round headers --}}
                <div class="flex items-end mb-2">
                    @foreach($bracketRounds as $bri => $round)
                    @php $isLast = $bri === $bracketRounds->count() - 1; @endphp
                    <div style="width: 196px;" class="px-1">
                        <div class="round-col-header {{ $isLast ? 'is-final' : '' }}">
                            {{ $roundLabels[$round] ?? $round }}
                            @if(isset($roundPointsMap[$round]))
                            <span class="rpts">{{ $roundPointsMap[$round] }} pts</span>
                            @endif
                        </div>
                    </div>
                    @if(!$isLast)<div style="width: 26px;"></div>@endif
                    @endforeach
                    <div style="width: 26px;"></div>
                    <div style="width: 120px;" class="text-center">
                        <div class="round-col-header is-final">Campeón</div>
                    </div>
                </div>

                {{-- Bracket body --}}
                @php
                    $slotH      = 76;
                    $firstCount = $matches[$bracketRounds[0]]->count();
                    $totalH     = $firstCount * $slotH;
                @endphp
                <div class="flex" style="height: {{ $totalH }}px;">

                    @foreach($bracketRounds as $bri => $round)
                    @php
                        $roundMatches  = $matches[$round]->values();
                        $matchCount    = $roundMatches->count();
                        $isLast        = $bri === $bracketRounds->count() - 1;
                        $slotsPerMatch = $firstCount / max($matchCount, 1);
                        $matchH        = $slotsPerMatch * $slotH;
                    @endphp

                    {{-- Round column --}}
                    <div style="width: 196px; height: {{ $totalH }}px; position: relative; flex-shrink: 0;">
                        @foreach($roundMatches as $mi => $match)
                        @php
                            $topPx    = $mi * $matchH;
                            $centerPx = $topPx + $matchH / 2;
                        @endphp

                        {{-- BYE card --}}
                        @if($match->status === 'bye')
                        <div class="px-1" style="position:absolute; width:100%; transform:translateY(-50%); top:{{ $centerPx }}px;">
                            <div class="bye-card">
                                <div class="pr n" style="opacity:0.55;">
                                    <span class="text-[7.5px] font-mono w-3 text-right opacity-40 shrink-0">{{ $match->player1->ranking ?? '' }}</span>
                                    <img src="{{ $match->player1->flag_url }}" alt="" class="w-4 h-3 rounded-sm object-cover shrink-0" loading="lazy">
                                    <span class="font-semibold truncate flex-1">{{ strtoupper($match->player1->name) }}</span>
                                </div>
                                <div class="h-px bg-slate-100"></div>
                                <div class="pr l" style="justify-content:center;">
                                    <span class="text-[8px] font-bold uppercase tracking-widest text-slate-300">BYE</span>
                                </div>
                            </div>
                        </div>
                        @continue
                        @endif

                        @php
                            $isPending      = $match->status === 'pending';
                            $isLive         = $match->status === 'live';
                            $isFinished     = $match->status === 'finished';
                            $isCancelled    = $match->status === 'cancelled';
                            $p1Won          = $match->winner_id && $match->winner_id == $match->player1_id;
                            $p2Won          = $match->winner_id && $match->winner_id == $match->player2_id;
                            $isPlaceholder1 = $match->player1 && preg_match('/^(Qf|SF|WSF|WQF|F|Ganador|TBD)\s?\d?/i', $match->player1->name);
                            $isPlaceholder2 = $match->player2 && preg_match('/^(Qf|SF|WSF|WQF|F|Ganador|TBD)\s?\d?/i', $match->player2->name);
                            $position       = $mi + 1;
                            $userPick       = $userPicksJs[$round][$position] ?? null;
                            $pickPlayerId   = $userPick['player_id'] ?? null;
                            $pickCorrect    = $userPick ? $userPick['is_correct'] : null;
                            $pickPoints     = $userPick['points_earned'] ?? 0;
                            $feedPos1       = ($position - 1) * 2 + 1;
                            $feedPos2       = ($position - 1) * 2 + 2;

                            $cardClass = $isLive ? 'live-border' : '';
                            if ($pickCorrect === true)  $cardClass = 'result-correct';
                            elseif ($pickCorrect === false) $cardClass = 'result-wrong';
                            elseif ($pickPlayerId && $pickCorrect === null) $cardClass = 'user-picked';
                        @endphp

                        <div class="px-1" style="position:absolute; width:100%; transform:translateY(-50%); top:{{ $centerPx }}px;">

                            <div class="match-card {{ $cardClass }}">
                                {{-- Result badge (inside card, no overflow) --}}
                                @if($pickCorrect === true)
                                <div class="flex items-center justify-center gap-1 py-0.5 bg-green-50 border-b border-green-200/60">
                                    <svg class="w-2.5 h-2.5 text-green-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                    <span class="text-[8px] font-bold uppercase tracking-widest text-green-600">+{{ $pickPoints }} pts</span>
                                </div>
                                @elseif($pickCorrect === false)
                                <div class="flex items-center justify-center gap-1 py-0.5 bg-red-50 border-b border-red-200/60">
                                    <svg class="w-2.5 h-2.5 text-red-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                                    <span class="text-[8px] font-bold uppercase tracking-widest text-red-500">Fallaste</span>
                                </div>
                                @endif

                                {{-- Live bar --}}
                                @if($isLive)
                                <div class="flex items-center justify-center gap-1 py-0.5 bg-orange-50 border-b border-orange-200/60">
                                    <span class="w-1.5 h-1.5 rounded-full bg-orange-400 animate-pulse"></span>
                                    <span class="text-[8px] font-bold uppercase tracking-widest text-orange-500">En Vivo</span>
                                </div>
                                @endif

                                {{-- Player 1 --}}
                                @if($isPlaceholder1 && $bri > 0)
                                @php $prevRound = $bracketRounds[$bri - 1]; @endphp
                                <div class="pr n"
                                     :class="{ 'pk': isPickedHere('{{ $round }}', {{ $position }}, getPropagated('{{ $prevRound }}', {{ $feedPos1 }})), '!cursor-pointer': !locked && getPropagated('{{ $prevRound }}', {{ $feedPos1 }}) }"
                                     x-on:click="if(!locked && getPropagated('{{ $prevRound }}', {{ $feedPos1 }})) pickWinner('{{ $round }}', {{ $position }}, getPropagated('{{ $prevRound }}', {{ $feedPos1 }}))">
                                    <template x-if="getPropagated('{{ $prevRound }}', {{ $feedPos1 }})">
                                        <span class="flex items-center gap-1.5 flex-1">
                                            <img :src="getPlayerInfo(getPropagated('{{ $prevRound }}', {{ $feedPos1 }}))?.flag" alt="" class="w-4 h-3 rounded-sm object-cover shrink-0">
                                            <span class="font-semibold truncate text-tc-primary/60 text-[10px]" x-text="getPlayerInfo(getPropagated('{{ $prevRound }}', {{ $feedPos1 }}))?.name?.toUpperCase()"></span>
                                        </span>
                                    </template>
                                    <template x-if="!getPropagated('{{ $prevRound }}', {{ $feedPos1 }})">
                                        <span class="text-[9px] text-gray-300 italic flex-1">Por definir</span>
                                    </template>
                                    <template x-if="isPickedHere('{{ $round }}', {{ $position }}, getPropagated('{{ $prevRound }}', {{ $feedPos1 }}))">
                                        <span class="pk-dot pnd shrink-0"><svg class="w-2 h-2 text-tc-primary" fill="currentColor" viewBox="0 0 20 20"><path d="M10 15l-5.878 3.09 1.123-6.545L.489 6.91l6.572-.955L10 0l2.939 5.955 6.572.955-4.756 4.635 1.123 6.545z"/></svg></span>
                                    </template>
                                </div>
                                @elseif($isPlaceholder1)
                                <div class="pr l"><span class="text-[9px] text-gray-300 italic flex-1">Por definir</span></div>
                                @else
                                <div class="pr {{ $isCancelled ? 'l' : ($isFinished ? ($p1Won ? 'w' : 'l') : ($isLive ? 'lv' : 'n')) }} {{ $pickPlayerId == $match->player1_id && $pickCorrect === true ? 'correct' : '' }} {{ $pickPlayerId == $match->player1_id && $pickCorrect === false ? 'wrong' : '' }}"
                                     @auth
                                     @if(!$predictionsLocked && !$bracketSaved && !$isFinished && !$isCancelled)
                                     x-on:click="pickWinner('{{ $round }}', {{ $position }}, {{ $match->player1_id }})"
                                     style="cursor:pointer;"
                                     :class="{ 'pk': isPickedHere('{{ $round }}', {{ $position }}, {{ $match->player1_id }}) }"
                                     @endif
                                     @endauth>
                                    <span class="text-[7.5px] font-mono w-3 text-right opacity-30 shrink-0">{{ $match->player1->ranking ?? '' }}</span>
                                    <img src="{{ $match->player1->flag_url }}" alt="" class="w-4 h-3 rounded-sm object-cover shrink-0" loading="lazy">
                                    <span class="font-semibold truncate flex-1">{{ strtoupper($match->player1->name) }}</span>
                                    @if($match->score)
                                        @foreach(explode(' ', $match->score) as $set)
                                            @php $s = explode('-', $set); @endphp
                                            <span class="ss text-[9px] font-mono font-bold w-2.5 text-center">{{ $s[0] ?? '' }}</span>
                                        @endforeach
                                    @endif
                                    @if($pickPlayerId == $match->player1_id)
                                    <span class="pk-dot {{ $pickCorrect === true ? 'ok' : ($pickCorrect === false ? 'err' : 'pnd') }} shrink-0">
                                        @if($pickCorrect === true)<svg class="w-2 h-2 text-green-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                        @elseif($pickCorrect === false)<svg class="w-2 h-2 text-red-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                                        @else<svg class="w-2 h-2 text-tc-primary" fill="currentColor" viewBox="0 0 20 20"><path d="M10 15l-5.878 3.09 1.123-6.545L.489 6.91l6.572-.955L10 0l2.939 5.955 6.572.955-4.756 4.635 1.123 6.545z"/></svg>
                                        @endif
                                    </span>
                                    @endif
                                </div>
                                @endif

                                <div class="h-px bg-gray-100"></div>

                                {{-- Player 2 --}}
                                @if($isPlaceholder2 && $bri > 0)
                                @php $prevRound = $bracketRounds[$bri - 1]; @endphp
                                <div class="pr n"
                                     :class="{ 'pk': isPickedHere('{{ $round }}', {{ $position }}, getPropagated('{{ $prevRound }}', {{ $feedPos2 }})), '!cursor-pointer': !locked && getPropagated('{{ $prevRound }}', {{ $feedPos2 }}) }"
                                     x-on:click="if(!locked && getPropagated('{{ $prevRound }}', {{ $feedPos2 }})) pickWinner('{{ $round }}', {{ $position }}, getPropagated('{{ $prevRound }}', {{ $feedPos2 }}))">
                                    <template x-if="getPropagated('{{ $prevRound }}', {{ $feedPos2 }})">
                                        <span class="flex items-center gap-1.5 flex-1">
                                            <img :src="getPlayerInfo(getPropagated('{{ $prevRound }}', {{ $feedPos2 }}))?.flag" alt="" class="w-4 h-3 rounded-sm object-cover shrink-0">
                                            <span class="font-semibold truncate text-tc-primary/60 text-[10px]" x-text="getPlayerInfo(getPropagated('{{ $prevRound }}', {{ $feedPos2 }}))?.name?.toUpperCase()"></span>
                                        </span>
                                    </template>
                                    <template x-if="!getPropagated('{{ $prevRound }}', {{ $feedPos2 }})">
                                        <span class="text-[9px] text-gray-300 italic flex-1">Por definir</span>
                                    </template>
                                    <template x-if="isPickedHere('{{ $round }}', {{ $position }}, getPropagated('{{ $prevRound }}', {{ $feedPos2 }}))">
                                        <span class="pk-dot pnd shrink-0"><svg class="w-2 h-2 text-tc-primary" fill="currentColor" viewBox="0 0 20 20"><path d="M10 15l-5.878 3.09 1.123-6.545L.489 6.91l6.572-.955L10 0l2.939 5.955 6.572.955-4.756 4.635 1.123 6.545z"/></svg></span>
                                    </template>
                                </div>
                                @elseif($isPlaceholder2)
                                <div class="pr l"><span class="text-[9px] text-gray-300 italic flex-1">Por definir</span></div>
                                @else
                                <div class="pr {{ $isCancelled ? 'l' : ($isFinished ? ($p2Won ? 'w' : 'l') : ($isLive ? 'lv' : 'n')) }} {{ $pickPlayerId == $match->player2_id && $pickCorrect === true ? 'correct' : '' }} {{ $pickPlayerId == $match->player2_id && $pickCorrect === false ? 'wrong' : '' }}"
                                     @auth
                                     @if(!$predictionsLocked && !$bracketSaved && !$isFinished && !$isCancelled)
                                     x-on:click="pickWinner('{{ $round }}', {{ $position }}, {{ $match->player2_id }})"
                                     style="cursor:pointer;"
                                     :class="{ 'pk': isPickedHere('{{ $round }}', {{ $position }}, {{ $match->player2_id }}) }"
                                     @endif
                                     @endauth>
                                    <span class="text-[7.5px] font-mono w-3 text-right opacity-30 shrink-0">{{ $match->player2->ranking ?? '' }}</span>
                                    <img src="{{ $match->player2->flag_url }}" alt="" class="w-4 h-3 rounded-sm object-cover shrink-0" loading="lazy">
                                    <span class="font-semibold truncate flex-1">{{ strtoupper($match->player2->name) }}</span>
                                    @if($match->score)
                                        @foreach(explode(' ', $match->score) as $set)
                                            @php $s = explode('-', $set); @endphp
                                            <span class="ss text-[9px] font-mono font-bold w-2.5 text-center">{{ $s[1] ?? '' }}</span>
                                        @endforeach
                                    @endif
                                    @if($isCancelled)
                                        <span class="text-[7px] font-bold text-gray-400 bg-gray-100 px-1 py-px rounded">CANC</span>
                                    @endif
                                    @if($pickPlayerId == $match->player2_id)
                                    <span class="pk-dot {{ $pickCorrect === true ? 'ok' : ($pickCorrect === false ? 'err' : 'pnd') }} shrink-0">
                                        @if($pickCorrect === true)<svg class="w-2 h-2 text-green-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                        @elseif($pickCorrect === false)<svg class="w-2 h-2 text-red-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                                        @else<svg class="w-2 h-2 text-tc-primary" fill="currentColor" viewBox="0 0 20 20"><path d="M10 15l-5.878 3.09 1.123-6.545L.489 6.91l6.572-.955L10 0l2.939 5.955 6.572.955-4.756 4.635 1.123 6.545z"/></svg>
                                        @endif
                                    </span>
                                    @endif
                                </div>
                                @endif

                                {{-- Pick strips --}}
                                @if($pickCorrect === false && $pickPlayerId)
                                @php $pickedPlayer = ($pickPlayerId == $match->player1_id) ? $match->player1 : $match->player2; @endphp
                                <div class="ghost-pick">
                                    <svg class="w-2.5 h-2.5 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                                    <img src="{{ $pickedPlayer->flag_url }}" alt="" class="w-3 h-2 rounded-sm object-cover opacity-50 shrink-0">
                                    <span class="ghost-name">{{ strtoupper($pickedPlayer->name) }}</span>
                                </div>
                                @elseif($pickCorrect === true && $pickPlayerId)
                                <div class="flex items-center gap-1 px-2.5 py-1 bg-green-50 border-t border-green-100">
                                    <svg class="w-2.5 h-2.5 text-green-500 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                    <span class="text-[8px] font-bold text-green-700 uppercase tracking-wide">Acertaste</span>
                                </div>
                                @elseif($pickPlayerId && $pickCorrect === null && !$isPending)
                                @php $pickedPlayer = ($pickPlayerId == $match->player1_id) ? $match->player1 : $match->player2; @endphp
                                <div class="flex items-center gap-1 px-2.5 py-1 bg-yellow-50/70 border-t border-yellow-200/50">
                                    <svg class="w-2.5 h-2.5 text-yellow-500 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path d="M10 15l-5.878 3.09 1.123-6.545L.489 6.91l6.572-.955L10 0l2.939 5.955 6.572.955-4.756 4.635 1.123 6.545z"/></svg>
                                    <span class="text-[8px] font-bold text-yellow-700 truncate">{{ strtoupper($pickedPlayer->name) }}</span>
                                </div>
                                @endif
                            </div>
                        </div>
                        @endforeach
                    </div>

                    {{-- Connectors --}}
                    @if(!$isLast)
                    <div style="width: 26px; height: {{ $totalH }}px; position: relative; flex-shrink: 0;">
                        @for($ci = 0; $ci < intdiv($matchCount, 2); $ci++)
                        @php
                            $pairH   = $matchH * 2;
                            $topEdge = $ci * $pairH;
                            $y1      = $matchH * 0.5;
                            $y2      = $matchH * 1.5;
                            $ymid    = ($y1 + $y2) / 2;
                        @endphp
                        <div style="position:absolute; top:{{ $topEdge }}px; width:26px; height:{{ $pairH }}px;">
                            <svg width="26" height="{{ $pairH }}" viewBox="0 0 26 {{ $pairH }}" fill="none" style="display:block;">
                                <line x1="0" y1="{{ $y1 }}" x2="13" y2="{{ $y1 }}" stroke="#cbd5e1" stroke-width="1.2"/>
                                <line x1="0" y1="{{ $y2 }}" x2="13" y2="{{ $y2 }}" stroke="#cbd5e1" stroke-width="1.2"/>
                                <line x1="13" y1="{{ $y1 }}" x2="13" y2="{{ $y2 }}" stroke="#cbd5e1" stroke-width="1.2"/>
                                <line x1="13" y1="{{ $ymid }}" x2="26" y2="{{ $ymid }}" stroke="#cbd5e1" stroke-width="1.2"/>
                            </svg>
                        </div>
                        @endfor
                    </div>
                    @endif

                    @endforeach

                    {{-- Champion --}}
                    @php
                        $finalRound = $bracketRounds->last();
                        $champion   = $matches[$finalRound]->first()?->winner;
                    @endphp
                    <div style="width:26px; height:{{ $totalH }}px; display:flex; align-items:center; flex-shrink:0;">
                        <svg width="26" height="2" style="display:block;"><line x1="0" y1="1" x2="26" y2="1" stroke="#cbd5e1" stroke-width="1.2"/></svg>
                    </div>
                    <div style="width:120px; height:{{ $totalH }}px; display:flex; align-items:center; flex-shrink:0;">
                        <div class="w-full px-1">
                            @if($champion)
                            <div class="champion-wrap">
                                <div class="trophy-glow mb-2">
                                    <svg class="w-9 h-9 mx-auto text-tc-accent" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M5 3h14c.6 0 1 .4 1 1v2c0 3.3-2.7 6-6 6h-.7c-.4 1.2-1.2 2.2-2.3 2.8V18h3c.6 0 1 .4 1 1v2c0 .6-.4 1-1 1H9c-.6 0-1-.4-1-1v-2c0-.6.4-1 1-1h3v-3.2c-1.1-.6-1.9-1.6-2.3-2.8H9c-3.3 0-6-2.7-6-6V4c0-.6.4-1 1-1zm1 2v1c0 2.2 1.8 4 4 4h4c2.2 0 4-1.8 4-4V5H6z"/>
                                    </svg>
                                </div>
                                <img src="{{ $champion->flag_url }}" alt="" class="w-6 h-4 mx-auto rounded-sm object-cover shadow mb-1.5">
                                <div class="text-[10px] font-black text-tc-primary uppercase leading-tight tracking-tight">{{ $champion->name }}</div>
                                <div class="text-[8px] font-black text-tc-accent tracking-widest mt-1">CAMPEÓN</div>
                            </div>
                            @else
                            <div class="text-center py-4" style="opacity:0.25;">
                                <svg class="w-9 h-9 mx-auto text-tc-primary" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M5 3h14c.6 0 1 .4 1 1v2c0 3.3-2.7 6-6 6h-.7c-.4 1.2-1.2 2.2-2.3 2.8V18h3c.6 0 1 .4 1 1v2c0 .6-.4 1-1 1H9c-.6 0-1-.4-1-1v-2c0-.6.4-1 1-1h3v-3.2c-1.1-.6-1.9-1.6-2.3-2.8H9c-3.3 0-6-2.7-6-6V4c0-.6.4-1 1-1zm1 2v1c0 2.2 1.8 4 4 4h4c2.2 0 4-1.8 4-4V5H6z"/>
                                </svg>
                                <div class="text-[9px] font-bold text-gray-300 mt-2 uppercase tracking-wide">Por definir</div>
                            </div>
                            @endif
                        </div>
                    </div>

                </div>
            </div>
        </div>

        {{-- ── Confirm modal ── --}}
        <div x-show="showConfirm" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" style="display:none;">
            <div class="fixed inset-0 bg-black/50 backdrop-blur-sm"
                 x-on:click="showConfirm = false"
                 x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"></div>

            <div x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 scale-90 translate-y-6"
                 x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100 scale-100"
                 x-transition:leave-end="opacity-0 scale-95"
                 class="modal-surface relative max-w-sm w-full">

                {{-- Header --}}
                <div class="bg-gradient-to-br from-tc-primary to-[#264a6e] px-6 pt-8 pb-6 text-center relative overflow-hidden">
                    <div class="absolute inset-0 opacity-[0.04]">
                        <div class="absolute -top-12 -right-12 w-44 h-44 rounded-full border-[24px] border-white"></div>
                        <div class="absolute -bottom-10 -left-10 w-36 h-36 rounded-full border-[20px] border-white"></div>
                    </div>
                    <div class="relative">
                        <div class="w-14 h-14 mx-auto mb-3 rounded-2xl bg-tc-accent/20 border border-tc-accent/25 flex items-center justify-center">
                            <svg class="w-7 h-7 text-tc-accent" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M5 3h14c.6 0 1 .4 1 1v2c0 3.3-2.7 6-6 6h-.7c-.4 1.2-1.2 2.2-2.3 2.8V18h3c.6 0 1 .4 1 1v2c0 .6-.4 1-1 1H9c-.6 0-1-.4-1-1v-2c0-.6.4-1 1-1h3v-3.2c-1.1-.6-1.9-1.6-2.3-2.8H9c-3.3 0-6-2.7-6-6V4c0-.6.4-1 1-1zm1 2v1c0 2.2 1.8 4 4 4h4c2.2 0 4-1.8 4-4V5H6z"/>
                            </svg>
                        </div>
                        <h3 class="text-white font-black text-lg tracking-tight">Confirmar Bracket</h3>
                        <p class="text-white/40 text-xs mt-1">Esta acción es irreversible</p>
                    </div>
                </div>

                {{-- Round checklist --}}
                <div class="px-6 py-5 border-b border-gray-100">
                    <div class="space-y-2.5">
                        <template x-for="[round, needed] of Object.entries(requiredPicks)" :key="round">
                            <div class="flex items-center gap-3">
                                <div class="w-5 h-5 rounded-full flex items-center justify-center shrink-0 transition-colors"
                                     :class="roundPickCount(round) >= needed ? 'bg-green-500' : 'bg-gray-100'">
                                    <svg x-show="roundPickCount(round) >= needed" class="w-2.5 h-2.5 text-white" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                    <span x-show="roundPickCount(round) < needed" class="text-[7px] font-bold text-gray-400" x-text="roundPickCount(round) + '/' + needed"></span>
                                </div>
                                <span class="text-xs font-semibold flex-1 transition-colors"
                                      :class="roundPickCount(round) >= needed ? 'text-gray-700' : 'text-gray-400'"
                                      x-text="roundLabels[round] || round"></span>
                                <span class="text-[10px] font-mono tabular-nums"
                                      :class="roundPickCount(round) >= needed ? 'text-green-600' : 'text-gray-300'"
                                      x-text="roundPickCount(round) + '/' + needed"></span>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- Progress --}}
                <div class="px-6 py-4 border-b border-gray-100">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Progreso total</span>
                        <span class="text-sm font-black tabular-nums transition-colors"
                              :class="isComplete ? 'text-green-600' : 'text-tc-primary'"
                              x-text="totalPicks + '/' + totalRequired"></span>
                    </div>
                    <div class="h-1.5 bg-gray-100 rounded-full overflow-hidden">
                        <div class="h-full rounded-full transition-all duration-500 ease-out"
                             :class="isComplete ? 'bg-gradient-to-r from-green-400 to-green-500' : 'bg-gradient-to-r from-tc-accent to-yellow-400'"
                             :style="'width:' + Math.min(100, Math.round(totalPicks / totalRequired * 100)) + '%'"></div>
                    </div>

                    <template x-if="!isComplete">
                        <div class="flex items-start gap-2 p-3 rounded-xl bg-amber-50 border border-amber-200/60 mt-3">
                            <svg class="w-4 h-4 text-amber-500 shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                            <p class="text-xs text-amber-800 leading-relaxed">Completa todas las rondas para guardar tu bracket.</p>
                        </div>
                    </template>
                    <template x-if="isComplete">
                        <div class="flex items-start gap-2 p-3 rounded-xl bg-green-50 border border-green-200/60 mt-3">
                            <svg class="w-4 h-4 text-green-500 shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                            <p class="text-xs text-green-800 leading-relaxed">Bracket completo. Una vez guardado <strong>no podrás modificarlo</strong>.</p>
                        </div>
                    </template>
                </div>

                {{-- Buttons --}}
                <div class="flex gap-3 px-6 py-5">
                    <button x-on:click="showConfirm = false"
                        class="flex-1 px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-semibold text-gray-500 hover:bg-gray-50 hover:border-gray-300 transition-all">
                        Revisar
                    </button>
                    <button x-on:click="showConfirm = false; saveBracket()"
                        :disabled="!isComplete"
                        class="flex-1 px-4 py-2.5 rounded-xl text-sm font-bold transition-all"
                        :class="isComplete
                            ? 'bg-tc-primary text-white hover:bg-tc-primary-hover shadow-sm hover:shadow-md'
                            : 'bg-gray-100 text-gray-400 cursor-not-allowed'">
                        <span x-show="isComplete">Confirmar y Guardar</span>
                        <span x-show="!isComplete">Bracket incompleto</span>
                    </button>
                </div>
            </div>
        </div>

        {{-- Toast --}}
        <div x-show="saveMsg" x-cloak
             x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0 translate-y-4"
             class="fixed bottom-6 left-1/2 -translate-x-1/2 z-50" style="display:none;">
            <div class="px-6 py-3 rounded-2xl shadow-2xl text-sm font-bold"
                 :class="saveOk ? 'bg-green-500 text-white' : 'bg-red-500 text-white'"
                 x-text="saveMsg"></div>
        </div>
    </div>
    @endif
</div>

{{-- No matches --}}
@if($orderedRounds->count() === 0)
<div class="bg-gradient-to-b from-gray-50 to-gray-100 min-h-[50vh] flex items-center justify-center">
    <div class="text-center">
        <div class="w-14 h-14 mx-auto mb-4 rounded-2xl bg-gray-100 flex items-center justify-center">
            <svg class="w-7 h-7 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
        </div>
        <p class="text-gray-400 font-semibold text-sm">No hay partidos programados</p>
        <p class="text-gray-300 text-xs mt-1">Los partidos aparecerán cuando el cuadro sea publicado</p>
    </div>
</div>
@endif

@guest
<div class="bg-gradient-to-r from-tc-primary/5 to-tc-accent/5 border-t border-tc-primary/10">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 text-center">
        <p class="text-sm text-gray-500 mb-3">Inicia sesión para predecir y ganar puntos</p>
        <a href="{{ route('login') }}" class="inline-flex items-center gap-2 px-6 py-2.5 bg-tc-primary text-white rounded-xl text-sm font-bold hover:bg-tc-primary-hover transition shadow-sm">Ingresar</a>
    </div>
</div>
@endguest

@push('scripts')
<script>
function bracketApp() {
    return {
        picks: @json($userPicksJs ?? []),
        players: @json(collect($bracketData)->flatten(1)->flatMap(fn($m) => collect([$m['player1'], $m['player2']])->filter())->unique('id')->keyBy('id')->toArray()),
        locked: {{ ($bracketSaved || $predictionsLocked) ? 'true' : 'false' }},
        requiredPicks: @json($bracketRounds->mapWithKeys(fn($r) => [
                            $r => $matches[$r]->filter(fn($m) =>
                                ($m->player1?->name && !preg_match('/^(Qf|SF|WSF|WQF|F|Ganador|TBD)\s?\d?/i', $m->player1->name)) ||
                                ($m->player2?->name && !preg_match('/^(Qf|SF|WSF|WQF|F|Ganador|TBD)\s?\d?/i', $m->player2->name))
                            )->count()
                        ])->filter(fn($count) => $count > 0)->all()),
        allRoundCounts: @json($bracketRounds->mapWithKeys(fn($r) => [
                            $r => $matches[$r]->filter(fn($m) =>
                                ($m->player1?->name && !preg_match('/^(Qf|SF|WSF|WQF|F|Ganador|TBD)\s?\d?/i', $m->player1->name)) ||
                                ($m->player2?->name && !preg_match('/^(Qf|SF|WSF|WQF|F|Ganador|TBD)\s?\d?/i', $m->player2->name))
                            )->count()
                        ])->all()),
        validPositions: @json($validPositionsMap),
        roundLabels: @json($roundLabels),
        showConfirm: false,
        saving: false,
        saveMsg: '',
        saveOk: false,

        init() {
            // Guard against Alpine calling init() twice (auto + x-init)
            if (this._initialized) return;
            this._initialized = true;
            const loaded = this.picks;
            this.picks = {};
            for (const [round, positions] of Object.entries(loaded)) {
                this.picks[round] = {};
                for (const [pos, data] of Object.entries(positions)) {
                    this.picks[round][pos] = typeof data === 'object' ? data.player_id : data;
                }
            }
        },

        roundPickCount(round) {
            if (!this.picks[round]) return 0;
            return Object.values(this.picks[round]).filter(v => v).length;
        },

        get totalPicks() {
            let count = 0;
            for (const round of Object.keys(this.picks)) count += this.roundPickCount(round);
            return count;
        },

        get totalRequired() {
            return @json(collect($matches)->flatten()->where('status', '!=', 'bye')->count());
        },

        get isComplete() {
            for (const [round, needed] of Object.entries(this.requiredPicks)) {
                if (this.roundPickCount(round) < needed) return false;
            }
            return true;
        },

        pickWinner(round, position, playerId) {
            if (!playerId || this.locked) return;
            if (!this.picks[round]) this.picks[round] = {};
            this.picks[round][position] = playerId;
            this.picks = { ...this.picks };
        },

        isPickedHere(round, position, playerId) {
            return playerId && this.picks[round] && this.picks[round][position] === playerId;
        },

        getPropagated(prevRound, prevPosition) {
            if (!this.picks[prevRound]) return null;
            return this.picks[prevRound][String(prevPosition)] || null;
        },

        getSlotPlayer(round, position, prevRound, prevPosition) {
            return this.getPropagated(prevRound, prevPosition);
        },

        getPlayerInfo(playerId) {
            if (!playerId) return null;
            return this.players[playerId] || null;
        },

        async saveBracket() {
            this.saving = true;
            this.saveMsg = '';
            try {
                const res = await fetch('{{ route("bracket-predictions.store", $tournament) }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                    body: JSON.stringify({ picks: this.picks })
                });
                const data = await res.json();
                if (data.success) { this.saveOk = true; this.locked = true; this.saveMsg = 'Bracket guardado exitosamente'; setTimeout(() => window.location.reload(), 800); }
                else { this.saveOk = false; this.saveMsg = data.message || 'Error al guardar'; }
            } catch (e) { this.saveOk = false; this.saveMsg = 'Error de conexión'; }
            this.saving = false;
            setTimeout(() => this.saveMsg = '', 3000);
        }
    };
}
</script>
@endpush
@endsection
