{{-- Mobile bottom navigation — style floating center CTA --}}
@auth
<nav class="fixed inset-x-0 bottom-0 z-50 border-t border-slate-800 bg-slate-950 pb-[env(safe-area-inset-bottom)] md:hidden"
     aria-label="Menu bawah">
    <div class="relative mx-auto flex h-16 max-w-lg items-end justify-between px-2">
        <a href="{{ route('landing') }}"
           class="flex w-1/5 flex-col items-center gap-0.5 pb-2 pt-2 transition-colors {{ request()->routeIs('landing') ? 'text-white font-semibold' : 'text-slate-400 hover:text-white' }}">
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955a1.126 1.126 0 011.591 0L21.75 12M4.5 9.75V19.5A2.25 2.25 0 006.75 21.75h3.75V16.5a.75.75 0 01.75-.75h1.5a.75.75 0 01.75.75v5.25h3.75A2.25 2.25 0 0019.5 19.5V9.75" />
            </svg>
            <span class="text-[10px] leading-none">Home</span>
        </a>

        <a href="{{ route('otp-orders.index') }}"
           class="flex w-1/5 flex-col items-center gap-0.5 pb-2 pt-2 transition-colors {{ request()->routeIs('otp-orders.*') ? 'text-white font-semibold' : 'text-slate-400 hover:text-white' }}">
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
            </svg>
            <span class="text-[10px] leading-none">OTP</span>
        </a>

        {{-- Center raised button --}}
        <div class="relative -top-5 flex w-1/5 flex-col items-center">
            <a href="{{ route('dashboard') }}"
               class="flex h-14 w-14 items-center justify-center rounded-full border-[3px] border-slate-950 bg-slate-900 text-white shadow-lg ring-1 ring-slate-700 transition-all hover:bg-slate-800 {{ request()->routeIs('dashboard', 'bots.*') ? 'ring-2 ring-accent bg-slate-800 text-accent-soft' : '' }}"
               aria-label="Dashboard">
                <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 3v1.5M4.5 8.25H3m18 0h-1.5M4.5 12H3m18 0h-1.5m-15 3.75H3m18 0h-1.5M8.25 19.5V21M12 3v1.5m0 15V21m3.75-18v1.5m0 15V21m-9-1.5h10.5a2.25 2.25 0 002.25-2.25V6.75a2.25 2.25 0 00-2.25-2.25H6.75A2.25 2.25 0 004.5 6.75v10.5a2.25 2.25 0 002.25 2.25zm.75-12h9v9h-9v-9z" />
                </svg>
            </a>
            <span class="mt-1 text-[10px] font-semibold leading-none {{ request()->routeIs('dashboard', 'bots.*') ? 'text-white' : 'text-slate-400' }}">Bot</span>
        </div>

        <a href="{{ route('payments.index') }}"
           class="flex w-1/5 flex-col items-center gap-0.5 pb-2 pt-2 transition-colors {{ request()->routeIs('payments.*') ? 'text-white font-semibold' : 'text-slate-400 hover:text-white' }}">
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z" />
            </svg>
            <span class="text-[10px] leading-none">Bayar</span>
        </a>

        <a href="{{ route('profile.edit') }}"
           class="flex w-1/5 flex-col items-center gap-0.5 pb-2 pt-2 transition-colors {{ request()->routeIs('profile.*') ? 'text-white font-semibold' : 'text-slate-400 hover:text-white' }}">
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
            </svg>
            <span class="text-[10px] leading-none">Profil</span>
        </a>
    </div>
</nav>
@endauth
