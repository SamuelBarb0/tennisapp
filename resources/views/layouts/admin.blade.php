<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Admin - @yield('title', 'Dashboard') | Tennis Challenge</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        [x-cloak] { display: none !important; }
        .fade-in { animation: fadeIn 0.4s ease-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    </style>
    @stack('styles')
</head>
<body class="bg-gray-50 antialiased" x-data="{ sidebarOpen: window.innerWidth >= 1024 }" @resize.window="sidebarOpen = window.innerWidth >= 1024">
    {{-- Toast --}}
    <div x-data="{ show: false, message: '', type: 'success' }"
         x-init="
            @if(session('success')) message = '{{ session('success') }}'; type = 'success'; show = true; setTimeout(() => show = false, 4000); @endif
            @if(session('error')) message = '{{ session('error') }}'; type = 'error'; show = true; setTimeout(() => show = false, 4000); @endif
         "
         x-show="show" x-cloak
         x-transition
         class="fixed top-6 right-6 z-[100] max-w-sm">
        <div :class="type === 'success' ? 'bg-green-500' : 'bg-red-500'" class="text-white px-6 py-3 rounded-2xl shadow-lg flex items-center gap-3">
            <span x-text="message" class="text-sm font-medium"></span>
            <button @click="show = false"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
        </div>
    </div>

    <div class="flex min-h-screen">
        {{-- Sidebar --}}
        <aside :class="sidebarOpen ? 'translate-x-0 w-64' : '-translate-x-full w-0 lg:w-20 lg:translate-x-0'"
               class="fixed lg:sticky top-0 h-screen bg-white border-r border-gray-200 z-40 transition-all duration-300 overflow-hidden flex flex-col">
            <div class="p-4 border-b border-gray-100 flex items-center">
                <img x-show="sidebarOpen" x-cloak src="{{ asset('images/image-removebg-preview.png') }}" alt="Tennis Challenge" class="h-9">
                <div x-show="!sidebarOpen" class="w-9 h-9 bg-tc-primary rounded-xl flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><circle cx="12" cy="12" r="10" stroke-width="2"/></svg>
                </div>
            </div>
            <nav class="flex-1 p-3 space-y-1 overflow-y-auto">
                @php
                $adminLinks = [
                    ['route' => 'admin.dashboard', 'label' => 'Dashboard', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>'],
                    ['route' => 'admin.tournaments.index', 'label' => 'Torneos', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>'],
                    ['route' => 'admin.matches.index', 'label' => 'Partidos', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>'],
                    ['route' => 'admin.players.index', 'label' => 'Jugadores', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>'],
                    ['route' => 'admin.users.index', 'label' => 'Usuarios', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>'],
                    ['route' => 'admin.prizes.index', 'label' => 'Premios', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7"/>'],
                    ['route' => 'admin.redemptions.index', 'label' => 'Canjes', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>'],
                    ['route' => 'admin.banners.index', 'label' => 'Banners', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>'],
                    ['route' => 'admin.settings.index', 'label' => 'Configuración', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>'],
                    ['route' => 'admin.api-sync.index', 'label' => 'API Sync', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>'],
                ];
                @endphp
                @foreach($adminLinks as $link)
                    <a href="{{ route($link['route']) }}"
                       class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all {{ request()->routeIs($link['route'].'*') ? 'bg-tc-primary text-white shadow-sm' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900' }}">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">{!! $link['icon'] !!}</svg>
                        <span x-show="sidebarOpen" x-cloak class="whitespace-nowrap">{{ $link['label'] }}</span>
                    </a>
                @endforeach
            </nav>
            <div class="p-3 border-t border-gray-100">
                <a href="{{ route('home') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium text-gray-600 hover:bg-gray-100 transition-all">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    <span x-show="sidebarOpen" x-cloak class="whitespace-nowrap">Volver al sitio</span>
                </a>
            </div>
        </aside>

        {{-- Mobile overlay --}}
        <div x-show="sidebarOpen" @click="sidebarOpen = false" x-cloak class="lg:hidden fixed inset-0 bg-black/50 z-30"></div>

        {{-- Main content --}}
        <div class="flex-1 flex flex-col min-h-screen">
            {{-- Topbar --}}
            <header class="bg-white border-b border-gray-200 sticky top-0 z-20">
                <div class="flex items-center justify-between px-4 lg:px-8 h-16">
                    <div class="flex items-center gap-4">
                        <button @click="sidebarOpen = !sidebarOpen" class="p-2 rounded-lg text-gray-500 hover:bg-gray-100">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                        </button>
                        <h1 class="text-lg font-semibold text-gray-900">@yield('title', 'Dashboard')</h1>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="text-sm text-gray-500">{{ auth()->user()->name }}</span>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="text-sm text-gray-400 hover:text-red-500 transition-colors">Cerrar sesión</button>
                        </form>
                    </div>
                </div>
            </header>

            {{-- Page content --}}
            <main class="flex-1 p-4 lg:p-8 fade-in">
                @yield('content')
            </main>
        </div>
    </div>
    @stack('scripts')
</body>
</html>
