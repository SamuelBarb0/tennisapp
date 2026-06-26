@extends('layouts.admin')
@section('title', 'Marcas · ' . $tournament->name)

@section('content')
<div class="max-w-6xl">
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('admin.tournaments.edit', $tournament) }}" class="text-gray-400 hover:text-gray-600">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
        </a>
        <div>
            <h2 class="text-xl font-bold">Marcas Q / WC / LL / Seeds</h2>
            <p class="text-xs text-gray-500 mt-0.5">{{ $tournament->name }} [{{ $tournament->type }}] · {{ $startingRound }}</p>
        </div>
    </div>

    @if(session('success'))
    <div class="mb-5 px-4 py-3 bg-green-50 border border-green-200 rounded-xl text-sm text-green-700 font-medium">{{ session('success') }}</div>
    @endif

    <div class="bg-blue-50 border border-blue-200 rounded-2xl px-5 py-4 mb-6 text-sm text-blue-900">
        <div class="font-bold mb-1">¿Cómo funciona?</div>
        <p class="text-blue-800/80">
            Asigna a cada jugador su marca: número de seed (1-32), <strong>Q</strong> (Qualifier), <strong>WC</strong> (Wild Card),
            <strong>LL</strong> (Lucky Loser), <strong>PR</strong> (Protected Ranking) o <strong>SE</strong> (Special Entry).
            La marca se aplica al iniciar la primera ronda y se propaga sola a las rondas siguientes cuando el jugador avanza.
            Deja en blanco si no aplica.
        </p>
        <p class="text-blue-800/80 mt-2">
            <strong>Bandera:</strong> si un jugador muestra la bandera equivocada (o ninguna), elige el país correcto en el
            selector junto a su nombre. El cambio corrige la bandera del jugador en <strong>todos</strong> los torneos.
            Deja en <em>“— Mantener —”</em> para no tocarla.
        </p>
    </div>

    <form method="POST" action="{{ route('admin.tournaments.badges.update', $tournament) }}">
        @csrf

        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-100">
                        <tr>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase w-12">Pos</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Jugador 1</th>
                            <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase w-32">Marca</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Jugador 2</th>
                            <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase w-32">Marca</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach($matches as $match)
                        @php
                            $tbdName = 'TBD';
                            $p1IsReal = $match->player1 && $match->player1->name !== $tbdName;
                            $p2IsReal = $match->player2 && $match->player2->name !== $tbdName;
                            $ov1 = $p1IsReal ? $overrides->get($match->player1_id) : null;
                            $ov2 = $p2IsReal ? $overrides->get($match->player2_id) : null;
                            $current1 = $ov1?->badge ?? $match->player1_seed;
                            $current2 = $ov2?->badge ?? $match->player2_seed;
                        @endphp
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-2 text-xs font-mono text-gray-400">{{ $match->bracket_position }}</td>
                            <td class="px-4 py-2">
                                @if($p1IsReal)
                                <div class="flex items-center gap-2">
                                    <img src="{{ $match->player1->flag_url }}" alt="" class="w-4 h-3 rounded-sm shrink-0">
                                    <span class="font-medium">{{ $match->player1->name }}</span>
                                </div>
                                <select name="flags[{{ $match->player1_id }}]"
                                        class="mt-1 w-44 px-2 py-1 text-xs border border-gray-200 rounded-lg bg-white focus:ring-2 focus:ring-tc-primary focus:border-transparent outline-none">
                                    <option value="">— Mantener ({{ strtoupper($match->player1->iso2) }}) —</option>
                                    @foreach($countries as $iso2 => $cname)
                                    <option value="{{ $iso2 }}" @selected($match->player1->iso2 === $iso2)>{{ $cname }}</option>
                                    @endforeach
                                </select>
                                @else
                                <span class="text-gray-300 italic text-xs">Por definir</span>
                                @endif
                            </td>
                            <td class="px-2 py-2">
                                @if($p1IsReal)
                                <input type="text"
                                       name="badges[{{ $match->player1_id }}]"
                                       value="{{ $current1 }}"
                                       maxlength="3"
                                       placeholder="—"
                                       class="w-20 px-2 py-1.5 text-center text-sm font-mono font-bold border border-gray-200 rounded-lg focus:ring-2 focus:ring-tc-primary focus:border-transparent outline-none">
                                @endif
                            </td>
                            <td class="px-4 py-2">
                                @if($p2IsReal)
                                <div class="flex items-center gap-2">
                                    <img src="{{ $match->player2->flag_url }}" alt="" class="w-4 h-3 rounded-sm shrink-0">
                                    <span class="font-medium">{{ $match->player2->name }}</span>
                                </div>
                                <select name="flags[{{ $match->player2_id }}]"
                                        class="mt-1 w-44 px-2 py-1 text-xs border border-gray-200 rounded-lg bg-white focus:ring-2 focus:ring-tc-primary focus:border-transparent outline-none">
                                    <option value="">— Mantener ({{ strtoupper($match->player2->iso2) }}) —</option>
                                    @foreach($countries as $iso2 => $cname)
                                    <option value="{{ $iso2 }}" @selected($match->player2->iso2 === $iso2)>{{ $cname }}</option>
                                    @endforeach
                                </select>
                                @else
                                <span class="text-gray-300 italic text-xs">Por definir</span>
                                @endif
                            </td>
                            <td class="px-2 py-2">
                                @if($p2IsReal)
                                <input type="text"
                                       name="badges[{{ $match->player2_id }}]"
                                       value="{{ $current2 }}"
                                       maxlength="3"
                                       placeholder="—"
                                       class="w-20 px-2 py-1.5 text-center text-sm font-mono font-bold border border-gray-200 rounded-lg focus:ring-2 focus:ring-tc-primary focus:border-transparent outline-none">
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="px-5 py-4 border-t border-gray-100 bg-gray-50 flex justify-between items-center">
                <div class="text-xs text-gray-500">
                    Valores válidos: <span class="font-mono font-bold">1-64</span> · <span class="font-mono font-bold">Q</span> · <span class="font-mono font-bold">WC</span> · <span class="font-mono font-bold">LL</span> · <span class="font-mono font-bold">PR</span> · <span class="font-mono font-bold">SE</span>
                </div>
                <button type="submit" class="px-5 py-2 text-sm font-bold text-white bg-tc-primary rounded-xl hover:bg-tc-primary/90 shadow">
                    Guardar y aplicar
                </button>
            </div>
        </div>
    </form>
</div>
@endsection
