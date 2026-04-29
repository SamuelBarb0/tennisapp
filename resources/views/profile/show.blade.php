@extends('layouts.app')
@section('title', 'Mi Perfil')

@push('styles')
<style>
    .profile-hero {
        background:
            radial-gradient(ellipse at 15% 50%, rgba(238,229,57,0.15) 0%, transparent 60%),
            radial-gradient(ellipse at 85% 30%, rgba(255,255,255,0.04) 0%, transparent 50%),
            linear-gradient(135deg, #0e1f30 0%, #1b3d5d 50%, #264a6e 100%);
    }
    .profile-hero::before {
        content: ''; position: absolute; inset: 0; opacity: 0.04;
        background-image: linear-gradient(rgba(255,255,255,0.4) 1px, transparent 1px),
                          linear-gradient(90deg, rgba(255,255,255,0.4) 1px, transparent 1px);
        background-size: 32px 32px;
    }
    .kpi-card {
        background: #fff;
        border-radius: 18px;
        padding: 18px;
        border: 1px solid #f1f5f9;
        transition: transform 0.18s ease, box-shadow 0.18s ease;
        position: relative;
        overflow: hidden;
    }
    .kpi-card:hover { transform: translateY(-2px); box-shadow: 0 12px 28px rgba(27,61,93,0.08); }
    .kpi-card .kpi-bg {
        position: absolute; right: -18px; bottom: -18px; opacity: 0.05;
        font-size: 100px; font-weight: 900; line-height: 1;
        pointer-events: none;
    }

    .bracket-card {
        background: #fff;
        border-radius: 20px;
        border: 1px solid #f1f5f9;
        overflow: hidden;
        transition: all 0.18s ease;
    }
    .bracket-card:hover {
        border-color: #cbd5e1;
        box-shadow: 0 12px 28px rgba(27,61,93,0.08);
        transform: translateY(-2px);
    }

    .pay-status-approved { background:#dcfce7; color:#15803d; border:1px solid #bbf7d0; }
    .pay-status-pending  { background:#fef3c7; color:#a16207; border:1px solid #fde68a; }
    .pay-status-rejected { background:#fee2e2; color:#b91c1c; border:1px solid #fecaca; }
    .pay-status-cancelled{ background:#f1f5f9; color:#475569; border:1px solid #e2e8f0; }
    .pay-status-refunded { background:#ede9fe; color:#6d28d9; border:1px solid #ddd6fe; }
</style>
@endpush

@section('content')

{{-- ═══════ HERO ═══════ --}}
<section class="profile-hero relative overflow-hidden text-white">
    <div class="relative max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-10 md:py-14">
        <div class="flex flex-col md:flex-row items-center gap-6">
            {{-- Avatar --}}
            <div class="relative shrink-0">
                <div class="w-24 h-24 md:w-28 md:h-28 rounded-3xl bg-gradient-to-br from-tc-accent to-yellow-500 flex items-center justify-center text-tc-primary text-4xl md:text-5xl font-black shadow-2xl ring-4 ring-white/10">
                    {{ strtoupper(substr($user->name, 0, 1)) }}
                </div>
                @if($user->is_admin)
                <div class="absolute -bottom-2 left-1/2 -translate-x-1/2 px-2.5 py-0.5 rounded-full bg-tc-accent text-tc-primary text-[9px] font-black uppercase tracking-widest shadow-lg">
                    Admin
                </div>
                @endif
            </div>

            {{-- Identity --}}
            <div class="text-center md:text-left flex-1 min-w-0">
                <h1 class="text-2xl md:text-3xl font-black tracking-tight">{{ $user->name }}</h1>
                <p class="text-white/60 text-sm mt-1 break-all">{{ $user->email }}</p>
                <div class="mt-3 flex flex-wrap items-center justify-center md:justify-start gap-2">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-white/5 border border-white/10 text-[11px] text-white/70">
                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"/></svg>
                        Miembro desde {{ $user->created_at->translatedFormat('M Y') }}
                    </span>
                    @if($user->email_verified_at)
                    <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full bg-green-500/15 border border-green-400/30 text-[11px] text-green-300">
                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                        Email verificado
                    </span>
                    @endif
                </div>
            </div>

            {{-- Action --}}
            <a href="{{ route('profile.edit') }}"
               class="px-5 py-2.5 rounded-xl bg-white/10 hover:bg-white/20 border border-white/15 text-sm font-bold backdrop-blur transition shrink-0">
                <span class="hidden md:inline">Editar perfil</span>
                <span class="md:hidden">Editar</span>
            </a>
        </div>
    </div>
</section>

{{-- ═══════ KPIs ═══════ --}}
<section class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 -mt-8 relative z-10 mb-10">
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <div class="kpi-card">
            <div class="kpi-bg text-tc-accent">★</div>
            <div class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Puntos totales</div>
            <div class="text-2xl md:text-3xl font-black text-tc-primary mt-1 tabular-nums">{{ number_format($totalPointsEarned) }}</div>
            <div class="text-[10px] text-gray-400 mt-1">{{ number_format($user->points) }} acumulados global</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-bg text-tc-primary">⚏</div>
            <div class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Brackets</div>
            <div class="text-2xl md:text-3xl font-black text-tc-primary mt-1 tabular-nums">{{ $myBrackets->count() }}</div>
            <div class="text-[10px] text-gray-400 mt-1">torneos jugados</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-bg text-green-500">✓</div>
            <div class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Aciertos</div>
            <div class="text-2xl md:text-3xl font-black text-green-600 mt-1 tabular-nums">{{ number_format($correctPicks) }}</div>
            <div class="text-[10px] text-gray-400 mt-1">de {{ number_format($totalPicks) }} predicciones</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-bg text-tc-accent">%</div>
            <div class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Precisión</div>
            <div class="text-2xl md:text-3xl font-black text-tc-primary mt-1 tabular-nums">{{ $accuracy }}%</div>
            @if($pendingPicks > 0)
            <div class="text-[10px] text-amber-600 mt-1">{{ $pendingPicks }} pendientes</div>
            @else
            <div class="text-[10px] text-gray-400 mt-1">de tus picks resueltos</div>
            @endif
        </div>
    </div>
</section>

<div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 pb-20 space-y-8">

    {{-- ═══════ MIS BRACKETS ═══════ --}}
    <section>
        <div class="flex items-end justify-between mb-4">
            <div>
                <h2 class="text-lg md:text-xl font-black text-tc-primary uppercase tracking-tight">Mis Brackets</h2>
                <p class="text-xs text-gray-400 mt-0.5">Torneos donde ya hiciste predicciones</p>
            </div>
            <a href="{{ route('tournaments.index') }}" class="text-xs font-bold text-tc-primary hover:underline">Explorar más →</a>
        </div>

        @if($myBrackets->isEmpty())
        <div class="bg-white rounded-2xl p-10 text-center border border-gray-100">
            <svg class="w-14 h-14 mx-auto text-gray-200 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2"/></svg>
            <p class="text-sm font-bold text-gray-700">Todavía no tienes brackets</p>
            <p class="text-xs text-gray-400 mt-1">Elige un torneo y haz tus predicciones para empezar</p>
            <a href="{{ route('tournaments.index') }}" class="inline-flex items-center gap-2 mt-5 px-5 py-2.5 bg-tc-primary text-white rounded-xl text-sm font-bold hover:bg-tc-primary-hover transition">
                Ver torneos
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
        </div>
        @else
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @foreach($myBrackets as $t)
            @php
                $isFinished = ($t->status ?? '') === 'finished';
                $isLive     = in_array($t->status ?? '', ['in_progress', 'live'], true);
                $accuracy   = $t->user_picks_total > 0
                    ? round(($t->user_picks_correct / $t->user_picks_total) * 100)
                    : 0;
            @endphp
            <a href="{{ route('tournaments.show', $t) }}" class="bracket-card flex flex-col group">
                {{-- Header --}}
                <div class="flex items-start gap-3 p-4 border-b border-gray-100">
                    {{-- Tournament image / badge --}}
                    @if($t->image)
                    <div class="w-14 h-14 rounded-xl overflow-hidden shrink-0 bg-gray-100">
                        <img src="{{ asset('storage/' . $t->image) }}" alt="" class="w-full h-full object-cover">
                    </div>
                    @else
                    <div class="w-14 h-14 rounded-xl bg-gradient-to-br {{ $t->type === 'GrandSlam' ? 'from-yellow-400 to-orange-500' : (str_starts_with($t->type, 'ATP') ? 'from-tc-primary to-blue-700' : 'from-purple-500 to-pink-500') }} flex items-center justify-center text-white text-2xl font-black shrink-0">
                        {{ substr($t->type, 0, 1) }}
                    </div>
                    @endif

                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-1.5 mb-1">
                            <span class="px-1.5 py-0.5 rounded text-[8px] font-black uppercase tracking-widest {{ $t->type === 'GrandSlam' ? 'bg-yellow-100 text-yellow-700' : (str_starts_with($t->type, 'ATP') ? 'bg-blue-100 text-blue-700' : 'bg-purple-100 text-purple-700') }}">{{ $t->type }}</span>
                            @if($isLive)
                            <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[8px] font-bold uppercase bg-red-100 text-red-700"><span class="w-1 h-1 bg-red-500 rounded-full animate-pulse"></span>LIVE</span>
                            @elseif($isFinished)
                            <span class="px-1.5 py-0.5 rounded text-[8px] font-bold uppercase bg-gray-200 text-gray-600">Finalizado</span>
                            @else
                            <span class="px-1.5 py-0.5 rounded text-[8px] font-bold uppercase bg-tc-accent/30 text-tc-primary">Próximo</span>
                            @endif
                        </div>
                        <h3 class="font-bold text-sm text-gray-800 truncate group-hover:text-tc-primary transition">{{ $t->name }}</h3>
                        <p class="text-[10px] text-gray-400 mt-0.5">{{ $t->start_date->format('d M') }} – {{ $t->end_date->format('d M, Y') }}</p>
                    </div>
                </div>

                {{-- Stats --}}
                <div class="grid grid-cols-3 gap-0 divide-x divide-gray-100 bg-gray-50/30">
                    <div class="p-3 text-center">
                        <div class="text-[9px] font-black text-gray-400 uppercase tracking-widest">Puntos</div>
                        <div class="text-lg font-black text-tc-primary mt-0.5 tabular-nums">{{ number_format($t->user_points_earned) }}</div>
                    </div>
                    <div class="p-3 text-center">
                        <div class="text-[9px] font-black text-gray-400 uppercase tracking-widest">Aciertos</div>
                        <div class="text-lg font-black text-green-600 mt-0.5 tabular-nums">{{ $t->user_picks_correct }}<span class="text-[10px] text-gray-400 font-bold">/{{ $t->user_picks_total }}</span></div>
                    </div>
                    <div class="p-3 text-center">
                        <div class="text-[9px] font-black text-gray-400 uppercase tracking-widest">Posición</div>
                        <div class="text-lg font-black text-tc-primary mt-0.5 tabular-nums">#{{ $t->user_rank }}<span class="text-[10px] text-gray-400 font-bold">/{{ $t->user_total_players }}</span></div>
                    </div>
                </div>

                {{-- Accuracy bar --}}
                @if($t->user_picks_total > 0)
                <div class="px-4 py-3 border-t border-gray-100">
                    <div class="flex items-center justify-between mb-1.5">
                        <span class="text-[9px] font-black text-gray-400 uppercase tracking-widest">Precisión</span>
                        <span class="text-[10px] font-black text-tc-primary tabular-nums">{{ $accuracy }}%</span>
                    </div>
                    <div class="h-1.5 bg-gray-100 rounded-full overflow-hidden">
                        <div class="h-full rounded-full bg-gradient-to-r {{ $accuracy >= 60 ? 'from-green-400 to-green-500' : ($accuracy >= 40 ? 'from-tc-accent to-yellow-500' : 'from-orange-400 to-red-400') }}" style="width: {{ $accuracy }}%"></div>
                    </div>
                </div>
                @endif
            </a>
            @endforeach
        </div>
        @endif
    </section>

    {{-- ═══════ MIS PAGOS ═══════ --}}
    @if($myPayments->count() > 0)
    <section>
        <div class="flex items-end justify-between mb-4">
            <div>
                <h2 class="text-lg md:text-xl font-black text-tc-primary uppercase tracking-tight">Mis Pagos</h2>
                <p class="text-xs text-gray-400 mt-0.5">Historial de torneos premium</p>
            </div>
        </div>
        <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden divide-y divide-gray-100">
            @foreach($myPayments as $p)
            @php
                $statusLabel = match($p->status) {
                    'approved' => 'Aprobado',
                    'pending'  => 'Pendiente',
                    'rejected' => 'Rechazado',
                    'cancelled'=> 'Cancelado',
                    'refunded' => 'Reembolsado',
                    default    => $p->status,
                };
            @endphp
            <div class="flex items-center gap-4 p-4 hover:bg-gray-50/50 transition">
                <div class="w-10 h-10 rounded-xl bg-tc-primary text-tc-accent flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path d="M4 4a2 2 0 00-2 2v1h16V6a2 2 0 00-2-2H4z"/><path fill-rule="evenodd" d="M18 9H2v5a2 2 0 002 2h12a2 2 0 002-2V9zM4 13a1 1 0 011-1h1a1 1 0 110 2H5a1 1 0 01-1-1zm5-1a1 1 0 100 2h1a1 1 0 100-2H9z" clip-rule="evenodd"/></svg>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="font-bold text-sm text-gray-800 truncate">
                        @if($p->tournament)
                        <a href="{{ route('tournaments.show', $p->tournament) }}" class="hover:text-tc-primary transition">{{ $p->tournament->name }}</a>
                        @else
                        <span class="text-gray-400">Torneo eliminado</span>
                        @endif
                    </div>
                    <div class="text-[11px] text-gray-400 flex items-center gap-2 mt-0.5">
                        <span>{{ $p->created_at->translatedFormat('d M Y · H:i') }}</span>
                        @if($p->mp_payment_id)
                        <span class="text-gray-300">·</span>
                        <span class="font-mono text-[10px]">MP #{{ $p->mp_payment_id }}</span>
                        @endif
                    </div>
                </div>
                <div class="text-right shrink-0">
                    <div class="font-black text-tc-primary tabular-nums text-sm">${{ number_format($p->amount, 0, ',', '.') }} {{ $p->currency }}</div>
                    <span class="inline-block mt-1 px-2 py-0.5 rounded-full text-[9px] font-bold uppercase tracking-widest pay-status-{{ $p->status }}">
                        {{ $statusLabel }}
                    </span>
                </div>
            </div>
            @endforeach
        </div>
    </section>
    @endif

    {{-- ═══════ MIS CANJES ═══════ --}}
    @if($redemptions->count() > 0)
    <section>
        <div class="flex items-end justify-between mb-4">
            <div>
                <h2 class="text-lg md:text-xl font-black text-tc-primary uppercase tracking-tight">Mis Canjes</h2>
                <p class="text-xs text-gray-400 mt-0.5">Premios que reclamaste</p>
            </div>
            <a href="{{ route('prizes.index') }}" class="text-xs font-bold text-tc-primary hover:underline">Ver premios →</a>
        </div>
        <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden divide-y divide-gray-100">
            @foreach($redemptions as $r)
            @php
                $statusCfg = match($r->status) {
                    'delivered' => ['label' => 'Entregado',  'cls' => 'bg-green-100 text-green-700 border-green-200'],
                    'approved'  => ['label' => 'Aprobado',   'cls' => 'bg-blue-100 text-blue-700 border-blue-200'],
                    'rejected'  => ['label' => 'Rechazado',  'cls' => 'bg-red-100 text-red-700 border-red-200'],
                    default     => ['label' => 'Pendiente',  'cls' => 'bg-yellow-100 text-yellow-800 border-yellow-200'],
                };
            @endphp
            <div class="flex items-center gap-4 p-4 hover:bg-gray-50/50 transition">
                <div class="w-12 h-12 rounded-xl bg-tc-accent/15 flex items-center justify-center shrink-0">
                    <svg class="w-6 h-6 text-tc-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7"/></svg>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="font-bold text-sm text-gray-800 truncate">{{ $r->prize->name }}</div>
                    <div class="text-[11px] text-gray-400 mt-0.5">{{ $r->created_at->translatedFormat('d M Y') }}</div>
                </div>
                <span class="px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-widest border {{ $statusCfg['cls'] }}">
                    {{ $statusCfg['label'] }}
                </span>
            </div>
            @endforeach
        </div>
    </section>
    @endif

</div>
@endsection
