@extends('layouts.admin')
@section('title', 'Editar Torneo')

@section('content')
<div class="max-w-2xl">
    <div class="flex items-center justify-between gap-3 mb-6">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.tournaments.index') }}" class="text-gray-400 hover:text-gray-600"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg></a>
            <h2 class="text-xl font-bold">Editar Torneo</h2>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('admin.tournaments.badges', $tournament) }}"
               class="inline-flex items-center gap-2 px-4 py-2 bg-tc-primary hover:bg-tc-primary/90 text-white text-sm font-bold rounded-xl shadow-sm transition-all">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                </svg>
                Marcas (Q/WC/LL)
            </a>
            <a href="{{ route('admin.tournaments.tiebreaks', $tournament) }}"
               class="inline-flex items-center gap-2 px-4 py-2 bg-amber-500 hover:bg-amber-600 text-white text-sm font-bold rounded-xl shadow-sm transition-all">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                </svg>
                Desempates
            </a>
        </div>
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
                @php
                    // Match the tier strings the api-tennis sync stores so editing
                    // a synced tournament doesn't downgrade its tier on save.
                    $tournamentTypes = [
                        'ATP Grand Slam'   => 'ATP Grand Slam',
                        'WTA Grand Slam'   => 'WTA Grand Slam',
                        'ATP Masters 1000' => 'ATP Masters 1000',
                        'WTA 1000'         => 'WTA 1000',
                        'ATP'              => 'ATP (genérico)',
                        'WTA'              => 'WTA (genérico)',
                        'GrandSlam'        => 'Grand Slam (legado)',
                    ];
                    $currentType = old('type', $tournament->type);
                @endphp
                <select name="type" required class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-tc-primary focus:border-transparent outline-none">
                    @foreach($tournamentTypes as $value => $label)
                        <option value="{{ $value }}" {{ $currentType === $value ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
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
                <input type="date" name="start_date" value="{{ old('start_date', $tournament->start_date?->format('Y-m-d')) }}" required class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-tc-primary focus:border-transparent outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Fecha fin</label>
                <input type="date" name="end_date" value="{{ old('end_date', $tournament->end_date?->format('Y-m-d')) }}" required class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-tc-primary focus:border-transparent outline-none">
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
        <div class="flex items-center gap-6 flex-wrap">
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" name="is_premium" value="1" {{ old('is_premium', $tournament->is_premium) ? 'checked' : '' }} class="w-4 h-4 rounded border-gray-300 text-tc-primary focus:ring-tc-primary">
                <span class="text-sm">Premium (de pago)</span>
            </label>
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" name="is_active" value="1" {{ old('is_active', $tournament->is_active) ? 'checked' : '' }} class="w-4 h-4 rounded border-gray-300 text-tc-primary focus:ring-tc-primary">
                <span class="text-sm">Activo</span>
            </label>
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" name="featured_on_home" value="1" {{ old('featured_on_home', $tournament->featured_on_home) ? 'checked' : '' }} class="w-4 h-4 rounded border-gray-300 text-tc-accent focus:ring-tc-accent">
                <span class="text-sm font-semibold text-tc-primary">⭐ Destacar en home (Próximo a predecir)</span>
            </label>
        </div>

        {{-- Matchstat sync ID --}}
        <div class="bg-blue-50 border border-blue-200 rounded-xl p-4">
            <label class="block text-sm font-medium text-gray-700 mb-1.5">ID de Matchstat (auto-sync)</label>
            <div class="flex items-center gap-2">
                <span class="text-gray-400 text-xs">tournamentId:</span>
                <input type="number" name="matchstat_tournament_id" value="{{ old('matchstat_tournament_id', $tournament->matchstat_tournament_id) }}" placeholder="Ej: 21325 (Madrid Open)"
                       class="flex-1 px-4 py-2.5 bg-white border border-blue-200 rounded-xl text-sm focus:ring-2 focus:ring-tc-primary focus:border-transparent outline-none">
            </div>
            <p class="text-[11px] text-gray-500 mt-2">Si el torneo existe en Matchstat, pega aquí su <code>tournamentId</code>. El sistema sincronizará fixtures y scores automáticamente cada 2 minutos.</p>
        </div>

        {{-- Price (only relevant when Premium) --}}
        <div class="bg-amber-50 border border-amber-200 rounded-xl p-4">
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Precio (COP)</label>
            <div class="flex items-center gap-2">
                <span class="text-gray-400 font-mono">$</span>
                <input type="number" name="price" value="{{ old('price', $tournament->price) }}" min="0" step="100" placeholder="Ej: 15000"
                       class="flex-1 px-4 py-2.5 bg-white border border-amber-200 rounded-xl text-sm focus:ring-2 focus:ring-tc-primary focus:border-transparent outline-none">
                <span class="text-xs text-gray-500">COP</span>
            </div>
            <p class="text-[11px] text-gray-500 mt-2">Solo aplica si el torneo es <strong>Premium</strong>. Deja en blanco o 0 para gratis.</p>
            @error('price') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>
        <div class="flex items-center gap-3 pt-2">
            <button type="submit" class="px-6 py-2.5 bg-tc-primary text-white rounded-xl text-sm font-medium hover:bg-tc-primary-hover transition-colors">Actualizar torneo</button>
            <a href="{{ route('admin.tournaments.index') }}" class="px-6 py-2.5 bg-gray-100 text-gray-600 rounded-xl text-sm font-medium hover:bg-gray-200 transition-colors">Cancelar</a>
        </div>
    </form>
</div>
@endsection
