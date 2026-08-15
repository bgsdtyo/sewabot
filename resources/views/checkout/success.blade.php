<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-2xl font-extrabold tracking-tight text-brand-900">Selesai</h1>
            <p class="mt-1 text-sm text-brand-500">{{ $order->invoice_number }}</p>
        </div>
    </x-slot>

    <div class="mx-auto max-w-2xl px-4 py-10 sm:px-6 lg:px-8">
        <x-checkout-steps :current="4" />

        <div class="rounded-2xl border border-brand-200 bg-white p-6 text-center shadow-soft sm:p-10">
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-emerald-50 text-emerald-700">
                <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            </div>

            <h2 class="mt-6 text-2xl font-extrabold text-brand-900">Step 4 — Pengajuan Berhasil</h2>

            @if ($order->status === 'paid')
                <p class="mx-auto mt-3 max-w-md text-sm leading-relaxed text-brand-500">
                    Pembayaran sudah dikonfirmasi. Bot kamu aktif dan siap digunakan.
                </p>
                <p class="mt-4 text-sm font-semibold text-emerald-700">● PAID</p>
            @else
                <p class="mx-auto mt-3 max-w-md text-sm leading-relaxed text-brand-500">
                    Bukti pembayaran berhasil dikirim. Admin akan melakukan pengecekan pembayaran Anda.
                </p>
                <p class="mt-4 text-sm font-semibold text-amber-700">● MENUNGGU KONFIRMASI</p>
            @endif

            <div class="mx-auto mt-8 max-w-sm rounded-xl border border-brand-100 bg-brand-50 px-5 py-4 text-left text-sm">
                <div class="flex justify-between gap-4 py-1.5">
                    <span class="text-brand-500">Invoice</span>
                    <span class="font-semibold text-brand-900">{{ $order->invoice_number }}</span>
                </div>
                <div class="flex justify-between gap-4 py-1.5">
                    <span class="text-brand-500">Total</span>
                    <span class="font-semibold text-brand-900">{{ $order->formattedAmount() }}</span>
                </div>
                <div class="flex justify-between gap-4 py-1.5">
                    <span class="text-brand-500">Durasi</span>
                    <span class="font-semibold text-brand-900">{{ $order->duration_days }} Hari</span>
                </div>
                @if ($order->telegramBot)
                    <div class="flex justify-between gap-4 py-1.5">
                        <span class="text-brand-500">Bot</span>
                        <span class="font-semibold text-brand-900">{{ $order->telegramBot->name }}</span>
                    </div>
                @endif
            </div>

            <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:justify-center">
                <a href="{{ route('dashboard') }}" class="rounded-xl bg-brand-900 px-6 py-3 text-sm font-semibold text-white hover:bg-brand-700">
                    Ke Dashboard
                </a>
                <a href="{{ route('payments.index') }}" class="rounded-xl border border-brand-200 px-6 py-3 text-sm font-semibold text-brand-700 hover:bg-brand-50">
                    Riwayat Pembayaran
                </a>
            </div>
        </div>
    </div>
</x-app-layout>
