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
    <div class="min-h-screen @auth pb-28 md:pb-32 @endauth">
        @include('layouts.navigation')

        @isset($header)
            <header class="border-b border-brand-200 bg-white">
                <div class="mx-auto max-w-6xl px-4 py-6 sm:px-6 lg:px-8">
                    {{ $header }}
                </div>
            </header>
        @endisset

        <main class="min-w-0 overflow-x-hidden">
            <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                @if (session('success'))
                    <div class="mt-6 flex items-center gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold text-emerald-800 shadow-sm">
                        <svg class="h-5 w-5 shrink-0 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        <span>{{ session('success') }}</span>
                    </div>
                @endif

                @if (session('status'))
                    <div class="mt-6 flex items-center gap-3 rounded-2xl border border-sky-200 bg-sky-50 p-4 text-sm font-semibold text-sky-800 shadow-sm">
                        <svg class="h-5 w-5 shrink-0 text-sky-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span>{{ session('status') }}</span>
                    </div>
                @endif

                @if (session('error'))
                    <div class="mt-6 flex items-center gap-3 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm font-semibold text-rose-800 shadow-sm">
                        <svg class="h-5 w-5 shrink-0 text-rose-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span>{{ session('error') }}</span>
                    </div>
                @endif

                @if (session('info'))
                    <div class="mt-6 flex items-center gap-3 rounded-2xl border border-sky-200 bg-sky-50 p-4 text-sm font-semibold text-sky-800 shadow-sm">
                        <svg class="h-5 w-5 shrink-0 text-sky-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span>{{ session('info') }}</span>
                    </div>
                @endif

                @if ($errors->any())
                    <div class="mt-6 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm font-medium text-rose-800 shadow-sm">
                        <p class="font-bold">Terjadi kesalahan:</p>
                        <ul class="mt-1 list-disc list-inside space-y-1">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>

            {{ $slot }}
        </main>
    </div>

    @include('layouts.bottom-nav')
</body>
</html>
