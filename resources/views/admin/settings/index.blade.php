@extends('layouts.admin')
@section('title', 'Configuración')

@section('content')
<div class="max-w-2xl">
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-xl font-bold">Configuración General</h2>
    </div>

    @if(session('success'))
    <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-xl text-sm text-green-700">{{ session('success') }}</div>
    @endif

    <form action="{{ route('admin.settings.update') }}" method="POST" class="space-y-6">
        @csrf
        @method('PUT')

        {{-- General --}}
        <div class="bg-white rounded-2xl p-6 border border-gray-100 shadow-sm space-y-5">
            <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wider">General</h3>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Nombre del sitio</label>
                <input type="text" name="site_name" value="{{ $settings['site_name'] ?? '' }}" class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-[#0071E3] focus:border-transparent outline-none transition-all">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Descripción del sitio</label>
                <textarea name="site_description" rows="3" class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-[#0071E3] focus:border-transparent outline-none resize-none">{{ $settings['site_description'] ?? '' }}</textarea>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Color primario</label>
                    <div class="flex items-center gap-3">
                        <input type="color" name="primary_color" value="{{ $settings['primary_color'] ?? '#0071E3' }}" class="w-10 h-10 rounded-lg border border-gray-200 cursor-pointer">
                        <input type="text" value="{{ $settings['primary_color'] ?? '#0071E3' }}" class="flex-1 px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-[#0071E3] focus:border-transparent outline-none" disabled>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Color secundario</label>
                    <div class="flex items-center gap-3">
                        <input type="color" name="secondary_color" value="{{ $settings['secondary_color'] ?? '#5856D6' }}" class="w-10 h-10 rounded-lg border border-gray-200 cursor-pointer">
                        <input type="text" value="{{ $settings['secondary_color'] ?? '#5856D6' }}" class="flex-1 px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-[#0071E3] focus:border-transparent outline-none" disabled>
                    </div>
                </div>
            </div>
        </div>

        {{-- Contact & Social --}}
        <div class="bg-white rounded-2xl p-6 border border-gray-100 shadow-sm space-y-5">
            <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wider">Contacto y Redes Sociales</h3>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Email de contacto</label>
                <input type="email" name="contact_email" value="{{ $settings['contact_email'] ?? '' }}" class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-[#0071E3] focus:border-transparent outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Instagram</label>
                <div class="relative">
                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-sm text-gray-400">@</span>
                    <input type="text" name="instagram" value="{{ $settings['instagram'] ?? '' }}" class="w-full pl-8 pr-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-[#0071E3] focus:border-transparent outline-none" placeholder="usuario">
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Twitter / X</label>
                <div class="relative">
                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-sm text-gray-400">@</span>
                    <input type="text" name="twitter" value="{{ $settings['twitter'] ?? '' }}" class="w-full pl-8 pr-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-[#0071E3] focus:border-transparent outline-none" placeholder="usuario">
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Facebook</label>
                <input type="text" name="facebook" value="{{ $settings['facebook'] ?? '' }}" class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-[#0071E3] focus:border-transparent outline-none" placeholder="URL o nombre de página">
            </div>
        </div>

        {{-- Points System --}}
        <div class="bg-white rounded-2xl p-6 border border-gray-100 shadow-sm space-y-5">
            <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wider">Sistema de Puntos</h3>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Puntos por acierto</label>
                    <input type="number" name="points_per_correct" value="{{ $settings['points_per_correct'] ?? 10 }}" min="1" class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-[#0071E3] focus:border-transparent outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Bonus campeón</label>
                    <input type="number" name="bonus_champion" value="{{ $settings['bonus_champion'] ?? 50 }}" min="0" class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-[#0071E3] focus:border-transparent outline-none">
                </div>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <button type="submit" class="px-6 py-2.5 bg-[#0071E3] text-white rounded-xl text-sm font-medium hover:bg-[#0062CC] transition-colors">Guardar configuración</button>
        </div>
    </form>
</div>
@endsection
