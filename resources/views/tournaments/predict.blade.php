@extends('layouts.app')
@section('title', 'Pronósticos - ' . $tournament->name)

@section('content')
<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
    <div class="mb-8">
        <a href="{{ route('tournaments.show', $tournament) }}" class="text-tc-primary text-sm font-medium hover:underline">&larr; Volver al torneo</a>
        <h1 class="text-3xl font-bold mt-3 mb-2">Hacer pronósticos</h1>
        <p class="text-gray-500">{{ $tournament->name }} &middot; Selecciona al ganador de cada partido</p>
    </div>

    @if($matches->count() > 0)
    <div class="space-y-4">
        @foreach($matches as $match)
        <div class="bg-white rounded-3xl p-6 border border-gray-100 shadow-sm fade-in" style="animation-delay: {{ $loop->index * 0.05 }}s"
             x-data="{ selected: null }">
            <div class="text-xs text-gray-400 mb-4 flex items-center justify-between">
                <span>{{ $match->round }} &middot; {{ $match->scheduled_at->format('d M, H:i') }}</span>
                <span class="px-2 py-0.5 bg-gray-100 text-gray-500 rounded-full">Pendiente</span>
            </div>
            <div class="grid grid-cols-2 gap-4">
                {{-- Player 1 --}}
                <form action="{{ route('predictions.store') }}" method="POST">
                    @csrf
                    <input type="hidden" name="match_id" value="{{ $match->id }}">
                    <input type="hidden" name="predicted_winner_id" value="{{ $match->player1_id }}">
                    <button type="submit"
                            class="w-full p-4 rounded-2xl border-2 transition-all text-center hover:border-tc-primary hover:bg-blue-50 border-gray-200 group"
                            @click="selected = {{ $match->player1_id }}">
                        <div class="w-14 h-14 rounded-full overflow-hidden mx-auto mb-3 ring-2 ring-tc-primary/20 group-hover:ring-tc-primary transition-all">
                            <img src="{{ $match->player1->flag_url }}" alt="{{ $match->player1->nationality_code }}" class="w-full h-full object-cover">
                        </div>
                        <div class="font-semibold text-sm">{{ $match->player1->name }}</div>
                        <div class="text-xs text-gray-400 mt-1">#{{ $match->player1->ranking }} &middot; {{ $match->player1->country }}</div>
                    </button>
                </form>

                {{-- Player 2 --}}
                <form action="{{ route('predictions.store') }}" method="POST">
                    @csrf
                    <input type="hidden" name="match_id" value="{{ $match->id }}">
                    <input type="hidden" name="predicted_winner_id" value="{{ $match->player2_id }}">
                    <button type="submit"
                            class="w-full p-4 rounded-2xl border-2 transition-all text-center hover:border-green-500 hover:bg-green-50 border-gray-200 group">
                        <div class="w-14 h-14 rounded-full overflow-hidden mx-auto mb-3 ring-2 ring-green-200 group-hover:ring-green-500 transition-all">
                            <img src="{{ $match->player2->flag_url }}" alt="{{ $match->player2->nationality_code }}" class="w-full h-full object-cover">
                        </div>
                        <div class="font-semibold text-sm">{{ $match->player2->name }}</div>
                        <div class="text-xs text-gray-400 mt-1">#{{ $match->player2->ranking }} &middot; {{ $match->player2->country }}</div>
                    </button>
                </form>
            </div>
        </div>
        @endforeach
    </div>
    @else
    <div class="text-center py-16 bg-white rounded-3xl">
        <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
        <p class="text-gray-500">No hay partidos pendientes para pronosticar en este torneo.</p>
    </div>
    @endif
</div>
@endsection
