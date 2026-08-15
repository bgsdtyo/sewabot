@php
    $kopken = $services->first(fn ($s) => strtoupper($s->name) === 'KOPKEN') ?? $services->first();
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
                   class="inline-flex w-full items-center justify-center rounded-xl border border-brand-200 px-5 py-3 text-sm font-semibold text-brand-900 hover:bg-brand-50 sm:w-auto">
                    Buka Telegram
                </a>
            @endif
        </div>
    </x-slot>

    <div class="mx-auto max-w-3xl space-y-10 px-4 py-10 sm:px-6 lg:px-8">
        @if (session('success'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                {{ session('success') }}
            </div>
        @endif

        <section class="grid grid-cols-2 gap-4">
            <div class="rounded-2xl border border-brand-200 bg-white px-4 py-5">
                <p class="text-xs text-brand-500">API Key</p>
                <p class="mt-2 text-base font-extrabold {{ filled($telegramBot->otp_api_key) ? 'text-emerald-700' : 'text-amber-700' }}">
                    {{ filled($telegramBot->otp_api_key) ? 'Siap' : 'Kosong' }}
                </p>
            </div>
            <div class="rounded-2xl border border-brand-200 bg-white px-4 py-5">
                <p class="text-xs text-brand-500">Harga KOPKEN</p>
                <p class="mt-2 text-base font-extrabold text-brand-900">
                    {{ $kopken ? $telegramBot->formattedSellPriceFor($kopken->provider_price) : '-' }}
                </p>
                <p class="mt-1 text-xs text-brand-500">Markup {{ $telegramBot->markupLabel() }}</p>
            </div>
        </section>

        <section>
            <h2 class="text-lg font-extrabold text-brand-900">Pengaturan</h2>
            <p class="mt-1 text-sm text-brand-500">API key dan markup bot ini. Member & riwayat OTP ada di Dashboard.</p>

            <form method="POST" action="{{ route('bots.settings', $telegramBot) }}"
                  class="mt-6 space-y-8 rounded-2xl border border-brand-200 bg-white p-5 sm:p-8">
                @csrf
                @method('PUT')

                <div>
                    <label class="mb-2 block text-sm font-semibold text-brand-900">API Key provider</label>

                    @if (filled($telegramBot->otp_api_key))
                        <div x-data="{ editing: false }">
                            <div x-show="!editing" class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <p class="rounded-xl border border-brand-200 bg-brand-50 px-4 py-3 text-sm font-medium text-emerald-700 sm:flex-1">
                                    API key tersimpan
                                </p>
                                <button type="button" @click="editing = true"
                                        class="rounded-xl border border-brand-200 px-4 py-3 text-sm font-semibold text-brand-900 hover:bg-brand-50">
                                    Ganti
                                </button>
                            </div>

                            <div x-show="editing" style="display: none;" class="space-y-3">
                                <input type="password" name="otp_api_key" autocomplete="off"
                                       placeholder="Tempel API key baru"
                                       class="w-full rounded-xl border-brand-200 focus:border-brand-900 focus:ring-brand-900">
                                <div class="flex flex-wrap items-center gap-3">
                                    <button type="button" @click="editing = false"
                                            class="rounded-xl border border-brand-200 px-4 py-2 text-sm font-semibold text-brand-700 hover:bg-brand-50">
                                        Batal
                                    </button>
                                    <label class="flex items-center gap-2 text-sm text-brand-500">
                                        <input type="checkbox" name="clear_api_key" value="1" class="rounded border-brand-300 text-brand-900 focus:ring-brand-900">
                                        Hapus key
                                    </label>
                                </div>
                            </div>
                        </div>
                    @else
                        <input type="password" name="otp_api_key" autocomplete="off" required
                               placeholder="Tempel API key"
                               class="w-full rounded-xl border-brand-200 focus:border-brand-900 focus:ring-brand-900">
                    @endif
                    @error('otp_api_key')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div x-data="{
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
                    <label class="mb-3 block text-sm font-semibold text-brand-900">Markup jual</label>

                    <div class="mb-4 grid grid-cols-2 gap-2">
                        <label class="cursor-pointer rounded-xl border px-4 py-3 text-center text-sm font-semibold transition"
                               :class="markupType === 'percent' ? 'border-brand-900 bg-brand-50 text-brand-900' : 'border-brand-200 text-brand-500'">
                            <input type="radio" name="otp_markup_type" value="percent" class="sr-only" x-model="markupType">
                            Persen (%)
                        </label>
                        <label class="cursor-pointer rounded-xl border px-4 py-3 text-center text-sm font-semibold transition"
                               :class="markupType === 'flat' ? 'border-brand-900 bg-brand-50 text-brand-900' : 'border-brand-200 text-brand-500'">
                            <input type="radio" name="otp_markup_type" value="flat" class="sr-only" x-model="markupType">
                            Flat (Rp)
                        </label>
                    </div>

                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                        <div class="flex w-full overflow-hidden rounded-xl border border-brand-200 sm:w-52">
                            <span class="inline-flex min-w-[3.25rem] items-center justify-center bg-brand-900 px-3 text-sm font-bold text-white"
                                  x-text="markupType === 'flat' ? 'Rp' : '%'"></span>
                            <input type="number" name="otp_markup_percent" min="0" required x-model.number="markupValue"
                                   class="w-full border-0 bg-white px-3 py-2.5 text-brand-900 focus:border-transparent focus:outline-none focus:ring-0">
                        </div>
                        <p class="text-sm text-brand-500">
                            Modal <span x-text="formatRp(modal)"></span>
                            → jual
                            <span class="font-semibold text-brand-900" x-text="formatRp(sellPrice)"></span>
                        </p>
                    </div>
                    @error('otp_markup_type')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                    @error('otp_markup_percent')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="border-t border-brand-100 pt-6">
                    <h3 class="text-sm font-extrabold text-brand-900">Kontak Deposit Saldo</h3>
                    <p class="mt-1 text-sm text-brand-500">
                        Deposit saat ini manual. Tombol WhatsApp & Telegram muncul di bot saat member pilih Deposit.
                    </p>

                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-2 block text-sm font-semibold text-brand-900">WhatsApp admin</label>
                            <input type="text" name="deposit_whatsapp"
                                   value="{{ old('deposit_whatsapp', $telegramBot->deposit_whatsapp) }}"
                                   placeholder="62812xxxx atau https://wa.me/62812xxxx"
                                   class="w-full rounded-xl border-brand-200 focus:border-brand-900 focus:ring-brand-900">
                            @error('deposit_whatsapp')
                                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-semibold text-brand-900">Telegram admin</label>
                            <input type="text" name="deposit_telegram"
                                   value="{{ old('deposit_telegram', $telegramBot->deposit_telegram) }}"
                                   placeholder="@username atau https://t.me/username"
                                   class="w-full rounded-xl border-brand-200 focus:border-brand-900 focus:ring-brand-900">
                            @error('deposit_telegram')
                                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="mt-4">
                        <label class="mb-2 block text-sm font-semibold text-brand-900">Catatan deposit (opsional)</label>
                        <textarea name="deposit_note" rows="3"
                                  placeholder="Contoh: Transfer ke rekening BCA a/n ... lalu kirim bukti ke admin."
                                  class="w-full rounded-xl border-brand-200 focus:border-brand-900 focus:ring-brand-900">{{ old('deposit_note', $telegramBot->deposit_note) }}</textarea>
                        @error('deposit_note')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="border-t border-brand-100 pt-6">
                    <button type="submit" class="w-full rounded-xl bg-brand-900 px-5 py-3 text-sm font-semibold text-white hover:bg-brand-700 sm:w-auto">
                        Simpan
                    </button>
                </div>
            </form>

            <form method="POST" action="{{ route('bots.sync-services', $telegramBot) }}" class="mt-4">
                @csrf
                <button type="submit"
                        class="w-full rounded-xl border border-brand-200 bg-white px-5 py-3 text-sm font-semibold text-brand-900 hover:bg-brand-50 disabled:cursor-not-allowed disabled:opacity-50 sm:w-auto"
                        @disabled(! filled($telegramBot->otp_api_key))>
                    Sync layanan KOPKEN
                </button>
            </form>
        </section>
    </div>
</x-app-layout>
