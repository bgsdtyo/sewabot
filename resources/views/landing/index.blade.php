<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SewaBot — Sewa Telegram Bot OTP WhatsApp</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-white text-brand-900">
    <div class="min-h-screen">
        <header class="border-b border-brand-200 bg-white/90 backdrop-blur">
            <div class="mx-auto flex h-16 max-w-6xl items-center justify-between px-4 sm:px-6 lg:px-8">
                <a href="{{ route('landing') }}" class="text-xl font-extrabold tracking-tight">SewaBot</a>
                <div class="flex items-center gap-3 text-sm">
                    @auth
                        <a href="{{ route('dashboard') }}" class="rounded-lg bg-brand-900 px-4 py-2 font-medium text-white">Dashboard</a>
                    @else
                        <a href="{{ route('login') }}" class="px-3 py-2 font-medium text-brand-700">Login</a>
                        <a href="{{ route('register') }}" class="rounded-lg bg-brand-900 px-4 py-2 font-medium text-white">Mulai Sewa</a>
                    @endauth
                </div>
            </div>
        </header>

        <section class="relative overflow-hidden border-b border-brand-200">
            <div class="absolute inset-0 bg-[length:28px_28px] bg-grid-fade opacity-70"></div>
            <div class="absolute -right-24 -top-24 h-72 w-72 rounded-full bg-accent-soft blur-3xl"></div>
            <div class="relative mx-auto grid max-w-6xl gap-12 px-4 py-20 sm:px-6 lg:grid-cols-2 lg:items-center lg:px-8 lg:py-28">
                <div>
                    <p class="mb-4 text-sm font-semibold uppercase tracking-[0.18em] text-accent">SewaBot</p>
                    <h1 class="text-4xl font-extrabold tracking-tight text-brand-900 sm:text-5xl">
                        Sewa Telegram Bot OTP WhatsApp
                    </h1>
                    <p class="mt-5 max-w-xl text-lg leading-relaxed text-brand-500">
                        Aktivasi cepat, bayar via QRIS manual, bot langsung aktif setelah admin konfirmasi.
                    </p>
                    <div class="mt-8 flex flex-wrap gap-3">
                        @auth
                            @if ($product)
                                <a href="{{ route('checkout.select-bot', $product) }}" class="rounded-xl bg-brand-900 px-6 py-3 text-sm font-semibold text-white shadow-soft hover:bg-brand-700">Sewa Bot Sekarang</a>
                            @endif
                        @else
                            <a href="{{ route('register') }}" class="rounded-xl bg-brand-900 px-6 py-3 text-sm font-semibold text-white shadow-soft hover:bg-brand-700">Register Gratis</a>
                            <a href="{{ route('login') }}" class="rounded-xl border border-brand-200 bg-white px-6 py-3 text-sm font-semibold text-brand-700 hover:bg-brand-50">Login</a>
                        @endauth
                    </div>
                </div>
                <div class="rounded-3xl border border-brand-200 bg-white p-8 shadow-soft">
                    <p class="text-sm font-semibold uppercase tracking-wide text-brand-500">Harga</p>
                    <div class="mt-6 space-y-5">
                        <div class="flex items-end justify-between border-b border-brand-100 pb-5">
                            <div>
                                <p class="font-semibold text-brand-900">Aktivasi + Sewa 30 Hari</p>
                                <p class="text-sm text-brand-500">Termasuk setup bot</p>
                            </div>
                            <p class="text-2xl font-extrabold">{{ $product?->formattedActivationPrice() ?? 'Rp150.000' }}</p>
                        </div>
                        <div class="flex items-end justify-between">
                            <div>
                                <p class="font-semibold text-brand-900">Setiap +30 Hari</p>
                                <p class="text-sm text-brand-500">Ditambahkan saat pilih durasi / perpanjang</p>
                            </div>
                            <p class="text-2xl font-extrabold">{{ $product?->formattedRenewalPrice() ?? 'Rp30.000' }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="mx-auto max-w-6xl px-4 py-20 sm:px-6 lg:px-8">
            <h2 class="text-2xl font-extrabold tracking-tight">Cara Kerja</h2>
            <p class="mt-2 max-w-2xl text-brand-500">Tanpa payment gateway. Transfer QRIS, upload bukti, admin approve, bot aktif.</p>
            <div class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ([
                    ['01', 'Register & Login', 'Buat akun lalu pilih Telegram Bot yang tersedia.'],
                    ['02', 'Bayar via QRIS', 'Scan QRIS, transfer sesuai nominal invoice.'],
                    ['03', 'Upload Bukti', 'Kirim bukti pembayaran, status menunggu konfirmasi.'],
                    ['04', 'Bot Aktif', 'Admin approve, subscription & bot langsung active.'],
                ] as [$num, $title, $desc])
                    <div class="border-t-2 border-brand-900 pt-5">
                        <p class="text-sm font-bold text-accent">{{ $num }}</p>
                        <h3 class="mt-2 font-semibold text-brand-900">{{ $title }}</h3>
                        <p class="mt-2 text-sm leading-relaxed text-brand-500">{{ $desc }}</p>
                    </div>
                @endforeach
            </div>
        </section>

        <footer class="border-t border-brand-200 bg-brand-50">
            <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-8 text-sm text-brand-500 sm:px-6 lg:px-8">
                <span class="font-semibold text-brand-900">SewaBot</span>
                <span>&copy; {{ date('Y') }} {{ $merchant }}</span>
            </div>
        </footer>
    </div>
</body>
</html>
