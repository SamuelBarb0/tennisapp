@extends('layouts.app')
@section('title', 'Reglas')

@section('content')
<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
    <div class="mb-8">
        <h1 class="text-3xl md:text-4xl font-bold">Reglas del Juego</h1>
        <p class="text-gray-500 mt-2">Todo lo que necesitas saber sobre cómo funciona Tennis Challenge</p>
    </div>

    <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-8 md:p-10 prose prose-sm max-w-none
        prose-headings:text-tc-primary prose-headings:font-bold
        prose-h2:text-2xl prose-h2:mt-8 prose-h2:mb-4
        prose-h3:text-lg prose-h3:mt-6 prose-h3:mb-3
        prose-p:text-gray-600 prose-p:leading-relaxed
        prose-li:text-gray-600
        prose-strong:text-gray-900
        prose-ul:space-y-2 prose-ol:space-y-2">
        {!! $rules !!}
    </div>

    @guest
    <div class="mt-10 text-center">
        <a href="{{ route('register') }}" class="inline-flex items-center gap-2 px-8 py-3.5 bg-tc-primary text-white rounded-full text-base font-semibold hover:bg-tc-primary-hover transition-all shadow-lg">
            Comenzar a jugar
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        </a>
    </div>
    @endguest
</div>
@endsection
