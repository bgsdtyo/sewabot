<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SewaBot — Sewa Telegram Bot Custom & Otomatisasi Bisnis</title>
    <meta name="description" content="Platform sewa Telegram Bot custom siap pakai dengan kustomisasi token BotFather, webhook performa tinggi, kontrol status running, dan panel dashboard modern.">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-white text-brand-900 selection:bg-brand-900 selection:text-white">
    <div class="min-h-screen flex flex-col">

        {{-- ==================== NAVBAR ==================== --}}
        <header class="sticky top-0 z-50 border-b border-brand-200 bg-white/95 backdrop-blur">
            <div class="mx-auto flex h-16 max-w-6xl items-center justify-between px-4 sm:px-6 lg:px-8">
                <a href="{{ route('landing') }}" class="flex items-center gap-2.5">
                    <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-brand-900 text-white shadow-sm">
                        <svg class="h-4.5 w-4.5 fill-current" viewBox="0 0 24 24">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm4.64 6.8c-.15 1.58-.8 5.42-1.13 7.19-.14.75-.42 1-.68 1.03-.58.05-1.02-.38-1.58-.75-.88-.58-1.38-.94-2.23-1.5-.99-.65-.35-1.01.22-1.59.15-.15 2.71-2.48 2.76-2.69a.2.2 0 00-.05-.18c-.06-.05-.14-.03-.21-.02-.09.02-1.49.95-4.22 2.79-.4.27-.76.41-1.08.4-.36-.01-1.04-.2-1.55-.37-.63-.2-1.12-.31-1.08-.66.02-.18.27-.36.74-.55 2.92-1.27 4.86-2.11 5.83-2.51 2.78-1.16 3.35-1.36 3.73-1.36.08 0 .27.02.39.12.1.08.13.19.14.27-.01.06.01.24 0 .38z"/>
                        </svg>
                    </div>
                    <span class="text-xl font-extrabold tracking-tight text-brand-900">SewaBot</span>
                </a>

                <nav class="hidden md:flex items-center gap-6 text-sm font-semibold text-brand-500">
                    <a href="#fitur" class="hover:text-brand-900 transition">Fitur</a>
                    <a href="#cara-kerja" class="hover:text-brand-900 transition">Cara Kerja</a>
                    <a href="#harga" class="hover:text-brand-900 transition">Harga</a>
                    <a href="#faq" class="hover:text-brand-900 transition">FAQ</a>
                </nav>

                <div class="flex items-center gap-3 text-sm">
                    @auth
                        <a href="{{ route('dashboard') }}" class="rounded-xl bg-brand-900 px-4 py-2 font-bold text-white shadow-soft hover:bg-brand-700 transition">
                            Dashboard
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="px-3 py-2 font-semibold text-brand-700 hover:text-brand-900 transition">
                            Login
                        </a>
                        <a href="{{ route('register') }}" class="rounded-xl bg-brand-900 px-4 py-2 font-bold text-white shadow-soft hover:bg-brand-700 transition">
                            Mulai Sewa
                        </a>
                    @endauth
                </div>
            </div>
        </header>

        <main class="flex-1">

            {{-- ==================== HERO SECTION ==================== --}}
            <section class="relative overflow-hidden border-b border-brand-200 bg-white">
                <div class="absolute inset-0 bg-[length:28px_28px] bg-grid-fade opacity-70"></div>
                <div class="absolute -right-24 -top-24 h-72 w-72 rounded-full bg-accent-soft blur-3xl"></div>

                <div class="relative mx-auto grid max-w-6xl gap-12 px-4 py-16 sm:px-6 lg:grid-cols-2 lg:items-center lg:px-8 lg:py-24">

                    {{-- Left Column: Headline & CTA --}}
                    <div>
                        <div class="inline-flex items-center gap-2 rounded-full border border-brand-200 bg-brand-50 px-3.5 py-1 mb-5">
                            <span class="h-2 w-2 rounded-full bg-emerald-500 animate-pulse"></span>
                            <span class="text-xs font-bold uppercase tracking-wider text-brand-700">Platform Sewa Bot Telegram Custom</span>
                        </div>

                        <h1 class="text-4xl font-extrabold tracking-tight text-brand-900 sm:text-5xl leading-[1.15]">
                            Sewa Telegram Bot Custom Siap Pakai
                        </h1>

                        <p class="mt-5 max-w-xl text-base sm:text-lg leading-relaxed text-brand-500">
                            Miliki bot Telegram profesional dengan identitas bisnis Anda. Pasang token BotFather langsung, kendalikan status operasional kapan saja, dan kelola semua fitur lewat dashboard web tanpa perlu koding.
                        </p>

                        <div class="mt-8 flex flex-wrap items-center gap-3">
                            @auth
                                @if ($product)
                                    <a href="{{ route('checkout.select-bot', $product) }}"
                                       class="rounded-xl bg-brand-900 px-6 py-3.5 text-sm font-extrabold text-white shadow-soft hover:bg-brand-700 transition">
                                        Sewa Bot Sekarang
                                    </a>
                                @else
                                    <a href="{{ route('dashboard') }}"
                                       class="rounded-xl bg-brand-900 px-6 py-3.5 text-sm font-extrabold text-white shadow-soft hover:bg-brand-700 transition">
                                        Buka Dashboard
                                    </a>
                                @endif
                            @else
                                <a href="{{ route('register') }}"
                                   class="rounded-xl bg-brand-900 px-6 py-3.5 text-sm font-extrabold text-white shadow-soft hover:bg-brand-700 transition">
                                    Mulai Sewa Bot
                                </a>
                                <a href="{{ route('login') }}"
                                   class="rounded-xl border border-brand-200 bg-white px-5 py-3.5 text-sm font-bold text-brand-700 hover:bg-brand-50 transition">
                                    Login ke Akun
                                </a>
                            @endauth
                        </div>

                        <div class="mt-8 grid grid-cols-2 gap-3 border-t border-brand-100 pt-5 text-xs font-semibold text-brand-700 sm:grid-cols-4">
                            <div class="flex items-center gap-1.5">
                                <span class="text-emerald-600 font-bold">✓</span>
                                <span>Token BotFather</span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <span class="text-emerald-600 font-bold">✓</span>
                                <span>Switch Running / Off</span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <span class="text-emerald-600 font-bold">✓</span>
                                <span>Webhook Cepat</span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <span class="text-emerald-600 font-bold">✓</span>
                                <span>Multi-Admin ID</span>
                            </div>
                        </div>
                    </div>

                    {{-- Right Column: Preview & Pricing Card --}}
                    <div>
                        <div class="rounded-3xl border border-brand-200 bg-white p-7 sm:p-8 shadow-soft space-y-6">

                            {{-- Bot Header --}}
                            <div class="flex items-center justify-between border-b border-brand-100 pb-4">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-brand-900 text-white font-black text-sm">
                                        SB
                                    </div>
                                    <div>
                                        <p class="text-base font-extrabold text-brand-900">{{ $product?->name ?? 'Bot Telegram Custom' }}</p>
                                        <p class="font-mono text-xs text-brand-500">@nama_bot_anda</p>
                                    </div>
                                </div>
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 border border-emerald-200 px-3 py-1 text-xs font-extrabold text-emerald-700">
                                    <span class="h-2 w-2 rounded-full bg-emerald-500 animate-pulse"></span>
                                    RUNNING
                                </span>
                            </div>

                            {{-- Price details --}}
                            <div class="space-y-4">
                                <div class="flex items-end justify-between border-b border-brand-100 pb-4">
                                    <div>
                                        <p class="font-bold text-brand-900 text-sm">Aktivasi + Sewa {{ $product?->duration_days ?? 30 }} Hari</p>
                                        <p class="text-xs text-brand-500">Setup webhook & server siap pakai</p>
                                    </div>
                                    <p class="text-2xl font-black text-brand-900">{{ $product?->formattedActivationPrice() ?? 'Rp150.000' }}</p>
                                </div>
                                <div class="flex items-end justify-between">
                                    <div>
                                        <p class="font-bold text-brand-900 text-sm">Perpanjangan (+30 Hari)</p>
                                        <p class="text-xs text-brand-500">Dapat diperpanjang fleksibel kapan saja</p>
                                    </div>
                                    <p class="text-xl font-extrabold text-brand-700">{{ $product?->formattedRenewalPrice() ?? 'Rp30.000' }}</p>
                                </div>
                            </div>

                            {{-- Feature list --}}
                            <div class="rounded-2xl bg-brand-50/70 p-4 text-xs space-y-2 text-brand-700">
                                <div class="flex items-center justify-between">
                                    <span>Kustomisasi Token Bot:</span>
                                    <span class="font-bold text-brand-900">Bebas dari @BotFather</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span>Kontrol Status Operasional:</span>
                                    <span class="font-bold text-emerald-700">Aktif / Nonaktif Instan</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span>Integrasi Webhook:</span>
                                    <span class="font-bold text-brand-900">HTTPS Dedicated Auto-Sync</span>
                                </div>
                            </div>

                            {{-- Card CTA --}}
                            <div>
                                @auth
                                    @if ($product)
                                        <a href="{{ route('checkout.select-bot', $product) }}"
                                           class="w-full flex items-center justify-center rounded-xl bg-brand-900 py-3 text-sm font-bold text-white shadow-soft hover:bg-brand-700 transition">
                                            Sewa Paket Ini
                                        </a>
                                    @endif
                                @else
                                    <a href="{{ route('register') }}"
                                       class="w-full flex items-center justify-center rounded-xl bg-brand-900 py-3 text-sm font-bold text-white shadow-soft hover:bg-brand-700 transition">
                                        Mulai Sekarang
                                    </a>
                                @endauth
                            </div>

                        </div>
                    </div>

                </div>
            </section>

            {{-- ==================== FITUR SECTION ==================== --}}
            <section id="fitur" class="border-b border-brand-200 bg-brand-50/50 py-20 lg:py-24">
                <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                    <div class="text-center max-w-2xl mx-auto space-y-2.5">
                        <div class="inline-flex items-center gap-1.5 rounded-full border border-brand-200 bg-white px-3.5 py-1 shadow-2xs">
                            <span class="h-1.5 w-1.5 rounded-full bg-accent"></span>
                            <span class="text-xs font-bold uppercase tracking-wider text-brand-700">Fitur Unggulan</span>
                        </div>
                        <h2 class="text-3xl font-extrabold tracking-tight text-brand-900 sm:text-4xl">
                            Semua Kendali Bot di Tangan Anda
                        </h2>
                        <p class="text-sm sm:text-base text-brand-500 leading-relaxed">
                            Dirancang dengan arsitektur modern agar bot Telegram Anda berjalan stabil, responsif, dan mudah dikonfigurasi kapan saja.
                        </p>
                    </div>

                    <div class="mt-14 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">

                        {{-- Card 1: Custom Token --}}
                        <div class="group relative flex flex-col justify-between rounded-2xl border border-brand-200/90 bg-white p-7 shadow-xs hover:border-brand-300 hover:shadow-soft transition duration-200">
                            <div>
                                <div class="flex items-center justify-between">
                                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-brand-50 border border-brand-100 text-brand-900 transition group-hover:scale-105 group-hover:bg-brand-900 group-hover:text-white">
                                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                                        </svg>
                                    </div>
                                    <span class="rounded-full bg-brand-50 border border-brand-100 px-2.5 py-0.5 text-[11px] font-bold text-brand-700">BotFather</span>
                                </div>
                                <h3 class="mt-5 text-lg font-extrabold text-brand-900">Custom Token BotFather</h3>
                                <p class="mt-2 text-sm leading-relaxed text-brand-500">
                                    Gunakan API token bot Telegram milik Anda sendiri. Identitas nama, username, dan foto profil 100% mewakili brand bisnis Anda.
                                </p>
                            </div>
                            <div class="mt-5 pt-4 border-t border-brand-100/70 flex items-center gap-2 text-xs font-semibold text-emerald-700">
                                <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                </svg>
                                <span>Verifikasi otomatis via getMe</span>
                            </div>
                        </div>

                        {{-- Card 2: Running Toggle --}}
                        <div class="group relative flex flex-col justify-between rounded-2xl border border-brand-200/90 bg-white p-7 shadow-xs hover:border-brand-300 hover:shadow-soft transition duration-200">
                            <div>
                                <div class="flex items-center justify-between">
                                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-50 border border-emerald-100 text-emerald-700 transition group-hover:scale-105 group-hover:bg-emerald-600 group-hover:text-white">
                                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                        </svg>
                                    </div>
                                    <span class="rounded-full bg-emerald-50 border border-emerald-200 px-2.5 py-0.5 text-[11px] font-extrabold text-emerald-700">1-Click Toggle</span>
                                </div>
                                <h3 class="mt-5 text-lg font-extrabold text-brand-900">Kontrol Running / Nonaktif</h3>
                                <p class="mt-2 text-sm leading-relaxed text-brand-500">
                                    Nyalakan atau istirahatkan operasional bot Anda secara instan dari dashboard. Webhook otomatis menyesuaikan status bot secara real-time.
                                </p>
                            </div>
                            <div class="mt-5 pt-4 border-t border-brand-100/70 flex items-center gap-2 text-xs font-semibold text-emerald-700">
                                <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                </svg>
                                <span>Pesan maintenance otomatis</span>
                            </div>
                        </div>

                        {{-- Card 3: Fast Webhook --}}
                        <div class="group relative flex flex-col justify-between rounded-2xl border border-brand-200/90 bg-white p-7 shadow-xs hover:border-brand-300 hover:shadow-soft transition duration-200">
                            <div>
                                <div class="flex items-center justify-between">
                                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-accent-soft/60 border border-accent/20 text-accent transition group-hover:scale-105 group-hover:bg-accent group-hover:text-white">
                                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                        </svg>
                                    </div>
                                    <span class="rounded-full bg-brand-50 border border-brand-100 px-2.5 py-0.5 text-[11px] font-bold text-brand-700">&lt; 150ms Latency</span>
                                </div>
                                <h3 class="mt-5 text-lg font-extrabold text-brand-900">Webhook Performa Tinggi</h3>
                                <p class="mt-2 text-sm leading-relaxed text-brand-500">
                                    Didukung infrastruktur server dedicated dengan HTTPS Webhook Telegram untuk menjamin pemrosesan pesan pengguna super kilat.
                                </p>
                            </div>
                            <div class="mt-5 pt-4 border-t border-brand-100/70 flex items-center gap-2 text-xs font-semibold text-emerald-700">
                                <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                </svg>
                                <span>Auto-Sync URL Webhook</span>
                            </div>
                        </div>

                        {{-- Card 4: Multi-Admin --}}
                        <div class="group relative flex flex-col justify-between rounded-2xl border border-brand-200/90 bg-white p-7 shadow-xs hover:border-brand-300 hover:shadow-soft transition duration-200">
                            <div>
                                <div class="flex items-center justify-between">
                                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-brand-50 border border-brand-100 text-brand-900 transition group-hover:scale-105 group-hover:bg-brand-900 group-hover:text-white">
                                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                                        </svg>
                                    </div>
                                    <span class="rounded-full bg-brand-50 border border-brand-100 px-2.5 py-0.5 text-[11px] font-bold text-brand-700">Multi-User</span>
                                </div>
                                <h3 class="mt-5 text-lg font-extrabold text-brand-900">Hak Akses Multi-Admin</h3>
                                <p class="mt-2 text-sm leading-relaxed text-brand-500">
                                    Daftarkan beberapa Telegram ID admin sekaligus untuk mengelola perintah khusus (/admin, monitoring member, dan manajemen saldo).
                                </p>
                            </div>
                            <div class="mt-5 pt-4 border-t border-brand-100/70 flex items-center gap-2 text-xs font-semibold text-emerald-700">
                                <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                </svg>
                                <span>Akses aman berbasis ID</span>
                            </div>
                        </div>

                        {{-- Card 5: Kontak Deposit --}}
                        <div class="group relative flex flex-col justify-between rounded-2xl border border-brand-200/90 bg-white p-7 shadow-xs hover:border-brand-300 hover:shadow-soft transition duration-200">
                            <div>
                                <div class="flex items-center justify-between">
                                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-brand-50 border border-brand-100 text-brand-900 transition group-hover:scale-105 group-hover:bg-brand-900 group-hover:text-white">
                                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                                        </svg>
                                    </div>
                                    <span class="rounded-full bg-brand-50 border border-brand-100 px-2.5 py-0.5 text-[11px] font-bold text-brand-700">Direct Contact</span>
                                </div>
                                <h3 class="mt-5 text-lg font-extrabold text-brand-900">Kontak Deposit Terintegrasi</h3>
                                <p class="mt-2 text-sm leading-relaxed text-brand-500">
                                    Tombol WhatsApp dan Telegram admin muncul secara otomatis pada menu bot untuk memudahkan konfirmasi pelanggan Anda.
                                </p>
                            </div>
                            <div class="mt-5 pt-4 border-t border-brand-100/70 flex items-center gap-2 text-xs font-semibold text-emerald-700">
                                <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                </svg>
                                <span>Support WhatsApp & Telegram</span>
                            </div>
                        </div>

                        {{-- Card 6: Perpanjangan Fleksibel --}}
                        <div class="group relative flex flex-col justify-between rounded-2xl border border-brand-200/90 bg-white p-7 shadow-xs hover:border-brand-300 hover:shadow-soft transition duration-200">
                            <div>
                                <div class="flex items-center justify-between">
                                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-brand-50 border border-brand-100 text-brand-900 transition group-hover:scale-105 group-hover:bg-brand-900 group-hover:text-white">
                                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                        </svg>
                                    </div>
                                    <span class="rounded-full bg-brand-50 border border-brand-100 px-2.5 py-0.5 text-[11px] font-bold text-brand-700">Tanpa Reset Data</span>
                                </div>
                                <h3 class="mt-5 text-lg font-extrabold text-brand-900">Perpanjangan Sewa Praktis</h3>
                                <p class="mt-2 text-sm leading-relaxed text-brand-500">
                                    Perpanjang masa aktif sewa bot Anda kapan saja tanpa khawatir kehilangan riwayat, saldo, atau pengaturan yang telah dibuat.
                                </p>
                            </div>
                            <div class="mt-5 pt-4 border-t border-brand-100/70 flex items-center gap-2 text-xs font-semibold text-emerald-700">
                                <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                </svg>
                                <span>Perpanjangan instan via QRIS</span>
                            </div>
                        </div>

                    </div>
                </div>
            </section>

            {{-- ==================== CARA KERJA ==================== --}}
            <section id="cara-kerja" class="border-b border-brand-200 bg-white py-20">
                <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                    <div class="max-w-2xl">
                        <p class="text-xs font-bold uppercase tracking-widest text-accent">Langkah Mudah</p>
                        <h2 class="mt-2 text-3xl font-extrabold tracking-tight text-brand-900">
                            Cara Kerja Layanan SewaBot
                        </h2>
                        <p class="mt-2 text-sm sm:text-base text-brand-500">
                            Proses aktivasi praktis tanpa ribet konfigurasi teknis server.
                        </p>
                    </div>

                    <div class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                        @foreach ([
                            ['01', 'Pilih Paket & Durasi', 'Daftar akun dan pilih bot beserta durasi sewa yang sesuai.'],
                            ['02', 'Bayar via QRIS', 'Scan QRIS, transfer nominal invoice, dan upload bukti pembayaran.'],
                            ['03', 'Input Token BotFather', 'Masukkan API token dari @BotFather di dashboard bot Anda.'],
                            ['04', 'Bot Langsung Running', 'Bot Telegram custom Anda otomatis aktif dan siap melayani 24/7.'],
                        ] as [$num, $title, $desc])
                            <div class="border-t-2 border-brand-900 pt-5 space-y-2">
                                <p class="text-sm font-bold text-accent font-mono">{{ $num }}</p>
                                <h3 class="font-extrabold text-brand-900 text-base">{{ $title }}</h3>
                                <p class="text-xs sm:text-sm leading-relaxed text-brand-500">{{ $desc }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>

            {{-- ==================== PAKET & HARGA ==================== --}}
            <section id="harga" class="border-b border-brand-200 bg-brand-50/40 py-20">
                <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                    <div class="text-center max-w-xl mx-auto mb-12 space-y-2">
                        <p class="text-xs font-bold uppercase tracking-widest text-accent">Biaya Transparan</p>
                        <h2 class="text-3xl font-extrabold tracking-tight text-brand-900">
                            Paket Sewa Bot Telegram
                        </h2>
                        <p class="text-sm text-brand-500">
                            Semua fitur sudah termasuk tanpa biaya tambahan tersembunyi.
                        </p>
                    </div>

                    <div class="max-w-md mx-auto rounded-3xl border-2 border-brand-900 bg-white p-8 shadow-soft space-y-6">
                        <div>
                            <span class="inline-block rounded-md bg-brand-900 px-2.5 py-1 text-[11px] font-bold text-white uppercase tracking-wider">
                                Paket Lengkap
                            </span>
                            <h3 class="mt-3 text-2xl font-black text-brand-900">{{ $product?->name ?? 'Sewa Bot Telegram Custom' }}</h3>
                            <p class="mt-1 text-xs sm:text-sm text-brand-500">Masa aktif bot, webhook dedicated, dan akses panel dashboard.</p>
                        </div>

                        <div class="border-y border-brand-100 py-5 space-y-3">
                            <div class="flex items-baseline justify-between">
                                <span class="text-sm font-bold text-brand-900">Aktivasi + 30 Hari:</span>
                                <span class="text-2xl font-black text-brand-900">{{ $product?->formattedActivationPrice() ?? 'Rp150.000' }}</span>
                            </div>
                            <div class="flex items-baseline justify-between text-xs sm:text-sm">
                                <span class="text-brand-600">Perpanjangan per +30 Hari:</span>
                                <span class="font-extrabold text-brand-800">{{ $product?->formattedRenewalPrice() ?? 'Rp30.000' }}</span>
                            </div>
                        </div>

                        <ul class="space-y-2.5 text-xs sm:text-sm text-brand-700">
                            <li class="flex items-center gap-2">
                                <span class="text-emerald-600 font-bold">✓</span>
                                <span>Token BotFather pribadi tanpa batas</span>
                            </li>
                            <li class="flex items-center gap-2">
                                <span class="text-emerald-600 font-bold">✓</span>
                                <span>Switch status Running / Nonaktif kapan saja</span>
                            </li>
                            <li class="flex items-center gap-2">
                                <span class="text-emerald-600 font-bold">✓</span>
                                <span>HTTPS Webhook auto-sync & auto-renew</span>
                            </li>
                            <li class="flex items-center gap-2">
                                <span class="text-emerald-600 font-bold">✓</span>
                                <span>Manajemen hak akses Admin Telegram</span>
                            </li>
                            <li class="flex items-center gap-2">
                                <span class="text-emerald-600 font-bold">✓</span>
                                <span>Server uptime 99.9% 24 jam nonstop</span>
                            </li>
                        </ul>

                        <div>
                            @auth
                                @if ($product)
                                    <a href="{{ route('checkout.select-bot', $product) }}"
                                       class="w-full flex items-center justify-center rounded-xl bg-brand-900 py-3.5 text-sm font-extrabold text-white shadow-soft hover:bg-brand-700 transition">
                                        Sewa Bot Sekarang
                                    </a>
                                @endif
                            @else
                                <a href="{{ route('register') }}"
                                   class="w-full flex items-center justify-center rounded-xl bg-brand-900 py-3.5 text-sm font-extrabold text-white shadow-soft hover:bg-brand-700 transition">
                                    Daftar & Sewa Bot
                                </a>
                            @endauth
                        </div>
                    </div>
                </div>
            </section>

            {{-- ==================== FAQ ==================== --}}
            <section id="faq" class="border-b border-brand-200 bg-white py-20">
                <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
                    <div class="text-center max-w-xl mx-auto mb-12 space-y-2">
                        <p class="text-xs font-bold uppercase tracking-widest text-accent">Tanya Jawab</p>
                        <h2 class="text-3xl font-extrabold tracking-tight text-brand-900">
                            Frequently Asked Questions
                        </h2>
                    </div>

                    <div class="space-y-4">
                        @foreach ([
                            [
                                'q' => 'Apakah saya perlu keahlian koding untuk menggunakan bot ini?',
                                'a' => 'Tidak perlu. Bot sudah siap pakai. Anda cukup memasukkan Token BotFather dan mengelola semua pengaturannya lewat dashboard web.'
                            ],
                            [
                                'q' => 'Bagaimana cara mendapatkan Token Bot Telegram?',
                                'a' => 'Buka Telegram, cari @BotFather, ketik /newbot untuk membuat bot baru, lalu salin API token yang diberikan dan masukkan ke menu Konfigurasi Bot.'
                            ],
                            [
                                'q' => 'Bisakah bot dinonaktifkan sementara?',
                                'a' => 'Bisa. Di dashboard terdapat tombol switch status Running vs Nonaktif. Saat dinonaktifkan, webhook dilepas dan bot akan berhenti melayani pesan sementara.'
                            ],
                            [
                                'q' => 'Metode pembayaran apa saja yang didukung?',
                                'a' => 'Kami mendukung pembayaran QRIS manual yang dapat dibayar dari aplikasi m-banking (BCA, Mandiri, BRI, BNI, dll.) maupun e-wallet (GoPay, OVO, DANA, ShopeePay).'
                            ],
                            [
                                'q' => 'Bagaimana jika masa sewa bot habis?',
                                'a' => 'Anda dapat melakukan perpanjangan masa aktif kapan saja di dashboard. Data konfigurasi dan member Anda tetap tersimpan dengan aman.'
                            ],
                        ] as $faq)
                            <div class="rounded-2xl border border-brand-200 bg-brand-50/40 p-5 sm:p-6">
                                <h3 class="font-extrabold text-brand-900 text-sm sm:text-base">{{ $faq['q'] }}</h3>
                                <p class="mt-2 text-xs sm:text-sm leading-relaxed text-brand-600">{{ $faq['a'] }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>

        </main>

        {{-- ==================== FOOTER ==================== --}}
        <footer class="border-t border-brand-200 bg-brand-50 py-10">
            <div class="mx-auto flex max-w-6xl flex-col gap-6 px-4 sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8 text-sm text-brand-500">
                <div class="flex items-center gap-2.5">
                    <span class="font-extrabold text-brand-900">SewaBot</span>
                    <span>·</span>
                    <span>Platform Sewa Telegram Bot Custom</span>
                </div>
                <div>
                    &copy; {{ date('Y') }} {{ $merchant }}. All rights reserved.
                </div>
            </div>
        </footer>

    </div>
</body>
</html>
