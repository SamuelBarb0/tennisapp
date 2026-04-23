@extends('layouts.admin')
@section('title', 'Editar Torneo')

@section('content')
<div class="max-w-2xl">
    <div class="flex items-center justify-between gap-3 mb-6">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.tournaments.index') }}" class="text-gray-400 hover:text-gray-600"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg></a>
            <h2 class="text-xl font-bold">Editar Torneo</h2>
        </div>
        <a href="{{ route('admin.tournaments.tiebreaks', $tournament) }}"
           class="inline-flex items-center gap-2 px-4 py-2 bg-amber-500 hover:bg-amber-600 text-white text-sm font-bold rounded-xl shadow-sm transition-all">
            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
            </svg>
            Desempates
        </a>
    </div>

    <form action="{{ route('admin.tournaments.update', $tournament) }}" method="POST" enctype="multipart/form-data" class="bg-white rounded-2xl p-6 border border-gray-100 shadow-sm space-y-5">
        @csrf
        @method('PUT')
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Nombre</label>
            <input type="text" name="name" value="{{ old('name', $tournament->name) }}" required class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-tc-primary focus:border-transparent outline-none transition-all">
            @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Tipo</label>
                <select name="type" required class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-tc-primary focus:border-transparent outline-none">
                    <option value="ATP" {{ old('type', $tournament->type) === 'ATP' ? 'selected' : '' }}>ATP Masters 1000</option>
                    <option value="WTA" {{ old('type', $tournament->type) === 'WTA' ? 'selected' : '' }}>WTA 1000</option>
                    <option value="GrandSlam" {{ old('type', $tournament->type) === 'GrandSlam' ? 'selected' : '' }}>Grand Slam</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Superficie</label>
                <input type="text" name="surface" value="{{ old('surface', $tournament->surface) }}" placeholder="Duro, Arcilla, Hierba..." class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-tc-primary focus:border-transparent outline-none">
            </div>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Ubicación</label>
            <input type="text" name="location" value="{{ old('location', $tournament->location) }}" required class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-tc-primary focus:border-transparent outline-none">
        </div>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Ciudad</label>
                <input type="text" name="city" value="{{ old('city', $tournament->city) }}" required class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-tc-primary focus:border-transparent outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">País</label>
                <input type="text" name="country" value="{{ old('country', $tournament->country) }}" required class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-tc-primary focus:border-transparent outline-none">
            </div>
        </div>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Fecha inicio</label>
                <input type="date" name="start_date" value="{{ old('start_date', $tournament->start_date->format('Y-m-d')) }}" required class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-tc-primary focus:border-transparent outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Fecha fin</label>
                <input type="date" name="end_date" value="{{ old('end_date', $tournament->end_date->format('Y-m-d')) }}" required class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-tc-primary focus:border-transparent outline-none">
            </div>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Imagen</label>
            <input type="file" name="image" accept="image/*" class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-xl text-sm file:mr-4 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-sm file:bg-tc-primary file:text-white">
            @if($tournament->image)
                <p class="text-xs text-gray-400 mt-1">Imagen actual: {{ $tournament->image }}</p>
            @endif
        </div>

        {{-- Puntos por ronda --}}
        <div class="border-t border-gray-100 pt-5">
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Puntos por ronda</label>
            <p class="text-xs text-gray-500 mb-3">Configura cuántos puntos gana un usuario por predecir correctamente en cada ronda. Deja vacío para usar el valor por defecto.</p>
            @php
                $rounds = ['R128' => 'Ronda de 128', 'R64' => 'Ronda de 64', 'R32' => 'Ronda de 32', 'R16' => 'Octavos de Final', 'QF' => 'Cuartos de Final', 'SF' => 'Semifinal', 'F' => 'Final'];
                $existingPoints = $tournament->roundPoints->pluck('points', 'round');
            @endphp
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                @foreach($rounds as $key => $label)
                <div class="bg-gray-50 rounded-xl p-3">
                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ $label }} ({{ $key }})</label>
                    <input type="number" name="round_points[{{ $key }}]" value="{{ old('round_points.' . $key, $existingPoints[$key] ?? '') }}" min="0" placeholder="—" class="w-full px-3 py-2 bg-white border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-tc-primary focus:border-transparent outline-none">
                </div>
                @endforeach
            </div>
        </div>
        <div class="flex items-center gap-6">
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" name="is_premium" value="1" {{ old('is_premium', $tournament->is_premium) ? 'checked' : '' }} class="w-4 h-4 rounded border-gray-300 text-tc-primary focus:ring-tc-primary">
                <span class="text-sm">Premium</span>
            </label>
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" name="is_active" value="1" {{ old('is_active', $tournament->is_active) ? 'checked' : '' }} class="w-4 h-4 rounded border-gray-300 text-tc-primary focus:ring-tc-primary">
                <span class="text-sm">Activo</span>
            </label>
        </div>
        <div class="flex items-center gap-3 pt-2">
            <button type="submit" class="px-6 py-2.5 bg-tc-primary text-white rounded-xl text-sm font-medium hover:bg-tc-primary-hover transition-colors">Actualizar torneo</button>
            <a href="{{ route('admin.tournaments.index') }}" class="px-6 py-2.5 bg-gray-100 text-gray-600 rounded-xl text-sm font-medium hover:bg-gray-200 transition-colors">Cancelar</a>
        </div>
    </form>
</div>
@endsection
