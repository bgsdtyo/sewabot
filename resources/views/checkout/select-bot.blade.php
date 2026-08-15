<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-2xl font-extrabold tracking-tight text-brand-900">Sewa Telegram Bot</h1>
            <p class="mt-1 text-sm text-brand-500">{{ $product->name }}</p>
        </div>
    </x-slot>

    <div class="mx-auto max-w-2xl px-4 py-10 sm:px-6 lg:px-8">
        <x-checkout-steps :current="$step" />

        @if ($step === 1)
            <form method="POST" action="{{ route('checkout.duration', $product) }}" class="rounded-2xl border border-brand-200 bg-white p-6 shadow-soft sm:p-8">
                @csrf
                <h2 class="text-xl font-extrabold text-brand-900">Step 1 — Pilih Durasi</h2>
                <p class="mt-2 text-sm leading-relaxed text-brand-500">
                    Maksimal 120 hari. Aktivasi {{ $product->formattedActivationPrice() }}, tiap +30 hari +{{ $product->formattedRenewalPrice() }}.
                </p>

                <div class="mt-8 grid gap-4 sm:grid-cols-2">
                    @foreach ($periodOptions as $option)
                        <label class="relative flex cursor-pointer flex-col rounded-xl border p-5 transition hover:border-brand-500 has-[:checked]:border-brand-900 has-[:checked]:bg-brand-50 has-[:checked]:ring-1 has-[:checked]:ring-brand-900">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="font-semibold text-brand-900">{{ $option['label'] }}</p>
                                    <p class="mt-1.5 text-xs leading-relaxed text-brand-500">{{ $option['breakdown'] }}</p>
                                </div>
                                <input type="radio" name="periods" value="{{ $option['periods'] }}"
                                       class="mt-1 h-4 w-4 border-brand-300 text-brand-900 focus:ring-brand-900"
                                       @checked((int) $periods === (int) $option['periods'])
                                       required>
                            </div>
                            <p class="mt-4 text-lg font-extrabold text-brand-900">{{ $product->formatRp($option['amount']) }}</p>
                        </label>
                    @endforeach
                </div>

                @error('periods')
                    <p class="mt-4 text-sm text-red-600">{{ $message }}</p>
                @enderror

                <div class="mt-8 flex justify-end border-t border-brand-100 pt-6">
                    <button type="submit" class="rounded-xl bg-brand-900 px-6 py-3 text-sm font-semibold text-white hover:bg-brand-700">
                        Lanjut
                    </button>
                </div>
            </form>
        @else
            <form method="POST" action="{{ route('checkout.start', $product) }}" class="rounded-2xl border border-brand-200 bg-white p-6 shadow-soft sm:p-8">
                @csrf
                <input type="hidden" name="periods" value="{{ $periods }}">

                <h2 class="text-xl font-extrabold text-brand-900">Step 2 — Konfigurasi Bot</h2>
                <p class="mt-2 text-sm leading-relaxed text-brand-500">
                    Isi nama bot. Username Telegram akan diisi admin setelah bot siap.
                </p>

                <div class="mt-6 rounded-xl bg-brand-50 px-4 py-3 text-sm text-brand-500">
                    Durasi: <span class="font-semibold text-brand-900">{{ $selectedDays }} hari</span>
                    · Total: <span class="font-semibold text-brand-900">{{ $product->formatRp($selectedAmount) }}</span>
                </div>

                <div class="mt-6 space-y-5">
                    <div>
                        <label class="mb-2 block text-sm font-semibold text-brand-900">Nama Bot</label>
                        <input type="text" name="bot_name" required minlength="3" maxlength="60"
                               placeholder="Contoh: Toko Bagus OTP"
                               value="{{ old('bot_name') }}"
                               class="w-full rounded-xl border-brand-200 focus:border-brand-900 focus:ring-brand-900">
                        @error('bot_name')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="mt-8 flex flex-col-reverse gap-3 border-t border-brand-100 pt-6 sm:flex-row sm:justify-between">
                    <a href="{{ route('checkout.select-bot', ['product' => $product, 'step' => 1]) }}"
                       class="rounded-xl border border-brand-200 px-6 py-3 text-center text-sm font-semibold text-brand-700 hover:bg-brand-50">
                        Kembali
                    </a>
                    <button type="submit" class="rounded-xl bg-brand-900 px-6 py-3 text-sm font-semibold text-white hover:bg-brand-700">
                        Lanjut ke Pembayaran
                    </button>
                </div>
            </form>
        @endif

        @error('order')
            <p class="mt-4 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>
</x-app-layout>
