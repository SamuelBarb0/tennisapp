@extends('layouts.admin')
@section('title', 'Canjes')

@section('content')
<div class="flex items-center justify-between mb-6">
    <h2 class="text-xl font-bold">Gestión de Canjes</h2>
</div>

@if(session('success'))
<div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-xl text-sm text-green-700">{{ session('success') }}</div>
@endif

<div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="border-b border-gray-100">
                    <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase">Usuario</th>
                    <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase">Premio</th>
                    <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase">Fecha</th>
                    <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase">Estado</th>
                    <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase">Notas</th>
                    <th class="text-right px-6 py-3 text-xs font-semibold text-gray-500 uppercase">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @foreach($redemptions as $redemption)
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 bg-[#0071E3] rounded-full flex items-center justify-center text-white text-sm font-bold">{{ strtoupper(substr($redemption->user->name ?? '?', 0, 1)) }}</div>
                            <div>
                                <div class="font-medium text-sm">{{ $redemption->user->name ?? '-' }}</div>
                                <div class="text-xs text-gray-400">{{ $redemption->user->email ?? '' }}</div>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4 text-sm font-medium">{{ $redemption->prize->name ?? '-' }}</td>
                    <td class="px-6 py-4 text-sm text-gray-500">{{ $redemption->created_at->format('d/m/Y H:i') }}</td>
                    <td class="px-6 py-4">
                        <form action="{{ route('admin.redemptions.update', $redemption) }}" method="POST" class="inline" x-data>
                            @csrf @method('PATCH')
                            <select name="status" onchange="this.form.submit()" class="px-3 py-1.5 text-xs font-medium rounded-xl border-0 cursor-pointer focus:ring-2 focus:ring-[#0071E3]
                                {{ $redemption->status === 'completed' ? 'bg-green-100 text-green-600' : ($redemption->status === 'pending' ? 'bg-yellow-100 text-yellow-600' : ($redemption->status === 'rejected' ? 'bg-red-100 text-red-600' : 'bg-gray-100 text-gray-500')) }}">
                                <option value="pending" {{ $redemption->status === 'pending' ? 'selected' : '' }}>Pendiente</option>
                                <option value="completed" {{ $redemption->status === 'completed' ? 'selected' : '' }}>Completado</option>
                                <option value="rejected" {{ $redemption->status === 'rejected' ? 'selected' : '' }}>Rechazado</option>
                            </select>
                        </form>
                    </td>
                    <td class="px-6 py-4">
                        <form action="{{ route('admin.redemptions.update', $redemption) }}" method="POST" class="flex items-center gap-2" x-data="{ editing: false }">
                            @csrf @method('PATCH')
                            <input type="hidden" name="status" value="{{ $redemption->status }}">
                            <template x-if="!editing">
                                <span class="text-sm text-gray-500 cursor-pointer hover:text-gray-700" @click="editing = true">{{ $redemption->admin_notes ?: 'Agregar nota...' }}</span>
                            </template>
                            <template x-if="editing">
                                <div class="flex items-center gap-2">
                                    <input type="text" name="admin_notes" value="{{ $redemption->admin_notes }}" class="px-3 py-1.5 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#0071E3] focus:border-transparent outline-none" placeholder="Nota...">
                                    <button type="submit" class="px-2 py-1.5 text-xs font-medium text-[#0071E3] bg-blue-50 rounded-lg hover:bg-blue-100 transition-colors">Guardar</button>
                                </div>
                            </template>
                        </form>
                    </td>
                    <td class="px-6 py-4 text-right">
                        <a href="{{ route('admin.users.show', $redemption->user) }}" class="px-3 py-1.5 text-xs font-medium text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">Ver usuario</a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="px-6 py-4 border-t border-gray-100">
        {{ $redemptions->links() }}
    </div>
</div>
@endsection
