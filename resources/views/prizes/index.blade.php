@extends('layouts.app')
@section('title', 'Premios')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
    <div class="text-center mb-10 fade-in">
        <h1 class="text-3xl md:text-4xl font-bold mb-2">Premios</h1>
        <p class="text-gray-500">Canjea tus puntos por premios increíbles</p>
        @auth
        <div class="mt-4 inline-flex items-center gap-2 px-5 py-2.5 bg-yellow-50 rounded-2xl">
            <svg class="w-5 h-5 text-yellow-500" fill="currentColor" viewBox="0 0 20 20"><path d="M10 15l-5.878 3.09 1.123-6.545L.489 6.91l6.572-.955L10 0l2.939 5.955 6.572.955-4.756 4.635 1.123 6.545z"/></svg>
            <span class="font-bold text-yellow-700">{{ number_format(auth()->user()->points) }}</span>
            <span class="text-sm text-yellow-600">puntos disponibles</span>
        </div>
        @endauth
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
        @forelse($prizes as $prize)
        <div class="bg-white rounded-3xl overflow-hidden shadow-sm hover-lift border border-gray-100 fade-in flex flex-col" style="animation-delay: {{ $loop->index * 0.05 }}s">
            <div class="h-48 bg-gradient-to-br from-gray-100 to-gray-200 flex items-center justify-center">
                @if($prize->image)
                    <img src="{{ Storage::url($prize->image) }}" alt="{{ $prize->name }}" class="w-full h-full object-cover">
                @else
                    <svg class="w-16 h-16 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7"/></svg>
                @endif
            </div>
            <div class="p-5 flex-1 flex flex-col">
                <h3 class="font-semibold text-base mb-2">{{ $prize->name }}</h3>
                <p class="text-sm text-gray-500 mb-4 flex-1">{{ Str::limit($prize->description, 80) }}</p>
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-yellow-500" fill="currentColor" viewBox="0 0 20 20"><path d="M10 15l-5.878 3.09 1.123-6.545L.489 6.91l6.572-.955L10 0l2.939 5.955 6.572.955-4.756 4.635 1.123 6.545z"/></svg>
                        <span class="font-bold text-sm">{{ number_format($prize->points_required) }}</span>
                        <span class="text-xs text-gray-400">puntos</span>
                    </div>
                    <span class="text-xs text-gray-400">{{ $prize->stock }} disponibles</span>
                </div>
                @auth
                    @if(auth()->user()->points >= $prize->points_required)
                        <form action="{{ route('prizes.redeem', $prize) }}" method="POST"
                              x-data="{ confirming: false }">
                            @csrf
                            <button type="button" x-show="!confirming" @click="confirming = true"
                                    class="w-full py-2.5 bg-tc-primary text-white rounded-2xl text-sm font-medium hover:bg-tc-primary-hover transition-all">
                                Canjear
                            </button>
                            <div x-show="confirming" x-cloak class="flex gap-2">
                                <button type="submit" class="flex-1 py-2.5 bg-green-500 text-white rounded-2xl text-sm font-medium hover:bg-green-600 transition-all">Confirmar</button>
                                <button type="button" @click="confirming = false" class="flex-1 py-2.5 bg-gray-200 text-gray-600 rounded-2xl text-sm font-medium hover:bg-gray-300 transition-all">Cancelar</button>
                            </div>
                        </form>
                    @else
                        <div class="w-full py-2.5 bg-gray-100 text-gray-400 rounded-2xl text-sm font-medium text-center">
                            Necesitas {{ number_format($prize->points_required - auth()->user()->points) }} pts más
                        </div>
                    @endif
                @else
                    <a href="{{ route('login') }}" class="w-full py-2.5 bg-gray-100 text-gray-600 rounded-2xl text-sm font-medium text-center hover:bg-gray-200 transition-all block">
                        Inicia sesión para canjear
                    </a>
                @endauth
            </div>
        </div>
        @empty
        <div class="col-span-full text-center py-16">
            <p class="text-gray-500">No hay premios disponibles en este momento.</p>
        </div>
        @endforelse
    </div>
</div>
@endsection
