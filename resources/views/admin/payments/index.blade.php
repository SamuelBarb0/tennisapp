@extends('layouts.admin')
@section('title', 'Pagos')

@section('content')
<div class="mb-6">
    <h2 class="text-xl font-bold text-tc-primary">Pagos de Torneos Premium</h2>
    <p class="text-xs text-gray-400 mt-0.5">Historial de transacciones procesadas por Mercado Pago</p>
</div>

{{-- Aggregates strip --}}
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-gradient-to-br from-green-500 to-emerald-600 rounded-2xl p-5 text-white">
        <div class="text-[10px] font-black uppercase tracking-widest text-white/80 mb-1">Confirmados</div>
        <div class="text-2xl font-black tabular-nums">${{ number_format($aggregates['approved_total'], 0, ',', '.') }}</div>
        <div class="text-xs text-white/70 mt-1">{{ $aggregates['approved_count'] }} pagos</div>
    </div>
    <div class="bg-gradient-to-br from-amber-400 to-orange-500 rounded-2xl p-5 text-white">
        <div class="text-[10px] font-black uppercase tracking-widest text-white/80 mb-1">Pendientes</div>
        <div class="text-2xl font-black tabular-nums">${{ number_format($aggregates['pending_total'], 0, ',', '.') }}</div>
        <div class="text-xs text-white/70 mt-1">{{ $aggregates['pending_count'] }} pagos</div>
    </div>
    <div class="bg-white border border-gray-100 rounded-2xl p-5">
        <div class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1">Rechazados</div>
        <div class="text-2xl font-black tabular-nums text-red-500">{{ $aggregates['rejected_count'] }}</div>
        <div class="text-xs text-gray-400 mt-1">pagos fallidos</div>
    </div>
    <div class="bg-white border border-gray-100 rounded-2xl p-5">
        <div class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1">Total transacciones</div>
        <div class="text-2xl font-black tabular-nums text-tc-primary">{{ $payments->total() }}</div>
        <div class="text-xs text-gray-400 mt-1">en el sistema</div>
    </div>
</div>

{{-- Filters --}}
<form method="GET" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 mb-6">
    <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
        <div>
            <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1">Buscar usuario</label>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Nombre o email..."
                   class="w-full px-3 py-2 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-tc-primary">
        </div>
        <div>
            <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1">Estado</label>
            <select name="status" class="w-full px-3 py-2 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-tc-primary">
                <option value="">Todos</option>
                <option value="approved"  {{ request('status') === 'approved'  ? 'selected' : '' }}>Aprobado</option>
                <option value="pending"   {{ request('status') === 'pending'   ? 'selected' : '' }}>Pendiente</option>
                <option value="rejected"  {{ request('status') === 'rejected'  ? 'selected' : '' }}>Rechazado</option>
                <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>Cancelado</option>
                <option value="refunded"  {{ request('status') === 'refunded'  ? 'selected' : '' }}>Reembolsado</option>
            </select>
        </div>
        <div>
            <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1">Torneo</label>
            <select name="tournament_id" class="w-full px-3 py-2 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-tc-primary">
                <option value="">Todos</option>
                @foreach($tournaments as $t)
                <option value="{{ $t->id }}" {{ request('tournament_id') == $t->id ? 'selected' : '' }}>{{ $t->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex items-end gap-2">
            <button type="submit" class="flex-1 px-4 py-2 bg-tc-primary text-white rounded-xl text-sm font-bold hover:bg-tc-primary-hover">Filtrar</button>
            @if(request()->anyFilled(['search', 'status', 'tournament_id']))
            <a href="{{ route('admin.payments.index') }}" class="px-3 py-2 border border-gray-200 rounded-xl text-sm text-gray-500">✕</a>
            @endif
        </div>
    </div>
</form>

{{-- Table --}}
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50/60 text-[10px] font-black uppercase tracking-widest text-gray-400">
                <tr>
                    <th class="px-5 py-3 text-left">Usuario</th>
                    <th class="px-5 py-3 text-left">Torneo</th>
                    <th class="px-5 py-3 text-right">Monto</th>
                    <th class="px-5 py-3 text-center">Estado</th>
                    <th class="px-5 py-3 text-left">Fecha</th>
                    <th class="px-5 py-3 text-left">MP ID</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($payments as $p)
                @php
                    $statusCfg = match($p->status) {
                        'approved' => ['bg-green-100 text-green-700 border-green-200', 'Aprobado'],
                        'pending'  => ['bg-amber-100 text-amber-700 border-amber-200', 'Pendiente'],
                        'rejected' => ['bg-red-100 text-red-700 border-red-200', 'Rechazado'],
                        'cancelled'=> ['bg-gray-100 text-gray-600 border-gray-200', 'Cancelado'],
                        'refunded' => ['bg-purple-100 text-purple-700 border-purple-200', 'Reembolsado'],
                        default    => ['bg-gray-100 text-gray-500 border-gray-200', $p->status],
                    };
                @endphp
                <tr class="hover:bg-gray-50/50">
                    <td class="px-5 py-3">
                        <div class="flex items-center gap-2">
                            <div class="w-7 h-7 rounded-full bg-tc-primary text-white text-[10px] font-bold flex items-center justify-center shrink-0">{{ strtoupper(substr($p->user->name ?? '?', 0, 1)) }}</div>
                            <div class="min-w-0">
                                <div class="font-medium truncate">{{ $p->user->name ?? 'Eliminado' }}</div>
                                <div class="text-[10px] text-gray-400 truncate">{{ $p->user->email ?? '' }}</div>
                            </div>
                        </div>
                    </td>
                    <td class="px-5 py-3">
                        @if($p->tournament)
                        <a href="{{ route('admin.tournaments.edit', $p->tournament) }}" class="text-tc-primary hover:underline font-medium">{{ $p->tournament->name }}</a>
                        @else
                        <span class="text-gray-400 italic">Eliminado</span>
                        @endif
                    </td>
                    <td class="px-5 py-3 text-right font-mono font-bold text-tc-primary">${{ number_format($p->amount, 0, ',', '.') }} <span class="text-[10px] text-gray-400">{{ $p->currency }}</span></td>
                    <td class="px-5 py-3 text-center">
                        <span class="inline-block px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-widest border {{ $statusCfg[0] }}">{{ $statusCfg[1] }}</span>
                    </td>
                    <td class="px-5 py-3 text-xs text-gray-500">
                        <div>{{ $p->created_at->format('d M Y') }}</div>
                        <div class="text-[10px] text-gray-400">{{ $p->created_at->format('H:i') }}</div>
                    </td>
                    <td class="px-5 py-3 font-mono text-[11px] text-gray-500">
                        {{ $p->mp_payment_id ?? '—' }}
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-5 py-16 text-center">
                        <svg class="w-12 h-12 mx-auto text-gray-200 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                        <p class="text-sm font-bold text-gray-700">Sin pagos registrados</p>
                        <p class="text-xs text-gray-400 mt-1">Cuando alguien pague un torneo premium aparecerá aquí.</p>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@if($payments->hasPages())
<div class="mt-5">
    {{ $payments->links() }}
</div>
@endif

@endsection
