@extends('layouts.app')
@section('title', 'Torneos')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
    <div class="mb-8">
        <h1 class="text-3xl md:text-4xl font-bold mb-2 fade-in">Torneos</h1>
        <p class="text-gray-500">Explora los torneos disponibles y haz tus pronósticos</p>
    </div>

    {{-- Filters --}}
    <div class="flex flex-wrap gap-2 mb-8" x-data="{ active: '{{ request('type', '') }}' }">
        <a href="{{ route('tournaments.index') }}"
           class="px-5 py-2 rounded-full text-sm font-medium transition-all {{ !request('type') ? 'bg-tc-primary text-white shadow-sm' : 'bg-white text-gray-600 hover:bg-gray-100 border border-gray-200' }}">
            Todos
        </a>
        <a href="{{ route('tournaments.index', ['type' => 'GrandSlam']) }}"
           class="px-5 py-2 rounded-full text-sm font-medium transition-all {{ request('type') === 'GrandSlam' ? 'bg-yellow-500 text-white shadow-sm' : 'bg-white text-gray-600 hover:bg-gray-100 border border-gray-200' }}">
            Grand Slams
        </a>
        <a href="{{ route('tournaments.index', ['type' => 'ATP']) }}"
           class="px-5 py-2 rounded-full text-sm font-medium transition-all {{ request('type') === 'ATP' ? 'bg-tc-primary text-white shadow-sm' : 'bg-white text-gray-600 hover:bg-gray-100 border border-gray-200' }}">
            ATP Masters 1000
        </a>
        <a href="{{ route('tournaments.index', ['type' => 'WTA']) }}"
           class="px-5 py-2 rounded-full text-sm font-medium transition-all {{ request('type') === 'WTA' ? 'bg-purple-500 text-white shadow-sm' : 'bg-white text-gray-600 hover:bg-gray-100 border border-gray-200' }}">
            WTA 1000
        </a>
    </div>

    {{-- Tournament Grid --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse($tournaments as $tournament)
        <a href="{{ route('tournaments.show', $tournament) }}" class="group block bg-white rounded-3xl overflow-hidden shadow-sm hover-lift border border-gray-100 fade-in" style="animation-delay: {{ $loop->index * 0.05 }}s">
            <div class="h-44 bg-gradient-to-br {{ $tournament->type === 'GrandSlam' ? 'from-yellow-400 to-orange-500' : ($tournament->type === 'ATP' ? 'from-tc-primary to-blue-700' : 'from-purple-500 to-pink-500') }} flex items-center justify-center relative">
                <span class="text-white/15 text-9xl font-black select-none">{{ substr($tournament->name, 0, 1) }}</span>
                @if($tournament->is_premium)
                    <span class="absolute top-4 right-4 px-3 py-1 bg-yellow-400 text-yellow-900 text-xs font-bold rounded-full">PREMIUM</span>
                @endif
                @php $status = $tournament->status; @endphp
                <span class="absolute top-4 left-4 px-3 py-1 {{ $status === 'live' ? 'bg-red-500 text-white' : ($status === 'upcoming' ? 'bg-white/90 text-gray-700' : 'bg-gray-700 text-white') }} text-xs font-medium rounded-full">
                    {{ $status === 'live' ? 'En curso' : ($status === 'upcoming' ? 'Próximamente' : 'Finalizado') }}
                </span>
            </div>
            <div class="p-5">
                <h3 class="font-semibold text-lg mb-2 group-hover:text-tc-primary transition-colors">{{ $tournament->name }}</h3>
                <p class="text-sm text-gray-500 flex items-center gap-1.5 mb-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    {{ $tournament->city }}, {{ $tournament->country }}
                </p>
                <div class="flex items-center justify-between mt-3 pt-3 border-t border-gray-100">
                    <span class="text-xs text-gray-400">
                        <svg class="w-3.5 h-3.5 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        {{ $tournament->start_date->format('d M') }} - {{ $tournament->end_date->format('d M') }}
                    </span>
                    <div class="flex items-center gap-2">
                        @if($tournament->surface)
                            <span class="text-xs font-medium px-2 py-1 bg-gray-100 rounded-lg">{{ $tournament->surface }}</span>
                        @endif
                        <span class="text-xs font-medium px-2 py-1 bg-gray-100 rounded-lg">{{ $tournament->type }}</span>
                    </div>
                </div>
            </div>
        </a>
        @empty
        <div class="col-span-full text-center py-16">
            <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5"/></svg>
            <p class="text-gray-500">No hay torneos disponibles en este momento.</p>
        </div>
        @endforelse
    </div>

    <div class="mt-8">
        {{ $tournaments->links() }}
    </div>
</div>
@endsection
