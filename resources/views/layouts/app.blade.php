<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name', 'SewaBot'))</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-brand-50 text-brand-900">
    <div class="min-h-screen @auth pb-24 md:pb-0 @endauth">
        @include('layouts.navigation')

        @isset($header)
            <header class="border-b border-brand-200 bg-white">
                <div class="mx-auto max-w-6xl px-4 py-6 sm:px-6 lg:px-8">
                    {{ $header }}
                </div>
            </header>
        @endisset

        <main>
            @if (session('info'))
                <div class="mx-auto max-w-6xl px-4 pt-6 sm:px-6 lg:px-8">
                    <div class="rounded-lg border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-800">
                        {{ session('info') }}
                    </div>
                </div>
            @endif
            {{ $slot }}
        </main>
    </div>

    @include('layouts.bottom-nav')
</body>
</html>
