@php
    $activeProvider = $telegramBot->activeOtpProvider();
    $kopken = $services->first(fn ($s) => in_array(strtoupper($s->name), ['KOPKEN', 'WHATSAPP'])) ?? $services->first();
    $isBotRunning = $telegramBot->isRunning();
    $hasToken = $telegramBot->hasValidToken();
    $providerName = $telegramBot->otpProviderName();
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <nav class="flex items-center gap-2 text-xs font-semibold text-brand-400 mb-1">
                    <a href="{{ route('dashboard') }}" class="hover:text-brand-900 transition">Dashboard</a>
                    <span>/</span>
                    <span class="text-brand-700">Bot Manager</span>
                </nav>
                <div class="flex flex-wrap items-center gap-3">
                    <h1 class="text-2xl font-black tracking-tight text-brand-900">{{ $telegramBot->name }}</h1>
                    @if ($telegramBot->username)
                        <a href="https://t.me/{{ $telegramBot->username }}" target="_blank"
                           class="inline-flex items-center gap-1 rounded-lg bg-sky-50 border border-sky-200/80 px-2.5 py-1 text-xs font-bold text-sky-700 hover:bg-sky-100 transition">
                            <svg class="h-3.5 w-3.5 text-[#229ED9]" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.894 8.221l-1.97 9.28c-.145.658-.537.818-1.084.508l-3-2.21-1.446 1.394c-.14.18-.357.295-.6.295-.002 0-.003 0-.005 0l.213-3.054 5.56-5.022c.24-.213-.054-.334-.373-.121l-6.869 4.326-2.96-.924c-.643-.204-.657-.643.136-.953l11.57-4.458c.538-.196 1.006.128.832.959z"/>
                            </svg>
                            {{ $telegramBot->displayUsername() }}
                        </a>
                    @endif
                    <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-[11px] font-black tracking-wider uppercase {{ $isBotRunning ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-slate-100 text-slate-600 border border-slate-200' }}">
                        <span class="h-2 w-2 rounded-full {{ $isBotRunning ? 'bg-emerald-500 animate-pulse' : 'bg-slate-400' }}"></span>
                        {{ $isBotRunning ? 'Running' : 'Nonaktif' }}
                    </span>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                @if ($telegramBot->telegramUrl())
                    <a href="{{ $telegramBot->telegramUrl() }}" target="_blank"
                       class="inline-flex items-center justify-center gap-2 rounded-xl border border-brand-200 bg-white px-4 py-2.5 text-xs font-bold text-brand-900 shadow-xs hover:bg-brand-50 transition">
                        <svg class="h-4 w-4 text-[#229ED9]" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.894 8.221l-1.97 9.28c-.145.658-.537.818-1.084.508l-3-2.21-1.446 1.394c-.14.18-.357.295-.6.295-.002 0-.003 0-.005 0l.213-3.054 5.56-5.022c.24-.213-.054-.334-.373-.121l-6.869 4.326-2.96-.924c-.643-.204-.657-.643.136-.953l11.57-4.458c.538-.196 1.006.128.832.959z"/>
                        </svg>
                        Buka Bot
                    </a>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8 space-y-6">

        {{-- ==================== TOP KPI QUICK STATS ==================== --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            {{-- Card 1: Status Bot --}}
            <div class="relative overflow-hidden rounded-2xl border border-brand-200/80 bg-white p-4 shadow-xs">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-brand-400">Status Bot</span>
                    <span class="h-2 w-2 rounded-full {{ $isBotRunning ? 'bg-emerald-500 animate-pulse' : 'bg-slate-400' }}"></span>
                </div>
                <div class="mt-2 flex items-baseline justify-between">
                    <p class="text-lg font-black text-brand-900">{{ $isBotRunning ? 'AKTIF ONLINE' : 'DIHENTIKAN' }}</p>
                    <span class="text-xs font-semibold {{ $hasToken ? 'text-emerald-700' : 'text-amber-600' }}">
                        {{ $hasToken ? 'Token OK' : 'No Token' }}
                    </span>
                </div>
                <p class="mt-1 text-[11px] text-brand-500 truncate">Webhook: {{ $isBotRunning && $hasToken ? 'Terhubung' : 'Nonaktif' }}</p>
            </div>

            {{-- Card 2: Provider Aktif --}}
            <div class="relative overflow-hidden rounded-2xl border border-brand-200/80 bg-white p-4 shadow-xs">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-brand-400">Provider Aktif</span>
                    <span class="inline-flex items-center rounded-md px-1.5 py-0.5 text-[10px] font-extrabold {{ $activeProvider === 'wahub' ? 'bg-blue-50 text-blue-700 border border-blue-200' : 'bg-purple-50 text-purple-700 border border-purple-200' }}">
                        {{ $activeProvider === 'wahub' ? 'WAHub' : 'Ninja OTP' }}
                    </span>
                </div>
                <p class="mt-2 text-lg font-black text-brand-900 truncate">{{ $activeProvider === 'wahub' ? 'WAHub (dehuyzotp)' : 'Ninja OTP (ninjatop)' }}</p>
                <p class="mt-1 text-[11px] {{ $telegramBot->hasOtpConfigured() ? 'text-emerald-700 font-semibold' : 'text-amber-600 font-semibold' }}">
                    {{ $telegramBot->hasOtpConfigured() ? '✓ API Key Terpasang' : '⚠ API Key Kosong' }}
                </p>
            </div>

            {{-- Card 3: Saldo Pusat API --}}
            <div class="relative overflow-hidden rounded-2xl border border-brand-200/80 bg-white p-4 shadow-xs">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-brand-400">Saldo Pusat API</span>
                    <form method="POST" action="{{ route('bots.provider-balance', $telegramBot) }}">
                        @csrf
                        <button type="submit"
                                title="Cek Saldo Live Sekarang"
                                class="inline-flex items-center gap-1 rounded-lg border border-brand-200 bg-brand-50 px-2 py-0.5 text-[10px] font-bold text-brand-800 hover:bg-brand-100 transition disabled:opacity-40"
                                @disabled(! $telegramBot->hasOtpConfigured())>
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-3 w-3">
                                <path fill-rule="evenodd" d="M15.312 11.424a5.5 5.5 0 0 1-9.201 2.466l-.312-.311h2.433a.75.75 0 0 0 0-1.5H4.39a.75.75 0 0 0-.75.75v3.842a.75.75 0 0 0 1.5 0v-2.16l.31.31a7 7 0 0 0 11.712-3.138.75.75 0 0 0-1.449-.39Zm-10.624-2.85a5.5 5.5 0 0 1 9.201-2.466l.312.312H11.77a.75.75 0 0 0 0 1.5h3.842a.75.75 0 0 0 .75-.75V3.328a.75.75 0 1 0-1.5 0V5.49l-.31-.31A7 7 0 0 0 3.04 8.316a.75.75 0 1 0 1.45.39Z" clip-rule="evenodd" />
                            </svg>
                            Refresh
                        </button>
                    </form>
                </div>
                <div class="mt-2 flex items-baseline gap-1.5">
                    <p class="text-lg font-black {{ $telegramBot->isProviderBalanceLow() ? 'text-rose-600' : 'text-brand-900' }}">
                        {{ $telegramBot->provider_balance !== null ? $telegramBot->formattedProviderBalance() : 'Belum Dicek' }}
                    </p>
                </div>
                <p class="mt-1 text-[11px] text-brand-400 truncate">
                    {{ $telegramBot->provider_balance_checked_at ? 'Update: ' . $telegramBot->provider_balance_checked_at->timezone(config('app.timezone'))->format('H:i:s') . ' WIB' : 'Klik Refresh untuk cek' }}
                </p>
            </div>

            {{-- Card 4: Harga & Margin --}}
            <div class="relative overflow-hidden rounded-2xl border border-brand-200/80 bg-white p-4 shadow-xs">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-brand-400">Harga Jual OTP</span>
                    <form method="POST" action="{{ route('bots.sync-services', $telegramBot) }}">
                        @csrf
                        <button type="submit"
                                title="Sync Layanan Live"
                                class="inline-flex items-center gap-1 rounded-lg border border-brand-200 bg-brand-50 px-2 py-0.5 text-[10px] font-bold text-brand-800 hover:bg-brand-100 transition disabled:opacity-40"
                                @disabled(! $telegramBot->hasOtpConfigured())>
                            Sync
                        </button>
                    </form>
                </div>
                <div class="mt-2 flex items-baseline justify-between">
                    <p class="text-lg font-black text-emerald-700">
                        {{ $kopken ? $telegramBot->formattedSellPriceFor($kopken->provider_price) : '-' }}
                    </p>
                    <span class="text-[11px] font-bold text-brand-600">Markup: {{ $telegramBot->markupLabel() }}</span>
                </div>
                <p class="mt-1 text-[11px] text-brand-500 truncate">
                    Modal: {{ $kopken ? $kopken->formattedProviderPrice() : '-' }} · Stok: {{ $kopken && $kopken->stock > 0 ? number_format($kopken->stock, 0, ',', '.') : '0' }}
                </p>
            </div>
        </div>

        {{-- ==================== FORM KONFIGURASI BOT ==================== --}}
        <form method="POST" action="{{ route('bots.settings', $telegramBot) }}"
              x-data="{
                  activeProvider: '{{ old('otp_provider', $activeProvider) }}',
                  botStatus: '{{ old('status', $telegramBot->status === 'active' ? 'active' : 'inactive') }}',
                  markupType: '{{ old('otp_markup_type', $telegramBot->otp_markup_type ?? 'percent') }}',
                  markupValue: {{ (int) old('otp_markup_percent', $telegramBot->otp_markup_percent ?? 50) }},
                  modalPrice: {{ (int) ($kopken->provider_price ?? 1650) }},
                  reminderEnabled: {{ ($telegramBot->min_provider_balance_alert && $telegramBot->min_provider_balance_alert > 0) ? 'true' : 'false' }},
                  reminderAmount: {{ (int) old('min_provider_balance_alert', $telegramBot->min_provider_balance_alert ?? 10000) }},
                  forceSubEnabled: {{ old('force_subscribe_enabled', $telegramBot->force_subscribe_enabled) ? 'true' : 'false' }},

                  get sellPrice() {
                      if (this.markupType === 'flat') return this.modalPrice + Number(this.markupValue || 0);
                      return Math.ceil(this.modalPrice * (100 + Number(this.markupValue || 0)) / 100);
                  },
                  get profit() {
                      return Math.max(0, this.sellPrice - this.modalPrice);
                  },
                  formatRp(n) {
                      return 'Rp' + new Intl.NumberFormat('id-ID').format(n || 0);
                  }
              }"
              class="space-y-6">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

                {{-- ==================== KOLOM KIRI (7 Kolom: Pengaturan Utama) ==================== --}}
                <div class="lg:col-span-7 space-y-6">

                    {{-- SECTION 1: KONEKSI BOT & PROVIDER --}}
                    <div class="rounded-2xl border border-brand-200/90 bg-white p-5 sm:p-6 shadow-xs space-y-5">
                        <div class="flex items-center justify-between border-b border-brand-100 pb-3">
                            <div class="flex items-center gap-2.5">
                                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-sky-50 text-[#229ED9]">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                    </svg>
                                </div>
                                <div>
                                    <h2 class="text-sm font-extrabold text-brand-900">Koneksi Bot & Provider OTP</h2>
                                    <p class="text-[11px] text-brand-400">Token Telegram dan penyedia stok nomor OTP</p>
                                </div>
                            </div>

                            {{-- Status Operasional Switch --}}
                            <div class="flex items-center gap-2 bg-brand-50 rounded-xl p-1 border border-brand-200/60">
                                <button type="button"
                                        @click="botStatus = 'active'"
                                        :class="botStatus === 'active' ? 'bg-emerald-600 text-white shadow-xs font-bold' : 'text-brand-600 hover:text-brand-900 font-medium'"
                                        class="rounded-lg px-2.5 py-1 text-xs transition">
                                    Aktif
                                </button>
                                <button type="button"
                                        @click="botStatus = 'inactive'"
                                        :class="botStatus === 'inactive' ? 'bg-rose-600 text-white shadow-xs font-bold' : 'text-brand-600 hover:text-brand-900 font-medium'"
                                        class="rounded-lg px-2.5 py-1 text-xs transition">
                                    Nonaktif
                                </button>
                                <input type="hidden" name="status" :value="botStatus">
                            </div>
                        </div>

                        {{-- Token Bot Telegram (BotFather) --}}
                        <div>
                            <div class="mb-1.5 flex items-center justify-between">
                                <label class="block text-xs font-bold text-brand-900">
                                    Token Bot Telegram <span class="text-rose-500">*</span>
                                </label>
                                <span class="text-[11px] font-bold {{ $hasToken ? 'text-emerald-700' : 'text-amber-600' }}">
                                    {{ $hasToken ? '✓ Terhubung' : '⚠ Belum Ada' }}
                                </span>
                            </div>

                            @if ($hasToken)
                                <div x-data="{ editing: false }">
                                    <div x-show="!editing" class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between rounded-xl border border-brand-200 bg-brand-50/50 p-2.5">
                                        <div class="flex items-center gap-2 truncate">
                                            <span class="font-mono text-xs font-bold text-brand-900">{{ $telegramBot->maskedToken() }}</span>
                                            @if ($telegramBot->username)
                                                <span class="rounded bg-brand-200/70 px-1.5 py-0.5 text-[10px] font-bold text-brand-800">
                                                    {{ $telegramBot->displayUsername() }}
                                                </span>
                                            @endif
                                        </div>
                                        <button type="button" @click="editing = true"
                                                class="shrink-0 rounded-lg border border-brand-200 bg-white px-3 py-1.5 text-xs font-bold text-brand-800 hover:bg-brand-50 shadow-2xs transition">
                                            Ganti Token
                                        </button>
                                    </div>

                                    <div x-show="editing" style="display: none;" class="space-y-2 mt-2">
                                        <input type="password" name="token" autocomplete="off"
                                               placeholder="1234567890:ABCdefGhIJKlmNoPQRsTUVwxyZ"
                                               class="w-full rounded-xl border-brand-200 font-mono text-xs focus:border-brand-900 focus:ring-brand-900">
                                        <div class="flex items-center justify-between">
                                            <button type="button" @click="editing = false"
                                                    class="rounded-lg border border-brand-200 px-2.5 py-1 text-xs font-semibold text-brand-600 hover:bg-brand-50">
                                                Batal
                                            </button>
                                            <label class="flex items-center gap-1.5 text-xs text-rose-600 cursor-pointer">
                                                <input type="checkbox" name="clear_token" value="1" class="rounded border-brand-300 text-rose-600 focus:ring-rose-500">
                                                Hapus token bot
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            @else
                                <input type="password" name="token" autocomplete="off"
                                       placeholder="1234567890:ABCdefGhIJKlmNoPQRsTUVwxyZ"
                                       class="w-full rounded-xl border-brand-200 font-mono text-xs focus:border-brand-900 focus:ring-brand-900">
                                <p class="mt-1 text-[11px] text-brand-400">Dapatkan token dari <b>@BotFather</b> di Telegram (/newbot).</p>
                            @endif
                            @error('token')
                                <p class="mt-1 text-xs text-red-600 font-semibold">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Pilihan Provider OTP --}}
                        <div class="border-t border-brand-100 pt-4 space-y-3">
                            <label class="block text-xs font-bold text-brand-900">Pilih Provider OTP</label>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                {{-- Provider 1: Ninja OTP --}}
                                <label class="relative flex cursor-pointer flex-col justify-between rounded-xl border p-3 transition"
                                       :class="activeProvider === 'kopken' ? 'border-purple-600 bg-purple-50/40 ring-2 ring-purple-500' : 'border-brand-200 bg-white hover:border-brand-300'">
                                    <div class="flex items-start justify-between gap-2">
                                        <div>
                                            <div class="flex items-center gap-1.5">
                                                <span class="inline-flex h-2 w-2 rounded-full bg-purple-600"></span>
                                                <span class="text-xs font-black text-brand-900">Ninja OTP</span>
                                            </div>
                                            <p class="mt-0.5 text-[11px] text-brand-500">app.ninjatop.cloud</p>
                                        </div>
                                        <input type="radio" name="otp_provider" value="kopken" class="sr-only" x-model="activeProvider">
                                        <span class="inline-flex h-4 w-4 shrink-0 items-center justify-center rounded-full border border-brand-300"
                                              :class="activeProvider === 'kopken' ? 'border-purple-600 bg-purple-600 text-white' : 'bg-white'">
                                            <span class="h-1.5 w-1.5 rounded-full bg-white" x-show="activeProvider === 'kopken'"></span>
                                        </span>
                                    </div>
                                    <span class="mt-2 inline-flex items-center text-[10px] font-bold {{ filled($telegramBot->otp_api_key) ? 'text-emerald-700' : 'text-amber-600' }}">
                                        {{ filled($telegramBot->otp_api_key) ? '✓ Key Terpasang' : '⚠ Key Belum Diisi' }}
                                    </span>
                                </label>

                                {{-- Provider 2: WAHub --}}
                                <label class="relative flex cursor-pointer flex-col justify-between rounded-xl border p-3 transition"
                                       :class="activeProvider === 'wahub' ? 'border-blue-600 bg-blue-50/40 ring-2 ring-blue-500' : 'border-brand-200 bg-white hover:border-brand-300'">
                                    <div class="flex items-start justify-between gap-2">
                                        <div>
                                            <div class="flex items-center gap-1.5">
                                                <span class="inline-flex h-2 w-2 rounded-full bg-blue-600"></span>
                                                <span class="text-xs font-black text-brand-900">WAHub</span>
                                            </div>
                                            <p class="mt-0.5 text-[11px] text-brand-500">dehuyzotp.shop</p>
                                        </div>
                                        <input type="radio" name="otp_provider" value="wahub" class="sr-only" x-model="activeProvider">
                                        <span class="inline-flex h-4 w-4 shrink-0 items-center justify-center rounded-full border border-brand-300"
                                              :class="activeProvider === 'wahub' ? 'border-blue-600 bg-blue-600 text-white' : 'bg-white'">
                                            <span class="h-1.5 w-1.5 rounded-full bg-white" x-show="activeProvider === 'wahub'"></span>
                                        </span>
                                    </div>
                                    <span class="mt-2 inline-flex items-center text-[10px] font-bold {{ filled($telegramBot->otp_wahub_api_key) ? 'text-emerald-700' : 'text-amber-600' }}">
                                        {{ filled($telegramBot->otp_wahub_api_key) ? '✓ Key Terpasang' : '⚠ Key Belum Diisi' }}
                                    </span>
                                </label>
                            </div>

                            {{-- Input API Key Ninja OTP --}}
                            <div x-show="activeProvider === 'kopken'" x-transition class="rounded-xl border border-purple-100 bg-purple-50/30 p-3.5 space-y-2">
                                <div class="flex items-center justify-between">
                                    <label class="block text-xs font-bold text-brand-900">API Key Ninja OTP (nk_...)</label>
                                    <a href="https://app.ninjatop.cloud/profil" target="_blank" class="text-[10px] font-bold text-purple-700 hover:underline">
                                        Buka Profil Ninja OTP ↗
                                    </a>
                                </div>

                                @if (filled($telegramBot->otp_api_key))
                                    <div x-data="{ editing: false }">
                                        <div x-show="!editing" class="flex items-center justify-between gap-2 rounded-lg bg-white border border-brand-200 px-3 py-2">
                                            <span class="text-xs font-bold text-emerald-700">✓ API Key Ninja OTP tersimpan</span>
                                            <button type="button" @click="editing = true" class="text-xs font-bold text-brand-900 hover:underline">
                                                Ganti Key
                                            </button>
                                        </div>
                                        <div x-show="editing" style="display: none;" class="space-y-2 mt-2">
                                            <input type="password" name="otp_api_key" autocomplete="off"
                                                   placeholder="Tempel API key Ninja OTP baru (nk_...)"
                                                   class="w-full rounded-xl border-brand-200 font-mono text-xs focus:border-purple-600 focus:ring-purple-600">
                                            <div class="flex items-center justify-between text-xs">
                                                <button type="button" @click="editing = false" class="text-brand-500 hover:underline">Batal</button>
                                                <label class="flex items-center gap-1 text-brand-500 cursor-pointer">
                                                    <input type="checkbox" name="clear_api_key" value="1" class="rounded border-brand-300">
                                                    Hapus key
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                @else
                                    <input type="password" name="otp_api_key" autocomplete="off"
                                           placeholder="Tempel API key Ninja OTP (nk_...)"
                                           class="w-full rounded-xl border-brand-200 font-mono text-xs focus:border-purple-600 focus:ring-purple-600">
                                @endif
                                @error('otp_api_key')
                                    <p class="mt-1 text-xs text-red-600 font-semibold">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Input API Key WAHub --}}
                            <div x-show="activeProvider === 'wahub'" x-transition class="rounded-xl border border-blue-100 bg-blue-50/30 p-3.5 space-y-2">
                                <div class="flex items-center justify-between">
                                    <label class="block text-xs font-bold text-brand-900">API Key WAHub (wh_live_...)</label>
                                    <a href="https://dehuyzotp.shop" target="_blank" class="text-[10px] font-bold text-blue-700 hover:underline">
                                        Buka Web WAHub ↗
                                    </a>
                                </div>

                                @if (filled($telegramBot->otp_wahub_api_key))
                                    <div x-data="{ editing: false }">
                                        <div x-show="!editing" class="flex items-center justify-between gap-2 rounded-lg bg-white border border-brand-200 px-3 py-2">
                                            <span class="text-xs font-bold text-emerald-700">✓ API Key WAHub tersimpan</span>
                                            <button type="button" @click="editing = true" class="text-xs font-bold text-brand-900 hover:underline">
                                                Ganti Key
                                            </button>
                                        </div>
                                        <div x-show="editing" style="display: none;" class="space-y-2 mt-2">
                                            <input type="password" name="otp_wahub_api_key" autocomplete="off"
                                                   placeholder="wh_live_xxxxxxxxxxxxxxxxxxxxxxxx"
                                                   class="w-full rounded-xl border-brand-200 font-mono text-xs focus:border-blue-600 focus:ring-blue-600">
                                            <div class="flex items-center justify-between text-xs">
                                                <button type="button" @click="editing = false" class="text-brand-500 hover:underline">Batal</button>
                                                <label class="flex items-center gap-1 text-brand-500 cursor-pointer">
                                                    <input type="checkbox" name="clear_wahub_api_key" value="1" class="rounded border-brand-300">
                                                    Hapus key
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                @else
                                    <input type="password" name="otp_wahub_api_key" autocomplete="off"
                                           placeholder="wh_live_xxxxxxxxxxxxxxxxxxxxxxxx"
                                           class="w-full rounded-xl border-brand-200 font-mono text-xs focus:border-blue-600 focus:ring-blue-600">
                                @endif
                                @error('otp_wahub_api_key')
                                    <p class="mt-1 text-xs text-red-600 font-semibold">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>

                    {{-- SECTION 2: HARGA, MARKUP & ALERT --}}
                    <div class="rounded-2xl border border-brand-200/90 bg-white p-5 sm:p-6 shadow-xs space-y-5">
                        <div class="flex items-center gap-2.5 border-b border-brand-100 pb-3">
                            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-50 text-emerald-700">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <div>
                                <h2 class="text-sm font-extrabold text-brand-900">Markup Harga & Reminder Saldo</h2>
                                <p class="text-[11px] text-brand-400">Atur keuntungan penjualan OTP dan peringatan saldo</p>
                            </div>
                        </div>

                        {{-- Markup Jual --}}
                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <label class="block text-xs font-bold text-brand-900">Pengaturan Markup Jual</label>
                                <div class="inline-flex rounded-lg bg-brand-100/70 p-0.5 border border-brand-200">
                                    <button type="button" @click="markupType = 'percent'"
                                            :class="markupType === 'percent' ? 'bg-white font-bold text-brand-900 shadow-2xs' : 'text-brand-600 font-medium'"
                                            class="rounded-md px-2.5 py-1 text-xs transition">
                                        Persen (%)
                                    </button>
                                    <button type="button" @click="markupType = 'flat'"
                                            :class="markupType === 'flat' ? 'bg-white font-bold text-brand-900 shadow-2xs' : 'text-brand-600 font-medium'"
                                            class="rounded-md px-2.5 py-1 text-xs transition">
                                        Flat (Rp)
                                    </button>
                                </div>
                                <input type="hidden" name="otp_markup_type" :value="markupType">
                            </div>

                            <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                                <div class="flex w-full sm:w-44 overflow-hidden rounded-xl border border-brand-200 shadow-2xs">
                                    <span class="inline-flex min-w-[2.75rem] items-center justify-center bg-brand-900 px-3 text-xs font-black text-white"
                                          x-text="markupType === 'flat' ? 'Rp' : '%'"></span>
                                    <input type="number" name="otp_markup_percent" min="0" required x-model.number="markupValue"
                                           class="w-full border-0 bg-white px-3 py-2 text-sm font-bold text-brand-900 focus:outline-none focus:ring-0">
                                </div>

                                {{-- Live Calculation Badge --}}
                                <div class="flex-1 rounded-xl bg-brand-50 border border-brand-200/70 px-3.5 py-2 text-xs flex items-center justify-between">
                                    <span class="text-brand-500">Modal <b class="text-brand-800" x-text="formatRp(modalPrice)"></b></span>
                                    <span>→</span>
                                    <span class="text-brand-500">Jual <b class="text-emerald-700 text-sm" x-text="formatRp(sellPrice)"></b></span>
                                    <span class="text-[10px] font-bold text-emerald-600 bg-emerald-100/60 rounded px-1.5 py-0.5" x-text="'+' + formatRp(profit)"></span>
                                </div>
                            </div>
                        </div>

                        {{-- Reminder Saldo Pusat --}}
                        <div class="border-t border-brand-100 pt-4 space-y-3">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-xs font-bold text-brand-900">Reminder Saldo Menipis</p>
                                    <p class="text-[11px] text-brand-400">Peringatan otomatis saat saldo provider di bawah batas</p>
                                </div>

                                <button type="button"
                                        @click="reminderEnabled = !reminderEnabled; if(reminderEnabled && reminderAmount <= 0) { reminderAmount = 10000; }"
                                        :class="reminderEnabled ? 'bg-brand-900' : 'bg-slate-300'"
                                        class="relative inline-flex h-5 w-10 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none"
                                        role="switch" :aria-checked="reminderEnabled">
                                    <span :class="reminderEnabled ? 'translate-x-5' : 'translate-x-0'"
                                          class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow-xs transition duration-200 ease-in-out"></span>
                                </button>
                            </div>

                            <input type="hidden" name="min_provider_balance_alert" :value="reminderEnabled ? reminderAmount : 0">

                            <div x-show="reminderEnabled" x-transition class="flex items-center gap-2">
                                <span class="text-xs text-brand-600 shrink-0">Batas Minimum:</span>
                                <div class="flex w-44 overflow-hidden rounded-xl border border-brand-200">
                                    <span class="inline-flex min-w-[2.5rem] items-center justify-center bg-brand-100 px-2.5 text-xs font-bold text-brand-700">Rp</span>
                                    <input type="number" step="1000" min="0" x-model.number="reminderAmount"
                                           class="w-full border-0 bg-white px-2.5 py-1.5 text-xs font-bold text-brand-900 focus:outline-none focus:ring-0">
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- SECTION 3: MENU BOT & FITUR TAMBAHAN --}}
                    <div class="rounded-2xl border border-brand-200/90 bg-white p-5 sm:p-6 shadow-xs space-y-5">
                        <div class="flex items-center gap-2.5 border-b border-brand-100 pb-3">
                            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-50 text-amber-700">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                            </div>
                            <div>
                                <h2 class="text-sm font-extrabold text-brand-900">Admin, Kontak & Fitur Tambahan</h2>
                                <p class="text-[11px] text-brand-400">Kontak deposit manual, hak akses /admin, dan force subscribe</p>
                            </div>
                        </div>

                        {{-- Kontak Deposit Manual --}}
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="mb-1 block text-xs font-bold text-brand-900">WhatsApp Admin (Deposit)</label>
                                <input type="text" name="deposit_whatsapp"
                                       value="{{ old('deposit_whatsapp', $telegramBot->deposit_whatsapp) }}"
                                       placeholder="62812xxxx"
                                       class="w-full rounded-xl border-brand-200 text-xs focus:border-brand-900 focus:ring-brand-900">
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-bold text-brand-900">Telegram Admin (Deposit)</label>
                                <input type="text" name="deposit_telegram"
                                       value="{{ old('deposit_telegram', $telegramBot->deposit_telegram) }}"
                                       placeholder="@username"
                                       class="w-full rounded-xl border-brand-200 text-xs focus:border-brand-900 focus:ring-brand-900">
                            </div>
                        </div>

                        {{-- Akses Admin Bot --}}
                        <div class="border-t border-brand-100 pt-3">
                            <label class="mb-1 block text-xs font-bold text-brand-900">
                                ID Admin Bot (/admin)
                                <span class="text-[10px] font-normal text-brand-400">(pisahkan koma)</span>
                            </label>
                            <input type="text" name="admin_telegram_ids"
                                   value="{{ old('admin_telegram_ids', $telegramBot->admin_telegram_ids) }}"
                                   placeholder="123456789, 987654321"
                                   class="w-full rounded-xl border-brand-200 font-mono text-xs focus:border-brand-900 focus:ring-brand-900">
                        </div>

                        {{-- Force Subscribe Channel --}}
                        <div class="border-t border-brand-100 pt-3 space-y-3">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-xs font-bold text-brand-900">Force Subscribe Channel</p>
                                    <p class="text-[11px] text-brand-400">User wajib bergabung ke channel Telegram Anda sebelum order</p>
                                </div>
                                <button type="button"
                                        @click="forceSubEnabled = !forceSubEnabled"
                                        :class="forceSubEnabled ? 'bg-brand-900' : 'bg-slate-300'"
                                        class="relative inline-flex h-5 w-10 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none"
                                        role="switch" :aria-checked="forceSubEnabled">
                                    <span :class="forceSubEnabled ? 'translate-x-5' : 'translate-x-0'"
                                          class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow-xs transition duration-200 ease-in-out"></span>
                                </button>
                            </div>

                            <input type="hidden" name="force_subscribe_enabled" :value="forceSubEnabled ? '1' : '0'">

                            <div x-show="forceSubEnabled" x-transition class="rounded-xl border border-rose-100 bg-rose-50/40 p-3 space-y-3">
                                <div>
                                    <label class="mb-1 block text-[11px] font-bold text-brand-900">Username Channel / ID Numerik</label>
                                    <input type="text" name="force_subscribe_channel"
                                           value="{{ old('force_subscribe_channel', $telegramBot->force_subscribe_channel) }}"
                                           placeholder="@namachannel atau -1001234567890"
                                           class="w-full rounded-xl border-brand-200 font-mono text-xs focus:border-brand-900 focus:ring-brand-900">
                                </div>
                                <div>
                                    <label class="mb-1 block text-[11px] font-bold text-brand-900">Link Join Channel (Tombol)</label>
                                    <input type="url" name="force_subscribe_join_url"
                                           value="{{ old('force_subscribe_join_url', $telegramBot->force_subscribe_join_url) }}"
                                           placeholder="https://t.me/namachannel"
                                           class="w-full rounded-xl border-brand-200 text-xs focus:border-brand-900 focus:ring-brand-900">
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Sticky/Bottom Save Button --}}
                    <div class="pt-2">
                        <button type="submit"
                                class="w-full rounded-xl bg-brand-900 px-6 py-3.5 text-sm font-extrabold text-white shadow-md hover:bg-brand-800 transition active:scale-[0.99] flex items-center justify-center gap-2">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            Simpan Perubahan Bot
                        </button>
                    </div>
                </div>

                {{-- ==================== KOLOM KANAN (5 Kolom: Live Monitor & Info) ==================== --}}
                <div class="lg:col-span-5 space-y-5 lg:sticky lg:top-6">

                    {{-- Card Live Summary: Layanan & Stok Terkini --}}
                    <div class="rounded-2xl border border-brand-200 bg-white p-5 shadow-xs space-y-4">
                        <div class="flex items-center justify-between border-b border-brand-100 pb-2.5">
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-black uppercase tracking-wider text-brand-800">Layanan & Stok Live</span>
                                <span class="rounded bg-brand-100 px-1.5 py-0.5 text-[10px] font-bold text-brand-700">{{ $services->count() }}</span>
                            </div>
                            <form method="POST" action="{{ route('bots.sync-services', $telegramBot) }}">
                                @csrf
                                <button type="submit"
                                        class="inline-flex items-center gap-1 text-[11px] font-bold text-brand-700 hover:text-brand-900 transition"
                                        @disabled(! $telegramBot->hasOtpConfigured())>
                                    <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                    </svg>
                                    Sync
                                </button>
                            </form>
                        </div>

                        <div class="space-y-2 max-h-56 overflow-y-auto pr-1">
                            @forelse ($services as $svc)
                                <div class="flex items-center justify-between rounded-xl bg-brand-50/70 p-2.5 text-xs">
                                    <div class="min-w-0 pr-2">
                                        <p class="font-bold text-brand-900 truncate">{{ $svc->name }}</p>
                                        <p class="text-[10px] text-brand-400">Modal {{ $svc->formattedProviderPrice() }}</p>
                                    </div>
                                    <div class="text-right shrink-0">
                                        <p class="font-black {{ $svc->stock > 0 ? 'text-emerald-700' : 'text-rose-600' }}">
                                            {{ $svc->stock > 0 ? number_format($svc->stock, 0, ',', '.') . ' stok' : 'Habis' }}
                                        </p>
                                        <p class="text-[10px] font-extrabold text-brand-700">
                                            Jual {{ $telegramBot->formattedSellPriceFor($svc->provider_price) }}
                                        </p>
                                    </div>
                                </div>
                            @empty
                                <div class="rounded-xl border border-dashed border-brand-200 p-4 text-center text-xs text-brand-400">
                                    Belum ada layanan tersinkron. Klik Sync di atas.
                                </div>
                            @endforelse
                        </div>
                    </div>

                    {{-- Card Live Summary: Admin Terdaftar --}}
                    <div class="rounded-2xl border border-brand-200 bg-white p-5 shadow-xs space-y-3">
                        <div class="flex items-center justify-between border-b border-brand-100 pb-2.5">
                            <span class="text-xs font-black uppercase tracking-wider text-brand-800">Admin Terdaftar</span>
                            <span class="rounded bg-brand-100 px-1.5 py-0.5 text-[10px] font-bold text-brand-700">
                                {{ count($telegramBot->adminTelegramIdList()) }} ID
                            </span>
                        </div>

                        <div class="flex flex-wrap gap-1.5">
                            @forelse ($telegramBot->adminTelegramIdList() as $adminId)
                                <span class="rounded-lg bg-brand-50 border border-brand-200/80 px-2 py-1 font-mono text-xs font-bold text-brand-800">
                                    {{ $adminId }}
                                </span>
                            @empty
                                <p class="text-xs text-brand-400 italic">Belum ada admin terdaftar.</p>
                            @endforelse
                        </div>
                    </div>

                    {{-- Panduan Cepat --}}
                    <div class="rounded-2xl border border-brand-100 bg-brand-50/60 p-4 space-y-2 text-xs text-brand-600">
                        <p class="font-extrabold text-brand-900 flex items-center gap-1.5">
                            <svg class="h-3.5 w-3.5 text-brand-700" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Panduan Cepat
                        </p>
                        <ul class="space-y-1 text-[11px] leading-relaxed text-brand-500 list-disc list-inside">
                            <li><b>Status Aktif</b> langsung mengaktifkan webhook & bot siap melayani order.</li>
                            <li>Ganti token BotFather akan otomatis memperbarui username & webhook bot.</li>
                            <li>API Key Ninja OTP wajib diawali format <code class="bg-brand-100 px-1 rounded text-brand-800">nk_...</code>.</li>
                        </ul>
                    </div>

                </div>

            </div>
        </form>

    </div>
</x-app-layout>
