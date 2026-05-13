<x-guest-layout>
    <div class="text-center mb-6">
        <h1 class="text-2xl font-bold text-tc-primary">Crea tu cuenta</h1>
        <p class="text-sm text-gray-500 mt-1">Únete y empieza a predecir torneos</p>
    </div>

    <form method="POST" action="{{ route('register') }}" class="space-y-4" x-data="{ pw: false, pwc: false }">
        @csrf

        {{-- Name + last name --}}
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label for="name" class="block text-sm font-semibold text-gray-700 mb-1">Nombre</label>
                <input id="name" name="name" type="text" value="{{ old('name') }}" required autofocus autocomplete="given-name"
                       class="w-full px-4 py-3 border border-gray-200 rounded-xl text-sm focus:border-tc-primary focus:ring-2 focus:ring-tc-primary/20 transition-colors">
                @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="last_name" class="block text-sm font-semibold text-gray-700 mb-1">Apellido</label>
                <input id="last_name" name="last_name" type="text" value="{{ old('last_name') }}" required autocomplete="family-name"
                       class="w-full px-4 py-3 border border-gray-200 rounded-xl text-sm focus:border-tc-primary focus:ring-2 focus:ring-tc-primary/20 transition-colors">
                @error('last_name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        {{-- Email --}}
        <div>
            <label for="email" class="block text-sm font-semibold text-gray-700 mb-1">Correo electrónico</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="username"
                   class="w-full px-4 py-3 border border-gray-200 rounded-xl text-sm focus:border-tc-primary focus:ring-2 focus:ring-tc-primary/20 transition-colors">
            @error('email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        {{-- Phone --}}
        <div>
            <label for="phone" class="block text-sm font-semibold text-gray-700 mb-1">Celular</label>
            <input id="phone" name="phone" type="tel" value="{{ old('phone') }}" required autocomplete="tel" placeholder="+57 300 000 0000"
                   class="w-full px-4 py-3 border border-gray-200 rounded-xl text-sm focus:border-tc-primary focus:ring-2 focus:ring-tc-primary/20 transition-colors">
            @error('phone') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        {{-- City + country --}}
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label for="city" class="block text-sm font-semibold text-gray-700 mb-1">Ciudad</label>
                <input id="city" name="city" type="text" value="{{ old('city') }}" required autocomplete="address-level2"
                       class="w-full px-4 py-3 border border-gray-200 rounded-xl text-sm focus:border-tc-primary focus:ring-2 focus:ring-tc-primary/20 transition-colors">
                @error('city') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="country_code" class="block text-sm font-semibold text-gray-700 mb-1">País</label>
                <select id="country_code" name="country_code" required autocomplete="country"
                        class="w-full px-4 py-3 border border-gray-200 rounded-xl text-sm focus:border-tc-primary focus:ring-2 focus:ring-tc-primary/20 transition-colors bg-white">
                    <option value="">Selecciona…</option>
                    @foreach($countries as $code => $name)
                        <option value="{{ $code }}" @selected(old('country_code', 'CO') === $code)>{{ $name }}</option>
                    @endforeach
                </select>
                @error('country_code') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        {{-- Birth date --}}
        <div>
            <label for="birth_date" class="block text-sm font-semibold text-gray-700 mb-1">Fecha de nacimiento</label>
            <input id="birth_date" name="birth_date" type="date" value="{{ old('birth_date') }}" required max="{{ now()->subYears(18)->format('Y-m-d') }}"
                   class="w-full px-4 py-3 border border-gray-200 rounded-xl text-sm focus:border-tc-primary focus:ring-2 focus:ring-tc-primary/20 transition-colors">
            <p class="mt-1 text-[11px] text-gray-400">Debes ser mayor de 18 años.</p>
            @error('birth_date') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        {{-- Password (with toggle) --}}
        <div>
            <label for="password" class="block text-sm font-semibold text-gray-700 mb-1">Contraseña</label>
            <div class="relative">
                <input id="password" name="password" :type="pw ? 'text' : 'password'" required autocomplete="new-password"
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

        {{-- Password confirmation (with toggle) --}}
        <div>
            <label for="password_confirmation" class="block text-sm font-semibold text-gray-700 mb-1">Confirmar contraseña</label>
            <div class="relative">
                <input id="password_confirmation" name="password_confirmation" :type="pwc ? 'text' : 'password'" required autocomplete="new-password"
                       class="w-full px-4 py-3 pr-11 border border-gray-200 rounded-xl text-sm focus:border-tc-primary focus:ring-2 focus:ring-tc-primary/20 transition-colors">
                <button type="button" @click="pwc = !pwc" tabindex="-1"
                        class="absolute inset-y-0 right-0 px-3 flex items-center text-gray-400 hover:text-tc-primary transition-colors"
                        :aria-label="pwc ? 'Ocultar contraseña' : 'Mostrar contraseña'">
                    <svg x-show="!pwc" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                    <svg x-show="pwc" x-cloak class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.542 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                    </svg>
                </button>
            </div>
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
