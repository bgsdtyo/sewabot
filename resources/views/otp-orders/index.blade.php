<x-app-layout>
    <style>
        .otp-table-scroll {
            display: block;
            width: 100%;
            max-width: 100%;
            overflow-x: scroll;
            overflow-y: visible;
            -webkit-overflow-scrolling: touch;
            touch-action: pan-x;
            overscroll-behavior-x: contain;
        }
        .otp-table-inner {
            display: block;
            width: max-content;
            min-width: 1100px;
        }
        .otp-table-inner table {
            width: 1100px;
            min-width: 1100px;
            table-layout: fixed;
            border-collapse: separate;
            border-spacing: 0;
        }
        [x-cloak] { display: none !important; }
        .otp-filter-toggle { display: flex; width: 100%; align-items: center; justify-content: space-between; }
        .otp-filter-toggle .otp-filter-chevron { transition: transform 0.2s ease; }
        .otp-filter-toggle.is-open .otp-filter-chevron { transform: rotate(180deg); }
        .otp-filter-form { display: none; margin-top: 1rem; }
        .otp-filter-form.is-open { display: block; }
        @media (min-width: 640px) {
            .otp-filter-toggle { display: none !important; }
            .otp-filter-form { display: block !important; margin-top: 0; }
        }
        .otp-member-search { position: relative; }
        .otp-member-search input[type="text"] {
            width: 100%;
            border-radius: 0.75rem;
            border: 1px solid #cbd5e1;
            padding: 0.625rem 2.25rem 0.625rem 0.75rem;
            font-size: 0.875rem;
            color: #0f172a;
        }
        .otp-member-search input[type="text"]:focus {
            outline: none;
            border-color: #0f172a;
            box-shadow: 0 0 0 1px #0f172a;
        }
        .otp-member-list {
            position: absolute;
            z-index: 30;
            left: 0;
            right: 0;
            top: calc(100% + 4px);
            max-height: 16rem;
            overflow-y: auto;
            border: 1px solid #e2e8f0;
            border-radius: 0.75rem;
            background: #fff;
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.12);
        }
        .otp-member-list[hidden] { display: none !important; }
        .otp-member-list button {
            display: block;
            width: 100%;
            text-align: left;
            padding: 0.625rem 0.75rem;
            font-size: 0.8125rem;
            color: #0f172a;
            background: #fff;
            border: 0;
            border-bottom: 1px solid #f1f5f9;
        }
        .otp-member-list button:last-child { border-bottom: 0; }
        .otp-member-list button.is-selected,
        .otp-member-list button:hover { background: #1d4ed8; color: #fff; }
        .otp-member-empty { padding: 0.75rem; font-size: 0.75rem; color: #64748b; }
        .otp-detail-overlay {
            position: fixed;
            inset: 0;
            z-index: 50;
            display: flex;
            align-items: flex-end;
            justify-content: center;
            padding: 0;
        }
        .otp-detail-sheet {
            position: relative;
            display: flex;
            flex-direction: column;
            width: 100%;
            max-height: 92vh;
            background: #fff;
            border-radius: 1.25rem 1.25rem 0 0;
            box-shadow: 0 -8px 30px rgba(15, 23, 42, 0.18);
            overflow: hidden;
        }
        .otp-detail-head,
        .otp-detail-foot {
            flex-shrink: 0;
            background: #fff;
        }
        .otp-detail-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            padding: 16px 16px 12px;
            border-bottom: 1px solid #e2e8f0;
        }
        .otp-detail-body {
            flex: 1;
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
            padding: 16px;
        }
        .otp-detail-foot {
            padding: 12px 16px calc(12px + env(safe-area-inset-bottom, 0px));
            border-top: 1px solid #e2e8f0;
            background: #f8fafc;
        }
        .otp-detail-hero {
            text-align: center;
            padding: 16px 12px;
            border: 1px solid #bbf7d0;
            background: #ecfdf5;
            border-radius: 1rem;
        }
        .otp-detail-code {
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
            font-size: clamp(1.5rem, 8vw, 2rem);
            font-weight: 800;
            letter-spacing: 0.12em;
            color: #059669;
            word-break: break-all;
            line-height: 1.2;
        }
        .otp-detail-list {
            margin-top: 16px;
            border: 1px solid #e2e8f0;
            border-radius: 1rem;
            overflow: hidden;
        }
        .otp-detail-row {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            padding: 11px 14px;
            border-bottom: 1px solid #f1f5f9;
        }
        .otp-detail-row:last-child { border-bottom: 0; }
        .otp-detail-row .lbl {
            flex: 0 0 38%;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: #64748b;
            padding-top: 2px;
        }
        .otp-detail-row .val {
            flex: 1;
            min-width: 0;
            text-align: right;
            font-size: 13px;
            font-weight: 700;
            color: #0f172a;
            word-break: break-word;
        }
        .otp-detail-row .val.mono {
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
            font-size: 11px;
            word-break: break-all;
        }
        .otp-detail-sms {
            margin-top: 16px;
            padding: 12px 14px;
            border-radius: 1rem;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
        }
        .otp-detail-sms p {
            margin: 0;
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
            font-size: 12px;
            line-height: 1.5;
            color: #1e293b;
            white-space: pre-wrap;
            word-break: break-word;
        }
        .otp-detail-close {
            display: block;
            width: 100%;
            border-radius: 0.75rem;
            border: 1px solid #cbd5e1;
            background: #fff;
            padding: 10px 16px;
            font-size: 14px;
            font-weight: 600;
            color: #334155;
        }
        @media (min-width: 640px) {
            .otp-detail-overlay {
                align-items: center;
                padding: 16px;
            }
            .otp-detail-sheet {
                max-width: 28rem;
                border-radius: 1.5rem;
                max-height: 85vh;
            }
        }
    </style>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-extrabold tracking-tight text-brand-900">Riwayat Transaksi OTP</h1>
                <p class="mt-1 text-sm text-brand-500">Kelola dan pantau seluruh transaksi OTP dari semua member bot Telegram Anda.</p>
            </div>
            <div>
                <button type="button"
                        @click="$dispatch('open-create-otp-modal')"
                        class="inline-flex items-center gap-2 rounded-2xl bg-brand-900 px-5 py-2.5 text-sm font-bold text-white shadow-soft transition hover:bg-brand-700 active:scale-95">
                    <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                    <span>+ Tambah Order Manual</span>
                </button>
            </div>
        </div>
    </x-slot>

    <div x-data="{
        detailModal: false,
        createModal: false,
        editModal: false,
        deleteModal: false,
        selectedOrder: null,
        editForm: {
            id: null,
            phone_number: '',
            otp_code: '',
            sell_price: 0,
            provider_price: 0,
            status: 'pending',
            wallet_status: 'none',
            full_text: '',
            actionUrl: ''
        },
        deleteForm: {
            id: null,
            actionUrl: ''
        },
        copied: false,
        statusLabel(status) {
            const map = { completed: 'Berhasil', pending: 'Pending', cancelled: 'Dibatalkan', expired: 'Expired' };
            return map[status] || status || '-';
        },
        copyText(text) {
            if (!text) return;
            navigator.clipboard.writeText(text);
            this.copied = true;
            setTimeout(() => this.copied = false, 2000);
        },
        openDetail(order) {
            this.selectedOrder = order;
            this.detailModal = true;
        },
        openEdit(order, actionUrl) {
            this.editForm = {
                id: order.id,
                phone_number: order.phone_number || '',
                otp_code: order.otp_code || '',
                sell_price: order.sell_price || 0,
                provider_price: order.provider_price || 0,
                status: order.status || 'pending',
                wallet_status: order.wallet_status || 'none',
                full_text: order.full_text || '',
                actionUrl: actionUrl
            };
            this.editModal = true;
        },
        openDelete(order, actionUrl) {
            this.deleteForm = {
                id: order.id,
                actionUrl: actionUrl
            };
            this.deleteModal = true;
        }
    }"
    @open-create-otp-modal.window="createModal = true"
    class="mx-auto min-w-0 max-w-6xl space-y-8 px-4 py-8 sm:px-6 lg:px-8">

        {{-- Toast / Session Messages --}}
        @if (session('success'))
            <div class="flex items-center gap-3 rounded-2xl border border-emerald-200 bg-emerald-50/90 p-4 text-sm font-semibold text-emerald-900 shadow-sm">
                <svg class="h-5 w-5 shrink-0 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-2xl border border-rose-200 bg-rose-50/90 p-4 text-sm text-rose-900 shadow-sm">
                <p class="font-bold">Terjadi kesalahan:</p>
                <ul class="mt-1 list-disc list-inside space-y-0.5 font-medium">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Summary Metric Cards --}}
        <section class="grid grid-cols-2 gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <div class="rounded-2xl border border-brand-200 bg-white p-5 shadow-soft">
                <p class="text-xs font-bold uppercase tracking-wider text-brand-400">Total Order</p>
                <p class="mt-2 text-2xl font-black text-brand-900">{{ number_format($totalOrders, 0, ',', '.') }}</p>
                <p class="mt-1 text-[11px] font-semibold text-brand-500">Semua transaksi</p>
            </div>

            <div class="rounded-2xl border border-brand-200 bg-white p-5 shadow-soft">
                <p class="text-xs font-bold uppercase tracking-wider text-emerald-600">Berhasil (OTP Masuk)</p>
                <p class="mt-2 text-2xl font-black text-emerald-600">{{ number_format($completedCount, 0, ',', '.') }}</p>
                <p class="mt-1 text-[11px] font-semibold text-emerald-700">Rp{{ number_format($totalRevenue, 0, ',', '.') }} omset</p>
            </div>

            <div class="rounded-2xl border border-brand-200 bg-white p-5 shadow-soft">
                <p class="text-xs font-bold uppercase tracking-wider text-amber-600">Pending (Menunggu)</p>
                <p class="mt-2 text-2xl font-black text-amber-600">{{ number_format($pendingCount, 0, ',', '.') }}</p>
                <p class="mt-1 text-[11px] font-semibold text-amber-700">Sedang diproses</p>
            </div>

            <div class="rounded-2xl border border-brand-200 bg-white p-5 shadow-soft">
                <p class="text-xs font-bold uppercase tracking-wider text-rose-600">Batal / Expired</p>
                <p class="mt-2 text-2xl font-black text-rose-600">{{ number_format($cancelledCount, 0, ',', '.') }}</p>
                <p class="mt-1 text-[11px] font-semibold text-rose-700">Saldo direfund</p>
            </div>

            <div class="col-span-2 sm:col-span-2 lg:col-span-1 rounded-2xl border border-emerald-200 bg-emerald-50/50 p-5 shadow-soft">
                <p class="text-xs font-bold uppercase tracking-wider text-emerald-700">Estimasi Margin</p>
                <p class="mt-2 text-2xl font-black text-emerald-700">Rp{{ number_format($totalProfit, 0, ',', '.') }}</p>
                <p class="mt-1 text-[11px] font-semibold text-emerald-600">Dari order selesai</p>
            </div>
        </section>

        {{-- Filter & Search Section --}}
        @php
            $activeFilterCount = collect(['search', 'member_id', 'status', 'date_from', 'date_to'])
                ->filter(fn ($key) => filled(request($key)))
                ->count();
        @endphp
        <section class="rounded-3xl border border-brand-200 bg-white p-5 sm:p-6 shadow-soft">
            <button type="button"
                    id="otp-filter-toggle"
                    class="otp-filter-toggle text-sm font-bold text-brand-900"
                    aria-expanded="false"
                    aria-controls="otp-filter-form">
                <span class="inline-flex items-center gap-2">
                    <svg class="h-4 w-4 text-brand-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                    </svg>
                    Filter
                    @if ($activeFilterCount > 0)
                        <span class="rounded-full bg-brand-900 px-2 py-0.5 text-[10px] font-bold text-white">{{ $activeFilterCount }}</span>
                    @endif
                </span>
                <svg class="otp-filter-chevron h-5 w-5 text-brand-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </button>

            <form method="GET" action="{{ route('otp-orders.index') }}"
                  id="otp-filter-form"
                  class="otp-filter-form space-y-4">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {{-- Search Input --}}
                    <div>
                        <label for="filter-search" class="block text-xs font-bold uppercase tracking-wider text-brand-600 mb-1.5">
                            Pencarian
                        </label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-brand-400 pointer-events-none">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </span>
                            <input type="text" id="filter-search" name="search" value="{{ request('search') }}"
                                   placeholder="Cari nomor, OTP, ID, user..."
                                   class="w-full rounded-xl border-brand-200 pl-10 pr-4 py-2.5 text-sm text-brand-900 placeholder:text-brand-300 focus:border-brand-900 focus:ring-brand-900">
                        </div>
                    </div>

                    {{-- Filter by Provider --}}
                    <div>
                        <label for="filter-provider" class="block text-xs font-bold uppercase tracking-wider text-brand-600 mb-1.5">
                            Provider OTP
                        </label>
                        <select id="filter-provider" name="provider"
                                class="w-full rounded-xl border-brand-200 py-2.5 px-3 text-sm text-brand-900 focus:border-brand-900 focus:ring-brand-900">
                            <option value="">Semua Provider (2 Provider)</option>
                            <option value="kopken" @selected(request('provider') === 'kopken')>🟣 EngineUnicorn (Kopken)</option>
                            <option value="wahub" @selected(request('provider') === 'wahub')>🔵 WAHub (dehuyzotp.shop)</option>
                        </select>
                    </div>

                    {{-- Filter by User / Member --}}
                    @php
                        $selectedMember = $members->firstWhere('id', (int) request('member_id'));
                        $selectedMemberLabel = $selectedMember
                            ? $selectedMember->displayName().' (ID: '.$selectedMember->telegram_chat_id.')'
                            : '';
                    @endphp
                    <div>
                        <label for="otp-member-query" class="block text-xs font-bold uppercase tracking-wider text-brand-600 mb-1.5">
                            Filter Member (User)
                        </label>
                        <div class="otp-member-search" id="otp-member-search">
                            <input type="hidden" name="member_id" id="filter-member" value="{{ request('member_id') }}">
                            <input type="text"
                                   id="otp-member-query"
                                   value="{{ $selectedMemberLabel }}"
                                   placeholder="Cari username atau ID member..."
                                   autocomplete="off"
                                   inputmode="search">
                            <div class="otp-member-list" id="otp-member-list" hidden>
                                <button type="button" data-id="" data-label="Semua Member" data-search="semua member all">
                                    Semua Member
                                </button>
                                @foreach ($members as $m)
                                    @php
                                        $memberLabel = $m->displayName().' (ID: '.$m->telegram_chat_id.')';
                                        $memberSearch = strtolower(trim(implode(' ', array_filter([
                                            $m->displayName(),
                                            $m->telegram_username,
                                            $m->telegram_name,
                                            (string) $m->telegram_chat_id,
                                            (string) $m->id,
                                        ]))));
                                    @endphp
                                    <button type="button"
                                            data-id="{{ $m->id }}"
                                            data-label="{{ $memberLabel }}"
                                            data-search="{{ $memberSearch }}"
                                            @class(['is-selected' => (string) request('member_id') === (string) $m->id])>
                                        {{ $memberLabel }}
                                    </button>
                                @endforeach
                                <div class="otp-member-empty" id="otp-member-empty" hidden>Member tidak ditemukan</div>
                            </div>
                        </div>
                    </div>

                    {{-- Filter by Status --}}
                    <div>
                        <label for="filter-status" class="block text-xs font-bold uppercase tracking-wider text-brand-600 mb-1.5">
                            Status Order
                        </label>
                        <select id="filter-status" name="status"
                                class="w-full rounded-xl border-brand-200 py-2.5 px-3 text-sm text-brand-900 focus:border-brand-900 focus:ring-brand-900">
                            <option value="">Semua Status</option>
                            <option value="pending" @selected(request('status') === 'pending')>⏳ Pending</option>
                            <option value="completed" @selected(request('status') === 'completed')>✅ Berhasil (Completed)</option>
                            <option value="cancelled" @selected(request('status') === 'cancelled')>❌ Dibatalkan</option>
                            <option value="expired" @selected(request('status') === 'expired')>⌛ Expired</option>
                        </select>
                    </div>
                </div>

                {{-- Secondary Filters --}}
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3 pt-2 border-t border-brand-100">
                    {{-- Date From --}}
                    <div>
                        <label for="filter-date-from" class="block text-xs font-bold uppercase tracking-wider text-brand-600 mb-1.5">
                            Dari Tanggal
                        </label>
                        <input type="date" id="filter-date-from" name="date_from" value="{{ request('date_from') }}"
                               class="w-full rounded-xl border-brand-200 py-2.5 px-3 text-sm text-brand-900 focus:border-brand-900 focus:ring-brand-900">
                    </div>

                    {{-- Date To --}}
                    <div>
                        <label for="filter-date-to" class="block text-xs font-bold uppercase tracking-wider text-brand-600 mb-1.5">
                            Sampai Tanggal
                        </label>
                        <input type="date" id="filter-date-to" name="date_to" value="{{ request('date_to') }}"
                               class="w-full rounded-xl border-brand-200 py-2.5 px-3 text-sm text-brand-900 focus:border-brand-900 focus:ring-brand-900">
                    </div>

                    {{-- Action buttons --}}
                    <div class="flex items-end gap-2">
                        <button type="submit"
                                class="flex-1 inline-flex items-center justify-center gap-1.5 rounded-xl bg-brand-900 py-2.5 px-4 text-sm font-bold text-white shadow-sm transition hover:bg-brand-700 active:scale-95">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                            </svg>
                            <span>Filter</span>
                        </button>
                        <a href="{{ route('otp-orders.index') }}"
                           class="inline-flex items-center justify-center rounded-xl border border-brand-200 bg-white p-2.5 text-sm font-semibold text-brand-700 hover:bg-brand-50 transition active:scale-95"
                           title="Reset Filter">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                        </a>
                    </div>
                </div>
            </form>
        </section>
        <script>
            (function () {
                var btn = document.getElementById('otp-filter-toggle');
                var form = document.getElementById('otp-filter-form');
                if (btn && form) {
                    btn.addEventListener('click', function () {
                        var open = form.classList.toggle('is-open');
                        btn.classList.toggle('is-open', open);
                        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
                    });
                }

                var wrap = document.getElementById('otp-member-search');
                var hidden = document.getElementById('filter-member');
                var query = document.getElementById('otp-member-query');
                var list = document.getElementById('otp-member-list');
                var empty = document.getElementById('otp-member-empty');
                if (!wrap || !hidden || !query || !list) return;

                var options = Array.prototype.slice.call(list.querySelectorAll('button[data-id]'));

                function openList() {
                    list.hidden = false;
                }

                function closeList() {
                    list.hidden = true;
                }

                function filterOptions() {
                    var term = (query.value || '').toLowerCase().trim();
                    var shown = 0;
                    options.forEach(function (item) {
                        var hay = item.getAttribute('data-search') || item.getAttribute('data-label') || '';
                        var match = !term || hay.indexOf(term) !== -1;
                        item.hidden = !match;
                        if (match) shown += 1;
                    });
                    if (empty) empty.hidden = shown > 0;
                }

                function selectOption(item) {
                    hidden.value = item.getAttribute('data-id') || '';
                    query.value = item.getAttribute('data-label') || '';
                    options.forEach(function (opt) { opt.classList.remove('is-selected'); });
                    item.classList.add('is-selected');
                    closeList();
                }

                query.addEventListener('focus', function () {
                    openList();
                    filterOptions();
                });
                query.addEventListener('click', function () {
                    openList();
                    filterOptions();
                });
                query.addEventListener('input', function () {
                    hidden.value = '';
                    openList();
                    filterOptions();
                });

                options.forEach(function (item) {
                    item.addEventListener('click', function () {
                        selectOption(item);
                    });
                });

                document.addEventListener('click', function (e) {
                    if (!wrap.contains(e.target)) closeList();
                });
            })();
        </script>

        {{-- Table Card Section --}}
        <section class="min-w-0 overflow-hidden rounded-3xl border border-brand-200 bg-white shadow-soft">
            {{-- Mobile Hint --}}
            <div class="flex items-center justify-between px-5 py-3 bg-brand-50/80 border-b border-brand-100 text-xs font-semibold text-brand-600 sm:hidden">
                <span class="flex items-center gap-1.5">
                    <svg class="h-4 w-4 shrink-0 text-brand-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                    </svg>
                    Geser ke kanan untuk kolom lain
                </span>
                <span class="text-[11px] text-brand-400 font-mono">{{ $orders->total() }} Data</span>
            </div>

            {{-- Horizontal swipe container --}}
            <div class="relative min-w-0 max-w-full">
                <div
                    x-ref="otpTableScroll"
                    class="otp-table-scroll"
                    style="width:100%;max-width:100%;overflow-x:scroll;-webkit-overflow-scrolling:touch;touch-action:pan-x;"
                    x-init="
                        const el = $refs.otpTableScroll;
                        if (!el) return;
                        let startX = 0, startY = 0, startLeft = 0, locked = null, active = false;
                        el.addEventListener('touchstart', (e) => {
                            const t = e.touches[0];
                            active = true;
                            locked = null;
                            startX = t.pageX;
                            startY = t.pageY;
                            startLeft = el.scrollLeft;
                        }, { passive: true });
                        el.addEventListener('touchmove', (e) => {
                            if (!active) return;
                            const t = e.touches[0];
                            const dx = t.pageX - startX;
                            const dy = t.pageY - startY;
                            if (!locked) {
                                if (Math.abs(dx) < 8 && Math.abs(dy) < 8) return;
                                locked = Math.abs(dx) > Math.abs(dy) ? 'x' : 'y';
                            }
                            if (locked === 'x') {
                                e.preventDefault();
                                el.scrollLeft = startLeft - dx;
                            }
                        }, { passive: false });
                        el.addEventListener('touchend', () => { active = false; locked = null; }, { passive: true });
                        el.addEventListener('touchcancel', () => { active = false; locked = null; }, { passive: true });
                    "
                >
                    <div class="otp-table-inner" style="min-width:1100px;width:max-content;">
                    <table class="text-left text-sm text-brand-900">
                    <thead class="bg-brand-50/80 border-b border-brand-100 text-xs font-bold uppercase tracking-wider text-brand-500">
                        <tr>
                            <th class="px-5 py-4">Waktu & ID</th>
                            <th class="px-5 py-4">Member / User</th>
                            <th class="px-5 py-4">Layanan</th>
                            <th class="px-5 py-4">Nomor HP</th>
                            <th class="px-5 py-4">Kode OTP</th>
                            <th class="px-5 py-4">Harga / Margin</th>
                            <th class="px-5 py-4">Status</th>
                            <th class="px-5 py-4 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-brand-100">
                        @forelse ($orders as $order)
                            <tr class="hover:bg-brand-50/50 transition">
                                {{-- Waktu & ID --}}
                                <td class="px-5 py-4 whitespace-nowrap">
                                    <div class="font-mono text-xs font-bold text-brand-900">#{{ $order->id }}</div>
                                    <div class="text-[11px] text-brand-400 mt-0.5">
                                        {{ $order->created_at->timezone(config('app.timezone', 'Asia/Jakarta'))->format('d M Y, H:i') }}
                                    </div>
                                    @if ($order->isPartOfBatch())
                                        <span class="inline-block mt-1 rounded-md bg-purple-50 px-1.5 py-0.5 text-[10px] font-bold text-purple-700 border border-purple-200">
                                            Bulk Batch
                                        </span>
                                    @endif
                                </td>

                                {{-- Member / User --}}
                                <td class="px-5 py-4">
                                    <div class="flex items-center gap-2">
                                        <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-sky-50 text-sky-600 border border-sky-100 text-xs font-bold">
                                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="currentColor">
                                                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm4.64 6.8c-.15 1.58-.8 5.42-1.13 7.19-.14.75-.42 1-.68 1.03-.58.05-1.02-.38-1.58-.75-.88-.58-1.38-.94-2.23-1.5-.99-.65-.35-1.01.22-1.59.15-.15 2.71-2.48 2.76-2.69a.2.2 0 00-.05-.18c-.06-.05-.14-.03-.21-.02-.09.02-1.49.95-4.22 2.79-.4.27-.76.41-1.08.4-.36-.01-1.04-.2-1.55-.37-.63-.2-1.12-.31-1.08-.66.02-.18.27-.36.74-.55 2.92-1.27 4.86-2.11 5.83-2.51 2.78-1.16 3.35-1.36 3.73-1.36.08 0 .27.02.39.12.1.08.13.19.14.27-.01.06.01.24 0 .38z"/>
                                            </svg>
                                        </div>
                                        <div class="min-w-0">
                                            <p class="font-bold text-brand-900 text-xs truncate max-w-[130px]">
                                                {{ $order->botMember?->displayName() ?? 'Member #'.$order->bot_member_id }}
                                            </p>
                                            <p class="text-[11px] text-brand-400 font-mono">
                                                {{ $order->botMember?->telegram_chat_id ?? '-' }}
                                            </p>
                                        </div>
                                    </div>
                                </td>

                                {{-- Layanan & Provider --}}
                                <td class="px-5 py-4 whitespace-nowrap">
                                    <div class="flex items-center gap-1.5">
                                        <span class="font-bold text-brand-900 text-xs">{{ $order->otpService?->name ?? 'WhatsApp' }}</span>
                                        @if ($order->provider === 'wahub')
                                            <span class="inline-flex items-center rounded-md bg-blue-50 px-1.5 py-0.5 text-[10px] font-bold text-blue-700 border border-blue-200" title="Provider 2: WAHub">
                                                WAHub
                                            </span>
                                        @else
                                            <span class="inline-flex items-center rounded-md bg-purple-50 px-1.5 py-0.5 text-[10px] font-bold text-purple-700 border border-purple-200" title="Provider 1: EngineUnicorn">
                                                EngineUnicorn
                                            </span>
                                        @endif
                                    </div>
                                    @if ($order->provider_order_id || $order->provider_token)
                                        <div class="text-[10px] text-brand-400 font-mono mt-0.5">{{ Str::before($order->provider_order_id ?: $order->provider_token, '-') }}</div>
                                    @endif
                                </td>

                                {{-- Nomor HP --}}
                                <td class="px-5 py-4 whitespace-nowrap">
                                    @if ($order->phone_number)
                                        <div class="inline-flex items-center gap-1.5 rounded-lg bg-brand-50 border border-brand-200/60 px-2 py-1">
                                            <span class="font-mono text-xs font-bold text-brand-900">{{ $order->phone_number }}</span>
                                            <button type="button" @click="copyText('{{ $order->phone_number }}')" class="text-brand-400 hover:text-brand-900 transition" title="Salin Nomor">
                                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                                </svg>
                                            </button>
                                        </div>
                                    @else
                                        <span class="text-xs text-brand-300 italic">Menunggu...</span>
                                    @endif
                                </td>

                                {{-- Kode OTP --}}
                                <td class="px-5 py-4 whitespace-nowrap">
                                    @if (filled($order->otp_code))
                                        <div class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-50 border border-emerald-200 px-2.5 py-1 text-emerald-800">
                                            <span class="font-mono text-sm font-black tracking-wider">{{ $order->otp_code }}</span>
                                            <button type="button" @click="copyText('{{ $order->otp_code }}')" class="text-emerald-600 hover:text-emerald-950 transition" title="Salin OTP">
                                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                                </svg>
                                            </button>
                                        </div>
                                    @else
                                        <span class="text-xs text-brand-400 font-mono">-</span>
                                    @endif
                                </td>

                                {{-- Harga / Margin --}}
                                <td class="px-5 py-4 whitespace-nowrap">
                                    <div class="font-bold text-xs text-brand-900">
                                        Rp{{ number_format($order->sell_price, 0, ',', '.') }}
                                    </div>
                                    @php $margin = max(0, (int)$order->sell_price - (int)$order->provider_price); @endphp
                                    <div class="text-[10px] text-emerald-600 font-semibold">
                                        Profit: +Rp{{ number_format($margin, 0, ',', '.') }}
                                    </div>
                                </td>

                                {{-- Status --}}
                                <td class="px-5 py-4 whitespace-nowrap">
                                    @if ($order->status === 'completed')
                                        <span class="inline-flex items-center gap-1 rounded-lg bg-emerald-50 px-2 py-1 text-[11px] font-extrabold text-emerald-700 border border-emerald-200">
                                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                            Berhasil
                                        </span>
                                    @elseif ($order->status === 'pending')
                                        <span class="inline-flex items-center gap-1 rounded-lg bg-amber-50 px-2 py-1 text-[11px] font-extrabold text-amber-700 border border-amber-200">
                                            <span class="h-1.5 w-1.5 rounded-full bg-amber-500 animate-ping"></span>
                                            Pending
                                        </span>
                                    @elseif ($order->status === 'cancelled')
                                        <span class="inline-flex items-center gap-1 rounded-lg bg-rose-50 px-2 py-1 text-[11px] font-extrabold text-rose-700 border border-rose-200">
                                            <span class="h-1.5 w-1.5 rounded-full bg-rose-500"></span>
                                            Dibatalkan
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 rounded-lg bg-slate-100 px-2 py-1 text-[11px] font-extrabold text-slate-600 border border-slate-200">
                                            <span class="h-1.5 w-1.5 rounded-full bg-slate-400"></span>
                                            {{ ucfirst($order->status) }}
                                        </span>
                                    @endif
                                </td>

                                {{-- Actions --}}
                                <td class="px-5 py-4 whitespace-nowrap text-right text-xs">
                                    <div class="inline-flex items-center gap-1">
                                        {{-- Detail Button --}}
                                        <button type="button"
                                                @click="openDetail({{ json_encode([
                                                    'id' => $order->id,
                                                    'batch_id' => $order->batch_id,
                                                    'provider' => $order->provider ?? 'kopken',
                                                    'provider_label' => ($order->provider === 'wahub') ? 'WAHub (dehuyzotp.shop)' : 'EngineUnicorn (engineunicorn.cloud)',
                                                    'provider_order_id' => $order->provider_order_id ?: $order->provider_token,
                                                    'service_name' => $order->otpService?->name ?? 'WhatsApp',
                                                    'member_name' => $order->botMember?->displayName() ?? 'Member',
                                                    'member_chat_id' => $order->botMember?->telegram_chat_id ?? '-',
                                                    'phone_number' => $order->phone_number,
                                                    'otp_code' => $order->otp_code,
                                                    'sell_price' => number_format($order->sell_price, 0, ',', '.'),
                                                    'provider_price' => number_format($order->provider_price, 0, ',', '.'),
                                                    'status' => $order->status,
                                                    'wallet_status' => $order->wallet_status,
                                                    'full_text' => $order->full_text,
                                                    'created_at' => $order->created_at->timezone(config('app.timezone', 'Asia/Jakarta'))->format('d M Y, H:i:s'),
                                                    'completed_at' => $order->completed_at ? $order->completed_at->timezone(config('app.timezone', 'Asia/Jakarta'))->format('d M Y, H:i:s') : '-',
                                                    'cancelled_at' => $order->cancelled_at ? $order->cancelled_at->timezone(config('app.timezone', 'Asia/Jakarta'))->format('d M Y, H:i:s') : '-',
                                                    'raw_payload' => $order->raw_payload,
                                                ]) }})"
                                                class="rounded-lg border border-brand-200 bg-white p-1.5 text-brand-700 hover:bg-brand-50 hover:text-brand-900 transition"
                                                title="Lihat Detail">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                        </button>

                                        {{-- Live Refresh Button (if pending) --}}
                                        @if ($order->status === 'pending' && ($order->provider_order_id || $order->provider_token))
                                            <form method="POST" action="{{ route('otp-orders.refresh', $order) }}" class="inline">
                                                @csrf
                                                <button type="submit" class="rounded-lg border border-amber-200 bg-amber-50 p-1.5 text-amber-700 hover:bg-amber-100 transition" title="Cek Status Live ke Provider">
                                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                                    </svg>
                                                </button>
                                            </form>
                                        @endif

                                        {{-- Edit Button --}}
                                        <button type="button"
                                                @click="openEdit({{ json_encode($order) }}, '{{ route('otp-orders.update', $order) }}')"
                                                class="rounded-lg border border-brand-200 bg-white p-1.5 text-brand-700 hover:bg-brand-50 hover:text-brand-900 transition"
                                                title="Edit Order">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                        </button>

                                        {{-- Delete Button --}}
                                        <button type="button"
                                                @click="openDelete({{ json_encode($order) }}, '{{ route('otp-orders.destroy', $order) }}')"
                                                class="rounded-lg border border-rose-200 bg-white p-1.5 text-rose-600 hover:bg-rose-50 transition"
                                                title="Hapus Data">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-5 py-12 text-center text-brand-500">
                                    <div class="flex flex-col items-center justify-center">
                                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-brand-50 text-brand-400 mb-3 border border-brand-100">
                                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                                            </svg>
                                        </div>
                                        <p class="font-bold text-brand-900">Belum ada data riwayat OTP</p>
                                        <p class="text-xs text-brand-400 mt-1 max-w-sm">Data transaksi OTP dari member bot Anda akan tampil di sini secara real-time.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                    </div>
                </div>
                <div class="pointer-events-none absolute inset-y-0 right-0 w-8 bg-gradient-to-l from-white to-transparent sm:hidden"></div>
            </div>

            {{-- Pagination Links --}}
            @if ($orders->hasPages())
                <div class="border-t border-brand-100 px-5 py-4 bg-brand-50/50">
                    {{ $orders->links() }}
                </div>
            @endif
        </section>

        {{-- ================= MODAL DETAIL ORDER ================= --}}
        <div x-cloak x-show="detailModal" class="otp-detail-overlay" role="dialog" aria-modal="true">
            <div x-show="detailModal"
                 x-transition:enter="ease-out duration-200"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="ease-in duration-150"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 @click="detailModal = false"
                 class="fixed inset-0 bg-brand-900/60"></div>

            <div x-show="detailModal && selectedOrder"
                 x-transition:enter="ease-out duration-200"
                 x-transition:enter-start="opacity-0 translate-y-6"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 x-transition:leave="ease-in duration-150"
                 x-transition:leave-start="opacity-100 translate-y-0"
                 x-transition:leave-end="opacity-0 translate-y-6"
                 class="otp-detail-sheet"
                 @click.stop>
                <div class="otp-detail-head">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <h3 class="text-base font-extrabold text-brand-900">
                                Detail OTP <span class="font-mono text-brand-500" x-text="selectedOrder ? '#' + selectedOrder.id : ''"></span>
                            </h3>
                            <span class="inline-flex items-center rounded px-1.5 py-0.5 text-[10px] font-extrabold"
                                  :class="selectedOrder?.provider === 'wahub' ? 'bg-blue-50 text-blue-700 border border-blue-200' : 'bg-purple-50 text-purple-700 border border-purple-200'"
                                  x-text="selectedOrder?.provider === 'wahub' ? 'WAHub' : 'EngineUnicorn'"></span>
                        </div>
                        <p class="mt-0.5 text-[11px] text-brand-500" x-text="(selectedOrder?.service_name || 'WhatsApp') + ' • ' + (selectedOrder?.provider_label || 'EngineUnicorn')"></p>
                    </div>
                    <button type="button" @click="detailModal = false" class="shrink-0 rounded-xl p-1.5 text-brand-400">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <div class="otp-detail-body">
                    <div class="otp-detail-hero">
                        <p class="text-[11px] font-bold uppercase tracking-wider text-emerald-700">Kode OTP</p>
                        <div class="mt-2 flex items-center justify-center gap-2">
                            <span class="otp-detail-code" x-text="selectedOrder.otp_code || 'BELUM MASUK'"></span>
                            <button type="button" x-show="selectedOrder.otp_code" @click="copyText(selectedOrder.otp_code)" class="rounded-lg border border-emerald-200 bg-white p-1.5 text-emerald-700" title="Salin OTP">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                </svg>
                            </button>
                        </div>
                        <p class="mt-2 text-xs font-mono text-brand-700" x-text="'Nomor: ' + (selectedOrder.phone_number || '-')"></p>
                    </div>

                    <div class="otp-detail-list">
                        <div class="otp-detail-row">
                            <span class="lbl">Provider</span>
                            <span class="val font-semibold"
                                  :class="selectedOrder.provider === 'wahub' ? 'text-blue-700' : 'text-purple-700'"
                                  x-text="selectedOrder.provider_label || (selectedOrder.provider === 'wahub' ? 'WAHub' : 'EngineUnicorn')"></span>
                        </div>
                        <div class="otp-detail-row">
                            <span class="lbl">Layanan</span>
                            <span class="val" x-text="selectedOrder.service_name || 'WhatsApp'"></span>
                        </div>
                        <div class="otp-detail-row">
                            <span class="lbl">Member</span>
                            <span class="val" x-text="selectedOrder.member_name"></span>
                        </div>
                        <div class="otp-detail-row">
                            <span class="lbl">Chat ID</span>
                            <span class="val mono" x-text="selectedOrder.member_chat_id"></span>
                        </div>
                        <div class="otp-detail-row">
                            <span class="lbl">Harga</span>
                            <span class="val" x-text="'Rp' + selectedOrder.sell_price"></span>
                        </div>
                        <div class="otp-detail-row">
                            <span class="lbl">Modal</span>
                            <span class="val" x-text="'Rp' + selectedOrder.provider_price"></span>
                        </div>
                        <div class="otp-detail-row">
                            <span class="lbl">Status</span>
                            <span class="val"
                                  :style="selectedOrder.status === 'completed' ? 'color:#059669' : (selectedOrder.status === 'pending' ? 'color:#d97706' : 'color:#e11d48')"
                                  x-text="statusLabel(selectedOrder.status)"></span>
                        </div>
                        <div class="otp-detail-row">
                            <span class="lbl">Wallet</span>
                            <span class="val" x-text="selectedOrder.wallet_status || '-'"></span>
                        </div>
                        <div class="otp-detail-row">
                            <span class="lbl">Order</span>
                            <span class="val" x-text="selectedOrder.created_at"></span>
                        </div>
                        <div class="otp-detail-row">
                            <span class="lbl">Selesai</span>
                            <span class="val" x-text="selectedOrder.completed_at"></span>
                        </div>
                        <div class="otp-detail-row" x-show="selectedOrder.provider_order_id">
                            <span class="lbl">PID</span>
                            <span class="val mono" x-text="selectedOrder.provider_order_id"></span>
                        </div>
                    </div>

                    <div class="otp-detail-sms" x-show="selectedOrder.full_text">
                        <p class="mb-1 text-[11px] font-bold uppercase tracking-wider text-brand-500" style="font-family:inherit">Isi SMS</p>
                        <p x-text="selectedOrder.full_text"></p>
                    </div>
                </div>

                <div class="otp-detail-foot">
                    <button type="button" @click="detailModal = false" class="otp-detail-close">Tutup</button>
                </div>
            </div>
        </div>

        {{-- ================= MODAL TAMBAH ORDER MANUAL ================= --}}
        <div x-cloak x-show="createModal"
             class="fixed inset-0 z-50 flex items-center justify-center p-4"
             role="dialog" aria-modal="true">
            <div x-show="createModal"
                 x-transition:enter="ease-out duration-200"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="ease-in duration-150"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 @click="createModal = false"
                 class="fixed inset-0 bg-brand-900/60 backdrop-blur-sm"></div>

            <div x-show="createModal"
                 x-transition:enter="ease-out duration-200"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100"
                 x-transition:leave="ease-in duration-150"
                 x-transition:leave-start="opacity-100 scale-100"
                 x-transition:leave-end="opacity-0 scale-95"
                 class="relative w-full max-w-lg max-h-[90vh] overflow-y-auto rounded-3xl bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-brand-100 px-6 py-5 sticky top-0 bg-white/95 backdrop-blur z-10">
                    <div>
                        <h3 class="text-lg font-extrabold text-brand-900">Tambah Order OTP Manual</h3>
                        <p class="text-xs text-brand-500">Buat catatan pesanan OTP baru untuk member bot.</p>
                    </div>
                    <button type="button" @click="createModal = false" class="rounded-xl p-1.5 text-brand-400 hover:bg-brand-100 hover:text-brand-900">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <form method="POST" action="{{ route('otp-orders.store') }}" class="p-6 space-y-4">
                    @csrf

                    @if ($bots->count() > 1)
                        <div>
                            <label for="create-bot" class="block text-xs font-bold uppercase tracking-wider text-brand-600 mb-1.5">
                                Pilih Bot Telegram <span class="text-rose-500">*</span>
                            </label>
                            <select id="create-bot" name="telegram_bot_id" required
                                    class="w-full rounded-xl border-brand-200 py-2.5 px-3 text-sm text-brand-900 focus:border-brand-900 focus:ring-brand-900">
                                @foreach ($bots as $b)
                                    <option value="{{ $b->id }}">{{ $b->name }} ({{ $b->displayUsername() }}) - Default: {{ strtoupper($b->activeOtpProvider()) }}</option>
                                @endforeach
                            </select>
                        </div>
                    @else
                        <input type="hidden" name="telegram_bot_id" value="{{ $bots->first()?->id }}">
                    @endif

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="create-member" class="block text-xs font-bold uppercase tracking-wider text-brand-600 mb-1.5">
                                Member <span class="text-rose-500">*</span>
                            </label>
                            <select id="create-member" name="bot_member_id" required
                                    class="w-full rounded-xl border-brand-200 py-2.5 px-3 text-sm text-brand-900 focus:border-brand-900 focus:ring-brand-900">
                                <option value="">-- Pilih Member --</option>
                                @foreach ($members as $m)
                                    <option value="{{ $m->id }}">{{ $m->displayName() }} (ID: {{ $m->telegram_chat_id }})</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="create-provider" class="block text-xs font-bold uppercase tracking-wider text-brand-600 mb-1.5">
                                Provider Layanan OTP <span class="text-rose-500">*</span>
                            </label>
                            <select id="create-provider" name="provider" required
                                    class="w-full rounded-xl border-brand-200 py-2.5 px-3 text-sm text-brand-900 focus:border-brand-900 focus:ring-brand-900">
                                <option value="kopken">🟣 EngineUnicorn (Kopken)</option>
                                <option value="wahub">🔵 WAHub (dehuyzotp.shop)</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        {{-- Phone Number --}}
                        <div>
                            <label for="create-phone" class="block text-xs font-bold uppercase tracking-wider text-brand-600 mb-1.5">
                                Nomor Telepon <span class="text-rose-500">*</span>
                            </label>
                            <input type="text" id="create-phone" name="phone_number" required placeholder="628123456789"
                                   class="w-full rounded-xl border-brand-200 py-2.5 px-3 text-sm font-mono text-brand-900 focus:border-brand-900 focus:ring-brand-900">
                        </div>

                        {{-- OTP Code --}}
                        <div>
                            <label for="create-otp" class="block text-xs font-bold uppercase tracking-wider text-brand-600 mb-1.5">
                                Kode OTP (Opsional)
                            </label>
                            <input type="text" id="create-otp" name="otp_code" placeholder="123456"
                                   class="w-full rounded-xl border-brand-200 py-2.5 px-3 text-sm font-mono text-brand-900 focus:border-brand-900 focus:ring-brand-900">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        {{-- Sell Price --}}
                        <div>
                            <label for="create-sell-price" class="block text-xs font-bold uppercase tracking-wider text-brand-600 mb-1.5">
                                Harga Jual Member (Rp) <span class="text-rose-500">*</span>
                            </label>
                            <input type="number" id="create-sell-price" name="sell_price" required min="0" value="{{ $kopkenService?->sell_price ?? 4100 }}"
                                   class="w-full rounded-xl border-brand-200 py-2.5 px-3 text-sm font-mono text-brand-900 focus:border-brand-900 focus:ring-brand-900">
                        </div>

                        {{-- Provider Price --}}
                        <div>
                            <label for="create-provider-price" class="block text-xs font-bold uppercase tracking-wider text-brand-600 mb-1.5">
                                Modal Provider (Rp)
                            </label>
                            <input type="number" id="create-provider-price" name="provider_price" min="0" value="{{ $kopkenService?->provider_price ?? 3500 }}"
                                   class="w-full rounded-xl border-brand-200 py-2.5 px-3 text-sm font-mono text-brand-900 focus:border-brand-900 focus:ring-brand-900">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        {{-- Status --}}
                        <div>
                            <label for="create-status" class="block text-xs font-bold uppercase tracking-wider text-brand-600 mb-1.5">
                                Status Order <span class="text-rose-500">*</span>
                            </label>
                            <select id="create-status" name="status" required
                                    class="w-full rounded-xl border-brand-200 py-2.5 px-3 text-sm text-brand-900 focus:border-brand-900 focus:ring-brand-900">
                                <option value="completed">✅ Berhasil (Completed)</option>
                                <option value="pending">⏳ Pending</option>
                                <option value="cancelled">❌ Dibatalkan</option>
                                <option value="expired">⌛ Expired</option>
                            </select>
                        </div>

                        {{-- Wallet Status --}}
                        <div>
                            <label for="create-wallet-status" class="block text-xs font-bold uppercase tracking-wider text-brand-600 mb-1.5">
                                Status Saldo / Wallet <span class="text-rose-500">*</span>
                            </label>
                            <select id="create-wallet-status" name="wallet_status" required
                                    class="w-full rounded-xl border-brand-200 py-2.5 px-3 text-sm text-brand-900 focus:border-brand-900 focus:ring-brand-900">
                                <option value="charged">Charged (Terpotong)</option>
                                <option value="held">Held (Tertahan)</option>
                                <option value="refunded">Refunded (Dikembalikan)</option>
                                <option value="none">None (Tanpa Dompet)</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label for="create-full-text" class="block text-xs font-bold uppercase tracking-wider text-brand-600 mb-1.5">
                            Pesan SMS / Catatan
                        </label>
                        <textarea id="create-full-text" name="full_text" rows="2" placeholder="Teks SMS atau catatan internal..."
                                  class="w-full rounded-xl border-brand-200 py-2.5 px-3 text-sm text-brand-900 focus:border-brand-900 focus:ring-brand-900"></textarea>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-brand-100">
                        <button type="button" @click="createModal = false"
                                class="rounded-xl border border-brand-200 px-5 py-2.5 text-sm font-semibold text-brand-700 hover:bg-brand-50 transition">
                            Batal
                        </button>
                        <button type="submit"
                                class="inline-flex items-center gap-2 rounded-xl bg-brand-900 px-6 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-brand-700">
                            Simpan Order
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- ================= MODAL EDIT ORDER ================= --}}
        <div x-cloak x-show="editModal"
             class="fixed inset-0 z-50 flex items-center justify-center p-4"
             role="dialog" aria-modal="true">
            <div x-show="editModal"
                 x-transition:enter="ease-out duration-200"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="ease-in duration-150"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 @click="editModal = false"
                 class="fixed inset-0 bg-brand-900/60 backdrop-blur-sm"></div>

            <div x-show="editModal"
                 x-transition:enter="ease-out duration-200"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100"
                 x-transition:leave="ease-in duration-150"
                 x-transition:leave-start="opacity-100 scale-100"
                 x-transition:leave-end="opacity-0 scale-95"
                 class="relative w-full max-w-lg max-h-[90vh] overflow-y-auto rounded-3xl bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-brand-100 px-6 py-5 sticky top-0 bg-white/95 backdrop-blur z-10">
                    <div>
                        <h3 class="text-lg font-extrabold text-brand-900">
                            Edit Transaksi OTP <span class="font-mono text-brand-500" x-text="'#' + editForm.id"></span>
                        </h3>
                        <p class="text-xs text-brand-500">Koreksi nomor, kode OTP, atau status pesanan.</p>
                    </div>
                    <button type="button" @click="editModal = false" class="rounded-xl p-1.5 text-brand-400 hover:bg-brand-100 hover:text-brand-900">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <form method="POST" :action="editForm.actionUrl" class="p-6 space-y-4">
                    @csrf
                    @method('PUT')

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-brand-600 mb-1.5">
                                Nomor Telepon <span class="text-rose-500">*</span>
                            </label>
                            <input type="text" name="phone_number" x-model="editForm.phone_number" required
                                   class="w-full rounded-xl border-brand-200 py-2.5 px-3 text-sm font-mono text-brand-900 focus:border-brand-900 focus:ring-brand-900">
                        </div>

                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-brand-600 mb-1.5">
                                Kode OTP
                            </label>
                            <input type="text" name="otp_code" x-model="editForm.otp_code" placeholder="Kode OTP..."
                                   class="w-full rounded-xl border-brand-200 py-2.5 px-3 text-sm font-mono text-brand-900 focus:border-brand-900 focus:ring-brand-900">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-brand-600 mb-1.5">
                                Harga Jual (Rp) <span class="text-rose-500">*</span>
                            </label>
                            <input type="number" name="sell_price" x-model="editForm.sell_price" required min="0"
                                   class="w-full rounded-xl border-brand-200 py-2.5 px-3 text-sm font-mono text-brand-900 focus:border-brand-900 focus:ring-brand-900">
                        </div>

                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-brand-600 mb-1.5">
                                Modal Provider (Rp)
                            </label>
                            <input type="number" name="provider_price" x-model="editForm.provider_price" min="0"
                                   class="w-full rounded-xl border-brand-200 py-2.5 px-3 text-sm font-mono text-brand-900 focus:border-brand-900 focus:ring-brand-900">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-brand-600 mb-1.5">
                                Status Order <span class="text-rose-500">*</span>
                            </label>
                            <select name="status" x-model="editForm.status" required
                                    class="w-full rounded-xl border-brand-200 py-2.5 px-3 text-sm text-brand-900 focus:border-brand-900 focus:ring-brand-900">
                                <option value="completed">✅ Berhasil (Completed)</option>
                                <option value="pending">⏳ Pending</option>
                                <option value="cancelled">❌ Dibatalkan</option>
                                <option value="expired">⌛ Expired</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-brand-600 mb-1.5">
                                Status Wallet <span class="text-rose-500">*</span>
                            </label>
                            <select name="wallet_status" x-model="editForm.wallet_status" required
                                    class="w-full rounded-xl border-brand-200 py-2.5 px-3 text-sm text-brand-900 focus:border-brand-900 focus:ring-brand-900">
                                <option value="charged">Charged (Terpotong)</option>
                                <option value="held">Held (Tertahan)</option>
                                <option value="refunded">Refunded (Dikembalikan)</option>
                                <option value="none">None</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-brand-600 mb-1.5">
                            Isi Pesan SMS / Catatan
                        </label>
                        <textarea name="full_text" x-model="editForm.full_text" rows="2"
                                  class="w-full rounded-xl border-brand-200 py-2.5 px-3 text-sm text-brand-900 focus:border-brand-900 focus:ring-brand-900"></textarea>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-brand-100">
                        <button type="button" @click="editModal = false"
                                class="rounded-xl border border-brand-200 px-5 py-2.5 text-sm font-semibold text-brand-700 hover:bg-brand-50 transition">
                            Batal
                        </button>
                        <button type="submit"
                                class="inline-flex items-center gap-2 rounded-xl bg-brand-900 px-6 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-brand-700">
                            Perbarui Data
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- ================= MODAL HAPUS ORDER ================= --}}
        <div x-cloak x-show="deleteModal"
             class="fixed inset-0 z-50 flex items-center justify-center p-4"
             role="dialog" aria-modal="true">
            <div x-show="deleteModal"
                 x-transition:enter="ease-out duration-200"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="ease-in duration-150"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 @click="deleteModal = false"
                 class="fixed inset-0 bg-brand-900/60 backdrop-blur-sm"></div>

            <div x-show="deleteModal"
                 x-transition:enter="ease-out duration-200"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100"
                 x-transition:leave="ease-in duration-150"
                 x-transition:leave-start="opacity-100 scale-100"
                 x-transition:leave-end="opacity-0 scale-95"
                 class="relative w-full max-w-md rounded-3xl bg-white p-6 shadow-2xl text-center">
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-rose-50 text-rose-600 border border-rose-100 mb-4">
                    <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                </div>

                <h3 class="text-lg font-extrabold text-brand-900">Hapus Riwayat OTP?</h3>
                <p class="mt-2 text-xs text-brand-500 leading-relaxed">
                    Data transaksi <span class="font-mono font-bold text-brand-900" x-text="'#' + deleteForm.id"></span> akan dihapus permanen dari riwayat bot Anda. Tindakan ini tidak dapat dibatalkan.
                </p>

                <form method="POST" :action="deleteForm.actionUrl" class="mt-6 flex items-center justify-center gap-3">
                    @csrf
                    @method('DELETE')
                    <button type="button" @click="deleteModal = false"
                            class="flex-1 rounded-xl border border-brand-200 py-2.5 text-sm font-semibold text-brand-700 hover:bg-brand-50 transition">
                        Batal
                    </button>
                    <button type="submit"
                            class="flex-1 rounded-xl bg-rose-600 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-rose-700 transition">
                        Ya, Hapus
                    </button>
                </form>
            </div>
        </div>

    </div>
</x-app-layout>