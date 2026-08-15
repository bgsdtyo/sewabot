{{-- Mobile bottom navigation — style floating center CTA --}}
@auth
<nav class="fixed inset-x-0 bottom-0 z-50 border-t border-brand-200 bg-[#F7F4EF] pb-[env(safe-area-inset-bottom)] md:hidden"
     aria-label="Menu bawah">
    <div class="relative mx-auto flex h-16 max-w-lg items-end justify-between px-2">
        <a href="{{ route('landing') }}"
           class="flex w-1/5 flex-col items-center gap-0.5 pb-2 pt-2 {{ request()->routeIs('landing') ? 'text-brand-900' : 'text-[#5C4A3A]' }}">
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955a1.126 1.126 0 011.591 0L21.75 12M4.5 9.75V19.5A2.25 2.25 0 006.75 21.75h3.75V16.5a.75.75 0 01.75-.75h1.5a.75.75 0 01.75.75v5.25h3.75A2.25 2.25 0 0019.5 19.5V9.75" />
            </svg>
            <span class="text-[10px] font-semibold leading-none">Home</span>
        </a>

        <a href="{{ route('payments.index') }}"
           class="flex w-1/5 flex-col items-center gap-0.5 pb-2 pt-2 {{ request()->routeIs('payments.*') ? 'text-brand-900' : 'text-[#5C4A3A]' }}">
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z" />
            </svg>
            <span class="text-[10px] font-semibold leading-none">Bayar</span>
        </a>

        {{-- Center raised button --}}
        <div class="relative -top-5 flex w-1/5 flex-col items-center">
            <a href="{{ route('dashboard') }}"
               class="flex h-14 w-14 items-center justify-center rounded-full border-[3px] border-white bg-brand-900 text-white shadow-soft ring-1 ring-brand-200 {{ request()->routeIs('dashboard', 'bots.*') ? 'ring-2 ring-accent' : '' }}"
               aria-label="Dashboard">
                <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 3v1.5M4.5 8.25H3m18 0h-1.5M4.5 12H3m18 0h-1.5m-15 3.75H3m18 0h-1.5M8.25 19.5V21M12 3v1.5m0 15V21m3.75-18v1.5m0 15V21m-9-1.5h10.5a2.25 2.25 0 002.25-2.25V6.75a2.25 2.25 0 00-2.25-2.25H6.75A2.25 2.25 0 004.5 6.75v10.5a2.25 2.25 0 002.25 2.25zm.75-12h9v9h-9v-9z" />
                </svg>
            </a>
            <span class="mt-1 text-[10px] font-semibold leading-none {{ request()->routeIs('dashboard', 'bots.*') ? 'text-brand-900' : 'text-[#5C4A3A]' }}">Bot</span>
        </div>

        <a href="{{ route('profile.edit') }}"
           class="flex w-1/5 flex-col items-center gap-0.5 pb-2 pt-2 {{ request()->routeIs('profile.*') ? 'text-brand-900' : 'text-[#5C4A3A]' }}">
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
            </svg>
            <span class="text-[10px] font-semibold leading-none">Profil</span>
        </a>

        <form method="POST" action="{{ route('logout') }}" class="flex w-1/5 flex-col items-center gap-0.5 pb-2 pt-2 text-[#5C4A3A]">
            @csrf
            <button type="submit" class="flex flex-col items-center gap-0.5">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75" />
                </svg>
                <span class="text-[10px] font-semibold leading-none">Keluar</span>
            </button>
        </form>
    </div>
</nav>
@endauth
