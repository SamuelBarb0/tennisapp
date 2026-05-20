@extends('layouts.admin')
@section('title', 'Banners')

@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <h2 class="text-xl font-bold">Gestión de Banners</h2>
        <p class="text-xs text-gray-500 mt-1">Banners agrupados por dónde aparecen en el sitio público.</p>
    </div>
</div>

@if(session('success'))
<div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-xl text-sm text-green-700">{{ session('success') }}</div>
@endif

<div class="space-y-8">
    @foreach(\App\Models\Banner::SLOTS as $slotKey => $slotCfg)
    @php $slotBanners = $bannersBySlot[$slotKey] ?? collect(); @endphp

    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        {{-- Section header --}}
        <div class="flex items-start justify-between gap-4 px-6 py-4 border-b border-gray-100 bg-gray-50/50">
            <div class="min-w-0">
                <h3 class="text-base font-bold text-gray-800">{{ $slotCfg['label'] }}</h3>
                <p class="text-xs text-gray-500 mt-0.5">{{ $slotCfg['description'] }}</p>
                @if(!($slotCfg['allows_many'] ?? false))
                    <span class="inline-block mt-1.5 px-2 py-0.5 bg-blue-50 text-blue-700 text-[10px] font-bold uppercase tracking-widest rounded">Único</span>
                @endif
            </div>
            <a href="{{ route('admin.banners.create', ['slot' => $slotKey]) }}"
               class="shrink-0 px-4 py-2 bg-tc-primary text-white rounded-lg text-xs font-medium hover:bg-tc-primary-hover transition-colors whitespace-nowrap">
                + Nuevo
            </a>
        </div>

        {{-- Section table --}}
        @if($slotBanners->isEmpty())
            <div class="px-6 py-10 text-center text-sm text-gray-400">
                Aún no hay banners en esta sección. Se mostrará el texto por defecto.
            </div>
        @else
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-gray-100 text-xs font-semibold text-gray-500 uppercase">
                        <th class="text-left px-6 py-3">Título</th>
                        <th class="text-left px-6 py-3">Subtítulo</th>
                        @if($slotCfg['allows_many'] ?? false)
                        <th class="text-left px-6 py-3">Orden</th>
                        @endif
                        <th class="text-left px-6 py-3">Estado</th>
                        <th class="text-right px-6 py-3">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach($slotBanners as $banner)
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                @if($banner->image)
                                    <img src="{{ asset('storage/' . $banner->image) }}" alt="{{ $banner->title }}" class="w-16 h-10 rounded-lg object-cover">
                                @else
                                    <div class="w-16 h-10 bg-gray-100 rounded-lg flex items-center justify-center">
                                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    </div>
                                @endif
                                <div class="font-medium text-sm">{{ $banner->title }}</div>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500">{{ Str::limit($banner->subtitle, 40) }}</td>
                        @if($slotCfg['allows_many'] ?? false)
                        <td class="px-6 py-4 text-sm font-medium">{{ $banner->order }}</td>
                        @endif
                        <td class="px-6 py-4">
                            <form action="{{ route('admin.banners.toggle', $banner) }}" method="POST">
                                @csrf @method('PATCH')
                                <button type="submit" class="px-2.5 py-1 text-xs font-medium rounded-full transition-colors {{ $banner->is_active ? 'bg-green-100 text-green-600 hover:bg-green-200' : 'bg-gray-100 text-gray-500 hover:bg-gray-200' }}">
                                    {{ $banner->is_active ? 'Activo' : 'Inactivo' }}
                                </button>
                            </form>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <a href="{{ route('admin.banners.edit', $banner) }}" class="px-3 py-1.5 text-xs font-medium text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">Editar</a>
                                <form action="{{ route('admin.banners.destroy', $banner) }}" method="POST" x-data @submit.prevent="if(confirm('¿Eliminar este banner?')) $el.submit()">
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
        @endif
    </div>
    @endforeach
</div>
@endsection
