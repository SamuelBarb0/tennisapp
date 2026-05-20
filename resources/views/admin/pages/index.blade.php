@extends('layouts.admin')
@section('title', 'Páginas del sitio')

@section('content')
<div class="mb-6">
    <h2 class="text-xl font-bold">Páginas del sitio</h2>
    <p class="text-sm text-gray-500 mt-1">Edita el contenido de las páginas legales y de contacto.</p>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    @foreach($pages as $page)
    <a href="{{ route('admin.pages.edit', $page) }}" class="block bg-white border border-gray-100 rounded-2xl p-5 shadow-sm hover:shadow-md transition group">
        <div class="flex items-start justify-between gap-3">
            <div class="flex-1 min-w-0">
                <h3 class="font-bold text-tc-primary group-hover:underline">{{ $page->title }}</h3>
                <p class="text-xs text-gray-400 mt-1">/{{ $page->slug }}</p>
                <p class="text-xs text-gray-500 mt-3 line-clamp-2">{{ Str::limit(strip_tags($page->content), 120) }}</p>
            </div>
            <div class="flex flex-col items-end gap-2">
                @if($page->is_published)
                    <span class="px-2.5 py-1 bg-green-100 text-green-700 text-[10px] font-bold rounded-full">PUBLICADA</span>
                @else
                    <span class="px-2.5 py-1 bg-gray-100 text-gray-500 text-[10px] font-bold rounded-full">OCULTA</span>
                @endif
                <span class="text-[10px] text-gray-400">{{ $page->updated_at?->diffForHumans() }}</span>
            </div>
        </div>
    </a>
    @endforeach
</div>
@endsection
