@php
    $activeProvider = $telegramBot->activeOtpProvider();
    $kopken = $services->first(fn ($s) => in_array(strtoupper($s->name), ['KOPKEN', 'WHATSAPP'])) ?? $services->first();
    $isBotRunning = $telegramBot->isRunning();
    $hasToken = $telegramBot->hasValidToken();
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <a href="{{ route('dashboard') }}" class="text-sm font-medium text-brand-500 hover:text-brand-900">Dashboard</a>
                <h1 class="mt-2 text-2xl font-extrabold tracking-tight text-brand-900">Konfigurasi Bot</h1>
                <div class="mt-1 flex flex-wrap items-center gap-2 text-sm text-brand-500">
                    <span class="font-semibold text-brand-800">{{ $telegramBot->name }}</span>
                    @if ($telegramBot->username)
                        <span>·</span>
                        <span class="font-mono text-brand-700">{{ $telegramBot->displayUsername() }}</span>
                    @endif
                    <span>·</span>
                    <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-extrabold {{ $isBotRunning ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-slate-100 text-slate-700 border border-slate-200' }}">
                        <span class="h-2 w-2 rounded-full {{ $isBotRunning ? 'bg-emerald-500 animate-pulse' : 'bg-slate-400' }}"></span>
                        {{ $isBotRunning ? 'RUNNING' : 'NONAKTIF' }}
                    </span>
                </div>
            </div>
            @if ($telegramBot->telegramUrl())
                <a href="{{ $telegramBot->telegramUrl() }}" target="_blank"
                   class="inline-flex w-full items-center justify-center rounded-xl border border-brand-200 bg-white px-5 py-3 text-sm font-semibold text-brand-900 shadow-xs hover:bg-brand-50 sm:w-auto">
                    <svg xmlns="http://www.w3.org/2000/svg" class="mr-2 h-4 w-4 text-[#229ED9]" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.894 8.221l-1.97 9.28c-.145.658-.537.818-1.084.508l-3-2.21-1.446 1.394c-.14.18-.357.295-.6.295-.002 0-.003 0-.005 0l.213-3.054 5.56-5.022c.24-.213-.054-.334-.373-.121l-6.869 4.326-2.96-.924c-.643-.204-.657-.643.136-.953l11.57-4.458c.538-.196 1.006.128.832.959z"/>
                    </svg>
                    Buka Telegram
                </a>
            @endif
        </div>
    </x-slot>

    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        {{-- 2-Column Responsive Layout: Kiri (Input & Form) | Kanan (Data & Status Live) --}}
        <div class="flex flex-col lg:flex-row gap-8 items-start">

            {{-- ==================== KOLOM KIRI (INPUT & FORMULIR) ==================== --}}
            <div class="w-full lg:w-7/12 space-y-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-extrabold text-brand-900">Pengaturan & Input</h2>
                        <p class="text-xs text-brand-500">Kelola token BotFather, status operasional, provider, API key, dan kontak bot.</p>
                    </div>
                </div>

                <form method="POST" action="{{ route('bots.settings', $telegramBot) }}"
                      x-data="{
                          activeProvider: '{{ old('otp_provider', $activeProvider) }}',
                          botStatus: '{{ old('status', $telegramBot->status === 'active' ? 'active' : 'inactive') }}'
                      }"
                      class="space-y-6 rounded-2xl border border-brand-200 bg-white p-5 shadow-xs sm:p-7">
                    @csrf
                    @method('PUT')

                    {{-- 1. Pengaturan Status Operasional Bot --}}
                    <div class="rounded-2xl border border-brand-100 bg-brand-50/50 p-4 sm:p-5">
                        <div class="mb-2">
                            <label class="block text-sm font-bold text-brand-900">Status Operasional Bot</label>
                            <p class="text-xs text-brand-500">Pilih apakah bot Telegram sedang aktif melayani pengguna atau dinonaktifkan sementara.</p>
                        </div>

                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 mt-3">
                            <label class="relative flex cursor-pointer flex-col justify-between rounded-xl border p-3.5 transition"
                                   :class="botStatus === 'active' ? 'border-emerald-600 bg-white ring-2 ring-emerald-500 shadow-xs' : 'border-brand-200 bg-white hover:border-brand-300 opacity-70'">
                                <div class="flex items-start justify-between gap-2">
                                    <div>
                                        <div class="flex items-center gap-1.5">
                                            <span class="h-2.5 w-2.5 rounded-full bg-emerald-500"></span>
                                            <span class="text-sm font-extrabold text-brand-900">Running / Aktif</span>
                                        </div>
                                        <p class="mt-1 text-[11px] text-brand-500 leading-tight">Bot online, memproses pesan & transaksi OTP secara otomatis.</p>
                                    </div>
                                    <input type="radio" name="status" value="active" class="sr-only" x-model="botStatus">
                                    <span class="inline-flex h-4 w-4 shrink-0 items-center justify-center rounded-full border border-brand-300"
                                          :class="botStatus === 'active' ? 'border-emerald-600 bg-emerald-600 text-white' : 'bg-white'">
                                        <span class="h-1.5 w-1.5 rounded-full bg-white" x-show="botStatus === 'active'"></span>
                                    </span>
                                </div>
                            </label>

                            <label class="relative flex cursor-pointer flex-col justify-between rounded-xl border p-3.5 transition"
                                   :class="botStatus === 'inactive' ? 'border-rose-600 bg-white ring-2 ring-rose-500 shadow-xs' : 'border-brand-200 bg-white hover:border-brand-300 opacity-70'">
                                <div class="flex items-start justify-between gap-2">
                                    <div>
                                        <div class="flex items-center gap-1.5">
                                            <span class="h-2.5 w-2.5 rounded-full bg-rose-500"></span>
                                            <span class="text-sm font-extrabold text-brand-900">Nonaktif / Berhenti</span>
                                        </div>
                                        <p class="mt-1 text-[11px] text-brand-500 leading-tight">Bot dihentikan sementara, webhook dimatikan & pesanan ditahan.</p>
                                    </div>
                                    <input type="radio" name="status" value="inactive" class="sr-only" x-model="botStatus">
                                    <span class="inline-flex h-4 w-4 shrink-0 items-center justify-center rounded-full border border-brand-300"
                                          :class="botStatus === 'inactive' ? 'border-rose-600 bg-rose-600 text-white' : 'bg-white'">
                                        <span class="h-1.5 w-1.5 rounded-full bg-white" x-show="botStatus === 'inactive'"></span>
                                    </span>
                                </div>
                            </label>
                        </div>
                        @error('status')
                            <p class="mt-2 text-xs text-red-600 font-semibold">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- 2. Token BotFather Telegram --}}
                    <div class="border-t border-brand-100 pt-5">
                        <div class="mb-2 flex items-center justify-between">
                            <div>
                                <label class="block text-sm font-bold text-brand-900">Token Bot Telegram (BotFather)</label>
                                <p class="text-xs text-brand-500">Token API bot yang diperoleh langsung dari @BotFather di Telegram.</p>
                            </div>
                            <span class="inline-flex items-center text-[11px] font-bold {{ $hasToken ? 'text-emerald-700' : 'text-amber-600' }}">
                                {{ $hasToken ? '✓ Token Terhubung' : '⚠ Belum Terisi' }}
                            </span>
                        </div>

                        @if ($hasToken)
                            <div x-data="{ editing: false }">
                                <div x-show="!editing" class="flex flex-col gap-2.5 sm:flex-row sm:items-center sm:justify-between">
                                    <div class="rounded-xl border border-brand-200 bg-brand-50 px-3.5 py-2.5 sm:flex-1">
                                        <div class="flex items-center justify-between">
                                            <p class="font-mono text-xs font-semibold text-emerald-800">
                                                {{ $telegramBot->maskedToken() }}
                                            </p>
                                            @if ($telegramBot->username)
                                                <span class="text-[11px] font-bold text-brand-600">
                                                    {{ $telegramBot->displayUsername() }}
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                    <button type="button" @click="editing = true"
                                            class="rounded-xl border border-brand-200 px-3.5 py-2 text-xs font-bold text-brand-900 hover:bg-brand-50 transition">
                                        Ganti Token
                                    </button>
                                </div>

                                <div x-show="editing" style="display: none;" class="space-y-2.5">
                                    <input type="password" name="token" autocomplete="off"
                                           placeholder="1234567890:ABCdefGhIJKlmNoPQRsTUVwxyZ"
                                           class="w-full rounded-xl border-brand-200 font-mono text-sm focus:border-brand-900 focus:ring-brand-900">
                                    <p class="text-[11px] text-brand-500">
                                        💡 Masukkan token BotFather baru. Sistem akan otomatis memvalidasi token ke Telegram dan menyetel Webhook.
                                    </p>
                                    <div class="flex flex-wrap items-center gap-3">
                                        <button type="button" @click="editing = false"
                                                class="rounded-xl border border-brand-200 px-3 py-1.5 text-xs font-semibold text-brand-700 hover:bg-brand-50">
                                            Batal
                                        </button>
                                        <label class="flex items-center gap-2 text-xs text-rose-600 cursor-pointer">
                                            <input type="checkbox" name="clear_token" value="1" class="rounded border-brand-300 text-rose-600 focus:ring-rose-500">
                                            Hapus token & putuskan koneksi bot
                                        </label>
                                    </div>
                                </div>
                            </div>
                        @else
                            <div class="space-y-2">
                                <input type="password" name="token" autocomplete="off"
                                       placeholder="1234567890:ABCdefGhIJKlmNoPQRsTUVwxyZ"
                                       class="w-full rounded-xl border-brand-200 font-mono text-sm focus:border-brand-900 focus:ring-brand-900">
                                <p class="text-[11px] text-brand-500">
                                    💡 Cara mendapatkan token: Buka Telegram & cari <b>@BotFather</b> &gt; ketik <code class="bg-brand-100 px-1 py-0.5 rounded text-brand-900">/newbot</code> atau <code class="bg-brand-100 px-1 py-0.5 rounded text-brand-900">/mybots</code> &gt; salin API Token di sini.
                                </p>
                            </div>
                        @endif
                        @error('token')
                            <p class="mt-1.5 text-xs text-red-600 font-semibold">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- 3. Pilihan Provider OTP Aktif --}}
                    <div class="border-t border-brand-100 pt-5">
                        <label class="mb-1 block text-sm font-bold text-brand-900">Pilih Provider OTP Aktif</label>
                        <p class="mb-3 text-xs text-brand-500">Pilih provider yang akan digunakan untuk melayani pesanan OTP bot ini.</p>

                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <label class="relative flex cursor-pointer flex-col justify-between rounded-xl border p-3.5 transition"
                                   :class="activeProvider === 'kopken' ? 'border-brand-900 bg-brand-50/80 ring-2 ring-brand-900' : 'border-brand-200 hover:border-brand-300 bg-white'">
                                <div class="flex items-start justify-between gap-2">
                                    <div>
                                        <span class="text-sm font-extrabold text-brand-900">Provider 1 (EngineUnicorn)</span>
                                        <p class="mt-0.5 text-[11px] text-brand-500">engineunicorn.cloud</p>
                                    </div>
                                    <input type="radio" name="otp_provider" value="kopken" class="sr-only" x-model="activeProvider">
                                    <span class="inline-flex h-4 w-4 shrink-0 items-center justify-center rounded-full border border-brand-300"
                                          :class="activeProvider === 'kopken' ? 'border-brand-900 bg-brand-900 text-white' : 'bg-white'">
                                        <span class="h-1.5 w-1.5 rounded-full bg-white" x-show="activeProvider === 'kopken'"></span>
                                    </span>
                                </div>
                                <span class="mt-2.5 inline-flex items-center text-[11px] font-bold {{ filled($telegramBot->otp_api_key) ? 'text-emerald-700' : 'text-amber-600' }}">
                                    {{ filled($telegramBot->otp_api_key) ? '✓ API Key Tersedia' : '⚠ API Key Kosong' }}
                                </span>
                            </label>

                            <label class="relative flex cursor-pointer flex-col justify-between rounded-xl border p-3.5 transition"
                                   :class="activeProvider === 'wahub' ? 'border-brand-900 bg-brand-50/80 ring-2 ring-brand-900' : 'border-brand-200 hover:border-brand-300 bg-white'">
                                <div class="flex items-start justify-between gap-2">
                                    <div>
                                        <span class="text-sm font-extrabold text-brand-900">Provider 2 (WAHub)</span>
                                        <p class="mt-0.5 text-[11px] text-brand-500">dehuyzotp.shop</p>
                                    </div>
                                    <input type="radio" name="otp_provider" value="wahub" class="sr-only" x-model="activeProvider">
                                    <span class="inline-flex h-4 w-4 shrink-0 items-center justify-center rounded-full border border-brand-300"
                                          :class="activeProvider === 'wahub' ? 'border-brand-900 bg-brand-900 text-white' : 'bg-white'">
                                        <span class="h-1.5 w-1.5 rounded-full bg-white" x-show="activeProvider === 'wahub'"></span>
                                    </span>
                                </div>
                                <span class="mt-2.5 inline-flex items-center text-[11px] font-bold {{ filled($telegramBot->otp_wahub_api_key) ? 'text-emerald-700' : 'text-amber-600' }}">
                                    {{ filled($telegramBot->otp_wahub_api_key) ? '✓ API Key Tersedia' : '⚠ API Key Kosong' }}
                                </span>
                            </label>
                        </div>
                        @error('otp_provider')
                            <p class="mt-2 text-xs text-red-600 font-semibold">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- 4. Input API Key Provider 1 (EngineUnicorn) --}}
                    <div class="border-t border-brand-100 pt-5" x-show="activeProvider === 'kopken'" x-transition>
                        <div class="mb-2 flex items-center justify-between">
                            <label class="block text-sm font-bold text-brand-900">API Key EngineUnicorn</label>
                            <span class="text-xs text-brand-500">Provider 1</span>
                        </div>

                        @if (filled($telegramBot->otp_api_key))
                            <div x-data="{ editing: false }">
                                <div x-show="!editing" class="flex flex-col gap-2.5 sm:flex-row sm:items-center sm:justify-between">
                                    <p class="rounded-xl border border-brand-200 bg-brand-50 px-3.5 py-2.5 text-xs font-semibold text-emerald-700 sm:flex-1">
                                        ✓ API Key EngineUnicorn tersimpan
                                    </p>
                                    <button type="button" @click="editing = true"
                                            class="rounded-xl border border-brand-200 px-3.5 py-2 text-xs font-bold text-brand-900 hover:bg-brand-50">
                                        Ganti Key
                                    </button>
                                </div>

                                <div x-show="editing" style="display: none;" class="space-y-2.5">
                                    <input type="password" name="otp_api_key" autocomplete="off"
                                           placeholder="Tempel API key EngineUnicorn baru"
                                           class="w-full rounded-xl border-brand-200 text-sm focus:border-brand-900 focus:ring-brand-900">
                                    <div class="flex flex-wrap items-center gap-3">
                                        <button type="button" @click="editing = false"
                                                class="rounded-xl border border-brand-200 px-3 py-1.5 text-xs font-semibold text-brand-700 hover:bg-brand-50">
                                            Batal
                                        </button>
                                        <label class="flex items-center gap-2 text-xs text-brand-500">
                                            <input type="checkbox" name="clear_api_key" value="1" class="rounded border-brand-300 text-brand-900 focus:ring-brand-900">
                                            Hapus key
                                        </label>
                                    </div>
                                </div>
                            </div>
                        @else
                            <input type="password" name="otp_api_key" autocomplete="off"
                                   placeholder="Tempel API key EngineUnicorn"
                                   class="w-full rounded-xl border-brand-200 text-sm focus:border-brand-900 focus:ring-brand-900">
                        @endif
                        @error('otp_api_key')
                            <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- 5. Input API Key Provider 2 (WAHub) --}}
                    <div class="border-t border-brand-100 pt-5" x-show="activeProvider === 'wahub'" x-transition>
                        <div class="mb-2 flex items-center justify-between">
                            <label class="block text-sm font-bold text-brand-900">API Key WAHub (dehuyzotp.shop)</label>
                            <span class="text-xs text-brand-500">Provider 2</span>
                        </div>

                        @if (filled($telegramBot->otp_wahub_api_key))
                            <div x-data="{ editing: false }">
                                <div x-show="!editing" class="flex flex-col gap-2.5 sm:flex-row sm:items-center sm:justify-between">
                                    <p class="rounded-xl border border-brand-200 bg-brand-50 px-3.5 py-2.5 text-xs font-semibold text-emerald-700 sm:flex-1">
                                        ✓ API Key WAHub tersimpan
                                    </p>
                                    <button type="button" @click="editing = true"
                                            class="rounded-xl border border-brand-200 px-3.5 py-2 text-xs font-bold text-brand-900 hover:bg-brand-50">
                                        Ganti Key
                                    </button>
                                </div>

                                <div x-show="editing" style="display: none;" class="space-y-2.5">
                                    <input type="password" name="otp_wahub_api_key" autocomplete="off"
                                           placeholder="wh_live_xxxxxxxxxxxxxxxxxxxxxxxx"
                                           class="w-full rounded-xl border-brand-200 text-sm focus:border-brand-900 focus:ring-brand-900">
                                    <div class="flex flex-wrap items-center gap-3">
                                        <button type="button" @click="editing = false"
                                                class="rounded-xl border border-brand-200 px-3 py-1.5 text-xs font-semibold text-brand-700 hover:bg-brand-50">
                                            Batal
                                        </button>
                                        <label class="flex items-center gap-2 text-xs text-brand-500">
                                            <input type="checkbox" name="clear_wahub_api_key" value="1" class="rounded border-brand-300 text-brand-900 focus:ring-brand-900">
                                            Hapus key
                                        </label>
                                    </div>
                                </div>
                            </div>
                        @else
                            <input type="password" name="otp_wahub_api_key" autocomplete="off"
                                   placeholder="wh_live_xxxxxxxxxxxxxxxxxxxxxxxx"
                                   class="w-full rounded-xl border-brand-200 text-sm focus:border-brand-900 focus:ring-brand-900">
                        @endif
                        @error('otp_wahub_api_key')
                            <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- 6. Markup Jual --}}
                    <div class="border-t border-brand-100 pt-5"
                         x-data="{
                             markupType: '{{ old('otp_markup_type', $telegramBot->otp_markup_type ?? 'percent') }}',
                             markupValue: {{ (int) old('otp_markup_percent', $telegramBot->otp_markup_percent ?? 50) }},
                             modal: {{ (int) ($kopken->provider_price ?? 1400) }},
                             get sellPrice() {
                                 if (this.markupType === 'flat') return this.modal + Number(this.markupValue || 0);
                                 return Math.ceil(this.modal * (100 + Number(this.markupValue || 0)) / 100);
                             },
                             formatRp(n) {
                                 return 'Rp' + new Intl.NumberFormat('id-ID').format(n);
                             }
                         }">
                        <label class="mb-2 block text-sm font-bold text-brand-900">Markup Jual OTP</label>

                        <div class="mb-3 grid grid-cols-2 gap-2">
                            <label class="cursor-pointer rounded-xl border px-3 py-2 text-center text-xs font-bold transition"
                                   :class="markupType === 'percent' ? 'border-brand-900 bg-brand-50 text-brand-900' : 'border-brand-200 text-brand-500'">
                                <input type="radio" name="otp_markup_type" value="percent" class="sr-only" x-model="markupType">
                                Persen (%)
                            </label>
                            <label class="cursor-pointer rounded-xl border px-3 py-2 text-center text-xs font-bold transition"
                                   :class="markupType === 'flat' ? 'border-brand-900 bg-brand-50 text-brand-900' : 'border-brand-200 text-brand-500'">
                                <input type="radio" name="otp_markup_type" value="flat" class="sr-only" x-model="markupType">
                                Flat (Rp)
                            </label>
                        </div>

                        <div class="flex flex-col gap-2.5 sm:flex-row sm:items-center">
                            <div class="flex w-full overflow-hidden rounded-xl border border-brand-200 sm:w-48">
                                <span class="inline-flex min-w-[3rem] items-center justify-center bg-brand-900 px-3 text-xs font-bold text-white"
                                      x-text="markupType === 'flat' ? 'Rp' : '%'"></span>
                                <input type="number" name="otp_markup_percent" min="0" required x-model.number="markupValue"
                                       class="w-full border-0 bg-white px-3 py-2 text-sm font-bold text-brand-900 focus:border-transparent focus:outline-none focus:ring-0">
                            </div>
                            <p class="text-xs text-brand-500">
                                Modal <span x-text="formatRp(modal)"></span> → Jual <span class="font-extrabold text-brand-900" x-text="formatRp(sellPrice)"></span>
                            </p>
                        </div>
                    </div>

                    {{-- 7. Reminder Saldo Pusat --}}
                    <div class="border-t border-brand-100 pt-5"
                         x-data="{
                             enabled: {{ ($telegramBot->min_provider_balance_alert && $telegramBot->min_provider_balance_alert > 0) ? 'true' : 'false' }},
                             rawAmount: {{ (int) old('min_provider_balance_alert', $telegramBot->min_provider_balance_alert ?? 10000) }},
                             formattedInput: '',
                             init() {
                                 this.updateDisplay(this.rawAmount || 10000);
                             },
                             onInput(e) {
                                 let digits = e.target.value.replace(/\D/g, '');
                                 this.rawAmount = digits ? parseInt(digits, 10) : 0;
                                 this.updateDisplay(this.rawAmount);
                             },
                             updateDisplay(val) {
                                 this.formattedInput = val > 0 ? new Intl.NumberFormat('id-ID').format(val) : '';
                             },
                             formatRp(n) {
                                 return 'Rp' + new Intl.NumberFormat('id-ID').format(n || 0);
                             }
                         }">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-sm font-bold text-brand-900">Reminder Saldo Pusat</h3>
                                <p class="text-xs text-brand-500">Notifikasi otomatis ke admin saat saldo provider menipis.</p>
                            </div>

                            <button type="button"
                                    @click="enabled = !enabled; if(enabled && rawAmount <= 0) { rawAmount = 10000; updateDisplay(10000); }"
                                    :class="enabled ? 'bg-brand-900' : 'bg-slate-300'"
                                    class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none"
                                    role="switch" :aria-checked="enabled">
                                <span :class="enabled ? 'translate-x-5' : 'translate-x-0'"
                                      class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow-sm ring-0 transition duration-200 ease-in-out"></span>
                            </button>
                        </div>

                        <input type="hidden" name="min_provider_balance_alert" :value="enabled ? rawAmount : 0">

                        <div x-show="enabled" x-transition class="mt-3 space-y-1.5">
                            <label class="block text-[11px] font-bold uppercase tracking-wider text-brand-500">Batas Minimal Saldo</label>
                            <div class="flex w-full max-w-xs overflow-hidden rounded-xl border border-brand-200">
                                <span class="inline-flex min-w-[2.75rem] items-center justify-center bg-brand-900 px-3 text-xs font-bold text-white">Rp</span>
                                <input type="text" inputmode="numeric" x-model="formattedInput" @input="onInput" placeholder="50.000"
                                       class="w-full border-0 bg-white px-3 py-2 text-sm font-bold text-brand-900 focus:border-transparent focus:outline-none focus:ring-0">
                            </div>
                        </div>
                    </div>

                    {{-- 8. Kontak Deposit Saldo --}}
                    <div class="border-t border-brand-100 pt-5">
                        <h3 class="text-sm font-bold text-brand-900">Kontak Deposit Manual</h3>
                        <p class="mb-3 text-xs text-brand-500">Tombol kontak WhatsApp & Telegram yang muncul saat user memilih menu Deposit di bot.</p>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-brand-900">WhatsApp Admin</label>
                                <input type="text" name="deposit_whatsapp"
                                       value="{{ old('deposit_whatsapp', $telegramBot->deposit_whatsapp) }}"
                                       placeholder="62812xxxx"
                                       class="w-full rounded-xl border-brand-200 text-sm focus:border-brand-900 focus:ring-brand-900">
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-brand-900">Telegram Admin</label>
                                <input type="text" name="deposit_telegram"
                                       value="{{ old('deposit_telegram', $telegramBot->deposit_telegram) }}"
                                       placeholder="@username"
                                       class="w-full rounded-xl border-brand-200 text-sm focus:border-brand-900 focus:ring-brand-900">
                            </div>
                        </div>
                    </div>

                    {{-- 9. Akses Admin Bot --}}
                    <div class="border-t border-brand-100 pt-5">
                        <h3 class="text-sm font-bold text-brand-900">Akses Admin Bot (/admin)</h3>
                        <p class="mb-2 text-xs text-brand-500">Telegram ID yang diperbolehkan mengakses menu /admin bot (pisahkan dengan koma).</p>
                        <input type="text" name="admin_telegram_ids"
                               value="{{ old('admin_telegram_ids', $telegramBot->admin_telegram_ids) }}"
                               placeholder="123456789, 987654321"
                               class="w-full rounded-xl border-brand-200 text-sm focus:border-brand-900 focus:ring-brand-900">
                    </div>

                    {{-- 10. Force Subscribe Channel --}}
                    <div class="border-t border-brand-100 pt-5"
                         x-data="{
                             enabled: {{ old('force_subscribe_enabled', $telegramBot->force_subscribe_enabled) ? 'true' : 'false' }},
                         }">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h3 class="text-sm font-bold text-brand-900">Force Subscribe Channel</h3>
                                <p class="mt-0.5 text-xs text-brand-500 leading-relaxed">
                                    User wajib join channel dulu sebelum bisa order / pakai bot.
                                    <span class="font-semibold text-amber-600">Bot harus jadi <b>admin</b> di channel.</span>
                                </p>
                            </div>
                            <button type="button"
                                    @click="enabled = !enabled"
                                    :class="enabled ? 'bg-rose-600' : 'bg-slate-300'"
                                    class="relative mt-0.5 inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none"
                                    role="switch" :aria-checked="enabled">
                                <span :class="enabled ? 'translate-x-5' : 'translate-x-0'"
                                      class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow-sm ring-0 transition duration-200 ease-in-out"></span>
                            </button>
                        </div>

                        <input type="hidden" name="force_subscribe_enabled" :value="enabled ? '1' : '0'">

                        <div x-show="enabled" x-transition class="mt-4 space-y-4 rounded-xl border border-rose-100 bg-rose-50/50 p-4">

                            {{-- Toggle label --}}
                            <div class="flex items-center gap-2 rounded-lg border border-rose-200 bg-white px-3 py-2.5">
                                <span class="h-2 w-2 rounded-full bg-rose-500 animate-pulse"></span>
                                <p class="text-xs font-semibold text-rose-700">
                                    Aktif — Blokir /start, List Produk, dan checkout sampai user join.
                                </p>
                            </div>

                            {{-- Channel Username / ID --}}
                            <div>
                                <label class="mb-1 block text-xs font-bold text-brand-900">Channel Username / ID</label>
                                <div class="flex overflow-hidden rounded-xl border border-brand-200 bg-white focus-within:ring-2 focus-within:ring-brand-900 focus-within:border-brand-900">
                                    <span class="inline-flex items-center bg-brand-900 px-3 text-xs font-bold text-white select-none">@</span>
                                    <input type="text"
                                           name="force_subscribe_channel"
                                           value="{{ old('force_subscribe_channel', ltrim((string) $telegramBot->force_subscribe_channel, '@')) }}"
                                           placeholder="premifystoreid"
                                           class="w-full border-0 bg-white px-3 py-2.5 text-sm font-mono font-semibold text-brand-900 focus:outline-none focus:ring-0">
                                </div>
                                <p class="mt-1.5 text-[11px] text-brand-500 leading-relaxed">
                                    Contoh: <code class="bg-brand-100 px-1 py-0.5 rounded text-brand-900">@premifystore</code>.
                                    Channel private pakai ID numerik <code class="bg-brand-100 px-1 py-0.5 rounded text-brand-900">-100...</code><br>
                                    Cara ambil ID: forward 1 post channel ke <b>@userinfobot</b> atau <b>@getidsbot</b>.
                                    Bot wajib jadi <b>admin</b> channel.
                                </p>
                                @error('force_subscribe_channel')
                                    <p class="mt-1.5 text-xs text-red-600 font-semibold">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Invite / Join URL (opsional) --}}
                            <div>
                                <label class="mb-1 block text-xs font-bold text-brand-900">Invite / Join URL <span class="font-normal text-brand-400">(opsional)</span></label>
                                <input type="url"
                                       name="force_subscribe_join_url"
                                       value="{{ old('force_subscribe_join_url', $telegramBot->force_subscribe_join_url) }}"
                                       placeholder="https://t.me/namachannel atau invite link"
                                       class="w-full rounded-xl border-brand-200 text-sm focus:border-brand-900 focus:ring-brand-900">
                                <p class="mt-1 text-[11px] text-brand-500">
                                    Kosongkan = URL otomatis dari @username. <b>Wajib diisi jika channel private.</b>
                                </p>
                                @error('force_subscribe_join_url')
                                    <p class="mt-1.5 text-xs text-red-600 font-semibold">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>

                    {{-- Submit Button --}}
                    <div class="border-t border-brand-100 pt-5">
                        <button type="submit" class="w-full rounded-xl bg-brand-900 px-5 py-3.5 text-sm font-bold text-white shadow-xs hover:bg-brand-800 transition active:scale-[0.99]">
                            Simpan Konfigurasi Bot
                        </button>
                    </div>
                </form>
            </div>


            {{-- ==================== KOLOM KANAN (DISPLAY DATA & STATUS LIVE) ==================== --}}
            <div class="w-full lg:w-5/12 space-y-5 lg:sticky lg:top-6">
                <div>
                    <h2 class="text-lg font-extrabold text-brand-900">Informasi & Status Live</h2>
                    <p class="text-xs text-brand-500">Status operasional bot, token Telegram, provider aktif, dan saldo.</p>
                </div>

                {{-- Card 1: Status Operasional & Bot Info --}}
                <div class="rounded-2xl border border-brand-200 bg-white p-5 shadow-xs">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold uppercase tracking-wider text-brand-500">Status Operasional</span>
                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-extrabold {{ $isBotRunning ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-slate-100 text-slate-700 border border-slate-200' }}">
                            <span class="h-2 w-2 rounded-full {{ $isBotRunning ? 'bg-emerald-500 animate-pulse' : 'bg-slate-400' }}"></span>
                            {{ $isBotRunning ? 'RUNNING / AKTIF' : 'NONAKTIF' }}
                        </span>
                    </div>

                    <div class="mt-4 space-y-3">
                        <div class="flex items-start justify-between border-b border-brand-100 pb-2.5">
                            <div>
                                <p class="text-xs text-brand-500">Nama Bot</p>
                                <p class="text-sm font-extrabold text-brand-900">{{ $telegramBot->name }}</p>
                            </div>
                            <div class="text-right">
                                <p class="text-xs text-brand-500">Username</p>
                                <p class="font-mono text-sm font-bold text-brand-800">{{ $telegramBot->displayUsername() }}</p>
                            </div>
                        </div>

                        <div class="flex items-center justify-between border-b border-brand-100 pb-2.5 text-xs">
                            <span class="text-brand-500">Token BotFather</span>
                            <span class="font-bold {{ $hasToken ? 'text-emerald-700' : 'text-amber-600' }}">
                                {{ $hasToken ? '✓ Terpasang' : 'Belum Ada' }}
                            </span>
                        </div>

                        <div class="flex items-center justify-between text-xs">
                            <span class="text-brand-500">Webhook URL</span>
                            <span class="font-bold {{ $isBotRunning && $hasToken ? 'text-emerald-700' : 'text-slate-500' }}">
                                {{ $isBotRunning && $hasToken ? '✓ Terhubung' : 'Nonaktif' }}
                            </span>
                        </div>
                    </div>
                </div>

                {{-- Card 2: Status Provider Aktif --}}
                <div class="rounded-2xl border border-brand-200 bg-white p-5 shadow-xs">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold uppercase tracking-wider text-brand-500">Provider Aktif</span>
                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-extrabold {{ $telegramBot->hasOtpConfigured() ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">
                            <span class="h-2 w-2 rounded-full {{ $telegramBot->hasOtpConfigured() ? 'bg-emerald-500' : 'bg-amber-500' }}"></span>
                            {{ $telegramBot->hasOtpConfigured() ? 'Terhubung' : 'Belum Terhubung' }}
                        </span>
                    </div>

                    <div class="mt-3 flex items-center gap-3">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl {{ $activeProvider === 'wahub' ? 'bg-blue-50 text-blue-700' : 'bg-purple-50 text-purple-700' }}">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-base font-extrabold text-brand-900">
                                {{ $activeProvider === 'wahub' ? 'WAHub' : 'EngineUnicorn' }}
                            </p>
                            <p class="text-xs text-brand-500">
                                {{ $activeProvider === 'wahub' ? 'dehuyzotp.shop' : 'engineunicorn.cloud' }}
                            </p>
                        </div>
                    </div>
                </div>

                {{-- Card 3: Saldo Pusat API --}}
                <div class="rounded-2xl border border-brand-200 bg-white p-5 shadow-xs">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold uppercase tracking-wider text-brand-500">Saldo Pusat API</span>
                        <form method="POST" action="{{ route('bots.provider-balance', $telegramBot) }}">
                            @csrf
                            <button type="submit"
                                    title="Cek saldo live provider sekarang"
                                    class="inline-flex items-center gap-1 rounded-lg border border-brand-200 bg-brand-50 px-2 py-1 text-xs font-bold text-brand-900 hover:bg-brand-100 disabled:opacity-40"
                                    @disabled(! $telegramBot->hasOtpConfigured())>
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-3.5 w-3.5">
                                    <path fill-rule="evenodd" d="M15.312 11.424a5.5 5.5 0 0 1-9.201 2.466l-.312-.311h2.433a.75.75 0 0 0 0-1.5H4.39a.75.75 0 0 0-.75.75v3.842a.75.75 0 0 0 1.5 0v-2.16l.31.31a7 7 0 0 0 11.712-3.138.75.75 0 0 0-1.449-.39Zm-10.624-2.85a5.5 5.5 0 0 1 9.201-2.466l.312.312H11.77a.75.75 0 0 0 0 1.5h3.842a.75.75 0 0 0 .75-.75V3.328a.75.75 0 1 0-1.5 0V5.49l-.31-.31A7 7 0 0 0 3.04 8.316a.75.75 0 1 0 1.45.39Z" clip-rule="evenodd" />
                                </svg>
                                Refresh
                            </button>
                        </form>
                    </div>

                    <div class="mt-3">
                        @if ($telegramBot->provider_balance !== null)
                            <div class="flex items-baseline gap-2">
                                <p class="text-2xl font-black {{ $telegramBot->isProviderBalanceLow() ? 'text-rose-600' : 'text-emerald-700' }}">
                                    {{ $telegramBot->formattedProviderBalance() }}
                                </p>
                                @if ($telegramBot->isProviderBalanceLow())
                                    <span class="rounded-md bg-rose-100 px-1.5 py-0.5 text-[10px] font-bold text-rose-700">Rendah</span>
                                @endif
                            </div>
                            <p class="mt-1 text-xs text-brand-500">
                                Dicek: {{ $telegramBot->provider_balance_checked_at?->timezone(config('app.timezone'))->format('d M Y H:i') ?? '-' }}
                            </p>
                        @else
                            <p class="text-2xl font-black text-brand-400">—</p>
                            <p class="mt-1 text-xs text-brand-500">Tekan tombol Refresh di atas untuk mengecek saldo.</p>
                        @endif
                    </div>
                </div>

                {{-- Card 4: Ringkasan Harga Layanan --}}
                <div class="rounded-2xl border border-brand-200 bg-white p-5 shadow-xs">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold uppercase tracking-wider text-brand-500">Layanan & Harga OTP</span>
                        <form method="POST" action="{{ route('bots.sync-services', $telegramBot) }}">
                            @csrf
                            <button type="submit"
                                    title="Sync harga & layanan live provider sekarang"
                                    class="inline-flex items-center gap-1 rounded-lg border border-brand-200 bg-brand-50 px-2 py-1 text-xs font-bold text-brand-900 hover:bg-brand-100 disabled:opacity-40"
                                    @disabled(! $telegramBot->hasOtpConfigured())>
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-3.5 w-3.5">
                                    <path fill-rule="evenodd" d="M15.312 11.424a5.5 5.5 0 0 1-9.201 2.466l-.312-.311h2.433a.75.75 0 0 0 0-1.5H4.39a.75.75 0 0 0-.75.75v3.842a.75.75 0 0 0 1.5 0v-2.16l.31.31a7 7 0 0 0 11.712-3.138.75.75 0 0 0-1.449-.39Zm-10.624-2.85a5.5 5.5 0 0 1 9.201-2.466l.312.312H11.77a.75.75 0 0 0 0 1.5h3.842a.75.75 0 0 0 .75-.75V3.328a.75.75 0 1 0-1.5 0V5.49l-.31-.31A7 7 0 0 0 3.04 8.316a.75.75 0 1 0 1.45.39Z" clip-rule="evenodd" />
                                </svg>
                                Sync Layanan
                            </button>
                        </form>
                    </div>

                    <div class="mt-3 space-y-2 text-sm">
                        <div class="flex items-center justify-between border-b border-brand-100 pb-2">
                            <span class="text-brand-600">Modal Provider:</span>
                            <span class="font-bold text-brand-900">{{ $kopken ? $kopken->formattedProviderPrice() : '-' }}</span>
                        </div>
                        <div class="flex items-center justify-between border-b border-brand-100 pb-2">
                            <span class="text-brand-600">Stok Pusat:</span>
                            @if ($kopken)
                                @php $stock = (int) ($kopken->stock ?? 0); @endphp
                                <span class="inline-flex items-center gap-1.5 font-bold {{ $stock > 0 ? 'text-emerald-700' : 'text-rose-600' }}">
                                    <span class="h-2 w-2 rounded-full {{ $stock > 0 ? 'bg-emerald-500' : 'bg-rose-500' }}"></span>
                                    {{ $stock > 0 ? number_format($stock, 0, ',', '.').' nomor' : 'Habis (0)' }}
                                </span>
                            @else
                                <span class="font-bold text-brand-900">-</span>
                            @endif
                        </div>
                        <div class="flex items-center justify-between border-b border-brand-100 pb-2">
                            <span class="text-brand-600">Markup Anda:</span>
                            <span class="font-bold text-brand-900">{{ $telegramBot->markupLabel() }}</span>
                        </div>
                        <div class="flex items-center justify-between pt-1">
                            <span class="font-bold text-brand-900">Harga Jual Bot:</span>
                            <span class="text-base font-extrabold text-emerald-700">{{ $kopken ? $telegramBot->formattedSellPriceFor($kopken->provider_price) : '-' }}</span>
                        </div>
                    </div>

                    @if ($services->count() > 1)
                        <div class="mt-3 border-t border-brand-100 pt-2.5">
                            <p class="mb-1.5 text-[11px] font-bold uppercase tracking-wider text-brand-400">Varian Layanan ({{ $services->count() }})</p>
                            <div class="space-y-1">
                                @foreach ($services as $svc)
                                    <div class="flex items-center justify-between rounded-lg bg-brand-50/70 px-2 py-1 text-xs">
                                        <span class="mr-2 truncate font-medium text-brand-800">{{ $svc->name }}</span>
                                        <span class="shrink-0 font-bold {{ $svc->stock > 0 ? 'text-emerald-700' : 'text-rose-600' }}">
                                            {{ $svc->stock > 0 ? number_format($svc->stock, 0, ',', '.').' stok' : 'Habis' }}
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>

                {{-- Card 5: Daftar Admin Bot --}}
                <div class="rounded-2xl border border-brand-200 bg-white p-5 shadow-xs">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold uppercase tracking-wider text-brand-500">Admin Terdaftar</span>
                        <span class="rounded-md bg-brand-50 px-2 py-0.5 text-xs font-bold text-brand-900">
                            {{ count($telegramBot->adminTelegramIdList()) }} ID
                        </span>
                    </div>

                    <div class="mt-3">
                        @if ($telegramBot->adminTelegramIdList() !== [])
                            <div class="flex flex-wrap gap-1.5">
                                @foreach ($telegramBot->adminTelegramIdList() as $adminId)
                                    <span class="rounded-lg bg-brand-50 border border-brand-100 px-2 py-1 font-mono text-xs font-bold text-brand-900">{{ $adminId }}</span>
                                @endforeach
                            </div>
                        @else
                            <p class="text-xs text-amber-700">Belum ada admin terdaftar. Isi Telegram ID di formulir sebelah kiri.</p>
                        @endif
                    </div>
                </div>

                {{-- Card 6: Info Webhook & Tips --}}
                <div class="rounded-2xl border border-brand-200 bg-brand-50/50 p-4 text-xs text-brand-600 space-y-2">
                    <p class="font-bold text-brand-900">💡 Tips & Panduan</p>
                    <p>
                        • <b>Token Bot:</b> Saat Anda memasukkan atau mengganti token BotFather, sistem akan langsung memvalidasi ke Telegram dan memperbarui username bot secara otomatis.
                    </p>
                    <p>
                        • <b>Status Running/Nonaktif:</b> Mengubah status ke <b>Running</b> akan langsung mendaftarkan webhook agar bot aktif melayani user. Jika diubah ke <b>Nonaktif</b>, webhook dinonaktifkan sehingga bot berhenti sementara.
                    </p>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
