@extends('layouts.app')
@section('title', 'Mi Perfil')

@section('content')
<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
    {{-- Profile Header --}}
    <div class="bg-white rounded-3xl p-8 shadow-sm border border-gray-100 mb-8 fade-in">
        <div class="flex flex-col sm:flex-row items-center gap-6">
            <div class="w-20 h-20 bg-[#0071E3] rounded-full flex items-center justify-center text-white text-3xl font-bold shadow-lg shadow-blue-500/25">
                {{ strtoupper(substr($user->name, 0, 1)) }}
            </div>
            <div class="text-center sm:text-left flex-1">
                <h1 class="text-2xl font-bold">{{ $user->name }}</h1>
                <p class="text-gray-500 text-sm">{{ $user->email }}</p>
                <p class="text-xs text-gray-400 mt-1">Miembro desde {{ $user->created_at->format('M Y') }}</p>
            </div>
            <a href="{{ route('profile.edit') }}" class="px-5 py-2.5 bg-gray-100 text-gray-700 rounded-2xl text-sm font-medium hover:bg-gray-200 transition-all">
                Editar perfil
            </a>
        </div>

        {{-- Stats --}}
        <div class="grid grid-cols-3 gap-4 mt-8">
            <div class="text-center p-4 bg-yellow-50 rounded-2xl">
                <div class="text-2xl font-bold text-yellow-600">{{ number_format($user->points) }}</div>
                <div class="text-xs text-yellow-600/70 mt-1">Puntos</div>
            </div>
            <div class="text-center p-4 bg-blue-50 rounded-2xl">
                <div class="text-2xl font-bold text-[#0071E3]">{{ $totalPredictions }}</div>
                <div class="text-xs text-blue-600/70 mt-1">Pronósticos</div>
            </div>
            <div class="text-center p-4 bg-green-50 rounded-2xl">
                <div class="text-2xl font-bold text-green-600">{{ $totalPredictions > 0 ? round(($correctPredictions / $totalPredictions) * 100) : 0 }}%</div>
                <div class="text-xs text-green-600/70 mt-1">Aciertos</div>
            </div>
        </div>
    </div>

    {{-- Recent Predictions --}}
    <div class="bg-white rounded-3xl p-6 shadow-sm border border-gray-100 mb-8">
        <h2 class="text-lg font-bold mb-4">Mis pronósticos recientes</h2>
        @if($predictions->count() > 0)
        <div class="space-y-3">
            @foreach($predictions as $prediction)
            <div class="flex items-center gap-4 p-3 rounded-2xl {{ $prediction->is_correct === true ? 'bg-green-50' : ($prediction->is_correct === false ? 'bg-red-50' : 'bg-gray-50') }}">
                <div class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0
                    {{ $prediction->is_correct === true ? 'bg-green-500 text-white' : ($prediction->is_correct === false ? 'bg-red-500 text-white' : 'bg-gray-300 text-white') }}">
                    @if($prediction->is_correct === true)
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                    @elseif($prediction->is_correct === false)
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                    @else
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    @endif
                </div>
                <div class="flex-1 min-w-0">
                    <div class="text-sm font-medium">{{ $prediction->match->player1->name }} vs {{ $prediction->match->player2->name }}</div>
                    <div class="text-xs text-gray-500">Pronóstico: {{ $prediction->predictedWinner->name }} &middot; {{ $prediction->match->tournament->name ?? '' }}</div>
                </div>
                @if($prediction->points_earned > 0)
                <span class="text-sm font-bold text-green-600">+{{ $prediction->points_earned }}</span>
                @endif
            </div>
            @endforeach
        </div>
        @else
        <p class="text-gray-500 text-sm text-center py-6">No has hecho pronósticos aún.</p>
        @endif
    </div>

    {{-- Redemptions --}}
    @if($redemptions->count() > 0)
    <div class="bg-white rounded-3xl p-6 shadow-sm border border-gray-100">
        <h2 class="text-lg font-bold mb-4">Mis canjes</h2>
        <div class="space-y-3">
            @foreach($redemptions as $redemption)
            <div class="flex items-center gap-4 p-3 rounded-2xl bg-gray-50">
                <div class="w-12 h-12 bg-gray-200 rounded-xl flex items-center justify-center flex-shrink-0">
                    <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7"/></svg>
                </div>
                <div class="flex-1">
                    <div class="font-medium text-sm">{{ $redemption->prize->name }}</div>
                    <div class="text-xs text-gray-400">{{ $redemption->created_at->format('d M, Y') }}</div>
                </div>
                <span class="px-3 py-1 text-xs font-medium rounded-full
                    {{ $redemption->status === 'delivered' ? 'bg-green-100 text-green-600' : ($redemption->status === 'approved' ? 'bg-blue-100 text-blue-600' : ($redemption->status === 'rejected' ? 'bg-red-100 text-red-600' : 'bg-yellow-100 text-yellow-600')) }}">
                    {{ $redemption->status === 'delivered' ? 'Entregado' : ($redemption->status === 'approved' ? 'Aprobado' : ($redemption->status === 'rejected' ? 'Rechazado' : 'Pendiente')) }}
                </span>
            </div>
            @endforeach
        </div>
    </div>
    @endif
</div>
@endsection
