@extends('layouts.admin')
@section('title', 'Torneos')

@section('content')
<div class="flex items-center justify-between mb-6">
    <h2 class="text-xl font-bold">Gestión de Torneos</h2>
    <a href="{{ route('admin.tournaments.create') }}" class="px-5 py-2.5 bg-[#0071E3] text-white rounded-xl text-sm font-medium hover:bg-[#0062CC] transition-colors">
        + Nuevo torneo
    </a>
</div>

<div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="border-b border-gray-100">
                    <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase">Torneo</th>
                    <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase">Tipo</th>
                    <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase">Fechas</th>
                    <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase">Estado</th>
                    <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase">Premium</th>
                    <th class="text-right px-6 py-3 text-xs font-semibold text-gray-500 uppercase">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @foreach($tournaments as $tournament)
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-6 py-4">
                        <div class="font-medium text-sm">{{ $tournament->name }}</div>
                        <div class="text-xs text-gray-400">{{ $tournament->city }}, {{ $tournament->country }}</div>
                    </td>
                    <td class="px-6 py-4">
                        <span class="px-2.5 py-1 text-xs font-medium rounded-full {{ $tournament->type === 'GrandSlam' ? 'bg-yellow-100 text-yellow-700' : ($tournament->type === 'ATP' ? 'bg-blue-100 text-blue-700' : 'bg-purple-100 text-purple-700') }}">{{ $tournament->type }}</span>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-500">{{ $tournament->start_date->format('d/m/Y') }} - {{ $tournament->end_date->format('d/m/Y') }}</td>
                    <td class="px-6 py-4">
                        @php $status = $tournament->status; @endphp
                        <span class="px-2.5 py-1 text-xs font-medium rounded-full {{ $status === 'live' ? 'bg-red-100 text-red-600' : ($status === 'upcoming' ? 'bg-blue-100 text-blue-600' : 'bg-gray-100 text-gray-500') }}">
                            {{ $status === 'live' ? 'En curso' : ($status === 'upcoming' ? 'Próximo' : 'Finalizado') }}
                        </span>
                    </td>
                    <td class="px-6 py-4">
                        @if($tournament->is_premium)
                            <span class="px-2 py-0.5 bg-yellow-100 text-yellow-700 text-xs rounded-full font-medium">Premium</span>
                        @else
                            <span class="text-xs text-gray-400">Gratis</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-right">
                        <div class="flex items-center justify-end gap-2">
                            <a href="{{ route('admin.tournaments.edit', $tournament) }}" class="px-3 py-1.5 text-xs font-medium text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">Editar</a>
                            <form action="{{ route('admin.tournaments.destroy', $tournament) }}" method="POST" x-data @submit.prevent="if(confirm('¿Eliminar este torneo?')) $el.submit()">
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
        {{ $tournaments->links() }}
    </div>
</div>
@endsection
