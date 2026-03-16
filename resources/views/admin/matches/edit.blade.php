@extends('layouts.admin')
@section('title', 'Editar Partido')

@section('content')
<div class="max-w-2xl">
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('admin.matches.index') }}" class="text-gray-400 hover:text-gray-600"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg></a>
        <h2 class="text-xl font-bold">Editar Partido</h2>
    </div>

    <form action="{{ route('admin.matches.update', $match) }}" method="POST" class="bg-white rounded-2xl p-6 border border-gray-100 shadow-sm space-y-5">
        @csrf
        @method('PUT')
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Torneo</label>
            <select name="tournament_id" required class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-tc-primary focus:border-transparent outline-none">
                <option value="">Seleccionar torneo...</option>
                @foreach($tournaments as $tournament)
                    <option value="{{ $tournament->id }}" {{ old('tournament_id', $match->tournament_id) == $tournament->id ? 'selected' : '' }}>{{ $tournament->name }}</option>
                @endforeach
            </select>
            @error('tournament_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Jugador 1</label>
                <select name="player1_id" required class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-tc-primary focus:border-transparent outline-none">
                    <option value="">Seleccionar jugador...</option>
                    <optgroup label="ATP">
                        @foreach($players->where('category', 'ATP') as $player)
                            <option value="{{ $player->id }}" {{ old('player1_id', $match->player1_id) == $player->id ? 'selected' : '' }}>#{{ $player->ranking }} {{ $player->name }}</option>
                        @endforeach
                    </optgroup>
                    <optgroup label="WTA">
                        @foreach($players->where('category', 'WTA') as $player)
                            <option value="{{ $player->id }}" {{ old('player1_id', $match->player1_id) == $player->id ? 'selected' : '' }}>#{{ $player->ranking }} {{ $player->name }}</option>
                        @endforeach
                    </optgroup>
                </select>
                @error('player1_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Jugador 2</label>
                <select name="player2_id" required class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-tc-primary focus:border-transparent outline-none">
                    <option value="">Seleccionar jugador...</option>
                    <optgroup label="ATP">
                        @foreach($players->where('category', 'ATP') as $player)
                            <option value="{{ $player->id }}" {{ old('player2_id', $match->player2_id) == $player->id ? 'selected' : '' }}>#{{ $player->ranking }} {{ $player->name }}</option>
                        @endforeach
                    </optgroup>
                    <optgroup label="WTA">
                        @foreach($players->where('category', 'WTA') as $player)
                            <option value="{{ $player->id }}" {{ old('player2_id', $match->player2_id) == $player->id ? 'selected' : '' }}>#{{ $player->ranking }} {{ $player->name }}</option>
                        @endforeach
                    </optgroup>
                </select>
                @error('player2_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
        </div>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Ronda</label>
                <select name="round" required class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-tc-primary focus:border-transparent outline-none">
                    <option value="R128" {{ old('round', $match->round) === 'R128' ? 'selected' : '' }}>R128</option>
                    <option value="R64" {{ old('round', $match->round) === 'R64' ? 'selected' : '' }}>R64</option>
                    <option value="R32" {{ old('round', $match->round) === 'R32' ? 'selected' : '' }}>R32</option>
                    <option value="R16" {{ old('round', $match->round) === 'R16' ? 'selected' : '' }}>R16</option>
                    <option value="QF" {{ old('round', $match->round) === 'QF' ? 'selected' : '' }}>Cuartos de Final</option>
                    <option value="SF" {{ old('round', $match->round) === 'SF' ? 'selected' : '' }}>Semifinal</option>
                    <option value="F" {{ old('round', $match->round) === 'F' ? 'selected' : '' }}>Final</option>
                </select>
                @error('round') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Fecha y hora</label>
                <input type="datetime-local" name="scheduled_at" value="{{ old('scheduled_at', $match->scheduled_at ? $match->scheduled_at->format('Y-m-d\TH:i') : '') }}" required class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-tc-primary focus:border-transparent outline-none">
                @error('scheduled_at') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        {{-- Match result fields --}}
        <div class="border-t border-gray-100 pt-5">
            <h3 class="text-sm font-semibold text-gray-700 mb-4">Resultado del partido</h3>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Score</label>
                    <input type="text" name="score" value="{{ old('score', $match->score) }}" placeholder="6-4, 3-6, 7-5" class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-tc-primary focus:border-transparent outline-none">
                    @error('score') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Estado</label>
                    <select name="status" required class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-tc-primary focus:border-transparent outline-none">
                        <option value="pending" {{ old('status', $match->status) === 'pending' ? 'selected' : '' }}>Pendiente</option>
                        <option value="live" {{ old('status', $match->status) === 'live' ? 'selected' : '' }}>En vivo</option>
                        <option value="finished" {{ old('status', $match->status) === 'finished' ? 'selected' : '' }}>Finalizado</option>
                    </select>
                    @error('status') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
            <div class="mt-4">
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Ganador</label>
                <select name="winner_id" class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-tc-primary focus:border-transparent outline-none">
                    <option value="">Sin definir</option>
                    @if($match->player1)
                        <option value="{{ $match->player1_id }}" {{ old('winner_id', $match->winner_id) == $match->player1_id ? 'selected' : '' }}>{{ $match->player1->name }}</option>
                    @endif
                    @if($match->player2)
                        <option value="{{ $match->player2_id }}" {{ old('winner_id', $match->winner_id) == $match->player2_id ? 'selected' : '' }}>{{ $match->player2->name }}</option>
                    @endif
                </select>
                @error('winner_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="flex items-center gap-3 pt-2">
            <button type="submit" class="px-6 py-2.5 bg-tc-primary text-white rounded-xl text-sm font-medium hover:bg-tc-primary-hover transition-colors">Actualizar partido</button>
            <a href="{{ route('admin.matches.index') }}" class="px-6 py-2.5 bg-gray-100 text-gray-600 rounded-xl text-sm font-medium hover:bg-gray-200 transition-colors">Cancelar</a>
        </div>
    </form>
</div>
@endsection
