<nav class="border-b border-brand-200 bg-white/90 backdrop-blur">
    <div class="mx-auto flex h-16 max-w-6xl items-center justify-between px-4 sm:px-6 lg:px-8">
        <a href="{{ route('landing') }}" class="text-lg font-extrabold tracking-tight text-brand-900">SewaBot</a>

        <div class="flex items-center gap-3 text-sm">
            @auth
                <span class="hidden text-brand-500 sm:inline">{{ Auth::user()->name }}</span>
            @else
                <a href="{{ route('login') }}" class="rounded-lg px-3 py-2 font-medium text-brand-700 hover:bg-brand-100">Login</a>
                <a href="{{ route('register') }}" class="rounded-lg bg-brand-900 px-3 py-2 font-medium text-white hover:bg-brand-700">Register</a>
            @endauth
        </div>
    </div>
</nav>
