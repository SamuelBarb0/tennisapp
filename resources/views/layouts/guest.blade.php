<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Tennis Challenge') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="bg-gray-50 antialiased">
    <div class="min-h-screen flex flex-col items-center justify-center px-4 py-12">
        <div class="mb-8">
            <a href="{{ route('home') }}">
                <img src="{{ asset('images/image-removebg-preview.png') }}" alt="Tennis Challenge" class="h-14">
            </a>
        </div>
        <div class="w-full max-w-md bg-white rounded-3xl shadow-xl shadow-gray-200/50 p-8 sm:p-10">
            {{ $slot }}
        </div>
        <p class="mt-6 text-sm text-gray-400">&copy; {{ date('Y') }} Tennis Challenge</p>
    </div>
</body>
</html>
