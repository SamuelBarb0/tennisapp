@extends('layouts.admin')
@section('title', 'Jugadores')

@section('content')
<div class="flex items-center justify-between mb-6">
    <h2 class="text-xl font-bold">Gestión de Jugadores</h2>
    <a href="{{ route('admin.players.create') }}" class="px-5 py-2.5 bg-[#0071E3] text-white rounded-xl text-sm font-medium hover:bg-[#0062CC] transition-colors">
        + Nuevo jugador
    </a>
</div>

{{-- Search & Filters --}}
<div class="bg-white rounded-2xl p-4 border border-gray-100 shadow-sm mb-6">
    <div class="flex flex-col sm:flex-row items-start sm:items-center gap-4">
        <form action="{{ route('admin.players.index') }}" method="GET" class="flex-1 w-full sm:w-auto">
            <div class="relative">
                <svg class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Buscar jugador..." class="w-full pl-10 pr-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-[#0071E3] focus:border-transparent outline-none">
            </div>
        </form>
        <div class="flex items-center gap-2">
            <a href="{{ route('admin.players.index') }}" class="px-4 py-2 text-sm rounded-xl font-medium {{ !request('category') ? 'bg-[#0071E3] text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }} transition-colors">Todos</a>
            <a href="{{ route('admin.players.index', ['category' => 'ATP']) }}" class="px-4 py-2 text-sm rounded-xl font-medium {{ request('category') === 'ATP' ? 'bg-[#0071E3] text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }} transition-colors">ATP</a>
            <a href="{{ route('admin.players.index', ['category' => 'WTA']) }}" class="px-4 py-2 text-sm rounded-xl font-medium {{ request('category') === 'WTA' ? 'bg-[#0071E3] text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }} transition-colors">WTA</a>
        </div>
    </div>
</div>

<div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="border-b border-gray-100">
                    <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase">Jugador</th>
                    <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase">País</th>
                    <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase">Categoría</th>
                    <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase">Ranking</th>
                    <th class="text-right px-6 py-3 text-xs font-semibold text-gray-500 uppercase">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @foreach($players as $player)
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-3">
                            @if($player->photo)
                                <img src="{{ asset('storage/' . $player->photo) }}" alt="{{ $player->name }}" class="w-10 h-10 rounded-full object-cover">
                            @else
                                <div class="w-10 h-10 bg-gray-100 rounded-full flex items-center justify-center text-gray-500 text-sm font-bold">{{ strtoupper(substr($player->name, 0, 1)) }}</div>
                            @endif
                            <div class="font-medium text-sm">{{ $player->name }}</div>
                        </div>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-500">{{ $player->country }}</td>
                    <td class="px-6 py-4">
                        <span class="px-2.5 py-1 text-xs font-medium rounded-full {{ $player->category === 'ATP' ? 'bg-blue-100 text-blue-700' : 'bg-purple-100 text-purple-700' }}">{{ $player->category }}</span>
                    </td>
                    <td class="px-6 py-4 text-sm font-medium">#{{ $player->ranking }}</td>
                    <td class="px-6 py-4 text-right">
                        <div class="flex items-center justify-end gap-2">
                            <a href="{{ route('admin.players.edit', $player) }}" class="px-3 py-1.5 text-xs font-medium text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">Editar</a>
                            <form action="{{ route('admin.players.destroy', $player) }}" method="POST" x-data @submit.prevent="if(confirm('¿Eliminar este jugador?')) $el.submit()">
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
        {{ $players->links() }}
    </div>
</div>
@endsection
