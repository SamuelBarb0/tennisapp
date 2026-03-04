<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'TennisApp') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="bg-gray-50 antialiased">
    <div class="min-h-screen flex flex-col items-center justify-center px-4 py-12">
        <div class="mb-8">
            <a href="{{ route('home') }}" class="flex items-center gap-3">
                <div class="w-12 h-12 bg-[#0071E3] rounded-2xl flex items-center justify-center shadow-lg shadow-blue-500/25">
                    <svg class="w-7 h-7 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><circle cx="12" cy="12" r="10" stroke-width="2"/><path d="M8 12a4 4 0 018 0" stroke-width="2"/></svg>
                </div>
                <span class="text-2xl font-bold tracking-tight">TennisApp</span>
            </a>
        </div>
        <div class="w-full max-w-md bg-white rounded-3xl shadow-xl shadow-gray-200/50 p-8 sm:p-10">
            {{ $slot }}
        </div>
        <p class="mt-6 text-sm text-gray-400">&copy; {{ date('Y') }} TennisApp</p>
    </div>
</body>
</html>
