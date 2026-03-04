<!DOCTYPE html>
<html lang="es" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'TennisApp') }} - @yield('title', 'Pronósticos de Tenis')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        [x-cloak] { display: none !important; }
        .glass { background: rgba(255,255,255,0.72); backdrop-filter: saturate(180%) blur(20px); -webkit-backdrop-filter: saturate(180%) blur(20px); }
        .fade-in { animation: fadeIn 0.6s ease-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .slide-up { animation: slideUp 0.5s ease-out; }
        @keyframes slideUp { from { opacity: 0; transform: translateY(40px); } to { opacity: 1; transform: translateY(0); } }
        .hover-lift { transition: transform 0.3s ease, box-shadow 0.3s ease; }
        .hover-lift:hover { transform: translateY(-4px); box-shadow: 0 12px 40px rgba(0,0,0,0.12); }
    </style>
    @stack('styles')
</head>
<body class="bg-gray-50 text-gray-900 font-sans antialiased min-h-screen flex flex-col">
    {{-- Toast Notifications --}}
    <div x-data="{ show: false, message: '', type: 'success' }"
         x-init="
            @if(session('success'))
                message = '{{ session('success') }}'; type = 'success'; show = true; setTimeout(() => show = false, 4000);
            @endif
            @if(session('error'))
                message = '{{ session('error') }}'; type = 'error'; show = true; setTimeout(() => show = false, 4000);
            @endif
         "
         x-show="show"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-[-20px]"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         x-cloak
         class="fixed top-6 right-6 z-[100] max-w-sm"
    >
        <div :class="type === 'success' ? 'bg-green-500' : 'bg-red-500'" class="text-white px-6 py-3 rounded-2xl shadow-lg flex items-center gap-3">
            <template x-if="type === 'success'">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
            </template>
            <template x-if="type === 'error'">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </template>
            <span x-text="message" class="text-sm font-medium"></span>
            <button @click="show = false" class="ml-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
    </div>

    {{-- Navigation --}}
    <nav class="glass sticky top-0 z-50 border-b border-gray-200/50" x-data="{ mobileOpen: false }">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <a href="{{ route('home') }}" class="flex items-center gap-2">
                        <div class="w-8 h-8 bg-[#0071E3] rounded-lg flex items-center justify-center">
                            <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><circle cx="12" cy="12" r="10" stroke-width="2"/><path d="M8 12a4 4 0 018 0" stroke-width="2"/></svg>
                        </div>
                        <span class="text-xl font-bold tracking-tight">TennisApp</span>
                    </a>
                </div>
                <div class="hidden md:flex items-center gap-1">
                    <a href="{{ route('tournaments.index') }}" class="px-4 py-2 rounded-full text-sm font-medium transition-colors {{ request()->routeIs('tournaments.*') ? 'bg-[#0071E3] text-white' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100' }}">Torneos</a>
                    <a href="{{ route('rankings.index') }}" class="px-4 py-2 rounded-full text-sm font-medium transition-colors {{ request()->routeIs('rankings.*') ? 'bg-[#0071E3] text-white' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100' }}">Rankings</a>
                    <a href="{{ route('prizes.index') }}" class="px-4 py-2 rounded-full text-sm font-medium transition-colors {{ request()->routeIs('prizes.*') ? 'bg-[#0071E3] text-white' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100' }}">Premios</a>
                </div>
                <div class="hidden md:flex items-center gap-3">
                    @auth
                        <div class="flex items-center gap-2 text-sm text-gray-600">
                            <svg class="w-4 h-4 text-yellow-500" fill="currentColor" viewBox="0 0 20 20"><path d="M10 15l-5.878 3.09 1.123-6.545L.489 6.91l6.572-.955L10 0l2.939 5.955 6.572.955-4.756 4.635 1.123 6.545z"/></svg>
                            <span class="font-semibold">{{ number_format(auth()->user()->points) }}</span> pts
                        </div>
                        <a href="{{ route('profile.show') }}" class="flex items-center gap-2 px-3 py-2 rounded-full text-sm font-medium text-gray-600 hover:bg-gray-100 transition-colors">
                            <div class="w-7 h-7 bg-[#0071E3] rounded-full flex items-center justify-center text-white text-xs font-bold">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</div>
                            {{ auth()->user()->name }}
                        </a>
                        @if(auth()->user()->is_admin)
                            <a href="{{ route('admin.dashboard') }}" class="px-3 py-2 rounded-full text-sm font-medium text-orange-600 hover:bg-orange-50 transition-colors">Admin</a>
                        @endif
                    @else
                        <a href="{{ route('login') }}" class="px-4 py-2 rounded-full text-sm font-medium text-gray-600 hover:text-gray-900 transition-colors">Ingresar</a>
                        <a href="{{ route('register') }}" class="px-5 py-2 bg-[#0071E3] text-white rounded-full text-sm font-medium hover:bg-[#0062CC] transition-colors shadow-sm">Registrarse</a>
                    @endauth
                </div>
                {{-- Mobile menu button --}}
                <div class="md:hidden flex items-center">
                    <button @click="mobileOpen = !mobileOpen" class="p-2 rounded-lg text-gray-600 hover:bg-gray-100">
                        <svg x-show="!mobileOpen" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                        <svg x-show="mobileOpen" x-cloak class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            </div>
        </div>
        {{-- Mobile menu --}}
        <div x-show="mobileOpen" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" class="md:hidden border-t border-gray-200/50 glass">
            <div class="px-4 py-3 space-y-1">
                <a href="{{ route('tournaments.index') }}" class="block px-4 py-2 rounded-xl text-sm font-medium {{ request()->routeIs('tournaments.*') ? 'bg-[#0071E3] text-white' : 'text-gray-600 hover:bg-gray-100' }}">Torneos</a>
                <a href="{{ route('rankings.index') }}" class="block px-4 py-2 rounded-xl text-sm font-medium {{ request()->routeIs('rankings.*') ? 'bg-[#0071E3] text-white' : 'text-gray-600 hover:bg-gray-100' }}">Rankings</a>
                <a href="{{ route('prizes.index') }}" class="block px-4 py-2 rounded-xl text-sm font-medium {{ request()->routeIs('prizes.*') ? 'bg-[#0071E3] text-white' : 'text-gray-600 hover:bg-gray-100' }}">Premios</a>
                @auth
                    <a href="{{ route('profile.show') }}" class="block px-4 py-2 rounded-xl text-sm font-medium text-gray-600 hover:bg-gray-100">Mi Perfil</a>
                    @if(auth()->user()->is_admin)
                        <a href="{{ route('admin.dashboard') }}" class="block px-4 py-2 rounded-xl text-sm font-medium text-orange-600 hover:bg-orange-50">Panel Admin</a>
                    @endif
                @else
                    <a href="{{ route('login') }}" class="block px-4 py-2 rounded-xl text-sm font-medium text-gray-600 hover:bg-gray-100">Ingresar</a>
                    <a href="{{ route('register') }}" class="block px-4 py-2 rounded-xl text-sm font-medium bg-[#0071E3] text-white">Registrarse</a>
                @endauth
            </div>
        </div>
    </nav>

    {{-- Main Content --}}
    <main class="flex-1">
        @yield('content')
    </main>

    {{-- Footer --}}
    <footer class="bg-white border-t border-gray-100 mt-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                <div class="md:col-span-1">
                    <div class="flex items-center gap-2 mb-4">
                        <div class="w-8 h-8 bg-[#0071E3] rounded-lg flex items-center justify-center">
                            <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><circle cx="12" cy="12" r="10" stroke-width="2"/><path d="M8 12a4 4 0 018 0" stroke-width="2"/></svg>
                        </div>
                        <span class="text-lg font-bold">TennisApp</span>
                    </div>
                    <p class="text-sm text-gray-500">La mejor plataforma de pronósticos de tenis profesional.</p>
                </div>
                <div>
                    <h3 class="text-sm font-semibold text-gray-900 mb-3">Plataforma</h3>
                    <ul class="space-y-2 text-sm text-gray-500">
                        <li><a href="{{ route('tournaments.index') }}" class="hover:text-[#0071E3] transition-colors">Torneos</a></li>
                        <li><a href="{{ route('rankings.index') }}" class="hover:text-[#0071E3] transition-colors">Rankings</a></li>
                        <li><a href="{{ route('prizes.index') }}" class="hover:text-[#0071E3] transition-colors">Premios</a></li>
                    </ul>
                </div>
                <div>
                    <h3 class="text-sm font-semibold text-gray-900 mb-3">Soporte</h3>
                    <ul class="space-y-2 text-sm text-gray-500">
                        <li><a href="#" class="hover:text-[#0071E3] transition-colors">Términos y condiciones</a></li>
                        <li><a href="#" class="hover:text-[#0071E3] transition-colors">Política de privacidad</a></li>
                        <li><a href="#" class="hover:text-[#0071E3] transition-colors">Contacto</a></li>
                    </ul>
                </div>
                <div>
                    <h3 class="text-sm font-semibold text-gray-900 mb-3">Síguenos</h3>
                    <div class="flex gap-3">
                        <a href="#" class="w-9 h-9 bg-gray-100 rounded-full flex items-center justify-center text-gray-500 hover:bg-[#0071E3] hover:text-white transition-all">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M24 4.557c-.883.392-1.832.656-2.828.775 1.017-.609 1.798-1.574 2.165-2.724-.951.564-2.005.974-3.127 1.195-.897-.957-2.178-1.555-3.594-1.555-3.179 0-5.515 2.966-4.797 6.045-4.091-.205-7.719-2.165-10.148-5.144-1.29 2.213-.669 5.108 1.523 6.574-.806-.026-1.566-.247-2.229-.616-.054 2.281 1.581 4.415 3.949 4.89-.693.188-1.452.232-2.224.084.626 1.956 2.444 3.379 4.6 3.419-2.07 1.623-4.678 2.348-7.29 2.04 2.179 1.397 4.768 2.212 7.548 2.212 9.142 0 14.307-7.721 13.995-14.646.962-.695 1.797-1.562 2.457-2.549z"/></svg>
                        </a>
                        <a href="#" class="w-9 h-9 bg-gray-100 rounded-full flex items-center justify-center text-gray-500 hover:bg-[#0071E3] hover:text-white transition-all">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg>
                        </a>
                        <a href="#" class="w-9 h-9 bg-gray-100 rounded-full flex items-center justify-center text-gray-500 hover:bg-[#0071E3] hover:text-white transition-all">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M9 8h-3v4h3v12h5v-12h3.642l.358-4h-4v-1.667c0-.955.192-1.333 1.115-1.333h2.885v-5h-3.808c-3.596 0-5.192 1.583-5.192 4.615v3.385z"/></svg>
                        </a>
                    </div>
                </div>
            </div>
            <div class="mt-8 pt-8 border-t border-gray-100 text-center text-sm text-gray-400">
                &copy; {{ date('Y') }} TennisApp. Todos los derechos reservados.
            </div>
        </div>
    </footer>

    @stack('scripts')
</body>
</html>
