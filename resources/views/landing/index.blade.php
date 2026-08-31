<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SewaBot — Platform Sewa Telegram Bot Custom & Otomatisasi Bisnis</title>
    <meta name="description" content="Platform sewa Bot Telegram custom siap pakai dengan kustomisasi token BotFather, webhook performa tinggi, kontrol status running, dan panel dashboard modern.">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800,900&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-white text-brand-900 selection:bg-brand-900 selection:text-white">
    <div class="min-h-screen flex flex-col">

        {{-- ==================== NAVBAR ==================== --}}
        <header class="sticky top-0 z-50 border-b border-brand-200/80 bg-white/85 backdrop-blur-md transition-all">
            <div class="mx-auto flex h-18 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
                <div class="flex items-center gap-8">
                    <a href="{{ route('landing') }}" class="group flex items-center gap-2.5">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-900 text-white shadow-xs transition group-hover:scale-105 group-hover:bg-brand-700">
                            <svg class="h-5 w-5 fill-current" viewBox="0 0 24 24">
                                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm4.64 6.8c-.15 1.58-.8 5.42-1.13 7.19-.14.75-.42 1-.68 1.03-.58.05-1.02-.38-1.58-.75-.88-.58-1.38-.94-2.23-1.5-.99-.65-.35-1.01.22-1.59.15-.15 2.71-2.48 2.76-2.69a.2.2 0 00-.05-.18c-.06-.05-.14-.03-.21-.02-.09.02-1.49.95-4.22 2.79-.4.27-.76.41-1.08.4-.36-.01-1.04-.2-1.55-.37-.63-.2-1.12-.31-1.08-.66.02-.18.27-.36.74-.55 2.92-1.27 4.86-2.11 5.83-2.51 2.78-1.16 3.35-1.36 3.73-1.36.08 0 .27.02.39.12.1.08.13.19.14.27-.01.06.01.24 0 .38z"/>
                            </svg>
                        </div>
                        <div>
                            <span class="text-xl font-extrabold tracking-tight text-brand-900 block leading-none">SewaBot</span>
                            <span class="text-[10px] font-bold uppercase tracking-widest text-accent mt-0.5 block">Custom Bot Platform</span>
                        </div>
                    </a>

                    <nav class="hidden md:flex items-center gap-6 text-sm font-semibold text-brand-500">
                        <a href="#fitur" class="hover:text-brand-900 transition">Fitur</a>
                        <a href="#keunggulan" class="hover:text-brand-900 transition">Keunggulan</a>
                        <a href="#cara-kerja" class="hover:text-brand-900 transition">Cara Kerja</a>
                        <a href="#harga" class="hover:text-brand-900 transition">Paket & Harga</a>
                        <a href="#faq" class="hover:text-brand-900 transition">FAQ</a>
                    </nav>
                </div>

                <div class="flex items-center gap-3">
                    @auth
                        <a href="{{ route('dashboard') }}"
                           class="inline-flex items-center gap-2 rounded-xl bg-brand-900 px-4.5 py-2.5 text-sm font-bold text-white shadow-soft hover:bg-brand-700 transition active:scale-95">
                            <span>Buka Dashboard</span>
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                            </svg>
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="px-4 py-2 text-sm font-semibold text-brand-700 hover:text-brand-900 transition">
                            Masuk
                        </a>
                        <a href="{{ route('register') }}"
                           class="inline-flex items-center gap-1.5 rounded-xl bg-brand-900 px-4.5 py-2.5 text-sm font-bold text-white shadow-soft hover:bg-brand-700 transition active:scale-95">
                            <span>Mulai Sewa</span>
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" />
                            </svg>
                        </a>
                    @endauth
                </div>
            </div>
        </header>

        <main class="flex-1">

            {{-- ==================== HERO SECTION ==================== --}}
            <section class="relative overflow-hidden border-b border-brand-200 bg-white">
                {{-- Decorative Background Elements --}}
                <div class="absolute inset-0 bg-[length:32px_32px] bg-grid-fade opacity-70 pointer-events-none"></div>
                <div class="absolute -right-32 -top-32 h-96 w-96 rounded-full bg-accent-soft/80 blur-3xl pointer-events-none"></div>
                <div class="absolute -left-32 top-1/2 h-80 w-80 rounded-full bg-brand-100/80 blur-3xl pointer-events-none"></div>

                <div class="relative mx-auto grid max-w-7xl gap-12 px-4 py-16 sm:px-6 lg:grid-cols-12 lg:items-center lg:gap-8 lg:px-8 lg:py-24">

                    {{-- Left Column: Copywriting & CTA --}}
                    <div class="lg:col-span-7 space-y-6">
                        <div class="inline-flex items-center gap-2 rounded-full border border-brand-200 bg-brand-50 px-3.5 py-1.5 shadow-2xs">
                            <span class="flex h-2 w-2 rounded-full bg-emerald-500 animate-pulse"></span>
                            <span class="text-xs font-bold uppercase tracking-wider text-brand-700">Platform Sewa Bot Telegram Custom</span>
                        </div>

                        <h1 class="text-4xl font-extrabold tracking-tight text-brand-900 sm:text-5xl lg:text-6xl leading-[1.12]">
                            Sewa Telegram Bot Custom <span class="text-accent underline decoration-accent/30 decoration-wavy underline-offset-8">Siap Pakai</span> & Otomatis 24/7
                        </h1>

                        <p class="max-w-2xl text-base sm:text-lg leading-relaxed text-brand-500">
                            Solusi praktis untuk memiliki bot Telegram dengan identitas bisnis Anda sendiri. Pasang token BotFather langsung, kendalikan status operasional bot kapan saja, dan kelola semua pengaturan lewat dashboard web modern tanpa perlu koding.
                        </p>

                        <div class="flex flex-wrap items-center gap-3.5 pt-2">
                            @auth
                                @if ($product)
                                    <a href="{{ route('checkout.select-bot', $product) }}"
                                       class="inline-flex items-center justify-center gap-2 rounded-xl bg-brand-900 px-6 py-3.5 text-sm font-extrabold text-white shadow-soft hover:bg-brand-700 transition active:scale-95 sm:w-auto">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                        </svg>
                                        <span>Sewa Bot Sekarang</span>
                                    </a>
                                @else
                                    <a href="{{ route('dashboard') }}"
                                       class="inline-flex items-center justify-center gap-2 rounded-xl bg-brand-900 px-6 py-3.5 text-sm font-extrabold text-white shadow-soft hover:bg-brand-700 transition active:scale-95">
                                        <span>Buka Dashboard</span>
                                    </a>
                                @endif
                            @else
                                <a href="{{ route('register') }}"
                                   class="inline-flex items-center justify-center gap-2 rounded-xl bg-brand-900 px-6 py-3.5 text-sm font-extrabold text-white shadow-soft hover:bg-brand-700 transition active:scale-95">
                                    <span>Mulai Sewa Bot</span>
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                                    </svg>
                                </a>
                                <a href="#cara-kerja"
                                   class="inline-flex items-center justify-center rounded-xl border border-brand-200 bg-white px-5 py-3.5 text-sm font-bold text-brand-700 hover:bg-brand-50 transition active:scale-95">
                                    Lihat Cara Kerja
                                </a>
                            @endauth
                        </div>

                        {{-- Value Badges --}}
                        <div class="grid grid-cols-2 gap-3 pt-4 sm:grid-cols-4 border-t border-brand-100">
                            <div class="flex items-center gap-2 text-xs font-semibold text-brand-700">
                                <svg class="h-4 w-4 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                </svg>
                                <span>Token BotFather</span>
                            </div>
                            <div class="flex items-center gap-2 text-xs font-semibold text-brand-700">
                                <svg class="h-4 w-4 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                </svg>
                                <span>Switch Running / Off</span>
                            </div>
                            <div class="flex items-center gap-2 text-xs font-semibold text-brand-700">
                                <svg class="h-4 w-4 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                </svg>
                                <span>Webhook Cepat</span>
                            </div>
                            <div class="flex items-center gap-2 text-xs font-semibold text-brand-700">
                                <svg class="h-4 w-4 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                </svg>
                                <span>Multi-Admin ID</span>
                            </div>
                        </div>
                    </div>

                    {{-- Right Column: Interactive Bot Mockup Showcase --}}
                    <div class="lg:col-span-5">
                        <div class="relative mx-auto w-full max-w-md rounded-3xl border border-brand-200 bg-white p-6 shadow-soft ring-1 ring-brand-900/5">

                            {{-- Mockup Top Status Bar --}}
                            <div class="flex items-center justify-between border-b border-brand-100 pb-4">
                                <div class="flex items-center gap-3">
                                    <div class="relative flex h-10 w-10 items-center justify-center rounded-2xl bg-brand-900 text-white font-black text-sm">
                                        SB
                                        <span class="absolute -bottom-0.5 -right-0.5 h-3 w-3 rounded-full border-2 border-white bg-emerald-500"></span>
                                    </div>
                                    <div>
                                        <p class="text-sm font-extrabold text-brand-900">Custom Telegram Bot</p>
                                        <p class="font-mono text-xs text-brand-500">@bisnis_anda_bot</p>
                                    </div>
                                </div>
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 border border-emerald-200 px-2.5 py-1 text-[11px] font-extrabold text-emerald-700">
                                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                    RUNNING
                                </span>
                            </div>

                            {{-- Mockup Interactive Panel Body --}}
                            <div class="mt-4 space-y-3">
                                <div class="rounded-xl border border-brand-100 bg-brand-50/70 p-3.5">
                                    <div class="flex items-center justify-between text-xs">
                                        <span class="text-brand-500 font-medium">Token BotFather:</span>
                                        <span class="font-mono font-bold text-emerald-700">79812••••••••c34k (Valid)</span>
                                    </div>
                                    <div class="mt-2 flex items-center justify-between text-xs">
                                        <span class="text-brand-500 font-medium">Webhook Status:</span>
                                        <span class="font-bold text-brand-900">✓ HTTPS Live Connected</span>
                                    </div>
                                    <div class="mt-2 flex items-center justify-between text-xs">
                                        <span class="text-brand-500 font-medium">Kecepatan Respons:</span>
                                        <span class="font-bold text-accent">&lt; 120 ms</span>
                                    </div>
                                </div>

                                {{-- Simulated Telegram Message Bubble --}}
                                <div class="space-y-2 rounded-2xl border border-brand-200 bg-white p-4 text-xs">
                                    <div class="flex items-center justify-between text-[11px] text-brand-400 font-mono">
                                        <span>SIMULASI BOT TELEGRAM</span>
                                        <span>Sekarang</span>
                                    </div>
                                    <div class="rounded-xl bg-brand-50 p-3 text-brand-800 space-y-1.5">
                                        <p class="font-bold text-brand-900">🤖 Halo! Selamat datang di Bot Bisnis Anda.</p>
                                        <p class="text-brand-600">Layanan otomatisasi siap melayani kebutuhan pelanggan Anda secara real-time tanpa henti.</p>
                                    </div>
                                    <div class="grid grid-cols-2 gap-1.5 pt-1">
                                        <div class="rounded-lg bg-brand-100/70 py-1.5 text-center font-bold text-brand-900 text-[11px]">
                                            📋 Menu Layanan
                                        </div>
                                        <div class="rounded-lg bg-brand-100/70 py-1.5 text-center font-bold text-brand-900 text-[11px]">
                                            💳 Cek Saldo & Transaksi
                                        </div>
                                    </div>
                                </div>

                                {{-- Quick Stats Card --}}
                                <div class="grid grid-cols-2 gap-3 pt-1">
                                    <div class="rounded-xl border border-brand-100 bg-brand-50/50 p-3 text-center">
                                        <p class="text-[11px] font-bold text-brand-500">Server Uptime</p>
                                        <p class="text-lg font-black text-brand-900">99.9%</p>
                                    </div>
                                    <div class="rounded-xl border border-brand-100 bg-brand-50/50 p-3 text-center">
                                        <p class="text-[11px] font-bold text-brand-500">Aktivasi</p>
                                        <p class="text-lg font-black text-emerald-700">Instan</p>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>

                </div>
            </section>

            {{-- ==================== KEUNGGULAN UTAMA ==================== --}}
            <section id="fitur" class="border-b border-brand-200 bg-brand-50/40 py-20">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="text-center max-w-3xl mx-auto space-y-3">
                        <p class="text-xs font-bold uppercase tracking-widest text-accent">Fitur & Kemudahan</p>
                        <h2 class="text-3xl font-extrabold tracking-tight text-brand-900 sm:text-4xl">
                            Semua yang Anda Butuhkan untuk Mengelola Bot Telegram
                        </h2>
                        <p class="text-base text-brand-500">
                            Dirancang khusus agar Anda dapat memiliki dan mengatur bot Telegram profesional dengan mudah, fleksibel, dan aman.
                        </p>
                    </div>

                    <div class="mt-14 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">

                        {{-- Feature Card 1 --}}
                        <div class="rounded-2xl border border-brand-200 bg-white p-7 shadow-xs hover:border-brand-300 hover:shadow-soft transition">
                            <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-brand-900 text-white mb-5">
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                                </svg>
                            </div>
                            <h3 class="text-lg font-extrabold text-brand-900">Custom Token BotFather</h3>
                            <p class="mt-2 text-sm leading-relaxed text-brand-500">
                                Gunakan token bot pribadi Anda langsung dari @BotFather. Nama bot, username, dan profil sepenuhnya milik brand Anda.
                            </p>
                        </div>

                        {{-- Feature Card 2 --}}
                        <div class="rounded-2xl border border-brand-200 bg-white p-7 shadow-xs hover:border-brand-300 hover:shadow-soft transition">
                            <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-emerald-700 text-white mb-5">
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                </svg>
                            </div>
                            <h3 class="text-lg font-extrabold text-brand-900">Kontrol Running / Nonaktif</h3>
                            <p class="mt-2 text-sm leading-relaxed text-brand-500">
                                Butuh mengistirahatkan bot atau melakukan maintenance? Aktifkan atau nonaktifkan bot seketika dengan 1 klik dari dashboard.
                            </p>
                        </div>

                        {{-- Feature Card 3 --}}
                        <div class="rounded-2xl border border-brand-200 bg-white p-7 shadow-xs hover:border-brand-300 hover:shadow-soft transition">
                            <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-accent text-white mb-5">
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                </svg>
                            </div>
                            <h3 class="text-lg font-extrabold text-brand-900">Webhook Engine Berkecepatan Tinggi</h3>
                            <p class="mt-2 text-sm leading-relaxed text-brand-500">
                                Integrasi otomatis HTTPS Webhook Telegram dengan latensi rendah untuk memastikan setiap interaksi pengguna direspons kilat.
                            </p>
                        </div>

                        {{-- Feature Card 4 --}}
                        <div class="rounded-2xl border border-brand-200 bg-white p-7 shadow-xs hover:border-brand-300 hover:shadow-soft transition">
                            <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-brand-900 text-white mb-5">
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                                </svg>
                            </div>
                            <h3 class="text-lg font-extrabold text-brand-900">Hak Akses Multi-Admin</h3>
                            <p class="mt-2 text-sm leading-relaxed text-brand-500">
                                Daftarkan ID Telegram tim atau partner Anda untuk mendapatkan hak akses perintah admin (/admin, rekap, monitoring, dan topup).
                            </p>
                        </div>

                        {{-- Feature Card 5 --}}
                        <div class="rounded-2xl border border-brand-200 bg-white p-7 shadow-xs hover:border-brand-300 hover:shadow-soft transition">
                            <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-brand-700 text-white mb-5">
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                </svg>
                            </div>
                            <h3 class="text-lg font-extrabold text-brand-900">Panel Web Kontrol Lengkap</h3>
                            <p class="mt-2 text-sm leading-relaxed text-brand-500">
                                Dashboard intuitif untuk mengatur kontak WhatsApp/Telegram deposit, memantau member, hingga konfigurasi lanjutan.
                            </p>
                        </div>

                        {{-- Feature Card 6 --}}
                        <div class="rounded-2xl border border-brand-200 bg-white p-7 shadow-xs hover:border-brand-300 hover:shadow-soft transition">
                            <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-accent text-white mb-5">
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                </svg>
                            </div>
                            <h3 class="text-lg font-extrabold text-brand-900">Perpanjangan Instan & Fleksibel</h3>
                            <p class="mt-2 text-sm leading-relaxed text-brand-500">
                                Bot tidak pernah kehilangan data. Perpanjang durasi langganan sewa Anda dengan cepat kapan saja melalui sistem invoice QRIS.
                            </p>
                        </div>

                    </div>
                </div>
            </section>

            {{-- ==================== CARA KERJA (4 LANGKAH MUDAH) ==================== --}}
            <section id="cara-kerja" class="border-b border-brand-200 bg-white py-20">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="text-center max-w-3xl mx-auto space-y-3">
                        <p class="text-xs font-bold uppercase tracking-widest text-accent">Langkah Sederhana</p>
                        <h2 class="text-3xl font-extrabold tracking-tight text-brand-900 sm:text-4xl">
                            Mulai Sewa Bot Hanya dalam 4 Langkah
                        </h2>
                        <p class="text-base text-brand-500">
                            Tanpa proses teknis yang rumit. Bot Anda siap digunakan hanya dalam beberapa menit.
                        </p>
                    </div>

                    <div class="mt-14 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                        @foreach ([
                            ['01', 'Pilih Paket Sewa', 'Daftar akun dan tentukan bot serta durasi masa aktif yang sesuai kebutuhan.'],
                            ['02', 'Bayar via QRIS', 'Lakukan pembayaran mudah melalui QRIS dan unggah bukti transfer ke sistem.'],
                            ['03', 'Input Token BotFather', 'Masukkan API token dari @BotFather di menu Konfigurasi Bot dashboard Anda.'],
                            ['04', 'Bot Langsung Running', 'Bot Telegram custom Anda otomatis online dan siap melayani pengguna 24/7.'],
                        ] as [$num, $title, $desc])
                            <div class="relative rounded-2xl border border-brand-200 bg-brand-50/40 p-6 transition hover:bg-white hover:shadow-soft">
                                <div class="flex items-center justify-between">
                                    <span class="text-2xl font-black text-brand-900 font-mono">{{ $num }}</span>
                                    <span class="h-2 w-2 rounded-full bg-accent"></span>
                                </div>
                                <h3 class="mt-4 text-base font-extrabold text-brand-900">{{ $title }}</h3>
                                <p class="mt-2 text-xs sm:text-sm leading-relaxed text-brand-500">{{ $desc }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>

            {{-- ==================== PAKET & HARGA ==================== --}}
            <section id="harga" class="border-b border-brand-200 bg-brand-50/30 py-20">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="text-center max-w-3xl mx-auto space-y-3">
                        <p class="text-xs font-bold uppercase tracking-widest text-accent">Transparan & Terjangkau</p>
                        <h2 class="text-3xl font-extrabold tracking-tight text-brand-900 sm:text-4xl">
                            Paket Sewa Bot Telegram Custom
                        </h2>
                        <p class="text-base text-brand-500">
                            Biaya yang jelas tanpa biaya tersembunyi. Dapatkan bot berkinerja tinggi dengan infrastruktur handal.
                        </p>
                    </div>

                    <div class="mt-12 max-w-xl mx-auto">
                        <div class="rounded-3xl border-2 border-brand-900 bg-white p-8 sm:p-10 shadow-soft relative overflow-hidden">
                            <div class="absolute top-0 right-0 bg-brand-900 text-white text-[11px] font-extrabold uppercase tracking-wider px-4 py-1 rounded-bl-xl">
                                Paket Populer
                            </div>

                            <div class="space-y-2">
                                <h3 class="text-2xl font-black text-brand-900">{{ $product?->name ?? 'Sewa Bot Telegram Custom' }}</h3>
                                <p class="text-sm text-brand-500 leading-relaxed">
                                    Akses penuh ke semua fitur bot custom, panel web, dan server uptime 24/7.
                                </p>
                            </div>

                            <div class="my-6 border-y border-brand-100 py-6 space-y-4">
                                <div class="flex items-baseline justify-between">
                                    <div>
                                        <p class="font-extrabold text-brand-900 text-base">Aktivasi Awal ({{ $product?->duration_days ?? 30 }} Hari)</p>
                                        <p class="text-xs text-brand-500">Termasuk setup webhook & server dedicated</p>
                                    </div>
                                    <p class="text-2xl sm:text-3xl font-black text-brand-900">
                                        {{ $product?->formattedActivationPrice() ?? 'Rp150.000' }}
                                    </p>
                                </div>

                                <div class="flex items-baseline justify-between pt-2 border-t border-dashed border-brand-100">
                                    <div>
                                        <p class="font-semibold text-brand-800 text-sm">Perpanjangan (+30 Hari)</p>
                                        <p class="text-xs text-brand-500">Dapat diperpanjang kapan saja</p>
                                    </div>
                                    <p class="text-xl font-extrabold text-brand-700">
                                        {{ $product?->formattedRenewalPrice() ?? 'Rp30.000' }}
                                    </p>
                                </div>
                            </div>

                            <div class="space-y-3 text-sm">
                                <p class="font-bold text-brand-900 uppercase tracking-wider text-xs">Fitur Yang Didapatkan:</p>
                                <ul class="space-y-2.5">
                                    @foreach ([
                                        'Kustom Token BotFather tanpa batas nama bot',
                                        'Tombol Switch Status Operasional (Running / Nonaktif)',
                                        'HTTPS Webhook berkecepatan tinggi & anti-delay',
                                        'Manajemen Multi-Admin Telegram ID',
                                        'Panel Web Dashboard untuk kontrol kontak deposit',
                                        'Uptime Server 99.9% 24 Jam Nonstop',
                                        'Sistem Perpanjangan Sewa Instan via QRIS',
                                    ] as $feature)
                                        <li class="flex items-center gap-2.5 text-brand-700">
                                            <svg class="h-4 w-4 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                            </svg>
                                            <span>{{ $feature }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>

                            <div class="mt-8">
                                @auth
                                    @if ($product)
                                        <a href="{{ route('checkout.select-bot', $product) }}"
                                           class="w-full flex items-center justify-center gap-2 rounded-xl bg-brand-900 px-6 py-4 text-sm font-extrabold text-white shadow-soft hover:bg-brand-700 transition active:scale-[0.98]">
                                            <span>Sewa Bot Sekarang</span>
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                                            </svg>
                                        </a>
                                    @endif
                                @else
                                    <a href="{{ route('register') }}"
                                       class="w-full flex items-center justify-center gap-2 rounded-xl bg-brand-900 px-6 py-4 text-sm font-extrabold text-white shadow-soft hover:bg-brand-700 transition active:scale-[0.98]">
                                        <span>Daftar & Mulai Sewa</span>
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                                        </svg>
                                    </a>
                                @endauth
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            {{-- ==================== FAQ SECTION ==================== --}}
            <section id="faq" class="border-b border-brand-200 bg-white py-20">
                <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
                    <div class="text-center space-y-3 mb-12">
                        <p class="text-xs font-bold uppercase tracking-widest text-accent">Pertanyaan Umum</p>
                        <h2 class="text-3xl font-extrabold tracking-tight text-brand-900 sm:text-4xl">
                            Frequently Asked Questions
                        </h2>
                        <p class="text-base text-brand-500">
                            Jawaban atas pertanyaan yang sering diajukan seputar layanan SewaBot.
                        </p>
                    </div>

                    <div class="space-y-4">
                        @foreach ([
                            [
                                'q' => 'Apakah saya perlu keahlian pemrograman/coding untuk menggunakan bot ini?',
                                'a' => 'Tidak sama sekali. Semua bot sudah siap pakai (ready-to-use). Anda cukup memasukkan Token BotFather dan mengelola semua pengaturannya lewat antarmuka web dashboard kami.'
                            ],
                            [
                                'q' => 'Bagaimana cara mendapatkan Token Bot Telegram?',
                                'a' => 'Sangat mudah! Buka aplikasi Telegram, cari akun resmi @BotFather, lalu ketik /newbot untuk membuat bot baru atau /mybots untuk melihat token yang sudah ada. Salin token tersebut dan tempelkan di halaman Konfigurasi Bot.'
                            ],
                            [
                                'q' => 'Bisakah saya mematikan sementara bot saya saat tidak digunakan?',
                                'a' => 'Bisa. Tersedia tombol kontrol Status Operasional (Running vs Nonaktif) di dashboard. Saat nonaktif, webhook dilepas dan bot akan berhenti melayani pesan sementara sampai Anda mengaktifkannya kembali.'
                            ],
                            [
                                'q' => 'Metode pembayaran apa saja yang didukung?',
                                'a' => 'Kami mendukung pembayaran QRIS manual. Anda dapat memindai kode QRIS menggunakan aplikasi perbankan (BCA, Mandiri, BRI, BNI, dll.) maupun e-wallet (GoPay, OVO, DANA, ShopeePay).'
                            ],
                            [
                                'q' => 'Bagaimana jika masa aktif sewa bot saya akan habis?',
                                'a' => 'Anda dapat melakukan perpanjangan masa aktif kapan saja langsung dari dashboard. Data pengaturan bot, kontak, dan member Anda akan tetap tersimpan aman.'
                            ],
                        ] as $faq)
                            <div class="rounded-2xl border border-brand-200 bg-brand-50/40 p-6">
                                <h3 class="text-base font-extrabold text-brand-900">{{ $faq['q'] }}</h3>
                                <p class="mt-2 text-sm leading-relaxed text-brand-600">{{ $faq['a'] }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>

            {{-- ==================== CTA SECTION ==================== --}}
            <section class="bg-brand-900 text-white py-16 sm:py-20 relative overflow-hidden">
                <div class="absolute inset-0 bg-[length:24px_24px] bg-grid-fade opacity-10 pointer-events-none"></div>
                <div class="relative mx-auto max-w-5xl px-4 text-center sm:px-6 lg:px-8 space-y-6">
                    <h2 class="text-3xl sm:text-4xl font-extrabold tracking-tight">
                        Siap Mengotomatisasi Bisnis Anda dengan Bot Telegram Custom?
                    </h2>
                    <p class="max-w-2xl mx-auto text-sm sm:text-base text-brand-200 leading-relaxed">
                        Bergabunglah sekarang dan rasakan kemudahan mengelola bot Telegram sendiri dengan performa cepat, aman, dan tanpa repot teknis.
                    </p>
                    <div class="pt-2 flex flex-wrap justify-center gap-4">
                        @auth
                            <a href="{{ route('dashboard') }}"
                               class="inline-flex items-center gap-2 rounded-xl bg-white px-7 py-3.5 text-sm font-extrabold text-brand-900 shadow-soft hover:bg-brand-50 transition active:scale-95">
                                <span>Buka Dashboard</span>
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                                </svg>
                            </a>
                        @else
                            <a href="{{ route('register') }}"
                               class="inline-flex items-center gap-2 rounded-xl bg-white px-7 py-3.5 text-sm font-extrabold text-brand-900 shadow-soft hover:bg-brand-50 transition active:scale-95">
                                <span>Mulai Sewa Bot Sekarang</span>
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                                </svg>
                            </a>
                            <a href="{{ route('login') }}"
                               class="inline-flex items-center rounded-xl border border-white/20 bg-brand-800/60 px-6 py-3.5 text-sm font-bold text-white hover:bg-brand-800 transition">
                                Masuk ke Akun
                            </a>
                        @endauth
                    </div>
                </div>
            </section>

        </main>

        {{-- ==================== FOOTER ==================== --}}
        <footer class="border-t border-brand-200 bg-brand-50/80 py-10">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="flex flex-col gap-6 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-3">
                        <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-brand-900 text-white font-black text-xs">
                            SB
                        </div>
                        <div>
                            <span class="font-extrabold text-brand-900 text-sm">SewaBot</span>
                            <span class="text-xs text-brand-500 block">Platform Sewa Bot Telegram Custom</span>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-6 text-xs font-semibold text-brand-600">
                        <a href="#fitur" class="hover:text-brand-900 transition">Fitur</a>
                        <a href="#keunggulan" class="hover:text-brand-900 transition">Keunggulan</a>
                        <a href="#cara-kerja" class="hover:text-brand-900 transition">Cara Kerja</a>
                        <a href="#harga" class="hover:text-brand-900 transition">Harga</a>
                        <a href="#faq" class="hover:text-brand-900 transition">FAQ</a>
                    </div>

                    <div class="text-xs text-brand-500">
                        &copy; {{ date('Y') }} {{ $merchant }}. All rights reserved.
                    </div>
                </div>
            </div>
        </footer>

    </div>
</body>
</html>
