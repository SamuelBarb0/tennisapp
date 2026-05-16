@extends('layouts.app')
@section('title', 'Calendario de Torneos')

@section('content')

@php
    $monthNamesEs = [
        '01' => 'Enero', '02' => 'Febrero', '03' => 'Marzo', '04' => 'Abril',
        '05' => 'Mayo', '06' => 'Junio', '07' => 'Julio', '08' => 'Agosto',
        '09' => 'Septiembre', '10' => 'Octubre', '11' => 'Noviembre', '12' => 'Diciembre',
    ];
    $surfaces = $tournaments->pluck('surface')->filter()->unique()->values();
@endphp

{{-- Hero --}}
<section class="bg-gradient-to-br from-tc-primary via-tc-primary-hover to-tc-primary-dark text-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 md:py-14">
        <div class="flex items-center gap-2 text-tc-accent text-xs font-bold uppercase tracking-widest mb-3">
            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"/></svg>
            Calendario {{ now()->year }}
        </div>
        <h1 class="text-3xl md:text-5xl font-black tracking-tight mb-2">Calendario de Torneos</h1>
        <p class="text-blue-100 text-base md:text-lg max-w-2xl">Explora los torneos del año por mes. Haz clic en cualquiera para ver su bracket y predecir.</p>
    </div>
