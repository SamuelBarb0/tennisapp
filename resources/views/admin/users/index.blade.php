@extends('layouts.admin')
@section('title', 'Usuarios')

@section('content')
<div class="flex items-center justify-between mb-6">
    <h2 class="text-xl font-bold">Gestión de Usuarios</h2>
</div>

{{-- Search --}}
<div class="bg-white rounded-2xl p-4 border border-gray-100 shadow-sm mb-6">
    <form action="{{ route('admin.users.index') }}" method="GET">
        <div class="relative max-w-md">
            <svg class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Buscar por nombre o email..." class="w-full pl-10 pr-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-[#0071E3] focus:border-transparent outline-none">
        </div>
    </form>
</div>

<div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="border-b border-gray-100">
                    <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase">Usuario</th>
                    <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase">Email</th>
                    <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase">Puntos</th>
                    <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase">Pronósticos</th>
                    <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase">Estado</th>
                    <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase">Registro</th>
                    <th class="text-right px-6 py-3 text-xs font-semibold text-gray-500 uppercase">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @foreach($users as $user)
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 bg-[#0071E3] rounded-full flex items-center justify-center text-white text-sm font-bold">{{ strtoupper(substr($user->name, 0, 1)) }}</div>
                            <div class="font-medium text-sm">{{ $user->name }}</div>
                        </div>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-500">{{ $user->email }}</td>
                    <td class="px-6 py-4 text-sm font-medium">{{ number_format($user->points ?? 0) }}</td>
                    <td class="px-6 py-4 text-sm text-gray-500">{{ $user->predictions_count ?? 0 }}</td>
                    <td class="px-6 py-4">
                        @if($user->is_blocked)
                            <span class="px-2.5 py-1 text-xs font-medium rounded-full bg-red-100 text-red-600">Bloqueado</span>
                        @else
                            <span class="px-2.5 py-1 text-xs font-medium rounded-full bg-green-100 text-green-600">Activo</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-500">{{ $user->created_at->format('d/m/Y') }}</td>
                    <td class="px-6 py-4 text-right">
                        <div class="flex items-center justify-end gap-2">
                            <a href="{{ route('admin.users.show', $user) }}" class="px-3 py-1.5 text-xs font-medium text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">Ver</a>
                            <form action="{{ route('admin.users.toggle-block', $user) }}" method="POST">
                                @csrf @method('PATCH')
                                <button class="px-3 py-1.5 text-xs font-medium rounded-lg transition-colors {{ $user->is_blocked ? 'text-green-600 bg-green-50 hover:bg-green-100' : 'text-red-600 bg-red-50 hover:bg-red-100' }}">
                                    {{ $user->is_blocked ? 'Desbloquear' : 'Bloquear' }}
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="px-6 py-4 border-t border-gray-100">
        {{ $users->links() }}
    </div>
</div>
@endsection
