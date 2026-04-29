<x-guest-layout>
    <div class="text-center mb-6">
        <h1 class="text-2xl font-bold text-tc-primary">Crea tu cuenta</h1>
        <p class="text-sm text-gray-500 mt-1">Únete y empieza a predecir torneos</p>
    </div>

    <form method="POST" action="{{ route('register') }}" class="space-y-4">
        @csrf

        <div>
            <label for="name" class="block text-sm font-semibold text-gray-700 mb-1">Nombre</label>
            <input id="name" name="name" type="text" value="{{ old('name') }}" required autofocus autocomplete="name"
                   class="w-full px-4 py-3 border border-gray-200 rounded-xl text-sm focus:border-tc-primary focus:ring-2 focus:ring-tc-primary/20 transition-colors">
            @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="email" class="block text-sm font-semibold text-gray-700 mb-1">Correo electrónico</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="username"
                   class="w-full px-4 py-3 border border-gray-200 rounded-xl text-sm focus:border-tc-primary focus:ring-2 focus:ring-tc-primary/20 transition-colors">
            @error('email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="password" class="block text-sm font-semibold text-gray-700 mb-1">Contraseña</label>
            <input id="password" name="password" type="password" required autocomplete="new-password"
                   class="w-full px-4 py-3 border border-gray-200 rounded-xl text-sm focus:border-tc-primary focus:ring-2 focus:ring-tc-primary/20 transition-colors">
            @error('password') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="password_confirmation" class="block text-sm font-semibold text-gray-700 mb-1">Confirmar contraseña</label>
            <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"
                   class="w-full px-4 py-3 border border-gray-200 rounded-xl text-sm focus:border-tc-primary focus:ring-2 focus:ring-tc-primary/20 transition-colors">
            @error('password_confirmation') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        <button type="submit"
                class="w-full mt-6 px-5 py-3 bg-tc-primary text-white font-bold rounded-full text-sm hover:bg-tc-primary/90 active:scale-[0.98] transition-all shadow-lg shadow-tc-primary/20">
            Crear cuenta
        </button>
    </form>

    <p class="mt-6 text-center text-sm text-gray-500">
        ¿Ya tienes cuenta?
        <a href="{{ route('login') }}" class="text-tc-primary font-semibold hover:underline">Ingresa aquí</a>
    </p>
</x-guest-layout>
