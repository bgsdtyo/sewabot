<nav class="border-b border-brand-200 bg-white/90 backdrop-blur">
    <div class="mx-auto flex h-16 max-w-6xl items-center justify-between px-4 sm:px-6 lg:px-8">
        <div class="flex items-center gap-8">
            <a href="{{ route('landing') }}" class="text-lg font-extrabold tracking-tight text-brand-900">SewaBot</a>
            @auth
                <div class="hidden items-center gap-6 text-sm font-medium text-brand-500 sm:flex">
                    <a href="{{ route('dashboard') }}" class="hover:text-brand-900 {{ request()->routeIs('dashboard') ? 'text-brand-900' : '' }}">Dashboard</a>
                    <a href="{{ route('payments.index') }}" class="hover:text-brand-900 {{ request()->routeIs('payments.*') ? 'text-brand-900' : '' }}">Pembayaran</a>
                </div>
            @endauth
        </div>

        <div class="flex items-center gap-3 text-sm">
            @auth
                <span class="hidden text-brand-500 sm:inline">{{ Auth::user()->name }}</span>
                <a href="{{ route('profile.edit') }}" class="rounded-lg px-3 py-2 font-medium text-brand-700 hover:bg-brand-100">Profil</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="rounded-lg bg-brand-900 px-3 py-2 font-medium text-white hover:bg-brand-700">Logout</button>
                </form>
            @else
                <a href="{{ route('login') }}" class="rounded-lg px-3 py-2 font-medium text-brand-700 hover:bg-brand-100">Login</a>
                <a href="{{ route('register') }}" class="rounded-lg bg-brand-900 px-3 py-2 font-medium text-white hover:bg-brand-700">Register</a>
            @endauth
        </div>
    </div>
</nav>
