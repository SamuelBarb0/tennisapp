@extends('layouts.admin')
@section('title', 'Usuario: ' . $user->name)

@section('content')
<div class="flex items-center gap-3 mb-6">
    <a href="{{ route('admin.users.index') }}" class="text-gray-400 hover:text-gray-600"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg></a>
    <h2 class="text-xl font-bold">Detalle de Usuario</h2>
</div>

{{-- User Profile Card --}}
<div class="bg-white rounded-2xl p-6 border border-gray-100 shadow-sm mb-6">
    <div class="flex items-start gap-5">
        <div class="w-16 h-16 bg-tc-primary rounded-2xl flex items-center justify-center text-white text-2xl font-bold">{{ strtoupper(substr($user->name, 0, 1)) }}</div>
        <div class="flex-1">
            <div class="flex items-center gap-3 mb-1">
                <h3 class="text-lg font-bold">{{ $user->name }}</h3>
                @if($user->is_blocked)
                    <span class="px-2.5 py-1 text-xs font-medium rounded-full bg-red-100 text-red-600">Bloqueado</span>
                @else
                    <span class="px-2.5 py-1 text-xs font-medium rounded-full bg-green-100 text-green-600">Activo</span>
                @endif
            </div>
            <p class="text-sm text-gray-500">{{ $user->email }}</p>
            <p class="text-xs text-gray-400 mt-1">Registrado: {{ $user->created_at->format('d/m/Y H:i') }}</p>
        </div>
        <form action="{{ route('admin.users.toggle-block', $user) }}" method="POST">
            @csrf @method('PATCH')
            <button class="px-4 py-2 text-sm font-medium rounded-xl transition-colors {{ $user->is_blocked ? 'text-green-600 bg-green-50 hover:bg-green-100' : 'text-red-600 bg-red-50 hover:bg-red-100' }}">
                {{ $user->is_blocked ? 'Desbloquear usuario' : 'Bloquear usuario' }}
            </button>
        </form>
    </div>

    {{-- Stats --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mt-6">
        <div class="bg-gray-50 rounded-xl p-4 text-center">
            <div class="text-2xl font-bold text-tc-primary">{{ number_format($user->points ?? 0) }}</div>
            <div class="text-xs text-gray-500 mt-1">Puntos</div>
        </div>
        <div class="bg-gray-50 rounded-xl p-4 text-center">
            <div class="text-2xl font-bold">{{ $user->predictions->count() }}</div>
            <div class="text-xs text-gray-500 mt-1">Pronósticos</div>
        </div>
        <div class="bg-gray-50 rounded-xl p-4 text-center">
            <div class="text-2xl font-bold text-green-600">{{ $user->predictions->where('is_correct', true)->count() }}</div>
            <div class="text-xs text-gray-500 mt-1">Aciertos</div>
        </div>
        <div class="bg-gray-50 rounded-xl p-4 text-center">
            <div class="text-2xl font-bold">{{ $user->redemptions->count() }}</div>
            <div class="text-xs text-gray-500 mt-1">Canjes</div>
        </div>
    </div>
</div>

{{-- Recent Predictions --}}
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden mb-6">
    <div class="px-6 py-4 border-b border-gray-100">
        <h3 class="font-semibold">Pronósticos recientes</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="border-b border-gray-100">
                    <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase">Partido</th>
                    <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase">Predicción</th>
                    <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase">Resultado</th>
                    <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase">Puntos</th>
                    <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase">Fecha</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse($user->predictions()->with(['match.player1', 'match.player2', 'predictedWinner'])->latest()->take(10)->get() as $prediction)
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-6 py-4 text-sm">{{ $prediction->match->player1->name ?? '' }} vs {{ $prediction->match->player2->name ?? '' }}</td>
                    <td class="px-6 py-4 text-sm text-gray-500">{{ $prediction->predictedWinner->name ?? '-' }}</td>
                    <td class="px-6 py-4">
                        @if($prediction->is_correct === true)
                            <span class="px-2.5 py-1 text-xs font-medium rounded-full bg-green-100 text-green-600">Correcto</span>
                        @elseif($prediction->is_correct === false)
                            <span class="px-2.5 py-1 text-xs font-medium rounded-full bg-red-100 text-red-600">Incorrecto</span>
                        @else
                            <span class="px-2.5 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-500">Pendiente</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-sm font-medium">{{ $prediction->points_earned ?? 0 }}</td>
                    <td class="px-6 py-4 text-sm text-gray-500">{{ $prediction->created_at->format('d/m/Y') }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="px-6 py-8 text-center text-sm text-gray-400">No hay pronósticos registrados</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Redemptions --}}
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-100">
        <h3 class="font-semibold">Historial de canjes</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="border-b border-gray-100">
                    <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase">Premio</th>
                    <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase">Puntos</th>
                    <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase">Estado</th>
                    <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase">Fecha</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse($user->redemptions()->with('prize')->latest()->get() as $redemption)
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-6 py-4 text-sm font-medium">{{ $redemption->prize->name ?? '-' }}</td>
                    <td class="px-6 py-4 text-sm text-gray-500">{{ number_format($redemption->points_spent ?? 0) }}</td>
                    <td class="px-6 py-4">
                        <span class="px-2.5 py-1 text-xs font-medium rounded-full
                            {{ $redemption->status === 'completed' ? 'bg-green-100 text-green-600' : ($redemption->status === 'pending' ? 'bg-yellow-100 text-yellow-600' : ($redemption->status === 'rejected' ? 'bg-red-100 text-red-600' : 'bg-gray-100 text-gray-500')) }}">
                            {{ $redemption->status === 'completed' ? 'Completado' : ($redemption->status === 'pending' ? 'Pendiente' : ($redemption->status === 'rejected' ? 'Rechazado' : $redemption->status)) }}
                        </span>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-500">{{ $redemption->created_at->format('d/m/Y') }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" class="px-6 py-8 text-center text-sm text-gray-400">No hay canjes registrados</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
