@extends('layouts.app')
@section('title', $page->title)

@php
    $email = trim(\App\Models\Setting::get('contact_email', ''));
    $phone = trim(\App\Models\Setting::get('contact_phone', ''));
    $city  = trim(\App\Models\Setting::get('contact_city',  ''));

    // Normalize phone for tel: and WhatsApp links (digits only)
    $phoneDigits = preg_replace('/\D+/', '', $phone);
    $waLink   = $phoneDigits ? "https://wa.me/{$phoneDigits}" : null;
    $telLink  = $phone       ? 'tel:' . preg_replace('/\s+/', '', $phone) : null;

    // Social handles
    $instaHandle = trim(\App\Models\Setting::get('instagram', ''), '@ ');
    $twHandle    = trim(\App\Models\Setting::get('twitter',  ''), '@ ');
    $fbValue     = trim(\App\Models\Setting::get('facebook', ''));
    $ttHandle    = trim(\App\Models\Setting::get('tiktok',   ''), '@ ');

    $socials = array_filter([
        'Instagram' => $instaHandle ? 'https://instagram.com/' . $instaHandle : null,
        'Twitter'   => $twHandle    ? 'https://twitter.com/'  . $twHandle    : null,
        'Facebook'  => $fbValue && str_starts_with($fbValue, 'http') ? $fbValue : ($fbValue ? 'https://facebook.com/' . $fbValue : null),
        'TikTok'    => $ttHandle    ? 'https://tiktok.com/@' . $ttHandle     : null,
    ]);
@endphp

@section('content')
<div class="max-w-3xl mx-auto px-4 sm:px-6 py-12">
    <div class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden">
        {{-- Header --}}
        <div class="bg-gradient-to-br from-tc-primary to-tc-primary-hover px-8 py-12 text-center text-white">
            <h1 class="text-3xl md:text-4xl font-black mb-2">{{ $page->title }}</h1>
            <p class="text-white/70 text-sm">Estamos aquí para ayudarte</p>
        </div>

        {{-- Editable intro (CMS content) --}}
        @if(trim(strip_tags($page->content)))
        <div class="px-8 pt-8 pb-2">
            <div class="prose prose-sm md:prose-base max-w-none prose-headings:text-tc-primary prose-a:text-tc-primary">
                {!! $page->content !!}
            </div>
        </div>
        @endif

        {{-- Contact methods --}}
        <div class="px-8 py-6 grid sm:grid-cols-2 gap-4">
            @if($email)
            <a href="mailto:{{ $email }}" class="flex items-start gap-3 p-4 rounded-2xl bg-gray-50 hover:bg-tc-primary/5 transition group">
                <div class="w-10 h-10 rounded-xl bg-tc-primary/10 flex items-center justify-center shrink-0 group-hover:bg-tc-primary group-hover:text-white transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                </div>
                <div class="min-w-0 flex-1">
                    <div class="text-[10px] font-bold uppercase tracking-widest text-gray-400 mb-0.5">Correo</div>
                    <div class="text-sm font-semibold text-tc-primary break-all">{{ $email }}</div>
                </div>
            </a>
            @endif

            @if($waLink)
            <a href="{{ $waLink }}" target="_blank" rel="noopener" class="flex items-start gap-3 p-4 rounded-2xl bg-gray-50 hover:bg-green-50 transition group">
                <div class="w-10 h-10 rounded-xl bg-green-100 text-green-600 flex items-center justify-center shrink-0 group-hover:bg-green-500 group-hover:text-white transition">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                </div>
                <div class="min-w-0 flex-1">
                    <div class="text-[10px] font-bold uppercase tracking-widest text-gray-400 mb-0.5">WhatsApp</div>
                    <div class="text-sm font-semibold text-tc-primary">{{ $phone }}</div>
                </div>
            </a>
            @elseif($telLink)
            <a href="{{ $telLink }}" class="flex items-start gap-3 p-4 rounded-2xl bg-gray-50 hover:bg-tc-primary/5 transition group">
                <div class="w-10 h-10 rounded-xl bg-tc-primary/10 flex items-center justify-center shrink-0 group-hover:bg-tc-primary group-hover:text-white transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                </div>
                <div class="min-w-0 flex-1">
                    <div class="text-[10px] font-bold uppercase tracking-widest text-gray-400 mb-0.5">Teléfono</div>
                    <div class="text-sm font-semibold text-tc-primary">{{ $phone }}</div>
                </div>
            </a>
            @endif

            @if($city)
            <div class="flex items-start gap-3 p-4 rounded-2xl bg-gray-50 sm:col-span-2">
                <div class="w-10 h-10 rounded-xl bg-tc-primary/10 flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-tc-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </div>
                <div class="min-w-0 flex-1">
                    <div class="text-[10px] font-bold uppercase tracking-widest text-gray-400 mb-0.5">Ubicación</div>
                    <div class="text-sm font-semibold text-tc-primary">{{ $city }}</div>
                </div>
            </div>
            @endif
        </div>

        {{-- Social --}}
        @if(count($socials))
        <div class="px-8 pb-8 border-t border-gray-100 pt-6">
            <div class="text-[10px] font-bold uppercase tracking-widest text-gray-400 text-center mb-4">Síguenos en redes</div>
            <div class="flex flex-wrap justify-center gap-3">
                @foreach($socials as $label => $url)
                <a href="{{ $url }}" target="_blank" rel="noopener" class="px-5 py-2.5 bg-tc-primary text-white text-sm font-bold rounded-full hover:bg-tc-primary-hover transition shadow-sm hover:shadow">
                    {{ $label }}
                </a>
                @endforeach
            </div>
        </div>
        @endif

        <p class="px-8 pb-6 text-xs text-gray-400 text-center">
            Última actualización: {{ $page->updated_at?->format('d/m/Y') }}
        </p>
    </div>
</div>
@endsection
