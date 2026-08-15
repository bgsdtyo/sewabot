<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'SewaBot') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-white text-brand-900">
    <div class="flex min-h-screen flex-col items-center justify-center bg-[length:24px_24px] bg-grid-fade px-4">
        <div class="mb-8 text-center">
            <a href="{{ route('landing') }}" class="text-2xl font-extrabold tracking-tight text-brand-900">SewaBot</a>
            <p class="mt-1 text-sm text-brand-500">Sewa Telegram Bot OTP WhatsApp</p>
        </div>
        <div class="w-full max-w-md rounded-2xl border border-brand-200 bg-white p-8 shadow-soft">
            {{ $slot }}
        </div>
    </div>
</body>
</html>
