@extends('layouts.app')
@section('title', 'Rankings')

@push('styles')
<style>
    /* ─── Hero texture ─── */
    .rankings-hero {
        background:
            radial-gradient(ellipse at 20% 50%, rgba(238,229,57,0.08) 0%, transparent 60%),
            radial-gradient(ellipse at 80% 30%, rgba(255,255,255,0.03) 0%, transparent 50%),
            linear-gradient(170deg, #0e1f30 0%, #1b3d5d 40%, #264a6e 100%);
        position: relative;
        overflow: hidden;
    }
    .rankings-hero::before {
        content: ''; position: absolute; inset: 0; opacity: 0.025;
        background-image: url("data:image/svg+xml,%3Csvg width='40' height='40' viewBox='0 0 40 40' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M20 0L40 20L20 40L0 20Z' fill='none' stroke='white' stroke-width='0.5'/%3E%3C/svg%3E");
        background-size: 40px 40px;
    }

    /* ─── Podium ─── */
    .podium-col { display: flex; flex-direction: column; align-items: center; position: relative; }
    .podium-avatar {
        border-radius: 50%; display: flex; align-items: center; justify-content: center;
        font-weight: 800; letter-spacing: -0.02em;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    .podium-avatar:hover { transform: scale(1.08); }

    .podium-1 .podium-avatar {
        width: 72px; height: 72px; font-size: 26px;
        background: linear-gradient(135deg, #eee539 0%, #d4cb00 100%);
        color: #1b3d5d;
        box-shadow: 0 0 0 4px rgba(238,229,57,0.3), 0 8px 32px rgba(238,229,57,0.25);
    }
    .podium-2 .podium-avatar {
        width: 56px; height: 56px; font-size: 20px;
        background: linear-gradient(135deg, #c0c0c0 0%, #a8a8a8 100%);
        color: #fff;
        box-shadow: 0 0 0 3px rgba(192,192,192,0.3), 0 6px 24px rgba(0,0,0,0.1);
    }
    .podium-3 .podium-avatar {
        width: 48px; height: 48px; font-size: 18px;
        background: linear-gradient(135deg, #cd7f32 0%, #b8690e 100%);
        color: #fff;
        box-shadow: 0 0 0 3px rgba(205,127,50,0.3), 0 6px 24px rgba(0,0,0,0.1);
    }

    .podium-bar {
        width: 100%; border-radius: 8px 8px 0 0;
        display: flex; align-items: flex-end; justify-content: center;
        padding-bottom: 8px; margin-top: 8px;
        font-weight: 900; position: relative;
    }
    .podium-1 .podium-bar {
        height: 80px;
        background: linear-gradient(0deg, rgba(238,229,57,0.15) 0%, rgba(238,229,57,0.05) 100%);
        border: 1px solid rgba(238,229,57,0.2); border-bottom: none;
        color: #eee539; font-size: 28px;
    }
    .podium-2 .podium-bar {
        height: 56px;
        background: linear-gradient(0deg, rgba(192,192,192,0.12) 0%, rgba(192,192,192,0.04) 100%);
        border: 1px solid rgba(192,192,192,0.15); border-bottom: none;
        color: #a0a0a0; font-size: 22px;
    }
    .podium-3 .podium-bar {
        height: 40px;
        background: linear-gradient(0deg, rgba(205,127,50,0.12) 0%, rgba(205,127,50,0.04) 100%);
        border: 1px solid rgba(205,127,50,0.15); border-bottom: none;
        color: #cd7f32; font-size: 20px;
    }

    /* ─── Accuracy bar ─── */
    .accuracy-track {
        width: 48px; height: 4px; border-radius: 99px;
        background: #e5e7eb; overflow: hidden;
    }
    .accuracy-fill {
        height: 100%; border-radius: 99px;
        transition: width 0.6s ease;
    }

    /* ─── Row hover ─── */
    .rank-row { transition: all 0.15s ease; }
    .rank-row:hover { background: rgba(27,61,93,0.03); }
    .rank-row.is-me { background: rgba(238,229,57,0.06); }
    .rank-row.is-me:hover { background: rgba(238,229,57,0.1); }

    /* ─── Stagger animation ─── */
    .stagger-in { opacity: 0; animation: staggerFade 0.55s ease forwards; }
    @keyframes staggerFade {
        from { opacity: 0; transform: translateY(18px); }
        to { opacity: 1; transform: translateY(0); }
    }

    /* ─── Podium rise (scroll-triggered) ─── */
    .podium-rise {
        opacity: 0;
        transform: translateY(30px) scaleY(0.85);
        transition: opacity 0.5s ease, transform 0.5s cubic-bezier(0.34,1.56,0.64,1);
    }
    .podium-rise.visible {
        opacity: 1;
        transform: translateY(0) scaleY(1);
    }

    /* ─── Accuracy bar animada ─── */
    .accuracy-fill { width: 0 !important; transition: width 1s cubic-bezier(0.16,1,0.3,1); }
    .accuracy-fill.animated { width: var(--acc-width) !important; }

    /* ─── Hero badge pulse ─── */
    @keyframes badgePulse {
        0%,100% { box-shadow: 0 0 0 0 rgba(238,229,57,0.4); }
        50%      { box-shadow: 0 0 0 8px rgba(238,229,57,0); }
    }
    .badge-pulse { animation: badgePulse 2.5s infinite; }

    /* ─── Compact podium for grid cards ─── */
    .podium-1 .podium-avatar { width: 52px; height: 52px; font-size: 20px; }
    .podium-2 .podium-avatar { width: 40px; height: 40px; font-size: 16px; }
    .podium-3 .podium-avatar { width: 36px; height: 36px; font-size: 14px; }
    .podium-1 .podium-bar { height: 56px; font-size: 22px; }
    .podium-2 .podium-bar { height: 40px; font-size: 18px; }
    .podium-3 .podium-bar { height: 28px; font-size: 16px; }
</style>
@endpush

@section('content')
{{-- ═══════ HERO ═══════ --}}
<div class="rankings-hero">
    <div class="relative max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-10 md:py-14 text-center">
        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-tc-accent/10 border border-tc-accent/20 mb-4 badge-pulse">
            <svg class="w-3.5 h-3.5 text-tc-accent" fill="currentColor" viewBox="0 0 24 24"><path d="M5 3h14c.6 0 1 .4 1 1v2c0 3.3-2.7 6-6 6h-.7c-.4 1.2-1.2 2.2-2.3 2.8V18h3c.6 0 1 .4 1 1v2c0 .6-.4 1-1 1H9c-.6 0-1-.4-1-1v-2c0-.6.4-1 1-1h3v-3.2c-1.1-.6-1.9-1.6-2.3-2.8H9c-3.3 0-6-2.7-6-6V4c0-.6.4-1 1-1zm1 2v1c0 2.2 1.8 4 4 4h4c2.2 0 4-1.8 4-4V5H6z"/></svg>
            <span class="text-tc-accent text-[10px] font-bold uppercase tracking-widest">Leaderboard</span>
        </div>
        <h1 class="text-3xl md:text-5xl font-black text-white tracking-tight mb-2">Rankings por Torneo</h1>
        <p class="text-white/40 text-sm max-w-md mx-auto">Los mejores pronosticadores de cada torneo activo</p>
    </div>
</div>

<div class="bg-gradient-to-b from-[#0e1f30] via-gray-50 to-gray-100">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 -mt-2 pb-16">

        @if(count($tournamentRankings) > 0)
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
            @foreach($tournamentRankings as $trIndex => $tr)
            @php
                $tournament = $tr['tournament'];
                $top10 = $tr['top10'];
                $currentUser = $tr['currentUser'];
                $heroGradient = match(true) {
                    $tournament->type === 'GrandSlam' => 'from-[#1a2a3a] via-tc-primary to-[#2a4a6a]',
                    str_starts_with($tournament->type, 'ATP') => 'from-tc-primary to-[#264a6e]',
                    default => 'from-purple-800 to-purple-600',
                };
                $surfaceLower = strtolower($tournament->surface ?? '');
                $surfaceColor = match(true) {
                    str_contains($surfaceLower, 'clay') => 'bg-orange-600',
                    str_contains($surfaceLower, 'grass') => 'bg-green-600',
                    str_contains($surfaceLower, 'indoor') => 'bg-purple-600',
                    default => 'bg-blue-500',
                };
                $statusLabel = match($tournament->computed_status) {
                    'finished' => 'Finalizado',
                    'in_progress', 'live' => 'En curso',
                    default => 'Próximo',
                };
                $statusColor = match($tournament->computed_status) {
                    'finished' => 'bg-gray-500',
                    'in_progress', 'live' => 'bg-green-500',
                    default => 'bg-blue-500',
                };
            @endphp

            <div class="stagger-in rounded-2xl overflow-hidden bg-white shadow-xl shadow-black/5 ring-1 ring-gray-200/60"
                 style="animation-delay: {{ $trIndex * 0.1 }}s">

                {{-- Tournament Header --}}
                <div class="bg-gradient-to-r {{ $heroGradient }} relative overflow-hidden">
                    {{-- Subtle pattern --}}
                    <div class="absolute inset-0 opacity-[0.03]" style="background-image: url(&quot;data:image/svg+xml,%3Csvg width='20' height='20' viewBox='0 0 20 20' xmlns='http://www.w3.org/2000/svg'%3E%3Ccircle cx='10' cy='10' r='1' fill='white'/%3E%3C/svg%3E&quot;); background-size: 20px 20px;"></div>

                    <div class="relative px-4 py-4 flex items-center justify-between gap-2">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-1.5 flex-wrap mb-1">
                                @if($tournament->type === 'GrandSlam')
                                <span class="px-1.5 py-0.5 rounded bg-tc-accent/15 border border-tc-accent/20 text-tc-accent text-[8px] font-bold uppercase tracking-wider">Grand Slam</span>
                                @else
                                <span class="px-1.5 py-0.5 rounded bg-white/10 border border-white/10 text-white/50 text-[8px] font-bold uppercase tracking-wider">{{ $tournament->type }}</span>
                                @endif
                                <span class="inline-flex items-center gap-1 text-white/50 text-[9px]">
                                    <span class="w-1.5 h-1.5 rounded-full {{ $statusColor }} {{ $tournament->computed_status === 'in_progress' ? 'animate-pulse' : '' }}"></span>
                                    {{ $statusLabel }}
                                </span>
                            </div>
                            <h2 class="text-white font-black text-sm leading-tight truncate">{{ $tournament->name }}</h2>
                            <div class="flex items-center gap-1.5 mt-0.5 text-white/35 text-[10px]">
                                <span>{{ $tournament->city }}</span>
                                @if($tournament->surface)
                                <span class="w-0.5 h-0.5 rounded-full bg-white/20"></span>
                                <span class="inline-flex items-center gap-1">
                                    <span class="w-1.5 h-1.5 rounded-full {{ $surfaceColor }}"></span>
                                    {{ $tournament->surface }}
                                </span>
                                @endif
                            </div>
                        </div>
                        <a href="{{ route('tournaments.show', $tournament) }}"
                           class="w-8 h-8 rounded-lg bg-white/10 hover:bg-white/20 border border-white/10 flex items-center justify-center transition-all shrink-0 group">
                            <svg class="w-3.5 h-3.5 text-white/60 group-hover:text-white group-hover:translate-x-0.5 transition-all" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </a>
                    </div>
                </div>

                {{-- Podium (top 3) --}}
                @if($top10->count() >= 3)
                @php
                    $firstName0 = explode(' ', $top10[0]->name)[0];
                    $firstName1 = explode(' ', $top10[1]->name)[0];
                    $firstName2 = explode(' ', $top10[2]->name)[0];
                @endphp
                <div class="bg-gradient-to-b from-gray-50/80 to-white px-3 pt-6 pb-0">
                    <div class="flex items-end justify-center gap-2">
                        {{-- 2nd --}}
                        <div class="podium-col podium-2 flex-1 podium-rise" style="transition-delay:0.1s">
                            <div class="podium-avatar">{{ strtoupper(substr($top10[1]->name, 0, 1)) }}</div>
                            <div class="mt-1.5 text-[11px] font-bold text-gray-700 text-center w-full truncate px-1">{{ $firstName1 }}</div>
                            <div class="text-[9px] text-gray-400 font-mono tabular-nums text-center">{{ number_format($top10[1]->tournament_points) }}</div>
                            <div class="podium-bar w-full">2</div>
                        </div>
                        {{-- 1st --}}
                        <div class="podium-col podium-1 flex-1 podium-rise" style="transition-delay:0s">
                            <div class="podium-avatar">{{ strtoupper(substr($top10[0]->name, 0, 1)) }}</div>
                            <div class="mt-1.5 text-xs font-black text-gray-800 text-center w-full truncate px-1">{{ $firstName0 }}</div>
                            <div class="text-[9px] text-tc-primary font-bold font-mono tabular-nums text-center">{{ number_format($top10[0]->tournament_points) }}</div>
                            <div class="podium-bar w-full">1</div>
                        </div>
                        {{-- 3rd --}}
                        <div class="podium-col podium-3 flex-1 podium-rise" style="transition-delay:0.2s">
                            <div class="podium-avatar">{{ strtoupper(substr($top10[2]->name, 0, 1)) }}</div>
                            <div class="mt-1.5 text-[11px] font-bold text-gray-700 text-center w-full truncate px-1">{{ $firstName2 }}</div>
                            <div class="text-[9px] text-gray-400 font-mono tabular-nums text-center">{{ number_format($top10[2]->tournament_points) }}</div>
                            <div class="podium-bar w-full">3</div>
                        </div>
                    </div>
                </div>
                @endif

                {{-- Rankings Table --}}
                <div>
                    {{-- Table header --}}
                    <div class="flex items-center gap-2 px-4 py-2 bg-gray-50/60 border-y border-gray-100 text-[9px] font-bold uppercase tracking-widest text-gray-400">
                        <div class="w-6 text-center">#</div>
                        <div class="flex-1">Jugador</div>
                        <div class="w-14 text-right">Pts</div>
                    </div>

                    {{-- Rows --}}
                    @foreach($top10 as $i => $ru)
                    @php
                        $rank = $i + 1;
                        $isMe = auth()->check() && auth()->id() == $ru->id;
                        $accuracy = $ru->total_predictions > 0 ? round(($ru->correct_predictions / $ru->total_predictions) * 100) : 0;
                        $accColor = $accuracy >= 60 ? 'bg-green-500' : ($accuracy >= 40 ? 'bg-tc-accent' : 'bg-orange-400');
                    @endphp
                    <div class="rank-row flex items-center gap-2 px-4 py-2 {{ $isMe ? 'is-me' : '' }} {{ $rank % 2 === 0 ? 'bg-gray-50/30' : '' }} border-b border-gray-50 last:border-b-0">
                        {{-- Rank badge --}}
                        <div class="w-6 text-center shrink-0">
                            @if($rank === 1)
                                <div class="w-5 h-5 mx-auto rounded bg-gradient-to-br from-tc-accent to-yellow-500 flex items-center justify-center">
                                    <span class="text-tc-primary text-[9px] font-black">1</span>
                                </div>
                            @elseif($rank === 2)
                                <div class="w-5 h-5 mx-auto rounded bg-gradient-to-br from-gray-300 to-gray-400 flex items-center justify-center">
                                    <span class="text-white text-[9px] font-black">2</span>
                                </div>
                            @elseif($rank === 3)
                                <div class="w-5 h-5 mx-auto rounded bg-gradient-to-br from-amber-500 to-amber-700 flex items-center justify-center">
                                    <span class="text-white text-[9px] font-black">3</span>
                                </div>
                            @else
                                <span class="text-[10px] font-bold text-gray-400 font-mono">{{ $rank }}</span>
                            @endif
                        </div>

                        {{-- Avatar --}}
                        <div class="w-6 h-6 rounded-full flex items-center justify-center text-[10px] font-bold shrink-0
                            {{ $isMe ? 'bg-tc-primary text-white ring-1 ring-tc-accent/40' : 'bg-gray-100 text-gray-500' }}">
                            {{ strtoupper(substr($ru->name, 0, 1)) }}
                        </div>

                        {{-- Name --}}
                        <div class="flex-1 min-w-0">
                            <div class="text-xs font-semibold text-gray-800 truncate">
                                {{ $ru->name }}
                                @if($isMe)<span class="ml-1 px-1 py-0.5 rounded bg-tc-primary/10 text-tc-primary text-[8px] font-bold">TÚ</span>@endif
                            </div>
                            <div class="text-[9px] text-gray-400 font-mono tabular-nums">{{ $accuracy }}%</div>
                        </div>

                        {{-- Points --}}
                        <div class="w-14 text-right shrink-0">
                            <div class="text-xs font-black text-tc-primary tabular-nums font-mono">{{ number_format($ru->tournament_points) }}</div>
                        </div>
                    </div>
                    @endforeach

                    {{-- Current user outside top 10 --}}
                    @if($currentUser)
                    @php
                        $myAccuracy = $currentUser->total_predictions > 0 ? round(($currentUser->correct_predictions / $currentUser->total_predictions) * 100) : 0;
                        $myAccColor = $myAccuracy >= 60 ? 'bg-green-500' : ($myAccuracy >= 40 ? 'bg-tc-accent' : 'bg-orange-400');
                    @endphp
                    {{-- Ellipsis separator --}}
                    <div class="flex items-center gap-3 px-6 py-1">
                        <div class="flex-1 border-t border-dashed border-gray-200"></div>
                        <div class="flex gap-1">
                            <span class="w-1 h-1 rounded-full bg-gray-300"></span>
                            <span class="w-1 h-1 rounded-full bg-gray-300"></span>
                            <span class="w-1 h-1 rounded-full bg-gray-300"></span>
                        </div>
                        <div class="flex-1 border-t border-dashed border-gray-200"></div>
                    </div>
                    {{-- User row --}}
                    <div class="rank-row is-me flex items-center gap-2 px-4 py-2 border-l-[3px] border-l-tc-accent">
                        <div class="w-6 text-center shrink-0">
                            <span class="text-[10px] font-black text-tc-primary font-mono">{{ $currentUser->position }}</span>
                        </div>
                        <div class="w-6 h-6 bg-tc-primary rounded-full flex items-center justify-center text-white text-[10px] font-bold shrink-0 ring-1 ring-tc-accent/40">
                            {{ strtoupper(substr($currentUser->name, 0, 1)) }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="text-xs font-semibold text-gray-800 truncate">
                                {{ $currentUser->name }}
                                <span class="ml-1 px-1 py-0.5 rounded bg-tc-primary/10 text-tc-primary text-[8px] font-bold">TÚ</span>
                            </div>
                            <div class="text-[9px] text-gray-400 font-mono tabular-nums">{{ $myAccuracy }}%</div>
                        </div>
                        <div class="w-14 text-right shrink-0">
                            <div class="text-xs font-black text-tc-primary tabular-nums font-mono">{{ number_format($currentUser->tournament_points) }}</div>
                        </div>
                    </div>
                    @endif
                </div>

                {{-- Mobile bracket link --}}
                <a href="{{ route('tournaments.show', $tournament) }}" class="flex items-center justify-center gap-2 px-4 py-2.5 bg-gray-50 border-t border-gray-100 text-tc-primary text-[11px] font-bold hover:bg-gray-100 transition-colors">
                    Ver bracket del torneo
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </a>
            </div>
            @endforeach
        </div>
        @else
        {{-- Empty state --}}
        <div class="mt-8 text-center py-20 bg-white rounded-2xl border border-gray-100 shadow-sm">
            <div class="w-20 h-20 mx-auto mb-5 rounded-2xl bg-gray-50 flex items-center justify-center">
                <svg class="w-10 h-10 text-gray-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
            </div>
            <p class="text-gray-500 font-bold text-sm">Aún no hay rankings disponibles</p>
            <p class="text-gray-400 text-xs mt-1.5 max-w-xs mx-auto">Los rankings aparecerán cuando los torneos tengan predicciones puntuadas</p>
            <a href="{{ route('tournaments.index') }}" class="inline-flex items-center gap-2 mt-6 px-6 py-2.5 bg-tc-primary text-white rounded-xl text-xs font-bold hover:bg-tc-primary-hover transition-all shadow-sm">
                Explorar torneos
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
        </div>
        @endif
    </div>
</div>
@push('scripts')
<script>
    // Podium rise animation
    const podiumIO = new IntersectionObserver((entries) => {
        entries.forEach(e => {
            if(e.isIntersecting){
                e.target.querySelectorAll('.podium-rise').forEach(el => el.classList.add('visible'));
                podiumIO.unobserve(e.target);
            }
        });
    }, { threshold: 0.3 });
    document.querySelectorAll('.podium-col').forEach(el => podiumIO.observe(el.closest('.flex') || el));

    // Accuracy bar animation on scroll
    const accIO = new IntersectionObserver((entries) => {
        entries.forEach(e => {
            if(e.isIntersecting){
                e.target.querySelectorAll('.accuracy-fill').forEach(bar => bar.classList.add('animated'));
                accIO.unobserve(e.target);
            }
        });
    }, { threshold: 0.2 });
    document.querySelectorAll('.rank-row').forEach(row => accIO.observe(row));

    // Trigger podium-rise for all podium cols
    const podiumColIO = new IntersectionObserver((entries) => {
        entries.forEach(e => {
            if(e.isIntersecting){ e.target.classList.add('visible'); podiumColIO.unobserve(e.target); }
        });
    }, { threshold: 0.2 });
    document.querySelectorAll('.podium-rise').forEach(el => podiumColIO.observe(el));
</script>
@endpush

@endsection
