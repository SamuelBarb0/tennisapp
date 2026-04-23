@extends('layouts.admin')
@section('title', 'Desempates · ' . $tournament->name)

@section('content')
<div class="max-w-5xl">
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('admin.tournaments.edit', $tournament) }}" class="text-gray-400 hover:text-gray-600">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
        </a>
        <div>
            <h2 class="text-xl font-bold">Ranking & Desempates</h2>
            <p class="text-xs text-gray-500 mt-0.5">{{ $tournament->name }}</p>
        </div>
    </div>

    @if(session('success'))
    <div class="mb-5 px-4 py-3 bg-green-50 border border-green-200 rounded-xl text-sm text-green-700 font-medium">{{ session('success') }}</div>
    @endif
    @if(session('error'))
    <div class="mb-5 px-4 py-3 bg-red-50 border border-red-200 rounded-xl text-sm text-red-700 font-medium">{{ session('error') }}</div>
    @endif

    @if($tiebreaksLocked)
    <div class="mb-6 px-5 py-4 bg-tc-primary text-white rounded-2xl flex items-center gap-3">
        <svg class="w-5 h-5 text-tc-accent shrink-0" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
        </svg>
        <div class="flex-1">
            <div class="text-sm font-bold">Desempate bloqueado</div>
            <div class="text-xs text-white/70 mt-0.5">El orden ya fue guardado y no puede modificarse.</div>
        </div>
    </div>
    @endif

    {{-- Real final score context --}}
    @if($finalMatch && $finalMatch->status === 'finished')
    <div class="bg-tc-primary text-white rounded-2xl p-5 mb-6 flex items-center gap-4">
        <svg class="w-8 h-8 text-tc-accent" fill="currentColor" viewBox="0 0 24 24">
            <path d="M5 3h14c.6 0 1 .4 1 1v2c0 3.3-2.7 6-6 6h-.7c-.4 1.2-1.2 2.2-2.3 2.8V18h3c.6 0 1 .4 1 1v2c0 .6-.4 1-1 1H9c-.6 0-1-.4-1-1v-2c0-.6.4-1 1-1h3v-3.2c-1.1-.6-1.9-1.6-2.3-2.8H9c-3.3 0-6-2.7-6-6V4c0-.6.4-1 1-1z"/>
        </svg>
        <div class="flex-1">
            <div class="text-[10px] font-black uppercase tracking-widest text-white/60 mb-1">Marcador real de la final</div>
            <div class="text-lg font-black tracking-wide font-mono">{{ $finalMatch->score ?? '—' }}</div>
        </div>
        @if($finalMatch->winner)
        <div class="text-right">
            <div class="text-[9px] uppercase tracking-widest text-white/50">Campeón</div>
            <div class="font-bold">{{ strtoupper($finalMatch->winner->name) }}</div>
        </div>
        @endif
    </div>
    @endif

    {{-- Summary banner --}}
    @if(!$hasAnyTie && !$tiebreaksLocked)
    <div class="mb-6 px-5 py-4 bg-blue-50 border border-blue-200 rounded-2xl flex items-start gap-3">
        <svg class="w-5 h-5 text-blue-500 shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>
        <div class="flex-1">
            <div class="text-sm font-bold text-blue-800">No hay empates pendientes</div>
            <div class="text-xs text-blue-700 mt-0.5">Puedes asignar orden manual desde ya si quieres adelantarte a posibles empates futuros.</div>
        </div>
    </div>
    @endif

    @if($tieGroups->isEmpty())
    <div class="bg-white rounded-2xl p-10 border border-gray-100 text-center">
        <svg class="w-14 h-14 mx-auto text-gray-200 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <p class="text-sm font-bold text-gray-700">Sin ranking aún</p>
        <p class="text-xs text-gray-400 mt-1">Todavía no hay usuarios con puntos en este torneo.</p>
    </div>
    @else
    <form method="POST" action="{{ route('admin.tournaments.tiebreaks.save', $tournament) }}" class="space-y-5" @if($tiebreaksLocked) onsubmit="return false" @endif>
        @csrf

        @foreach($tieGroups as $points => $users)
        @php $isTie = $users->count() >= 2; @endphp
        <div class="bg-white rounded-2xl overflow-hidden {{ $isTie ? 'border-2 border-amber-300' : 'border border-gray-200' }}">
            <div class="px-5 py-3 flex items-center justify-between border-b {{ $isTie ? 'bg-amber-50 border-amber-200' : 'bg-gray-50 border-gray-100' }}">
                <div class="flex items-center gap-2">
                    @if($isTie)
                    <svg class="w-4 h-4 text-amber-600" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                    </svg>
                    <span class="text-sm font-bold text-amber-800">EMPATE · {{ $users->count() }} usuarios</span>
                    @else
                    <svg class="w-4 h-4 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    <span class="text-sm font-bold text-gray-600">Sin empate</span>
                    @endif
                </div>
                <span class="text-xs font-mono font-black {{ $isTie ? 'text-amber-700 bg-white border-amber-300' : 'text-gray-600 bg-white border-gray-200' }} px-3 py-1 rounded-full border">
                    {{ number_format($points) }} pts
                </span>
            </div>
            <div class="divide-y divide-gray-100">
                @foreach($users as $idx => $user)
                <div class="px-5 py-4 flex items-center gap-4">
                    <div class="shrink-0">
                        <label class="block text-[9px] font-black text-gray-400 uppercase tracking-widest mb-1">Orden</label>
                        @if($tiebreaksLocked)
                        <div class="w-14 h-10 flex items-center justify-center bg-tc-primary text-tc-accent font-black text-lg rounded-lg">
                            {{ $user->manual_rank ?? '—' }}
                        </div>
                        @else
                        <input type="number" name="order[{{ $user->id }}]"
                               value="{{ $user->manual_rank ?? ($idx + 1) }}"
                               min="1"
                               class="w-14 px-2 py-1.5 text-center font-black text-lg border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-tc-primary focus:border-tc-primary">
                        @endif
                    </div>

                    <div class="flex-1 min-w-0">
                        <div class="font-bold text-gray-800 truncate">{{ $user->name }}</div>
                        <div class="text-xs text-gray-500 mt-0.5">{{ $user->correct_predictions }} aciertos</div>
                    </div>

                    <div class="shrink-0 text-right">
                        <div class="text-[9px] font-black text-gray-400 uppercase tracking-widest mb-1">Pronóstico final</div>
                        @if($user->final_score_prediction)
                        <div class="inline-block px-3 py-1 bg-tc-primary text-tc-accent font-mono font-black text-sm rounded-lg">
                            {{ $user->final_score_prediction }}
                        </div>
                        @else
                        <div class="text-xs text-gray-300 italic">Sin pronóstico</div>
                        @endif
                    </div>
                </div>
                @endforeach
            </div>
            @if($isTie)
            <div class="bg-gray-50 px-5 py-2.5 text-[11px] text-gray-500">
                @if($tiebreaksLocked)
                Orden final. Esta decisión ya fue guardada y no se puede modificar.
                @else
                Asigna el orden manualmente. 1 queda primero; 2 segundo; y así sucesivamente.
                @endif
            </div>
            @endif
        </div>
        @endforeach

        @if(!$tiebreaksLocked)
        <div class="flex justify-end gap-3 sticky bottom-4">
            <a href="{{ route('admin.tournaments.edit', $tournament) }}"
               class="px-5 py-2.5 border border-gray-200 rounded-xl text-sm font-semibold text-gray-600 hover:bg-gray-50">
                Cancelar
            </a>
            <button type="submit"
                    onclick="return confirm('¿Confirmas este orden? Una vez guardado NO podrás modificarlo.')"
                    class="px-6 py-2.5 bg-tc-primary text-white font-bold rounded-xl text-sm hover:bg-tc-primary-hover shadow-sm">
                Guardar orden
            </button>
        </div>
        @endif
    </form>
    @endif
</div>
@endsection
