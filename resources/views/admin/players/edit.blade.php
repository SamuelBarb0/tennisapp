@extends('layouts.admin')
@section('title', 'Editar Jugador')

@section('content')
<div class="max-w-2xl">
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('admin.players.index') }}" class="text-gray-400 hover:text-gray-600"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg></a>
        <h2 class="text-xl font-bold">Editar Jugador</h2>
    </div>

    <form action="{{ route('admin.players.update', $player) }}" method="POST" enctype="multipart/form-data" class="bg-white rounded-2xl p-6 border border-gray-100 shadow-sm space-y-5">
        @csrf
        @method('PUT')
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Nombre completo</label>
            <input type="text" name="name" value="{{ old('name', $player->name) }}" required class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-tc-primary focus:border-transparent outline-none transition-all">
            @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">País</label>
                <input type="text" name="country" value="{{ old('country', $player->country) }}" required class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-tc-primary focus:border-transparent outline-none">
                @error('country') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Código de nacionalidad</label>
                <input type="text" name="nationality_code" value="{{ old('nationality_code', $player->nationality_code) }}" maxlength="3" placeholder="ESP, USA, ARG..." required class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-tc-primary focus:border-transparent outline-none uppercase">
                @error('nationality_code') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
        </div>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Ranking</label>
                <input type="number" name="ranking" value="{{ old('ranking', $player->ranking) }}" min="1" required class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-tc-primary focus:border-transparent outline-none">
                @error('ranking') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Categoría</label>
                <select name="category" required class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-tc-primary focus:border-transparent outline-none">
                    <option value="ATP" {{ old('category', $player->category) === 'ATP' ? 'selected' : '' }}>ATP</option>
                    <option value="WTA" {{ old('category', $player->category) === 'WTA' ? 'selected' : '' }}>WTA</option>
                </select>
                @error('category') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Biografía</label>
            <textarea name="bio" rows="4" class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-tc-primary focus:border-transparent outline-none resize-none">{{ old('bio', $player->bio) }}</textarea>
            @error('bio') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Foto</label>
            <input type="file" name="photo" accept="image/*" class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-xl text-sm file:mr-4 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-sm file:bg-tc-primary file:text-white">
            @if($player->photo)
                <div class="mt-2 flex items-center gap-2">
                    <img src="{{ asset('storage/' . $player->photo) }}" alt="{{ $player->name }}" class="w-12 h-12 rounded-full object-cover">
                    <span class="text-xs text-gray-400">Foto actual</span>
                </div>
            @endif
            @error('photo') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>
        <div class="flex items-center gap-3 pt-2">
            <button type="submit" class="px-6 py-2.5 bg-tc-primary text-white rounded-xl text-sm font-medium hover:bg-tc-primary-hover transition-colors">Actualizar jugador</button>
            <a href="{{ route('admin.players.index') }}" class="px-6 py-2.5 bg-gray-100 text-gray-600 rounded-xl text-sm font-medium hover:bg-gray-200 transition-colors">Cancelar</a>
        </div>
    </form>
</div>
@endsection
