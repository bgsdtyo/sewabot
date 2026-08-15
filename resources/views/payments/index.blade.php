<x-app-layout>
    <x-slot name="header">
        <h1 class="text-2xl font-extrabold tracking-tight text-brand-900">Pembayaran</h1>
    </x-slot>

    <div class="mx-auto max-w-3xl px-4 py-10 sm:px-6 lg:px-8">
        <div class="space-y-3">
            @forelse ($orders as $order)
                <a href="{{ route('checkout.show', $order) }}" class="flex items-center justify-between rounded-2xl border border-brand-200 bg-white px-5 py-4 shadow-soft hover:border-brand-900">
                    <div>
                        <p class="font-semibold text-brand-900">{{ $order->invoice_number }}</p>
                        <p class="mt-1 text-sm text-brand-500">{{ $order->product->name }} · {{ $order->type === 'renewal' ? 'Perpanjangan' : 'Aktivasi' }}</p>
                    </div>
                    <div class="text-right">
                        <p class="font-bold text-brand-900">{{ $order->formattedAmount() }}</p>
                        <p class="mt-1 text-xs font-semibold
                            @if($order->status === 'pending') text-amber-700
                            @elseif($order->status === 'paid') text-emerald-700
                            @elseif($order->status === 'rejected') text-red-700
                            @else text-brand-500 @endif">
                            ● {{ $order->statusLabel() }}
                        </p>
                    </div>
                </a>
            @empty
                <div class="rounded-2xl border border-brand-200 bg-white px-5 py-10 text-center text-brand-500">
                    Belum ada riwayat pembayaran.
                </div>
            @endforelse
        </div>

        <div class="mt-8">
            {{ $orders->links() }}
        </div>
    </div>
</x-app-layout>
