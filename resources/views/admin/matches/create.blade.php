@extends('layouts.admin')
@section('title', 'Crear Partido')

@section('content')
<div class="max-w-2xl">
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('admin.matches.index') }}" class="text-gray-400 hover:text-gray-600"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg></a>
        <h2 class="text-xl font-bold">Crear Partido</h2>
    </div>

    <form action="{{ route('admin.matches.store') }}" method="POST" class="bg-white rounded-2xl p-6 border border-gray-100 shadow-sm space-y-5">
        @csrf
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Torneo</label>
            <select name="tournament_id" required class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-[#0071E3] focus:border-transparent outline-none">
                <option value="">Seleccionar torneo...</option>
                @foreach($tournaments as $tournament)
                    <option value="{{ $tournament->id }}" {{ old('tournament_id') == $tournament->id ? 'selected' : '' }}>{{ $tournament->name }}</option>
                @endforeach
            </select>
            @error('tournament_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Jugador 1</label>
                <select name="player1_id" required class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-[#0071E3] focus:border-transparent outline-none">
                    <option value="">Seleccionar jugador...</option>
                    <optgroup label="ATP">
                        @foreach($players->where('category', 'ATP') as $player)
                            <option value="{{ $player->id }}" {{ old('player1_id') == $player->id ? 'selected' : '' }}>#{{ $player->ranking }} {{ $player->name }}</option>
                        @endforeach
                    </optgroup>
                    <optgroup label="WTA">
                        @foreach($players->where('category', 'WTA') as $player)
                            <option value="{{ $player->id }}" {{ old('player1_id') == $player->id ? 'selected' : '' }}>#{{ $player->ranking }} {{ $player->name }}</option>
                        @endforeach
                    </optgroup>
                </select>
                @error('player1_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Jugador 2</label>
                <select name="player2_id" required class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-[#0071E3] focus:border-transparent outline-none">
                    <option value="">Seleccionar jugador...</option>
                    <optgroup label="ATP">
                        @foreach($players->where('category', 'ATP') as $player)
                            <option value="{{ $player->id }}" {{ old('player2_id') == $player->id ? 'selected' : '' }}>#{{ $player->ranking }} {{ $player->name }}</option>
                        @endforeach
                    </optgroup>
                    <optgroup label="WTA">
                        @foreach($players->where('category', 'WTA') as $player)
                            <option value="{{ $player->id }}" {{ old('player2_id') == $player->id ? 'selected' : '' }}>#{{ $player->ranking }} {{ $player->name }}</option>
                        @endforeach
                    </optgroup>
                </select>
                @error('player2_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
        </div>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Ronda</label>
                <select name="round" required class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-[#0071E3] focus:border-transparent outline-none">
                    <option value="R128" {{ old('round') === 'R128' ? 'selected' : '' }}>R128</option>
                    <option value="R64" {{ old('round') === 'R64' ? 'selected' : '' }}>R64</option>
                    <option value="R32" {{ old('round') === 'R32' ? 'selected' : '' }}>R32</option>
                    <option value="R16" {{ old('round') === 'R16' ? 'selected' : '' }}>R16</option>
                    <option value="QF" {{ old('round') === 'QF' ? 'selected' : '' }}>Cuartos de Final</option>
                    <option value="SF" {{ old('round') === 'SF' ? 'selected' : '' }}>Semifinal</option>
                    <option value="F" {{ old('round') === 'F' ? 'selected' : '' }}>Final</option>
                </select>
                @error('round') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Fecha y hora</label>
                <input type="datetime-local" name="scheduled_at" value="{{ old('scheduled_at') }}" required class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-[#0071E3] focus:border-transparent outline-none">
                @error('scheduled_at') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
        </div>
        <div class="flex items-center gap-3 pt-2">
            <button type="submit" class="px-6 py-2.5 bg-[#0071E3] text-white rounded-xl text-sm font-medium hover:bg-[#0062CC] transition-colors">Crear partido</button>
            <a href="{{ route('admin.matches.index') }}" class="px-6 py-2.5 bg-gray-100 text-gray-600 rounded-xl text-sm font-medium hover:bg-gray-200 transition-colors">Cancelar</a>
        </div>
    </form>
</div>
@endsection
