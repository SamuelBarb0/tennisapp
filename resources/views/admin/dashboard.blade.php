@extends('layouts.admin')
@section('title', 'Dashboard')

@section('content')

{{-- ═══════ KPI cards ═══════ --}}
<div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 mb-6">

    {{-- Revenue (highlight) --}}
    <div class="md:col-span-2 lg:col-span-2 bg-gradient-to-br from-tc-primary to-[#264a6e] rounded-2xl p-6 text-white relative overflow-hidden">
        <div class="absolute -right-6 -bottom-6 opacity-10 text-9xl">$</div>
        <div class="relative">
            <div class="flex items-center gap-2 text-tc-accent text-[10px] font-black uppercase tracking-widest mb-2">
                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M4 4a2 2 0 00-2 2v1h16V6a2 2 0 00-2-2H4z"/><path fill-rule="evenodd" d="M18 9H2v5a2 2 0 002 2h12a2 2 0 002-2V9z" clip-rule="evenodd"/></svg>
                Ingresos confirmados
            </div>
            <div class="text-4xl font-black tabular-nums">${{ number_format($stats['revenue_total'], 0, ',', '.') }}<span class="text-xl text-white/50 ml-1">COP</span></div>
            <div class="flex items-center gap-3 mt-3 text-xs text-white/60">
                <span class="flex items-center gap-1">
                    <span class="w-1.5 h-1.5 rounded-full bg-tc-accent"></span>
                    {{ $stats['paid_users'] }} usuarios pagaron
                </span>
                @if($stats['revenue_pending'] > 0)
                <span class="text-amber-300">
                    + ${{ number_format($stats['revenue_pending'], 0, ',', '.') }} pendientes
                </span>
                @endif
            </div>
        </div>
    </div>

    @php
    $cards = [
        ['label' => 'Usuarios',        'value' => number_format($stats['users']),                'icon_color' => 'text-blue-500',   'bg' => 'bg-blue-50',   'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>'],
        ['label' => 'Predicciones',    'value' => number_format($stats['predictions']),          'icon_color' => 'text-green-500',  'bg' => 'bg-green-50',  'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>'],
        ['label' => 'Torneos activos', 'value' => $stats['tournaments_active'] . '/' . $stats['tournaments_total'], 'icon_color' => 'text-purple-500', 'bg' => 'bg-purple-50', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5"/>'],
        ['label' => 'Partidos en vivo','value' => $stats['live_matches'],                        'icon_color' => 'text-red-500',    'bg' => 'bg-red-50',    'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>'],
        ['label' => 'Canjes pendientes','value' => $stats['pending_redemptions'],                'icon_color' => 'text-orange-500', 'bg' => 'bg-orange-50', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>'],
        ['label' => 'Pagos confirmados','value' => $stats['paid_users'],                         'icon_color' => 'text-tc-primary', 'bg' => 'bg-yellow-50', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>'],
    ];
    @endphp
    @foreach($cards as $card)
    <div class="bg-white rounded-2xl p-5 border border-gray-100 shadow-sm hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between mb-2">
            <span class="text-xs text-gray-500 font-medium">{{ $card['label'] }}</span>
            <div class="w-9 h-9 {{ $card['bg'] }} rounded-xl flex items-center justify-center">
                <svg class="w-5 h-5 {{ $card['icon_color'] }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">{!! $card['icon'] !!}</svg>
            </div>
        </div>
        <div class="text-2xl font-black tabular-nums text-gray-800">{{ $card['value'] }}</div>
    </div>
    @endforeach
</div>

{{-- ═══════ Tournaments at a glance ═══════ --}}
@if($tournamentsAtAGlance->count() > 0)
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm mb-6 overflow-hidden">
    <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
        <h3 class="font-bold text-tc-primary">Torneos activos</h3>
        <a href="{{ route('admin.tournaments.index') }}" class="text-xs text-tc-primary hover:underline font-bold">Ver todos →</a>
    </div>
    <div class="divide-y divide-gray-100">
        @foreach($tournamentsAtAGlance as $t)
        @php
            $progress = $t->total_matches > 0 ? round(($t->finished_matches / $t->total_matches) * 100) : 0;
        @endphp
        <a href="{{ route('admin.tournaments.edit', $t) }}" class="flex items-center gap-4 p-4 hover:bg-gray-50/50 transition">
            {{-- Type badge --}}
            <div class="w-11 h-11 rounded-xl bg-gradient-to-br {{ $t->type === 'GrandSlam' ? 'from-yellow-400 to-orange-500' : (str_starts_with($t->type, 'ATP') ? 'from-tc-primary to-blue-700' : 'from-purple-500 to-pink-500') }} flex items-center justify-center text-white font-black text-lg shrink-0">
                {{ substr($t->type, 0, 1) }}
            </div>
            {{-- Info --}}
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-1.5 flex-wrap mb-0.5">
                    <span class="font-bold text-sm text-gray-800 truncate">{{ $t->name }}</span>
                    @if($t->is_premium)
                    <span class="px-1.5 py-0.5 bg-yellow-100 text-yellow-800 text-[8px] font-black uppercase tracking-widest rounded">${{ number_format($t->price, 0) }}</span>
                    @endif
                    @if($t->featured_on_home)
                    <span class="px-1.5 py-0.5 bg-tc-accent/30 text-tc-primary text-[8px] font-black uppercase tracking-widest rounded">★ Destacado</span>
                    @endif
                </div>
                <div class="text-[10px] text-gray-400 flex items-center gap-2">
                    <span>{{ $t->city }}</span>
                    <span class="text-gray-300">·</span>
                    <span>{{ $t->start_date->format('d M') }}</span>
                </div>
            </div>
            {{-- Progress --}}
            <div class="hidden md:block w-32 shrink-0">
                <div class="flex items-center justify-between text-[10px] text-gray-400 mb-1">
                    <span>{{ $t->finished_matches }}/{{ $t->total_matches }} partidos</span>
                    <span class="font-mono font-bold text-tc-primary">{{ $progress }}%</span>
                </div>
                <div class="h-1.5 bg-gray-100 rounded-full overflow-hidden">
                    <div class="h-full bg-tc-accent rounded-full" style="width: {{ $progress }}%"></div>
                </div>
            </div>
            <svg class="w-4 h-4 text-gray-300 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        </a>
        @endforeach
    </div>
</div>
@endif

{{-- ═══════ Two columns: payments + predictions ═══════ --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">

    {{-- Recent Payments --}}
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
            <h3 class="font-bold text-tc-primary">Pagos recientes</h3>
            <a href="{{ route('admin.payments.index') }}" class="text-xs text-tc-primary hover:underline font-bold">Ver todos →</a>
        </div>
        @if($recentPayments->isEmpty())
        <div class="p-8 text-center text-xs text-gray-400">Sin pagos registrados todavía.</div>
        @else
        <div class="divide-y divide-gray-100">
            @foreach($recentPayments as $p)
            @php
                $statusCfg = match($p->status) {
                    'approved' => ['bg-green-100 text-green-700', 'Aprobado'],
                    'pending'  => ['bg-amber-100 text-amber-700', 'Pendiente'],
                    'rejected' => ['bg-red-100 text-red-700', 'Rechazado'],
                    'cancelled'=> ['bg-gray-100 text-gray-600', 'Cancelado'],
                    'refunded' => ['bg-purple-100 text-purple-700', 'Reembolsado'],
                    default    => ['bg-gray-100 text-gray-500', $p->status],
                };
            @endphp
            <div class="flex items-center gap-3 px-5 py-3">
                <div class="w-8 h-8 rounded-full bg-tc-primary text-white text-[10px] font-bold flex items-center justify-center shrink-0">
                    {{ strtoupper(substr($p->user->name ?? '?', 0, 1)) }}
                </div>
                <div class="flex-1 min-w-0">
                    <div class="text-sm font-medium text-gray-800 truncate">{{ $p->user->name ?? 'Usuario' }}</div>
                    <div class="text-[10px] text-gray-400 truncate">{{ $p->tournament->name ?? 'Torneo eliminado' }}</div>
                </div>
                <div class="text-right shrink-0">
                    <div class="text-sm font-black text-tc-primary tabular-nums">${{ number_format($p->amount, 0, ',', '.') }}</div>
                    <span class="inline-block mt-0.5 px-1.5 py-0.5 rounded-full text-[8px] font-bold uppercase tracking-widest {{ $statusCfg[0] }}">{{ $statusCfg[1] }}</span>
                </div>
            </div>
            @endforeach
        </div>
        @endif
    </div>

    {{-- Recent Predictions --}}
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
            <h3 class="font-bold text-tc-primary">Predicciones recientes</h3>
        </div>
        @if($recentPredictions->isEmpty())
        <div class="p-8 text-center text-xs text-gray-400">Sin predicciones todavía.</div>
        @else
        <div class="divide-y divide-gray-100">
            @foreach($recentPredictions as $pred)
            @php
                $resultCls = match(true) {
                    $pred->is_correct === true  => 'bg-green-100 text-green-700',
                    $pred->is_correct === false => 'bg-red-100 text-red-700',
                    default                     => 'bg-gray-100 text-gray-500',
                };
                $resultIcon = match(true) {
                    $pred->is_correct === true  => '✓',
                    $pred->is_correct === false => '✕',
                    default                     => '·',
                };
            @endphp
            <div class="flex items-center gap-3 px-5 py-3">
                <div class="w-8 h-8 rounded-full {{ $resultCls }} text-sm font-bold flex items-center justify-center shrink-0">
                    {{ $resultIcon }}
                </div>
                <div class="flex-1 min-w-0">
                    <div class="text-sm font-medium text-gray-800 truncate">
                        {{ $pred->user->name ?? 'Usuario' }} <span class="text-gray-400 font-normal">→</span> {{ $pred->predictedWinner->name ?? '?' }}
                    </div>
                    <div class="text-[10px] text-gray-400 truncate">{{ $pred->tournament->name ?? '' }} · {{ $pred->round }}</div>
                </div>
                @if($pred->points_earned > 0)
                <div class="text-xs font-black text-green-600 tabular-nums shrink-0">+{{ $pred->points_earned }}</div>
                @endif
            </div>
            @endforeach
        </div>
        @endif
    </div>
</div>

{{-- ═══════ Recent users ═══════ --}}
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
    <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
        <h3 class="font-bold text-tc-primary">Usuarios recientes</h3>
        <a href="{{ route('admin.users.index') }}" class="text-xs text-tc-primary hover:underline font-bold">Ver todos →</a>
    </div>
    <div class="divide-y divide-gray-100">
        @foreach($recentUsers as $u)
        <div class="flex items-center gap-3 px-5 py-3">
            <div class="w-9 h-9 rounded-full bg-tc-primary text-white text-sm font-bold flex items-center justify-center shrink-0">
                {{ strtoupper(substr($u->name, 0, 1)) }}
            </div>
            <div class="flex-1 min-w-0">
                <div class="text-sm font-medium text-gray-800 truncate">{{ $u->name }}</div>
                <div class="text-xs text-gray-400 truncate">{{ $u->email }}</div>
            </div>
            <span class="text-xs text-gray-400 shrink-0">{{ $u->created_at->diffForHumans() }}</span>
        </div>
        @endforeach
    </div>
</div>

@endsection
