@php
    $activeProvider = $telegramBot->activeOtpProvider();
    $kopken = $services->first(fn ($s) => in_array(strtoupper($s->name), ['KOPKEN', 'WHATSAPP'])) ?? $services->first();
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <a href="{{ route('dashboard') }}" class="text-sm font-medium text-brand-500 hover:text-brand-900">Dashboard</a>
                <h1 class="mt-2 text-2xl font-extrabold tracking-tight text-brand-900">Konfigurasi Bot</h1>
                <p class="mt-1 text-sm text-brand-500">
                    {{ $telegramBot->name }}
                    @if ($telegramBot->username)
                        · {{ $telegramBot->displayUsername() }}
                    @endif
                </p>
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
        @if (session('success'))
            <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold text-emerald-800 shadow-xs">
                {{ session('success') }}
            </div>
        @endif

        @error('provider_balance')
            <div class="mb-6 rounded-2xl border border-red-200 bg-red-50 p-4 text-sm font-semibold text-red-700 shadow-xs">
                {{ $message }}
            </div>
        @enderror

        {{-- 2-Column Grid Layout: Kiri (Input & Form) | Kanan (Data & Status) --}}
        <div class="grid grid-cols-1 gap-6 md:grid-cols-12 md:gap-6 lg:gap-8 items-start">

            {{-- ==================== KOLOM KIRI (INPUT & FORM) ==================== --}}
            <div class="space-y-6 md:col-span-7">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-extrabold text-brand-900">Pengaturan & Input</h2>
                        <p class="text-xs text-brand-500">Kelola provider aktif, API key, markup, dan kontak bot.</p>
                    </div>
                </div>

                <form method="POST" action="{{ route('bots.settings', $telegramBot) }}"
                      x-data="{
                          activeProvider: '{{ old('otp_provider', $activeProvider) }}'
                      }"
                      class="space-y-6 rounded-2xl border border-brand-200 bg-white p-5 shadow-xs sm:p-7">
                    @csrf
                    @method('PUT')

                    {{-- 1. Pilihan Provider OTP Aktif --}}
                    <div>
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

                    {{-- 2. Input API Key Provider 1 (EngineUnicorn) --}}
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

                    {{-- 3. Input API Key Provider 2 (WAHub) --}}
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

                    {{-- 4. Markup Jual --}}
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

                    {{-- 5. Reminder Saldo Pusat --}}
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

                    {{-- 6. Kontak Deposit Saldo --}}
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

                    {{-- 7. Akses Admin Bot --}}
                    <div class="border-t border-brand-100 pt-5">
                        <h3 class="text-sm font-bold text-brand-900">Akses Admin Bot (/admin)</h3>
                        <p class="mb-2 text-xs text-brand-500">Telegram ID yang diperbolehkan mengakses menu /admin bot (pisahkan dengan koma).</p>
                        <input type="text" name="admin_telegram_ids"
                               value="{{ old('admin_telegram_ids', $telegramBot->admin_telegram_ids) }}"
                               placeholder="123456789, 987654321"
                               class="w-full rounded-xl border-brand-200 text-sm focus:border-brand-900 focus:ring-brand-900">
                    </div>

                    {{-- Submit Button --}}
                    <div class="border-t border-brand-100 pt-5">
                        <button type="submit" class="w-full rounded-xl bg-brand-900 px-5 py-3 text-sm font-bold text-white shadow-xs hover:bg-brand-800 transition">
                            Simpan Konfigurasi
                        </button>
                    </div>
                </form>

                {{-- Sync Layanan Button Form --}}
                <form method="POST" action="{{ route('bots.sync-services', $telegramBot) }}">
                    @csrf
                    <button type="submit"
                            class="w-full rounded-2xl border border-brand-200 bg-white px-5 py-3 text-sm font-bold text-brand-900 shadow-xs hover:bg-brand-50 transition disabled:cursor-not-allowed disabled:opacity-50"
                            @disabled(! $telegramBot->hasOtpConfigured())>
                        🔄 Sync Layanan KOPKEN ({{ $telegramBot->otpProviderName() }})
                    </button>
                </form>
            </div>

            {{-- ==================== KOLOM KANAN (DISPLAY DATA & STATUS) ==================== --}}
            <div class="space-y-5 md:col-span-5">
                <div class="sticky top-6 space-y-5">
                    <div>
                        <h2 class="text-lg font-extrabold text-brand-900">Informasi & Status</h2>
                        <p class="text-xs text-brand-500">Data live provider, saldo, dan tarif bot.</p>
                    </div>

                    {{-- Card A: Status Provider Aktif --}}
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

                    {{-- Card B: Saldo Pusat API --}}
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

                    {{-- Card C: Ringkasan Harga Layanan KOPKEN --}}
                    <div class="rounded-2xl border border-brand-200 bg-white p-5 shadow-xs">
                        <span class="text-xs font-bold uppercase tracking-wider text-brand-500">Layanan & Harga KOPKEN</span>

                        <div class="mt-3 space-y-2 text-sm">
                            <div class="flex items-center justify-between border-b border-brand-100 pb-2">
                                <span class="text-brand-600">Modal Provider:</span>
                                <span class="font-bold text-brand-900">{{ $kopken ? $kopken->formattedProviderPrice() : '-' }}</span>
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
                    </div>

                    {{-- Card D: Daftar Admin Bot --}}
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

                    {{-- Card E: Info Webhook & Bot Telegram --}}
                    <div class="rounded-2xl border border-brand-200 bg-brand-50/50 p-4 text-xs text-brand-600">
                        <p class="font-bold text-brand-900">💡 Tips Penggunaan</p>
                        <p class="mt-1">
                            Setiap pergantian provider aktif atau perubahan API key akan langsung diterapkan secara instan ke bot Telegram Anda.
                        </p>
                    </div>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
