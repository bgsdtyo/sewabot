<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-extrabold text-brand-900">Pengaturan Akun</h1>
                <p class="mt-1 text-sm text-brand-500">Kelola informasi profil, email, dan keamanan kata sandi akun Anda.</p>
            </div>
            <a href="{{ route('dashboard') }}" class="hidden sm:inline-flex items-center gap-1.5 rounded-xl border border-brand-200 bg-white px-4 py-2 text-xs font-bold text-brand-700 hover:bg-brand-50 transition shadow-sm">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                <span>Kembali ke Dashboard</span>
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            {{-- Update Profile Info --}}
            <div class="rounded-3xl border border-brand-200 bg-white p-6 sm:p-8 shadow-soft">
                <div class="max-w-xl">
                    @include('profile.partials.update-profile-information-form')
                </div>
            </div>

            {{-- Update Password --}}
            <div class="rounded-3xl border border-brand-200 bg-white p-6 sm:p-8 shadow-soft">
                <div class="max-w-xl">
                    @include('profile.partials.update-password-form')
                </div>
            </div>

            {{-- Delete Account --}}
            <div class="rounded-3xl border border-rose-100 bg-white p-6 sm:p-8 shadow-soft">
                <div class="max-w-xl">
                    @include('profile.partials.delete-user-form')
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
