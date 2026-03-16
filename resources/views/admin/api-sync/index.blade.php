@extends('layouts.admin')

@section('title', 'Sincronización API')

@section('content')
<div class="max-w-5xl mx-auto space-y-6">
    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">Sincronización API Tennis</h2>
            <p class="text-sm text-gray-500 mt-1">Sincroniza torneos, jugadores y partidos desde la API de Tennis en tiempo real.</p>
        </div>
        <form method="POST" action="{{ route('admin.api-sync.all') }}">
            @csrf
            <button type="submit" class="bg-tc-primary text-white px-6 py-2.5 rounded-xl text-sm font-semibold hover:bg-[#16324d] transition-colors flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                Sincronizar Todo
            </button>
        </form>
    </div>

    {{-- Sync Cards Grid --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        {{-- Tournaments --}}
        <div class="bg-white rounded-2xl border border-gray-200 p-6">
            <div class="flex items-start justify-between mb-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-blue-100 rounded-xl flex items-center justify-center">
                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                    </div>
                    <div>
                        <h3 class="font-semibold text-gray-900">Torneos</h3>
                        <p class="text-xs text-gray-500">ATP Singles & WTA Singles</p>
                    </div>
                </div>
            </div>
            <p class="text-sm text-gray-600 mb-4">Sincroniza la lista completa de torneos ATP y WTA con superficie y tipo.</p>
            @if($lastSync['tournaments'])
                <p class="text-xs text-gray-400 mb-4">Última sync: {{ $lastSync['tournaments'] }}</p>
            @endif
            <form method="POST" action="{{ route('admin.api-sync.tournaments') }}">
                @csrf
                <button type="submit" class="w-full bg-blue-50 text-blue-700 px-4 py-2.5 rounded-xl text-sm font-medium hover:bg-blue-100 transition-colors">
                    Sincronizar Torneos
                </button>
            </form>
        </div>

        {{-- Players --}}
        <div class="bg-white rounded-2xl border border-gray-200 p-6">
            <div class="flex items-start justify-between mb-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-green-100 rounded-xl flex items-center justify-center">
                        <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    </div>
                    <div>
                        <h3 class="font-semibold text-gray-900">Jugadores & Rankings</h3>
                        <p class="text-xs text-gray-500">Rankings ATP y WTA</p>
                    </div>
                </div>
            </div>
            <p class="text-sm text-gray-600 mb-4">Importa jugadores con su ranking actual, país y categoría desde los standings oficiales.</p>
            @if($lastSync['players'])
                <p class="text-xs text-gray-400 mb-4">Última sync: {{ $lastSync['players'] }}</p>
            @endif
            <form method="POST" action="{{ route('admin.api-sync.players') }}">
                @csrf
                <button type="submit" class="w-full bg-green-50 text-green-700 px-4 py-2.5 rounded-xl text-sm font-medium hover:bg-green-100 transition-colors">
                    Sincronizar Jugadores
                </button>
            </form>
        </div>

        {{-- Fixtures --}}
        <div class="bg-white rounded-2xl border border-gray-200 p-6">
            <div class="flex items-start justify-between mb-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-purple-100 rounded-xl flex items-center justify-center">
                        <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    </div>
                    <div>
                        <h3 class="font-semibold text-gray-900">Partidos / Fixtures</h3>
                        <p class="text-xs text-gray-500">Programación y resultados</p>
                    </div>
                </div>
            </div>
            <p class="text-sm text-gray-600 mb-4">Sincroniza partidos por rango de fechas. Incluye resultados y evalúa predicciones automáticamente.</p>
            @if($lastSync['fixtures'])
                <p class="text-xs text-gray-400 mb-4">Última sync: {{ $lastSync['fixtures'] }}</p>
            @endif
            <form method="POST" action="{{ route('admin.api-sync.fixtures') }}" class="space-y-3">
                @csrf
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-xs text-gray-500 block mb-1">Desde</label>
                        <input type="date" name="date_from" value="{{ now()->format('Y-m-d') }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    </div>
                    <div>
                        <label class="text-xs text-gray-500 block mb-1">Hasta</label>
                        <input type="date" name="date_to" value="{{ now()->addDays(7)->format('Y-m-d') }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    </div>
                </div>
                <button type="submit" class="w-full bg-purple-50 text-purple-700 px-4 py-2.5 rounded-xl text-sm font-medium hover:bg-purple-100 transition-colors">
                    Sincronizar Partidos
                </button>
            </form>
        </div>

        {{-- Livescores --}}
        <div class="bg-white rounded-2xl border border-gray-200 p-6">
            <div class="flex items-start justify-between mb-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-red-100 rounded-xl flex items-center justify-center">
                        <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    </div>
                    <div>
                        <h3 class="font-semibold text-gray-900">Livescores</h3>
                        <p class="text-xs text-gray-500">Marcadores en tiempo real</p>
                    </div>
                </div>
            </div>
            <p class="text-sm text-gray-600 mb-4">Actualiza marcadores de partidos en vivo. Detecta partidos finalizados y evalúa predicciones.</p>
            @if($lastSync['livescores'])
                <p class="text-xs text-gray-400 mb-4">Última sync: {{ $lastSync['livescores'] }}</p>
            @endif
            <form method="POST" action="{{ route('admin.api-sync.livescores') }}">
                @csrf
                <button type="submit" class="w-full bg-red-50 text-red-700 px-4 py-2.5 rounded-xl text-sm font-medium hover:bg-red-100 transition-colors">
                    Sincronizar Livescores
                </button>
            </form>
        </div>
    </div>

    {{-- Info Card --}}
    <div class="bg-amber-50 border border-amber-200 rounded-2xl p-5">
        <div class="flex gap-3">
            <svg class="w-5 h-5 text-amber-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <div class="text-sm text-amber-800">
                <p class="font-medium mb-1">Sincronización automática</p>
                <p>Los livescores se sincronizan automáticamente cada 3 minutos durante horarios de partidos (10:00-23:59). Los fixtures se actualizan diariamente a las 6:00 AM y los rankings semanalmente los lunes.</p>
            </div>
        </div>
    </div>
</div>
@endsection
