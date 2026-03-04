@extends('layouts.admin')
@section('title', 'Partidos')

@section('content')
<div class="flex items-center justify-between mb-6">
    <h2 class="text-xl font-bold">Gestión de Partidos</h2>
    <a href="{{ route('admin.matches.create') }}" class="px-5 py-2.5 bg-[#0071E3] text-white rounded-xl text-sm font-medium hover:bg-[#0062CC] transition-colors">
        + Nuevo partido
    </a>
</div>

{{-- Filters --}}
<div class="bg-white rounded-2xl p-4 border border-gray-100 shadow-sm mb-6">
    <form action="{{ route('admin.matches.index') }}" method="GET" class="flex flex-col sm:flex-row items-start sm:items-center gap-4">
        <div class="flex-1 w-full sm:w-auto">
            <select name="tournament_id" class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-[#0071E3] focus:border-transparent outline-none">
                <option value="">Todos los torneos</option>
                @foreach($tournaments as $tournament)
                    <option value="{{ $tournament->id }}" {{ request('tournament_id') == $tournament->id ? 'selected' : '' }}>{{ $tournament->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('admin.matches.index') }}" class="px-4 py-2 text-sm rounded-xl font-medium {{ !request('status') ? 'bg-[#0071E3] text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }} transition-colors">Todos</a>
            <a href="{{ route('admin.matches.index', array_merge(request()->query(), ['status' => 'pending'])) }}" class="px-4 py-2 text-sm rounded-xl font-medium {{ request('status') === 'pending' ? 'bg-[#0071E3] text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }} transition-colors">Pendientes</a>
            <a href="{{ route('admin.matches.index', array_merge(request()->query(), ['status' => 'live'])) }}" class="px-4 py-2 text-sm rounded-xl font-medium {{ request('status') === 'live' ? 'bg-[#0071E3] text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }} transition-colors">En vivo</a>
            <a href="{{ route('admin.matches.index', array_merge(request()->query(), ['status' => 'finished'])) }}" class="px-4 py-2 text-sm rounded-xl font-medium {{ request('status') === 'finished' ? 'bg-[#0071E3] text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }} transition-colors">Finalizados</a>
        </div>
        <button type="submit" class="px-4 py-2.5 bg-gray-100 text-gray-600 rounded-xl text-sm font-medium hover:bg-gray-200 transition-colors">Filtrar</button>
    </form>
</div>

<div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="border-b border-gray-100">
                    <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase">Torneo</th>
                    <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase">Jugadores</th>
                    <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase">Ronda</th>
                    <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase">Fecha</th>
                    <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase">Estado</th>
                    <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase">Score</th>
                    <th class="text-right px-6 py-3 text-xs font-semibold text-gray-500 uppercase">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @foreach($matches as $match)
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-6 py-4 text-sm text-gray-500">{{ $match->tournament->name ?? '-' }}</td>
                    <td class="px-6 py-4">
                        <div class="text-sm font-medium">{{ $match->player1->name ?? 'TBD' }}</div>
                        <div class="text-xs text-gray-400">vs {{ $match->player2->name ?? 'TBD' }}</div>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-500">{{ $match->round }}</td>
                    <td class="px-6 py-4 text-sm text-gray-500">{{ $match->scheduled_at ? $match->scheduled_at->format('d/m/Y H:i') : '-' }}</td>
                    <td class="px-6 py-4">
                        <span class="px-2.5 py-1 text-xs font-medium rounded-full {{ $match->status === 'live' ? 'bg-red-100 text-red-600' : ($match->status === 'pending' ? 'bg-yellow-100 text-yellow-600' : 'bg-green-100 text-green-600') }}">
                            {{ $match->status === 'live' ? 'En vivo' : ($match->status === 'pending' ? 'Pendiente' : 'Finalizado') }}
                        </span>
                    </td>
                    <td class="px-6 py-4 text-sm font-mono">{{ $match->score ?? '-' }}</td>
                    <td class="px-6 py-4 text-right">
                        <div class="flex items-center justify-end gap-2">
                            <a href="{{ route('admin.matches.edit', $match) }}" class="px-3 py-1.5 text-xs font-medium text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">Editar</a>
                            <form action="{{ route('admin.matches.destroy', $match) }}" method="POST" x-data @submit.prevent="if(confirm('¿Eliminar este partido?')) $el.submit()">
                                @csrf @method('DELETE')
                                <button class="px-3 py-1.5 text-xs font-medium text-red-600 bg-red-50 rounded-lg hover:bg-red-100 transition-colors">Eliminar</button>
                            </form>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="px-6 py-4 border-t border-gray-100">
        {{ $matches->links() }}
    </div>
</div>
@endsection
