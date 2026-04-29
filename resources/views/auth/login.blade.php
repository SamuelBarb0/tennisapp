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

    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf

        <div>
            <label for="email" class="block text-sm font-semibold text-gray-700 mb-1">Correo electrónico</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                   class="w-full px-4 py-3 border border-gray-200 rounded-xl text-sm focus:border-tc-primary focus:ring-2 focus:ring-tc-primary/20 transition-colors">
            @error('email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="password" class="block text-sm font-semibold text-gray-700 mb-1">Contraseña</label>
            <input id="password" name="password" type="password" required autocomplete="current-password"
                   class="w-full px-4 py-3 border border-gray-200 rounded-xl text-sm focus:border-tc-primary focus:ring-2 focus:ring-tc-primary/20 transition-colors">
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
