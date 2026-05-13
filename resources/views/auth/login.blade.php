<x-guest-layout>
    <div class="text-center mb-6">
        <h1 class="text-2xl font-bold text-tc-primary">Bienvenido de vuelta</h1>
        <p class="text-sm text-gray-500 mt-1">Ingresa para continuar tus pronósticos</p>
    </div>

    @if (session('status'))
        <div class="mb-4 px-4 py-3 rounded-xl bg-green-50 text-green-700 text-sm font-medium">
            {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}" class="space-y-4" x-data="{ pw: false }">
        @csrf

        <div>
            <label for="email" class="block text-sm font-semibold text-gray-700 mb-1">Correo electrónico</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                   class="w-full px-4 py-3 border border-gray-200 rounded-xl text-sm focus:border-tc-primary focus:ring-2 focus:ring-tc-primary/20 transition-colors">
            @error('email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="password" class="block text-sm font-semibold text-gray-700 mb-1">Contraseña</label>
            <div class="relative">
                <input id="password" name="password" :type="pw ? 'text' : 'password'" required autocomplete="current-password"
                       class="w-full px-4 py-3 pr-11 border border-gray-200 rounded-xl text-sm focus:border-tc-primary focus:ring-2 focus:ring-tc-primary/20 transition-colors">
                <button type="button" @click="pw = !pw" tabindex="-1"
                        class="absolute inset-y-0 right-0 px-3 flex items-center text-gray-400 hover:text-tc-primary transition-colors"
                        :aria-label="pw ? 'Ocultar contraseña' : 'Mostrar contraseña'">
                    <svg x-show="!pw" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                    <svg x-show="pw" x-cloak class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.542 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                    </svg>
                </button>
            </div>
            @error('password') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="flex items-center justify-between text-sm">
            <label for="remember_me" class="inline-flex items-center text-gray-600">
                <input id="remember_me" name="remember" type="checkbox" class="rounded border-gray-300 text-tc-primary focus:ring-tc-primary">
                <span class="ml-2">Recordarme</span>
            </label>

            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}" class="text-tc-primary font-medium hover:underline">¿Olvidaste tu contraseña?</a>
            @endif
        </div>

        <button type="submit"
                class="w-full mt-6 px-5 py-3 bg-tc-primary text-white font-bold rounded-full text-sm hover:bg-tc-primary/90 active:scale-[0.98] transition-all shadow-lg shadow-tc-primary/20">
            Ingresar
        </button>
    </form>

    <p class="mt-6 text-center text-sm text-gray-500">
        ¿Aún no tienes cuenta?
        <a href="{{ route('register') }}" class="text-tc-primary font-semibold hover:underline">Regístrate</a>
    </p>
</x-guest-layout>
