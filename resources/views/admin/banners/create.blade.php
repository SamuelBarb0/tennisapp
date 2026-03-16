@extends('layouts.admin')
@section('title', 'Crear Banner')

@section('content')
<div class="max-w-2xl">
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('admin.banners.index') }}" class="text-gray-400 hover:text-gray-600"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg></a>
        <h2 class="text-xl font-bold">Crear Banner</h2>
    </div>

    <form action="{{ route('admin.banners.store') }}" method="POST" enctype="multipart/form-data" class="bg-white rounded-2xl p-6 border border-gray-100 shadow-sm space-y-5">
        @csrf
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Título</label>
            <input type="text" name="title" value="{{ old('title') }}" required class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-tc-primary focus:border-transparent outline-none transition-all">
            @error('title') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Subtítulo</label>
            <input type="text" name="subtitle" value="{{ old('subtitle') }}" class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-tc-primary focus:border-transparent outline-none">
            @error('subtitle') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Enlace (URL)</label>
            <input type="url" name="link" value="{{ old('link') }}" placeholder="https://..." class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-tc-primary focus:border-transparent outline-none">
            @error('link') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Orden</label>
                <input type="number" name="order" value="{{ old('order', 0) }}" min="0" class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-tc-primary focus:border-transparent outline-none">
                @error('order') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Imagen</label>
                <input type="file" name="image" accept="image/*" class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-xl text-sm file:mr-4 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-sm file:bg-tc-primary file:text-white">
                @error('image') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
        </div>
        <div>
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }} class="w-4 h-4 rounded border-gray-300 text-tc-primary focus:ring-tc-primary">
                <span class="text-sm">Activo</span>
            </label>
        </div>
        <div class="flex items-center gap-3 pt-2">
            <button type="submit" class="px-6 py-2.5 bg-tc-primary text-white rounded-xl text-sm font-medium hover:bg-tc-primary-hover transition-colors">Crear banner</button>
            <a href="{{ route('admin.banners.index') }}" class="px-6 py-2.5 bg-gray-100 text-gray-600 rounded-xl text-sm font-medium hover:bg-gray-200 transition-colors">Cancelar</a>
        </div>
    </form>
</div>
@endsection
