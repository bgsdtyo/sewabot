<nav x-data="{ openMobile: false, openDropdown: false }" class="sticky top-0 z-40 border-b border-brand-200 bg-white/95 backdrop-blur-md">
    <div class="mx-auto flex h-16 max-w-6xl items-center justify-between px-4 sm:px-6 lg:px-8">
        {{-- Left: Brand Logo & Links --}}
        <div class="flex items-center gap-8">
            <a href="{{ route('landing') }}" class="flex items-center gap-2.5 group">
                <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-brand-900 text-white font-black text-base shadow-sm transition group-hover:bg-brand-700">
                    S
                </div>
                <span class="text-xl font-extrabold tracking-tight text-brand-900">SewaBot</span>
            </a>

            @auth
                {{-- Desktop Nav Links --}}
                <div class="hidden md:flex items-center gap-1">
                    <a href="{{ route('dashboard') }}"
                       class="rounded-xl px-3.5 py-2 text-sm font-semibold transition {{ request()->routeIs('dashboard') ? 'bg-brand-100 text-brand-900 font-bold' : 'text-brand-600 hover:bg-brand-50 hover:text-brand-900' }}">
                        Dashboard
                    </a>
                    <a href="{{ route('otp-orders.index') }}"
                       class="rounded-xl px-3.5 py-2 text-sm font-semibold transition {{ request()->routeIs('otp-orders.*') ? 'bg-brand-100 text-brand-900 font-bold' : 'text-brand-600 hover:bg-brand-50 hover:text-brand-900' }}">
                        Riwayat OTP
                    </a>
                    <a href="{{ route('payments.index') }}"
                       class="rounded-xl px-3.5 py-2 text-sm font-semibold transition {{ request()->routeIs('payments.*') ? 'bg-brand-100 text-brand-900 font-bold' : 'text-brand-600 hover:bg-brand-50 hover:text-brand-900' }}">
                        Riwayat Bayar
                    </a>
                    <a href="{{ route('profile.edit') }}"
                       class="rounded-xl px-3.5 py-2 text-sm font-semibold transition {{ request()->routeIs('profile.*') ? 'bg-brand-100 text-brand-900 font-bold' : 'text-brand-600 hover:bg-brand-50 hover:text-brand-900' }}">
                        Pengaturan Akun
                    </a>
                </div>
            @endauth
        </div>

        {{-- Right: User Menu (Desktop) / Auth Buttons / Hamburger --}}
        <div class="flex items-center gap-3">
            @auth
                {{-- User Dropdown (Desktop) --}}
                <div class="relative hidden md:block" @click.outside="openDropdown = false">
                    <button type="button"
                            @click="openDropdown = !openDropdown"
                            class="flex items-center gap-2.5 rounded-2xl border border-brand-200 bg-white px-3.5 py-2 text-sm font-semibold text-brand-900 shadow-soft transition hover:border-brand-300 hover:bg-brand-50 active:scale-98">
                        <div class="flex h-7 w-7 items-center justify-center rounded-xl bg-brand-900 text-xs font-bold text-white">
                            {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                        </div>
                        <span class="max-w-[140px] truncate">{{ Auth::user()->name }}</span>
                        <svg class="h-4 w-4 text-brand-400 transition" :class="openDropdown ? 'rotate-180 text-brand-900' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>

                    {{-- Dropdown Menu --}}
                    <div x-cloak x-show="openDropdown"
                         x-transition:enter="transition ease-out duration-150"
                         x-transition:enter-start="opacity-0 scale-95 -translate-y-1"
                         x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                         x-transition:leave="transition ease-in duration-100"
                         x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                         x-transition:leave-end="opacity-0 scale-95 -translate-y-1"
                         class="absolute right-0 mt-2 w-64 origin-top-right overflow-hidden rounded-2xl border border-brand-200 bg-white py-2 shadow-xl ring-1 ring-black/5">
                        <div class="border-b border-brand-100 px-4 py-3 bg-brand-50/50">
                            <p class="text-[11px] font-bold uppercase tracking-wider text-brand-400">Masuk sebagai</p>
                            <p class="truncate text-sm font-extrabold text-brand-900">{{ Auth::user()->name }}</p>
                            <p class="truncate text-xs text-brand-500 font-mono">{{ Auth::user()->email }}</p>
                        </div>

                        <div class="py-1">
                            <a href="{{ route('dashboard') }}" class="flex items-center gap-3 px-4 py-2.5 text-sm text-brand-700 hover:bg-brand-50 hover:text-brand-900">
                                <svg class="h-4 w-4 text-brand-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                                </svg>
                                <span class="font-medium">Dashboard</span>
                            </a>
                            <a href="{{ route('otp-orders.index') }}" class="flex items-center gap-3 px-4 py-2.5 text-sm text-brand-700 hover:bg-brand-50 hover:text-brand-900">
                                <svg class="h-4 w-4 text-brand-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                                </svg>
                                <span class="font-medium">Riwayat Transaksi OTP</span>
                            </a>
                            <a href="{{ route('profile.edit') }}" class="flex items-center gap-3 px-4 py-2.5 text-sm text-brand-700 hover:bg-brand-50 hover:text-brand-900">
                                <svg class="h-4 w-4 text-brand-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                                <span class="font-medium">Pengaturan Akun & Password</span>
                            </a>
                            <a href="{{ route('payments.index') }}" class="flex items-center gap-3 px-4 py-2.5 text-sm text-brand-700 hover:bg-brand-50 hover:text-brand-900">
                                <svg class="h-4 w-4 text-brand-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                                </svg>
                                <span class="font-medium">Riwayat Pembayaran</span>
                            </a>
                            @if (Auth::user()->is_admin ?? false)
                                <a href="/admin" class="flex items-center gap-3 px-4 py-2.5 text-sm font-semibold text-amber-800 hover:bg-amber-50">
                                    <svg class="h-4 w-4 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                    <span>Panel Admin</span>
                                </a>
                            @endif
                        </div>

                        <div class="border-t border-brand-100 pt-1">
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="flex w-full items-center gap-3 px-4 py-2.5 text-sm font-semibold text-rose-600 hover:bg-rose-50">
                                    <svg class="h-4 w-4 text-rose-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                    </svg>
                                    <span>Keluar (Logout)</span>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                {{-- Hamburger Button (Mobile) --}}
                <button type="button"
                        @click="openMobile = !openMobile"
                        class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-brand-200 bg-white text-brand-700 hover:bg-brand-50 hover:text-brand-900 active:scale-95 md:hidden">
                    <svg x-show="!openMobile" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                    <svg x-show="openMobile" x-cloak class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            @else
                <a href="{{ route('login') }}" class="rounded-xl px-4 py-2 text-sm font-semibold text-brand-700 hover:bg-brand-100 transition">Masuk</a>
                <a href="{{ route('register') }}" class="rounded-xl bg-brand-900 px-4 py-2 text-sm font-bold text-white hover:bg-brand-700 transition shadow-sm">Daftar</a>
            @endauth
        </div>
    </div>

    @auth
        {{-- Mobile Drawer / Dropdown --}}
        <div x-cloak x-show="openMobile"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 -translate-y-2"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 translate-y-0"
             x-transition:leave-end="opacity-0 -translate-y-2"
             class="border-b border-brand-200 bg-white px-4 pt-3 pb-6 md:hidden shadow-lg">
            {{-- User info card on mobile --}}
            <div class="mb-4 flex items-center gap-3 rounded-2xl border border-brand-100 bg-brand-50 p-3.5">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-900 text-sm font-bold text-white">
                    {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                </div>
                <div class="overflow-hidden">
                    <p class="truncate text-sm font-bold text-brand-900">{{ Auth::user()->name }}</p>
                    <p class="truncate text-xs text-brand-500 font-mono">{{ Auth::user()->email }}</p>
                </div>
            </div>

            {{-- Nav items --}}
            <div class="space-y-1">
                <a href="{{ route('dashboard') }}"
                   class="flex items-center gap-3 rounded-xl px-3.5 py-2.5 text-sm font-semibold transition {{ request()->routeIs('dashboard') ? 'bg-brand-900 text-white font-bold' : 'text-brand-700 hover:bg-brand-50 hover:text-brand-900' }}">
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                    </svg>
                    <span>Dashboard</span>
                </a>

                <a href="{{ route('otp-orders.index') }}"
                   class="flex items-center gap-3 rounded-xl px-3.5 py-2.5 text-sm font-semibold transition {{ request()->routeIs('otp-orders.*') ? 'bg-brand-900 text-white font-bold' : 'text-brand-700 hover:bg-brand-50 hover:text-brand-900' }}">
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                    </svg>
                    <span>Riwayat OTP</span>
                </a>

                <a href="{{ route('profile.edit') }}"
                   class="flex items-center gap-3 rounded-xl px-3.5 py-2.5 text-sm font-semibold transition {{ request()->routeIs('profile.*') ? 'bg-brand-900 text-white font-bold' : 'text-brand-700 hover:bg-brand-50 hover:text-brand-900' }}">
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                    <span>Pengaturan Akun & Password</span>
                </a>

                <a href="{{ route('payments.index') }}"
                   class="flex items-center gap-3 rounded-xl px-3.5 py-2.5 text-sm font-semibold transition {{ request()->routeIs('payments.*') ? 'bg-brand-900 text-white font-bold' : 'text-brand-700 hover:bg-brand-50 hover:text-brand-900' }}">
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                    </svg>
                    <span>Riwayat Pembayaran</span>
                </a>

                @if (Auth::user()->is_admin ?? false)
                    <a href="/admin" class="flex items-center gap-3 rounded-xl px-3.5 py-2.5 text-sm font-semibold text-amber-800 hover:bg-amber-50">
                        <svg class="h-5 w-5 shrink-0 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        <span>Panel Admin</span>
                    </a>
                @endif
            </div>

            {{-- Logout form --}}
            <div class="mt-4 border-t border-brand-100 pt-3">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="flex w-full items-center gap-3 rounded-xl px-3.5 py-2.5 text-sm font-bold text-rose-600 hover:bg-rose-50">
                        <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                        </svg>
                        <span>Keluar (Logout)</span>
                    </button>
                </form>
            </div>
        </div>
    @endauth
</nav>
