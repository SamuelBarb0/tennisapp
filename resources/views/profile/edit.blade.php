@extends('layouts.app')
@section('title', 'Editar perfil')

@section('content')
<div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    {{-- Header --}}
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('profile.show') }}" class="text-gray-400 hover:text-tc-primary transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
        </a>
        <div>
            <h1 class="text-xl md:text-2xl font-black tracking-tight text-tc-primary">Editar perfil</h1>
            <p class="text-xs text-gray-400 mt-0.5">Actualiza tus datos personales y configuración de cuenta</p>
        </div>
    </div>

    @if(session('status') === 'profile-updated')
    <div class="mb-5 px-4 py-3 bg-green-50 border border-green-200 rounded-xl text-sm text-green-700 font-medium flex items-center gap-2">
        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
        Perfil actualizado correctamente
    </div>
    @endif

    <div class="space-y-5">

        {{-- Información personal --}}
        <section class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100">
                <h2 class="font-bold text-tc-primary">Información personal</h2>
                <p class="text-xs text-gray-400 mt-0.5">Tu nombre y email asociados a la cuenta.</p>
            </div>
            <div class="p-6">
                @include('profile.partials.update-profile-information-form')
            </div>
        </section>

        {{-- Contraseña --}}
        <section class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100">
                <h2 class="font-bold text-tc-primary">Contraseña</h2>
                <p class="text-xs text-gray-400 mt-0.5">Asegúrate de usar una contraseña segura y única.</p>
            </div>
            <div class="p-6">
                @include('profile.partials.update-password-form')
            </div>
        </section>

        {{-- Eliminar cuenta --}}
        <section class="bg-white rounded-2xl border border-red-100 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-red-100 bg-red-50/40">
                <h2 class="font-bold text-red-700">Zona peligrosa</h2>
                <p class="text-xs text-red-500/80 mt-0.5">Esta acción es irreversible. Perderás tu historial completo.</p>
            </div>
            <div class="p-6">
                @include('profile.partials.delete-user-form')
            </div>
        </section>

    </div>
</div>
@endsection