</section>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    {{-- Filters --}}
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 mb-8">
        <form method="GET" action="{{ route('tournaments.index') }}" class="grid grid-cols-1 md:grid-cols-4 gap-3">
            <div>
                <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1">Buscar</label>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Nombre del torneo..."
                       class="w-full px-3 py-2 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-tc-primary focus:border-transparent">
            </div>
            <div>
                <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1">Tipo</label>
                <select name="type" class="w-full px-3 py-2 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-tc-primary focus:border-transparent">
                    <option value="">Todos</option>
                    <option value="GrandSlam" {{ request('type') === 'GrandSlam' ? 'selected' : '' }}>Grand Slam</option>
                    <option value="ATP" {{ request('type') === 'ATP' ? 'selected' : '' }}>ATP</option>
                    <option value="WTA" {{ request('type') === 'WTA' ? 'selected' : '' }}>WTA</option>
                </select>
            </div>
            <div>
                <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1">Superficie</label>
                <select name="surface" class="w-full px-3 py-2 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-tc-primary focus:border-transparent">
                    <option value="">Todas</option>
                    @foreach($surfaces as $s)
                    <option value="{{ $s }}" {{ request('surface') === $s ? 'selected' : '' }}>{{ $s }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="flex-1 px-5 py-2 bg-tc-primary text-white rounded-xl text-sm font-bold hover:bg-tc-primary-hover transition">Filtrar</button>
                @if(request()->anyFilled(['search', 'type', 'surface']))
                <a href="{{ route('tournaments.index') }}" class="px-3 py-2 border border-gray-200 rounded-xl text-sm text-gray-600 hover:bg-gray-50 transition">✕</a>
                @endif
            </div>
        </form>
    </div>

    {{-- Calendar by month --}}
    @forelse($tournamentsByMonth as $monthKey => $monthTournaments)
    @php
        [$year, $month] = explode('-', $monthKey);
        $isCurrentMonth = now()->format('Y-m') === $monthKey;
    @endphp
    <section class="mb-10">
        <div class="flex items-center gap-3 mb-4 sticky top-0 bg-gray-50 -mx-4 px-4 py-2 z-10 backdrop-blur" style="background: rgba(249, 250, 251, 0.95);">
            <div class="w-1 h-8 bg-tc-accent rounded-full"></div>
            <h2 class="text-xl md:text-2xl font-black text-tc-primary uppercase tracking-tight">{{ $monthNamesEs[$month] }} {{ $year }}</h2>
            @if($isCurrentMonth)
            <span class="px-2 py-0.5 bg-tc-accent/30 text-tc-primary text-[10px] font-black uppercase tracking-widest rounded-full">Actual</span>
            @endif
            <span class="text-xs text-gray-400 ml-auto">{{ $monthTournaments->count() }} {{ Str::plural('torneo', $monthTournaments->count()) }}</span>
        </div>

        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden divide-y divide-gray-100">
            @foreach($monthTournaments as $tournament)
            @php $state = $tournament->bracket_state; @endphp
            <a href="{{ route('tournaments.show', $tournament) }}" class="flex items-stretch hover:bg-gray-50 transition group">
                {{-- Date column --}}
                <div class="w-20 sm:w-28 shrink-0 bg-gradient-to-br {{ $tournament->type === 'GrandSlam' ? 'from-yellow-400 to-orange-500' : (str_starts_with($tournament->type, 'ATP') ? 'from-tc-primary to-blue-700' : 'from-purple-500 to-pink-500') }} text-white flex flex-col items-center justify-center p-3">
                    @if($tournament->start_date)
                        <div class="text-[9px] font-bold uppercase tracking-widest opacity-80">{{ $monthNamesEs[$tournament->start_date->format('m')] ?? '' }}</div>
                        <div class="text-2xl sm:text-3xl font-black leading-none mt-0.5">{{ $tournament->start_date->format('d') }}</div>
                        <div class="text-[9px] opacity-70 mt-1">→ {{ $tournament->end_date?->format('d') ?? '?' }}</div>
                    @else
                        <div class="text-[9px] font-bold uppercase tracking-widest opacity-80">TBD</div>
                        <div class="text-base font-bold leading-none mt-0.5">--</div>
                    @endif
                </div>

                {{-- Tournament image (optional) --}}
                @if($tournament->image)
                <div class="hidden sm:block w-20 shrink-0 bg-gray-100 relative overflow-hidden">
                    <img src="{{ asset('storage/' . $tournament->image) }}" alt="" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                </div>
                @endif

                {{-- Info --}}
                <div class="flex-1 min-w-0 p-4 sm:p-5 flex items-center gap-4">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap mb-1">
                            <h3 class="font-bold text-base sm:text-lg group-hover:text-tc-primary transition-colors truncate">{{ $tournament->name }}</h3>
                            <span class="px-2 py-0.5 text-[9px] font-bold uppercase tracking-widest rounded-full {{ $tournament->type === 'GrandSlam' ? 'bg-yellow-100 text-yellow-700' : (str_starts_with($tournament->type, 'ATP') ? 'bg-blue-100 text-blue-700' : 'bg-purple-100 text-purple-700') }}">{{ $tournament->type }}</span>
                        </div>
                        <div class="text-xs text-gray-500 flex items-center gap-3 flex-wrap">
                            <span class="flex items-center gap-1">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                {{ $tournament->city }}, {{ $tournament->country }}
                            </span>
                            @if($tournament->surface)
                            <span class="px-2 py-0.5 bg-gray-100 rounded text-[10px] font-semibold text-gray-600">{{ $tournament->surface }}</span>
                            @endif
                            @if($tournament->requiresPayment())
                            <span class="px-2 py-0.5 bg-yellow-100 text-yellow-800 rounded text-[10px] font-bold">${{ number_format($tournament->price, 0, ',', '.') }} COP</span>
                            @else
                            <span class="px-2 py-0.5 bg-green-100 text-green-700 rounded text-[10px] font-bold">GRATIS</span>
                            @endif
                        </div>
                    </div>
                    <div class="hidden md:flex shrink-0 items-center gap-2">
                        @if($tournament->status === 'finished')
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-gray-200 text-gray-600 text-[11px] font-bold rounded-full">
                                FINALIZADO
                            </span>
                        @elseif($state === 'live')
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-red-100 text-red-700 text-[11px] font-bold rounded-full">
                                <span class="w-1.5 h-1.5 bg-red-500 rounded-full animate-pulse"></span>EN VIVO
                            </span>
                        @elseif($state === 'open')
                            <span class="px-3 py-1 bg-tc-accent text-tc-primary-dark text-[11px] font-bold rounded-full">PREDECIR</span>
                        @else
                            <span class="px-3 py-1 bg-gray-100 text-gray-500 text-[11px] font-bold rounded-full">PRÓXIMAMENTE</span>
                        @endif
                    </div>
                    <svg class="w-5 h-5 text-gray-300 group-hover:text-tc-primary transition shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </div>
            </a>
            @endforeach
        </div>
    </section>
    @empty
    <div class="text-center py-20">
        <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5"/></svg>
        <p class="text-gray-500 font-semibold">No hay torneos que coincidan con tu búsqueda</p>
        <a href="{{ route('tournaments.index') }}" class="text-sm text-tc-primary hover:underline mt-2 inline-block">Ver todos los torneos</a>
    </div>
    @endforelse
</div>
@endsection
