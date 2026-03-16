@extends('layouts.app')
@section('title', 'Rankings')

@section('content')
<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
    <div class="text-center mb-10 fade-in">
        <h1 class="text-3xl md:text-4xl font-bold mb-2">Rankings</h1>
        <p class="text-gray-500">Los mejores pronosticadores de la comunidad</p>
    </div>

    @if($users->count() > 0)
    {{-- Top 3 Podium --}}
    @if($users->currentPage() === 1)
    <div class="flex items-end justify-center gap-4 mb-12">
        @if($users->count() >= 2)
        <div class="text-center slide-up" style="animation-delay: 0.2s">
            <div class="w-16 h-16 bg-gray-200 rounded-full flex items-center justify-center mx-auto mb-2 text-gray-500 text-lg font-bold ring-4 ring-gray-300">
                {{ strtoupper(substr($users[1]->name, 0, 1)) }}
            </div>
            <div class="font-semibold text-sm">{{ $users[1]->name }}</div>
            <div class="text-xs text-gray-400">{{ number_format($users[1]->points) }} pts</div>
            <div class="mt-2 bg-gray-200 rounded-t-xl h-20 w-20 flex items-end justify-center pb-2">
                <span class="text-2xl font-bold text-gray-500">2</span>
            </div>
        </div>
        @endif

        @if($users->count() >= 1)
        <div class="text-center slide-up" style="animation-delay: 0.1s">
            <div class="w-20 h-20 bg-yellow-400 rounded-full flex items-center justify-center mx-auto mb-2 text-white text-2xl font-bold ring-4 ring-yellow-300 shadow-lg shadow-yellow-400/30">
                {{ strtoupper(substr($users[0]->name, 0, 1)) }}
            </div>
            <div class="font-bold">{{ $users[0]->name }}</div>
            <div class="text-sm text-yellow-600 font-semibold">{{ number_format($users[0]->points) }} pts</div>
            <div class="mt-2 bg-yellow-400 rounded-t-xl h-28 w-24 flex items-end justify-center pb-2">
                <span class="text-3xl font-bold text-white">1</span>
            </div>
        </div>
        @endif

        @if($users->count() >= 3)
        <div class="text-center slide-up" style="animation-delay: 0.3s">
            <div class="w-14 h-14 bg-orange-300 rounded-full flex items-center justify-center mx-auto mb-2 text-white text-lg font-bold ring-4 ring-orange-200">
                {{ strtoupper(substr($users[2]->name, 0, 1)) }}
            </div>
            <div class="font-semibold text-sm">{{ $users[2]->name }}</div>
            <div class="text-xs text-gray-400">{{ number_format($users[2]->points) }} pts</div>
            <div class="mt-2 bg-orange-300 rounded-t-xl h-14 w-20 flex items-end justify-center pb-2">
                <span class="text-2xl font-bold text-white">3</span>
            </div>
        </div>
        @endif
    </div>
    @endif

    {{-- Full Table --}}
    <div class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden">
        @foreach($users as $user)
        @php $rank = ($users->currentPage() - 1) * $users->perPage() + $loop->iteration; @endphp
        <div class="flex items-center gap-4 px-6 py-4 {{ !$loop->last ? 'border-b border-gray-50' : '' }} hover:bg-gray-50 transition-colors">
            <div class="w-8 text-center">
                @if($rank <= 3)
                    <span class="text-lg">{{ $rank === 1 ? '🥇' : ($rank === 2 ? '🥈' : '🥉') }}</span>
                @else
                    <span class="text-sm font-medium text-gray-400">{{ $rank }}</span>
                @endif
            </div>
            <div class="w-10 h-10 bg-tc-primary rounded-full flex items-center justify-center text-white text-sm font-bold flex-shrink-0">
                {{ strtoupper(substr($user->name, 0, 1)) }}
            </div>
            <div class="flex-1 min-w-0">
                <div class="font-medium text-sm truncate">{{ $user->name }}</div>
                <div class="text-xs text-gray-400">{{ $user->predictions()->count() }} pronósticos</div>
            </div>
            <div class="text-right">
                <div class="font-bold text-tc-primary">{{ number_format($user->points) }}</div>
                <div class="text-xs text-gray-400">puntos</div>
            </div>
        </div>
        @endforeach
    </div>

    <div class="mt-6">
        {{ $users->links() }}
    </div>
    @else
    <div class="text-center py-16 bg-white rounded-3xl">
        <p class="text-gray-500">Aún no hay usuarios en el ranking.</p>
    </div>
    @endif
</div>
@endsection
