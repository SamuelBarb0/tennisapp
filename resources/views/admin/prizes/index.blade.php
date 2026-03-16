@extends('layouts.admin')
@section('title', 'Premios')

@section('content')
<div class="flex items-center justify-between mb-6">
    <h2 class="text-xl font-bold">Gestión de Premios</h2>
    <a href="{{ route('admin.prizes.create') }}" class="px-5 py-2.5 bg-tc-primary text-white rounded-xl text-sm font-medium hover:bg-tc-primary-hover transition-colors">
        + Nuevo premio
    </a>
</div>

<div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="border-b border-gray-100">
                    <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase">Premio</th>
                    <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase">Puntos requeridos</th>
                    <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase">Stock</th>
                    <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase">Estado</th>
                    <th class="text-right px-6 py-3 text-xs font-semibold text-gray-500 uppercase">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @foreach($prizes as $prize)
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-3">
                            @if($prize->image)
                                <img src="{{ asset('storage/' . $prize->image) }}" alt="{{ $prize->name }}" class="w-12 h-12 rounded-xl object-cover">
                            @else
                                <div class="w-12 h-12 bg-gray-100 rounded-xl flex items-center justify-center">
                                    <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7"/></svg>
                                </div>
                            @endif
                            <div>
                                <div class="font-medium text-sm">{{ $prize->name }}</div>
                                <div class="text-xs text-gray-400 truncate max-w-xs">{{ Str::limit($prize->description, 50) }}</div>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4 text-sm font-medium">{{ number_format($prize->points_required) }} pts</td>
                    <td class="px-6 py-4 text-sm">
                        <span class="{{ $prize->stock <= 5 ? 'text-red-600 font-medium' : 'text-gray-500' }}">{{ $prize->stock }}</span>
                    </td>
                    <td class="px-6 py-4">
                        @if($prize->is_active)
                            <span class="px-2.5 py-1 text-xs font-medium rounded-full bg-green-100 text-green-600">Activo</span>
                        @else
                            <span class="px-2.5 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-500">Inactivo</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-right">
                        <div class="flex items-center justify-end gap-2">
                            <a href="{{ route('admin.prizes.edit', $prize) }}" class="px-3 py-1.5 text-xs font-medium text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">Editar</a>
                            <form action="{{ route('admin.prizes.destroy', $prize) }}" method="POST" x-data @submit.prevent="if(confirm('¿Eliminar este premio?')) $el.submit()">
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
        {{ $prizes->links() }}
    </div>
</div>
@endsection
