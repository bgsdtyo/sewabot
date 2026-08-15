<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-2xl font-extrabold tracking-tight text-brand-900">Dashboard</h1>
            <p class="mt-1 text-sm text-brand-500">Halo, {{ $user->name }}</p>
        </div>
    </x-slot>

    <div class="mx-auto max-w-3xl space-y-12 px-4 py-10 sm:px-6 lg:px-8">
        @if (session('success'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                {{ session('success') }}
            </div>
        @endif

        @if ($notifications->isNotEmpty())
            <div class="space-y-2">
                @foreach ($notifications as $notification)
                    <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                        {{ $notification->data['message'] ?? 'Notifikasi baru' }}
                    </div>
                    @php $notification->markAsRead(); @endphp
                @endforeach
            </div>
        @endif

        <section class="grid items-stretch gap-6 sm:grid-cols-2">
            @php
                $isActive = $subscription?->isActive() ?? false;
                $statusClass = $isActive ? 'text-emerald-700' : 'text-slate-500';
                $dotClass = $isActive ? 'bg-emerald-500' : 'bg-slate-400';
            @endphp

            <div class="flex h-full flex-col rounded-2xl border border-brand-200 bg-white p-6">
                <p class="text-xs font-semibold uppercase tracking-wide text-brand-500">Telegram Bot</p>

                @if ($subscription && $bot)
                    <div class="mt-3 flex h-5 items-center gap-2">
                        <span class="inline-block h-2.5 w-2.5 shrink-0 rounded-full {{ $dotClass }}"></span>
                        <span class="text-sm font-semibold {{ $statusClass }}">{{ strtoupper($subscription->status) }}</span>
                    </div>

                    <p class="mt-3 line-clamp-1 text-xl font-extrabold text-brand-900">{{ $bot->name }}</p>
                    <p class="mt-1 min-h-[1.25rem] font-mono text-sm font-semibold text-brand-700">
                        {{ filled($bot->username) ? $bot->displayUsername() : '—' }}
                    </p>
                    <p class="mt-3 min-h-[1.25rem] text-sm text-brand-500">
                        Berakhir {{ $subscription->expires_at?->translatedFormat('d M Y') ?? '-' }}
                        @if ($isActive)
                            · sisa {{ $subscription->daysRemaining() }} hari
                        @endif
                    </p>

                    <div class="mt-auto flex gap-2 pt-6">
                        <a href="{{ route('bots.show', $bot) }}" class="inline-flex flex-1 items-center justify-center rounded-xl bg-brand-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-700">
                            Konfigurasi
                        </a>
                        @if ($bot->telegramUrl())
                            <a href="{{ $bot->telegramUrl() }}" target="_blank" class="inline-flex flex-1 items-center justify-center rounded-xl border border-brand-200 px-4 py-2.5 text-sm font-semibold text-brand-900 hover:bg-brand-50">
                                Telegram
                            </a>
                        @endif
                    </div>
                @else
                    <div class="mt-3 flex h-5 items-center gap-2">
                        <span class="inline-block h-2.5 w-2.5 shrink-0 rounded-full bg-slate-400"></span>
                        <span class="text-sm font-semibold text-slate-500">NONE</span>
                    </div>
                    <p class="mt-3 text-xl font-extrabold text-brand-900">Belum ada bot</p>
                    <p class="mt-1 min-h-[1.25rem] text-sm text-brand-500">Sewa bot untuk mulai.</p>
                    <p class="mt-3 min-h-[1.25rem] text-sm text-brand-500">&nbsp;</p>
                    <div class="mt-auto pt-6">
                        @php $product = \App\Models\Product::where('is_active', true)->first(); @endphp
                        @if ($product)
                            <a href="{{ route('checkout.select-bot', $product) }}" class="inline-flex w-full items-center justify-center rounded-xl bg-brand-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-700">
                                Sewa Bot
                            </a>
                        @endif
                    </div>
                @endif
            </div>

            <div class="flex h-full flex-col rounded-2xl border border-brand-200 bg-white p-6">
                <p class="text-xs font-semibold uppercase tracking-wide text-brand-500">Subscription</p>

                @if ($subscription)
                    <div class="mt-3 flex h-5 items-center gap-2">
                        <span class="inline-block h-2.5 w-2.5 shrink-0 rounded-full {{ $dotClass }}"></span>
                        <span class="text-sm font-semibold {{ $statusClass }}">{{ strtoupper($subscription->status) }}</span>
                    </div>

                    <p class="mt-3 line-clamp-1 text-xl font-extrabold text-brand-900">{{ $subscription->product->name }}</p>
                    <p class="mt-1 min-h-[1.25rem] text-sm font-semibold text-brand-700">
                        {{ $subscription->product->formattedActivationPrice() }} / {{ $subscription->product->duration_days }} hari
                    </p>
                    <p class="mt-3 min-h-[1.25rem] text-sm text-brand-500">
                        Perpanjang {{ $subscription->product->formattedRenewalPrice() }} / 30 hari
                    </p>

                    <div class="mt-auto pt-6">
                        <a href="{{ route('subscriptions.renew', $subscription) }}" class="inline-flex w-full items-center justify-center rounded-xl border border-brand-200 px-4 py-2.5 text-sm font-semibold text-brand-900 hover:bg-brand-50">
                            Perpanjang {{ $subscription->product->formattedRenewalPrice() }}
                        </a>
                    </div>
                @else
                    <div class="mt-3 flex h-5 items-center gap-2">
                        <span class="inline-block h-2.5 w-2.5 shrink-0 rounded-full bg-slate-400"></span>
                        <span class="text-sm font-semibold text-slate-500">NONE</span>
                    </div>
                    <p class="mt-3 text-xl font-extrabold text-brand-900">Belum ada</p>
                    <p class="mt-1 min-h-[1.25rem] text-sm text-brand-500">Aktifkan lewat sewa bot.</p>
                    <p class="mt-3 min-h-[1.25rem] text-sm text-brand-500">&nbsp;</p>
                    <div class="mt-auto pt-6"></div>
                @endif
            </div>
        </section>

        @if ($bot)
            <section>
                <div class="flex items-end justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-extrabold text-brand-900">Member & Saldo</h2>
                        <p class="mt-1 text-sm text-brand-500">Topup saldo member untuk OTP.</p>
                    </div>
                </div>

                <div class="mt-6 space-y-4">
                    @forelse ($members as $member)
                        <div class="rounded-2xl border border-brand-200 bg-white p-5">
                            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <p class="font-semibold text-brand-900">{{ $member->displayName() }}</p>
                                    <p class="mt-1 text-xs text-brand-500">ID {{ $member->telegram_chat_id }}</p>
                                </div>
                                <div class="grid grid-cols-3 gap-3 text-sm sm:text-right">
                                    <div>
                                        <p class="text-xs text-brand-500">Saldo</p>
                                        <p class="mt-1 font-semibold">{{ $member->formattedBalance() }}</p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-brand-500">Hold</p>
                                        <p class="mt-1 font-semibold">Rp{{ number_format($member->held_balance, 0, ',', '.') }}</p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-brand-500">Tersedia</p>
                                        <p class="mt-1 font-semibold text-emerald-700">{{ $member->formattedAvailable() }}</p>
                                    </div>
                                </div>
                            </div>

                            <form method="POST" action="{{ route('bots.members.topup', [$bot, $member]) }}"
                                  class="mt-5 flex flex-col gap-3 border-t border-brand-100 pt-4 sm:flex-row sm:items-center">
                                @csrf
                                <input type="number" name="amount" min="100" step="100" required placeholder="Nominal topup"
                                       class="w-full rounded-xl border-brand-200 text-sm focus:border-brand-900 focus:ring-brand-900 sm:max-w-[180px]">
                                <button type="submit" class="rounded-xl bg-brand-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-700 sm:shrink-0">
                                    Topup
                                </button>
                            </form>
                        </div>
                    @empty
                        <div class="rounded-2xl border border-dashed border-brand-200 px-5 py-10 text-center text-sm text-brand-500">
                            Belum ada member. Suruh user ketik /start di bot.
                        </div>
                    @endforelse
                </div>

                @if ($members->hasPages())
                    <div class="mt-6">{{ $members->withQueryString()->links() }}</div>
                @endif
            </section>

            <section>
                <h2 class="text-lg font-extrabold text-brand-900">Riwayat OTP</h2>
                <div class="mt-6 space-y-3">
                    @forelse ($otpOrders as $order)
                        <div class="rounded-2xl border border-brand-200 bg-white px-5 py-4">
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <p class="font-semibold text-brand-900">{{ $order->otpService?->name ?? 'OTP' }}</p>
                                    <p class="mt-1 text-sm text-brand-500">
                                        {{ $order->botMember?->displayName() }}
                                        · {{ $order->created_at->format('d M Y, H:i') }}
                                    </p>
                                </div>
                                <p @class([
                                    'text-xs font-bold uppercase tracking-wide',
                                    'text-amber-700' => $order->status === 'pending',
                                    'text-emerald-700' => $order->status === 'completed',
                                    'text-red-600' => in_array($order->status, ['cancelled', 'expired']),
                                ])>{{ $order->status }}</p>
                            </div>
                            <div class="mt-3 grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
                                <div>
                                    <p class="text-xs text-brand-500">Nomor</p>
                                    <p class="mt-1 font-mono text-xs font-medium">{{ $order->phone_number ?: '-' }}</p>
                                </div>
                                <div>
                                    <p class="text-xs text-brand-500">OTP</p>
                                    <p class="mt-1 font-mono font-semibold">{{ $order->otp_code ?: '-' }}</p>
                                </div>
                                <div>
                                    <p class="text-xs text-brand-500">Harga</p>
                                    <p class="mt-1 font-medium">Rp{{ number_format($order->sell_price, 0, ',', '.') }}</p>
                                </div>
                                <div>
                                    <p class="text-xs text-brand-500">Wallet</p>
                                    <p class="mt-1 font-medium">{{ $order->wallet_status }}</p>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="rounded-2xl border border-dashed border-brand-200 px-5 py-10 text-center text-sm text-brand-500">
                            Belum ada order OTP.
                        </div>
                    @endforelse
                </div>

                @if ($otpOrders->hasPages())
                    <div class="mt-6">{{ $otpOrders->withQueryString()->links() }}</div>
                @endif
            </section>
        @endif
    </div>
</x-app-layout>
