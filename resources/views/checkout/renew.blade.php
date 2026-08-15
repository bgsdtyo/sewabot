<x-app-layout>
    <x-slot name="header">
        <h1 class="text-2xl font-extrabold tracking-tight text-brand-900">Perpanjang Subscription</h1>
    </x-slot>

    <div class="mx-auto max-w-lg px-4 py-10 sm:px-6 lg:px-8"
         x-data="{ periods: 1, renewal: {{ $subscription->product->price_renewal }}, periodDays: {{ $subscription->product->duration_days ?: 30 }} }">
        <div class="rounded-2xl border border-brand-200 bg-white p-8 shadow-soft">
            <p class="text-sm text-brand-500">{{ $subscription->product->name }}</p>
            <p class="mt-4 text-sm text-brand-500">
                Berakhir saat ini:
                <span class="font-semibold text-brand-900">{{ $subscription->expires_at?->translatedFormat('d F Y') ?? '-' }}</span>
            </p>
            <p class="mt-2 text-sm text-brand-500">
                Status: <span class="font-semibold">{{ strtoupper($subscription->status) }}</span>
            </p>
            <p class="mt-4 text-sm text-brand-500">
                Perpanjangan {{ $subscription->product->formattedRenewalPrice() }} / 30 hari · maks. 120 hari
            </p>

            <form method="POST" action="{{ route('subscriptions.renew.submit', $subscription) }}" class="mt-8 space-y-5">
                @csrf

                <div>
                    <label class="mb-2 block text-sm font-semibold text-brand-900">Pilih Durasi</label>
                    <div class="space-y-2">
                        @foreach ($periodOptions as $option)
                            <label class="flex cursor-pointer items-center justify-between rounded-xl border border-brand-200 px-4 py-3 hover:border-brand-900"
                                   :class="periods == {{ $option['periods'] }} ? 'border-brand-900 bg-brand-50' : ''">
                                <span class="font-medium">{{ $option['label'] }}</span>
                                <span class="flex items-center gap-3">
                                    <span class="font-bold">{{ $subscription->product->formatRp($option['amount']) }}</span>
                                    <input type="radio" name="periods" value="{{ $option['periods'] }}"
                                           class="h-4 w-4 border-brand-300 text-brand-900 focus:ring-brand-900"
                                           x-model.number="periods"
                                           @if($option['periods'] === 1) checked @endif>
                                </span>
                            </label>
                        @endforeach
                    </div>
                    @error('periods')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="rounded-xl bg-brand-50 px-4 py-3">
                    <p class="text-sm text-brand-500">Total</p>
                    <p class="text-2xl font-extrabold text-brand-900"
                       x-text="'Rp' + new Intl.NumberFormat('id-ID').format(periods * renewal)"></p>
                </div>

                <button type="submit" class="w-full rounded-xl bg-brand-900 px-5 py-3 text-sm font-semibold text-white hover:bg-brand-700">
                    Lanjutkan Pembayaran
                </button>
            </form>

            @error('order')
                <p class="mt-4 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>
    </div>
</x-app-layout>
