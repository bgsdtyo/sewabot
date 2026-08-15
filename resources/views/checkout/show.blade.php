<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-2xl font-extrabold tracking-tight text-brand-900">Pembayaran</h1>
            <p class="mt-1 text-sm text-brand-500">{{ $order->invoice_number }}</p>
        </div>
    </x-slot>

    <div class="mx-auto max-w-2xl px-4 py-10 sm:px-6 lg:px-8">
        <x-checkout-steps :current="3" />

        <div class="rounded-2xl border border-brand-200 bg-white p-6 shadow-soft sm:p-8">
            <h2 class="text-xl font-extrabold text-brand-900">Step 3 — Pembayaran</h2>

            <div class="mt-6 space-y-2 border-b border-brand-100 pb-6">
                <p class="text-sm text-brand-500">{{ $order->product->name }}</p>
                <p class="text-sm text-brand-500">
                    Masa Sewa: <span class="font-semibold text-brand-900">{{ $order->duration_days }} Hari</span>
                    @if ($order->type === 'renewal') · Perpanjangan @endif
                </p>
                @if ($order->telegramBot)
                    <p class="text-sm text-brand-500">
                        Nama Bot: <span class="font-semibold text-brand-900">{{ $order->telegramBot->name }}</span>
                    </p>
                @endif
                <p class="pt-2 text-sm text-brand-500">Total Pembayaran</p>
                <p class="text-3xl font-extrabold text-brand-900">{{ $order->formattedAmount() }}</p>
            </div>

            <div class="mt-8">
                <p class="font-semibold text-brand-900">Scan QRIS untuk pembayaran</p>
                <p class="mt-1 text-sm text-brand-500">Nominal sudah terisi otomatis: <span class="font-semibold text-brand-900">{{ $order->formattedAmount() }}</span></p>
                <div class="mt-5 flex justify-center rounded-2xl border border-dashed border-brand-200 bg-brand-50 p-6">
                    @if ($qrisDynamicReady)
                        <img src="{{ route('checkout.qris', $order) }}?v={{ $order->id }}-{{ (int) $order->amount }}"
                             alt="QRIS Dinamis"
                             class="max-h-72 w-auto rounded-lg bg-white p-2 shadow-sm"
                             width="288" height="288">
                    @elseif (!empty($payment['qris_image']))
                        <img src="{{ asset('storage/'.$payment['qris_image']) }}" alt="QRIS" class="max-h-64 w-auto rounded-lg">
                    @else
                        <div class="text-center text-sm text-brand-500">
                            <p class="font-medium text-brand-700">QRIS dinamis belum dikonfigurasi</p>
                            <p class="mt-1">Admin: Settings → Payment → paste QRIS Static String</p>
                        </div>
                    @endif
                </div>
                <p class="mt-4 text-sm text-brand-500">Nama penerima:</p>
                <p class="font-semibold text-brand-900">{{ $payment['merchant_name'] }}</p>
                @if (!empty($payment['payment_instruction']))
                    <p class="mt-4 whitespace-pre-line text-sm leading-relaxed text-brand-500">{{ $payment['payment_instruction'] }}</p>
                @endif
            </div>

            <div class="mt-8 border-t border-brand-100 pt-8">
                @if ($order->status === 'rejected' && $order->admin_note)
                    <div class="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                        {{ $order->admin_note }}
                    </div>
                @endif

                @if ($order->status === 'paid')
                    <a href="{{ route('checkout.success', $order) }}" class="inline-flex w-full items-center justify-center rounded-xl bg-brand-900 px-5 py-3 text-sm font-semibold text-white hover:bg-brand-700">
                        Lihat Status
                    </a>
                @elseif ($order->payment_proof && $order->status === 'pending')
                    <a href="{{ route('checkout.success', $order) }}" class="inline-flex w-full items-center justify-center rounded-xl bg-brand-900 px-5 py-3 text-sm font-semibold text-white hover:bg-brand-700">
                        Lihat Status Pengajuan
                    </a>
                @else
                    <form method="POST" action="{{ route('checkout.upload-proof', $order) }}" enctype="multipart/form-data" class="space-y-4">
                        @csrf
                        <div>
                            <label class="mb-2 block text-sm font-semibold text-brand-900">Upload Bukti Pembayaran</label>
                            <input type="file" name="payment_proof" accept="image/*" required
                                class="block w-full rounded-xl border border-brand-200 bg-white px-3 py-2 text-sm file:mr-4 file:rounded-lg file:border-0 file:bg-brand-900 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white">
                            @error('payment_proof')
                                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <button type="submit" class="w-full rounded-xl bg-brand-900 px-5 py-3 text-sm font-semibold text-white hover:bg-brand-700">
                            Kirim Pembayaran
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
