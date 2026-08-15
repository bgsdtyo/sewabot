<x-app-layout>
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
            <div x-data="{
                activeTab: '{{ request()->has('otp_page') ? 'otp' : 'members' }}',
                openTopup: false,
                memberId: null,
                memberName: '',
                memberChatId: '',
                memberAvailable: '',
                amount: '',
                note: '',
                formAction: '',
                isSubmitting: false,
                setAmount(val) {
                    this.amount = val;
                },
                addAmount(val) {
                    const current = parseInt(this.amount) || 0;
                    this.amount = current + val;
                },
                openModal(id, name, chatId, available, actionUrl) {
                    this.memberId = id;
                    this.memberName = name;
                    this.memberChatId = chatId;
                    this.memberAvailable = available;
                    this.amount = '';
                    this.note = '';
                    this.formAction = actionUrl;
                    this.isSubmitting = false;
                    this.openTopup = true;
                    this.$nextTick(() => {
                        this.$refs.amountInput?.focus();
                    });
                }
            }" class="space-y-6">

                {{-- Tabs Header Bar --}}
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between border-b border-brand-200 pb-4">
                    <div>
                        <h2 class="text-xl font-extrabold text-brand-900" x-text="activeTab === 'members' ? 'Member & Saldo' : 'Riwayat Transaksi OTP'"></h2>
                        <p class="mt-1 text-sm text-brand-500" x-text="activeTab === 'members' ? 'Kelola saldo & topup member bot Telegram Anda.' : 'Pantau riwayat pembelian nomor dan kode OTP member.'"></p>
                    </div>

                    {{-- Tab Switcher Pills --}}
                    <div class="inline-flex rounded-2xl bg-brand-100 p-1.5 shadow-inner">
                        <button type="button"
                                @click="activeTab = 'members'"
                                :class="activeTab === 'members' ? 'bg-brand-900 text-white shadow-sm' : 'text-brand-600 hover:text-brand-900 hover:bg-white/60'"
                                class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-xl px-5 py-2.5 text-xs font-bold transition active:scale-95">
                            <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                            </svg>
                            <span>Member & Saldo</span>
                        </button>

                        <button type="button"
                                @click="activeTab = 'otp'"
                                :class="activeTab === 'otp' ? 'bg-brand-900 text-white shadow-sm' : 'text-brand-600 hover:text-brand-900 hover:bg-white/60'"
                                class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-xl px-5 py-2.5 text-xs font-bold transition active:scale-95">
                            <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                            </svg>
                            <span>Riwayat OTP</span>
                        </button>
                    </div>
                </div>

                {{-- Tab 1: Member & Saldo --}}
                <div x-show="activeTab === 'members'"
                     x-transition:enter="transition ease-out duration-150"
                     x-transition:enter-start="opacity-0 translate-y-1"
                     x-transition:enter-end="opacity-100 translate-y-0"
                     class="space-y-4">
                    @forelse ($members as $member)
                        <div class="rounded-2xl border border-brand-200 bg-white p-5 shadow-soft transition hover:border-brand-300">
                            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-brand-100 text-brand-700 font-bold text-sm">
                                        {{ strtoupper(substr($member->displayName(), 0, 2)) }}
                                    </div>
                                    <div>
                                        <p class="font-bold text-brand-900">{{ $member->displayName() }}</p>
                                        <p class="text-xs text-brand-500">ID {{ $member->telegram_chat_id }}</p>
                                    </div>
                                </div>

                                <div class="flex flex-wrap items-center justify-between gap-4 border-t border-brand-100 pt-3 sm:border-t-0 sm:pt-0 sm:justify-end">
                                    <div class="grid grid-cols-3 gap-3 text-sm text-left sm:text-right">
                                        <div>
                                            <p class="text-xs text-brand-500">Saldo</p>
                                            <p class="mt-0.5 font-semibold text-brand-900">{{ $member->formattedBalance() }}</p>
                                        </div>
                                        <div>
                                            <p class="text-xs text-brand-500">Hold</p>
                                            <p class="mt-0.5 font-semibold text-brand-900">Rp{{ number_format($member->held_balance, 0, ',', '.') }}</p>
                                        </div>
                                        <div>
                                            <p class="text-xs text-brand-500">Tersedia</p>
                                            <p class="mt-0.5 font-bold text-emerald-600">{{ $member->formattedAvailable() }}</p>
                                        </div>
                                    </div>

                                    <button type="button"
                                            @click="openModal('{{ $member->id }}', '{{ addslashes($member->displayName()) }}', '{{ $member->telegram_chat_id }}', '{{ $member->formattedAvailable() }}', '{{ route('bots.members.topup', ['telegramBot' => $bot, 'botMember' => $member]) }}')"
                                            class="inline-flex items-center gap-1.5 rounded-xl bg-brand-900 px-4 py-2.5 text-xs font-bold text-white shadow-sm transition hover:bg-brand-700 active:scale-95 sm:shrink-0">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
                                        </svg>
                                        <span>Topup Saldo</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="rounded-2xl border border-dashed border-brand-200 px-5 py-10 text-center text-sm text-brand-500">
                            Belum ada member. Ajak user membuka bot Telegram dan ketik <b>/start</b>.
                        </div>
                    @endforelse

                    @if ($members->hasPages())
                        <div class="mt-6">{{ $members->withQueryString()->links() }}</div>
                    @endif
                </div>

                {{-- Tab 2: Riwayat OTP --}}
                <div x-show="activeTab === 'otp'"
                     x-transition:enter="transition ease-out duration-150"
                     x-transition:enter-start="opacity-0 translate-y-1"
                     x-transition:enter-end="opacity-100 translate-y-0"
                     class="space-y-3">
                    @forelse ($otpOrders as $order)
                        <div class="rounded-2xl border border-brand-200 bg-white px-5 py-4 shadow-soft transition hover:border-brand-300">
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <p class="font-semibold text-brand-900">{{ $order->otpService?->name ?? 'OTP' }}</p>
                                    <p class="mt-1 text-sm text-brand-500">
                                        {{ $order->botMember?->displayName() }}
                                        · {{ $order->created_at->timezone(config('app.timezone', 'Asia/Jakarta'))->format('d M Y, H:i') }}
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

                    @if ($otpOrders->hasPages())
                        <div class="mt-6">{{ $otpOrders->withQueryString()->links() }}</div>
                    @endif
                </div>

                {{-- Modal Topup Pop-up --}}
                <div x-cloak x-show="openTopup"
                     class="fixed inset-0 z-50 flex items-center justify-center p-4"
                     role="dialog" aria-modal="true">
                    {{-- Backdrop --}}
                    <div x-show="openTopup"
                         x-transition:enter="ease-out duration-200"
                         x-transition:enter-start="opacity-0"
                         x-transition:enter-end="opacity-100"
                         x-transition:leave="ease-in duration-150"
                         x-transition:leave-start="opacity-100"
                         x-transition:leave-end="opacity-0"
                         @click="openTopup = false"
                         class="fixed inset-0 bg-brand-900/60 backdrop-blur-sm"></div>

                    {{-- Modal Body --}}
                    <div x-show="openTopup"
                         x-transition:enter="ease-out duration-200"
                         x-transition:enter-start="opacity-0 scale-95"
                         x-transition:enter-end="opacity-100 scale-100"
                         x-transition:leave="ease-in duration-150"
                         x-transition:leave-start="opacity-100 scale-100"
                         x-transition:leave-end="opacity-0 scale-95"
                         class="relative w-full max-w-md overflow-hidden rounded-3xl bg-white shadow-2xl">
                        {{-- Modal Header --}}
                        <div class="flex items-center justify-between border-b border-brand-100 px-6 py-5">
                            <div>
                                <h3 class="text-lg font-extrabold text-brand-900">Topup Saldo Member</h3>
                                <p class="text-xs text-brand-500">Tambahkan saldo instan ke akun Telegram member.</p>
                            </div>
                            <button type="button" @click="openTopup = false" class="rounded-xl p-1.5 text-brand-400 hover:bg-brand-100 hover:text-brand-900">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>

                        {{-- Modal Form --}}
                        <form :action="formAction" method="POST" @submit="isSubmitting = true" class="p-6">
                            @csrf

                            {{-- Target Member Card --}}
                            <div class="mb-5 rounded-2xl border border-brand-100 bg-brand-50/80 p-4">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="font-bold text-brand-900 text-sm" x-text="memberName"></p>
                                        <p class="text-xs text-brand-500">ID: <span class="font-mono font-medium" x-text="memberChatId"></span></p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-xs text-brand-500">Saldo Saat Ini</p>
                                        <p class="font-bold text-emerald-600 text-sm" x-text="memberAvailable"></p>
                                    </div>
                                </div>
                            </div>

                            {{-- Quick Preset Chips --}}
                            <div class="mb-4">
                                <label class="block text-xs font-bold uppercase tracking-wider text-brand-500 mb-2">Pilihan Cepat (Nominal)</label>
                                <div class="grid grid-cols-3 gap-2">
                                    <button type="button" @click="setAmount(5000)"
                                            class="rounded-xl border border-brand-200 bg-white py-2 text-xs font-bold text-brand-700 transition hover:border-brand-900 hover:bg-brand-900 hover:text-white active:scale-95">
                                        +5.000
                                    </button>
                                    <button type="button" @click="setAmount(10000)"
                                            class="rounded-xl border border-brand-200 bg-white py-2 text-xs font-bold text-brand-700 transition hover:border-brand-900 hover:bg-brand-900 hover:text-white active:scale-95">
                                        +10.000
                                    </button>
                                    <button type="button" @click="setAmount(20000)"
                                            class="rounded-xl border border-brand-200 bg-white py-2 text-xs font-bold text-brand-700 transition hover:border-brand-900 hover:bg-brand-900 hover:text-white active:scale-95">
                                        +20.000
                                    </button>
                                    <button type="button" @click="setAmount(50000)"
                                            class="rounded-xl border border-brand-200 bg-white py-2 text-xs font-bold text-brand-700 transition hover:border-brand-900 hover:bg-brand-900 hover:text-white active:scale-95">
                                        +50.000
                                    </button>
                                    <button type="button" @click="setAmount(100000)"
                                            class="rounded-xl border border-brand-200 bg-white py-2 text-xs font-bold text-brand-700 transition hover:border-brand-900 hover:bg-brand-900 hover:text-white active:scale-95">
                                        +100.000
                                    </button>
                                    <button type="button" @click="setAmount(200000)"
                                            class="rounded-xl border border-brand-200 bg-white py-2 text-xs font-bold text-brand-700 transition hover:border-brand-900 hover:bg-brand-900 hover:text-white active:scale-95">
                                        +200.000
                                    </button>
                                </div>
                            </div>

                            {{-- Input Nominal --}}
                            <div class="mb-4">
                                <label for="topup-amount" class="block text-xs font-bold uppercase tracking-wider text-brand-500 mb-1.5">
                                    Nominal Topup (Rp) <span class="text-rose-500">*</span>
                                </label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-sm font-bold text-brand-400">Rp</span>
                                    <input type="number" id="topup-amount" name="amount" x-model="amount" x-ref="amountInput"
                                           min="100" step="100" required placeholder="0"
                                           class="w-full rounded-2xl border-brand-200 pl-10 pr-4 py-3 text-base font-bold text-brand-900 placeholder:text-brand-300 focus:border-brand-900 focus:ring-brand-900">
                                </div>
                            </div>

                            {{-- Input Catatan --}}
                            <div class="mb-6">
                                <label for="topup-note" class="block text-xs font-bold uppercase tracking-wider text-brand-500 mb-1.5">
                                    Catatan (Opsional)
                                </label>
                                <input type="text" id="topup-note" name="note" x-model="note" maxlength="200" placeholder="Contoh: Deposit manual via BCA"
                                       class="w-full rounded-2xl border-brand-200 px-4 py-2.5 text-sm text-brand-900 placeholder:text-brand-400 focus:border-brand-900 focus:ring-brand-900">
                            </div>

                            {{-- Action Buttons --}}
                            <div class="flex items-center justify-end gap-3 pt-2">
                                <button type="button" @click="openTopup = false"
                                        class="rounded-xl border border-brand-200 px-5 py-2.5 text-sm font-semibold text-brand-700 hover:bg-brand-50 transition">
                                    Batal
                                </button>
                                <button type="submit" :disabled="isSubmitting || !amount || amount < 100"
                                        class="inline-flex items-center gap-2 rounded-xl bg-brand-900 px-6 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-brand-700 disabled:opacity-50 disabled:cursor-not-allowed">
                                    <svg x-show="isSubmitting" class="h-4 w-4 animate-spin text-white" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <span x-text="isSubmitting ? 'Memproses...' : 'Konfirmasi Topup'"></span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-app-layout>
