@extends('layouts.admin')
@section('title', 'Crear Torneo')

@section('content')
<div class="max-w-2xl">
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('admin.tournaments.index') }}" class="text-gray-400 hover:text-gray-600"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg></a>
        <h2 class="text-xl font-bold">Crear Torneo</h2>
    </div>

    <form action="{{ route('admin.tournaments.store') }}" method="POST" enctype="multipart/form-data" class="bg-white rounded-2xl p-6 border border-gray-100 shadow-sm space-y-5">
        @csrf
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Nombre</label>
            <input type="text" name="name" value="{{ old('name') }}" required class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-tc-primary focus:border-transparent outline-none transition-all">
            @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Tipo</label>
                <select name="type" required class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-tc-primary focus:border-transparent outline-none">
                    <option value="ATP" {{ old('type') === 'ATP' ? 'selected' : '' }}>ATP Masters 1000</option>
                    <option value="WTA" {{ old('type') === 'WTA' ? 'selected' : '' }}>WTA 1000</option>
                    <option value="GrandSlam" {{ old('type') === 'GrandSlam' ? 'selected' : '' }}>Grand Slam</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Superficie</label>
                <input type="text" name="surface" value="{{ old('surface') }}" placeholder="Duro, Arcilla, Hierba..." class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-tc-primary focus:border-transparent outline-none">
            </div>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Ubicación</label>
            <input type="text" name="location" value="{{ old('location') }}" required class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-tc-primary focus:border-transparent outline-none">
        </div>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Ciudad</label>
                <input type="text" name="city" value="{{ old('city') }}" required class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-tc-primary focus:border-transparent outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">País</label>
                <input type="text" name="country" value="{{ old('country') }}" required class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-tc-primary focus:border-transparent outline-none">
            </div>
        </div>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Fecha inicio</label>
                <input type="date" name="start_date" value="{{ old('start_date') }}" required class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-tc-primary focus:border-transparent outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Fecha fin</label>
                <input type="date" name="end_date" value="{{ old('end_date') }}" required class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-tc-primary focus:border-transparent outline-none">
            </div>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Imagen</label>
            <input type="file" name="image" accept="image/*" class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-xl text-sm file:mr-4 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-sm file:bg-tc-primary file:text-white">
        </div>

        {{-- Puntos por ronda --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-3">Puntos por ronda</label>
            <p class="text-xs text-gray-500 mb-3">Configura cuántos puntos se otorgan por acertar en cada ronda. Deja en blanco para usar el valor por defecto.</p>
            @php
                $rounds = ['R128' => 'Ronda de 128', 'R64' => 'Ronda de 64', 'R32' => 'Ronda de 32', 'R16' => 'Octavos de Final', 'QF' => 'Cuartos de Final', 'SF' => 'Semifinal', 'F' => 'Final'];
            @endphp
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                @foreach($rounds as $key => $label)
                <div class="bg-gray-50 rounded-xl p-3">
                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ $label }} ({{ $key }})</label>
                    <input type="number" name="round_points[{{ $key }}]" value="{{ old('round_points.' . $key) }}" min="0" placeholder="—" class="w-full px-3 py-2 bg-white border border-gray-200 rounded-lg text-sm text-center focus:ring-2 focus:ring-tc-primary focus:border-transparent outline-none">
                </div>
                @endforeach
            </div>
        </div>
        <div class="flex items-center gap-6 flex-wrap">
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" name="is_premium" value="1" {{ old('is_premium') ? 'checked' : '' }} class="w-4 h-4 rounded border-gray-300 text-tc-primary focus:ring-tc-primary">
                <span class="text-sm">Premium (de pago)</span>
            </label>
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }} class="w-4 h-4 rounded border-gray-300 text-tc-primary focus:ring-tc-primary">
                <span class="text-sm">Activo</span>
            </label>
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" name="featured_on_home" value="1" {{ old('featured_on_home') ? 'checked' : '' }} class="w-4 h-4 rounded border-gray-300 text-tc-accent focus:ring-tc-accent">
                <span class="text-sm font-semibold text-tc-primary">⭐ Destacar en home (Próximo a predecir)</span>
            </label>
        </div>

        {{-- Price (only relevant when Premium) --}}
        <div class="bg-amber-50 border border-amber-200 rounded-xl p-4">
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Precio (COP)</label>
            <div class="flex items-center gap-2">
                <span class="text-gray-400 font-mono">$</span>
                <input type="number" name="price" value="{{ old('price') }}" min="0" step="100" placeholder="Ej: 15000"
                       class="flex-1 px-4 py-2.5 bg-white border border-amber-200 rounded-xl text-sm focus:ring-2 focus:ring-tc-primary focus:border-transparent outline-none">
                <span class="text-xs text-gray-500">COP</span>
            </div>
            <p class="text-[11px] text-gray-500 mt-2">Solo aplica si el torneo es <strong>Premium</strong>. Deja en blanco o 0 para gratis.</p>
            @error('price') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>
        <div class="flex items-center gap-3 pt-2">
            <button type="submit" class="px-6 py-2.5 bg-tc-primary text-white rounded-xl text-sm font-medium hover:bg-tc-primary-hover transition-colors">Crear torneo</button>
            <a href="{{ route('admin.tournaments.index') }}" class="px-6 py-2.5 bg-gray-100 text-gray-600 rounded-xl text-sm font-medium hover:bg-gray-200 transition-colors">Cancelar</a>
        </div>
    </form>
</div>
@endsection
