<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-extrabold tracking-tight text-brand-900">Perpanjang Subscription</h1>
                <p class="mt-1 text-sm text-brand-500">Perpanjang masa aktif sewa bot Telegram Anda.</p>
            </div>
            <a href="{{ route('dashboard') }}" class="hidden sm:inline-flex items-center gap-1.5 rounded-xl border border-brand-200 bg-white px-4 py-2 text-xs font-bold text-brand-700 hover:bg-brand-50 transition shadow-sm">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                <span>Kembali</span>
            </a>
        </div>
    </x-slot>

    @php
        $optionsJson = $periodOptions->keyBy('periods')->toJson();
    @endphp

    <div class="mx-auto max-w-xl px-4 py-8 sm:px-6 lg:px-8"
         x-data="{
             periods: 1,
             renewal: {{ $subscription->product->price_renewal }},
             options: {{ $optionsJson }},
             get currentOption() {
                 return this.options[this.periods] || {
                     days: this.periods * 30,
                     amount: this.periods * this.renewal,
                     new_expire_date: '-'
                 };
             }
         }">
        <div class="rounded-3xl border border-brand-200 bg-white p-6 sm:p-8 shadow-soft">
            {{-- Product & Current Status Card --}}
            <div class="rounded-2xl border border-brand-100 bg-brand-50/70 p-4">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wider text-brand-500">{{ $subscription->product->name }}</p>
                        <p class="mt-1 text-base font-extrabold text-brand-900">{{ $subscription->telegramBot?->name ?? 'Telegram OTP Bot' }}</p>
                        @if ($subscription->telegramBot?->username)
                            <p class="text-xs text-brand-500 font-mono">{{ $subscription->telegramBot->displayUsername() }}</p>
                        @endif
                    </div>
                    <span @class([
                        'rounded-full px-3 py-1 text-xs font-extrabold',
                        'bg-emerald-100 text-emerald-800' => $subscription->status === 'active' && ($subscription->expires_at === null || $subscription->expires_at->isFuture()),
                        'bg-rose-100 text-rose-800' => $subscription->status !== 'active' || ($subscription->expires_at && $subscription->expires_at->isPast()),
                    ])>
                        {{ $subscription->status === 'active' && ($subscription->expires_at === null || $subscription->expires_at->isFuture()) ? 'ACTIVE' : 'EXPIRED' }}
                    </span>
                </div>

                <div class="mt-4 grid grid-cols-2 gap-3 border-t border-brand-200/60 pt-3 text-xs">
                    <div>
                        <span class="text-brand-500">Berakhir saat ini:</span>
                        <p class="mt-0.5 font-bold text-brand-900">{{ $subscription->expires_at?->timezone(config('app.timezone', 'Asia/Jakarta'))->translatedFormat('d F Y') ?? '-' }}</p>
                    </div>
                    <div>
                        <span class="text-brand-500">Tarif Perpanjangan:</span>
                        <p class="mt-0.5 font-bold text-brand-900">{{ $subscription->product->formattedRenewalPrice() }} <span class="text-brand-500 font-normal">/ 30 hari</span></p>
                    </div>
                </div>
            </div>

            <form method="POST" action="{{ route('subscriptions.renew.submit', $subscription) }}" class="mt-6 space-y-6">
                @csrf

                <div>
                    <label class="mb-3 block text-sm font-extrabold text-brand-900">Pilih Durasi Perpanjangan</label>
                    <div class="space-y-2.5">
                        @foreach ($periodOptions as $option)
                            <label class="flex cursor-pointer items-center justify-between rounded-2xl border-2 p-4 transition"
                                   :class="periods == {{ $option['periods'] }} ? 'border-brand-900 bg-brand-50 shadow-sm' : 'border-brand-200 bg-white hover:border-brand-400'">
                                <div class="flex items-center gap-3">
                                    <input type="radio" name="periods" value="{{ $option['periods'] }}"
                                           class="h-4 w-4 border-brand-300 text-brand-900 focus:ring-brand-900"
                                           x-model.number="periods"
                                           @if($option['periods'] === 1) checked @endif>
                                    <div>
                                        <span class="block font-bold text-brand-900 text-sm">{{ $option['label'] }}</span>
                                        <span class="block text-xs text-brand-500 mt-0.5">
                                            Berakhir jadi: <b class="text-brand-900">{{ $option['new_expire_date'] }}</b>
                                        </span>
                                    </div>
                                </div>
                                <span class="text-base font-extrabold text-brand-900">
                                    {{ $subscription->product->formatRp($option['amount']) }}
                                </span>
                            </label>
                        @endforeach
                    </div>
                    @error('periods')
                        <p class="mt-2 text-sm text-rose-600 font-medium">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Summary Box --}}
                <div class="rounded-2xl border border-brand-200 bg-brand-50 p-4 space-y-2">
                    <div class="flex items-center justify-between text-xs text-brand-500">
                        <span>Tanggal Berakhir Baru (+<span x-text="currentOption.days"></span> hari):</span>
                        <span class="font-extrabold text-emerald-700 text-sm" x-text="currentOption.new_expire_date"></span>
                    </div>
                    <div class="flex items-baseline justify-between border-t border-brand-200 pt-2">
                        <span class="text-sm font-semibold text-brand-700">Total Pembayaran:</span>
                        <span class="text-2xl font-black text-brand-900"
                              x-text="'Rp' + new Intl.NumberFormat('id-ID').format(periods * renewal)"></span>
                    </div>
                </div>

                <button type="submit" class="w-full rounded-2xl bg-brand-900 px-5 py-3.5 text-sm font-bold text-white shadow-sm hover:bg-brand-700 transition active:scale-98">
                    Lanjutkan ke Pembayaran
                </button>
            </form>

            @error('order')
                <p class="mt-4 rounded-xl bg-rose-50 p-3 text-sm font-medium text-rose-600 border border-rose-200">{{ $message }}</p>
            @enderror
        </div>
    </div>
</x-app-layout>
