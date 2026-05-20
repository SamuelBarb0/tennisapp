@extends('layouts.app')
@section('title', $page->title)

@section('content')
<div class="max-w-3xl mx-auto px-4 sm:px-6 py-12">
    <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-8 md:p-12">
        <h1 class="text-3xl md:text-4xl font-black text-tc-primary mb-6">{{ $page->title }}</h1>
        <div class="prose prose-sm md:prose-base max-w-none prose-headings:text-tc-primary prose-a:text-tc-primary">
            {!! $page->content !!}
        </div>
        <p class="mt-10 pt-6 border-t border-gray-100 text-xs text-gray-400">
            Última actualización: {{ $page->updated_at?->format('d/m/Y') }}
        </p>
    </div>
</div>
@endsection
