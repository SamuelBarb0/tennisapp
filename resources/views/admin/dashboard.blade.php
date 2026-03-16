@extends('layouts.admin')
@section('title', 'Dashboard')

@section('content')
{{-- Stats Grid --}}
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
    @php
    $statCards = [
        ['label' => 'Usuarios', 'value' => number_format($stats['users']), 'color' => 'blue', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>'],
        ['label' => 'Pronósticos', 'value' => number_format($stats['predictions']), 'color' => 'green', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>'],
        ['label' => 'Torneos', 'value' => $stats['tournaments'], 'color' => 'purple', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5"/>'],
        ['label' => 'Ingresos', 'value' => '$' . number_format($stats['revenue'], 0), 'color' => 'yellow', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>'],
        ['label' => 'Partidos en vivo', 'value' => $stats['activeMatches'], 'color' => 'red', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>'],
        ['label' => 'Canjes pendientes', 'value' => $stats['pendingRedemptions'], 'color' => 'orange', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>'],
    ];
    @endphp
    @foreach($statCards as $card)
    <div class="bg-white rounded-2xl p-6 border border-gray-100 shadow-sm hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between mb-3">
            <span class="text-sm text-gray-500">{{ $card['label'] }}</span>
            <div class="w-10 h-10 bg-{{ $card['color'] }}-50 rounded-xl flex items-center justify-center">
                <svg class="w-5 h-5 text-{{ $card['color'] }}-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">{!! $card['icon'] !!}</svg>
            </div>
        </div>
        <div class="text-3xl font-bold">{{ $card['value'] }}</div>
    </div>
    @endforeach
</div>

{{-- Recent Activity --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    {{-- Recent Users --}}
    <div class="bg-white rounded-2xl p-6 border border-gray-100 shadow-sm">
        <div class="flex items-center justify-between mb-4">
            <h3 class="font-semibold">Usuarios recientes</h3>
            <a href="{{ route('admin.users.index') }}" class="text-sm text-tc-primary hover:underline">Ver todos</a>
        </div>
        <div class="space-y-3">
            @foreach($recentUsers as $user)
            <div class="flex items-center gap-3 p-2 rounded-xl hover:bg-gray-50 transition-colors">
                <div class="w-9 h-9 bg-tc-primary rounded-full flex items-center justify-center text-white text-sm font-bold">{{ strtoupper(substr($user->name, 0, 1)) }}</div>
                <div class="flex-1 min-w-0">
                    <div class="text-sm font-medium truncate">{{ $user->name }}</div>
                    <div class="text-xs text-gray-400">{{ $user->email }}</div>
                </div>
                <span class="text-xs text-gray-400">{{ $user->created_at->diffForHumans() }}</span>
            </div>
            @endforeach
        </div>
    </div>

    {{-- Recent Predictions --}}
    <div class="bg-white rounded-2xl p-6 border border-gray-100 shadow-sm">
        <div class="flex items-center justify-between mb-4">
            <h3 class="font-semibold">Pronósticos recientes</h3>
        </div>
        <div class="space-y-3">
            @foreach($recentPredictions as $prediction)
            <div class="flex items-center gap-3 p-2 rounded-xl hover:bg-gray-50 transition-colors">
                <div class="w-9 h-9 bg-gray-100 rounded-full flex items-center justify-center text-gray-500 text-sm font-bold">{{ strtoupper(substr($prediction->user->name ?? '?', 0, 1)) }}</div>
                <div class="flex-1 min-w-0">
                    <div class="text-sm font-medium truncate">{{ $prediction->user->name ?? 'Usuario' }}</div>
                    <div class="text-xs text-gray-400">{{ $prediction->match->player1->name ?? '' }} vs {{ $prediction->match->player2->name ?? '' }}</div>
                </div>
                <span class="text-xs text-gray-400">{{ $prediction->created_at->diffForHumans() }}</span>
            </div>
            @endforeach
        </div>
    </div>
</div>
@endsection
