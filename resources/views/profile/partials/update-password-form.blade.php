<section>
    <header>
        <h2 class="text-lg font-extrabold text-brand-900">
            Perbarui Kata Sandi
        </h2>

        <p class="mt-1 text-sm text-brand-500">
            Gunakan kombinasi kata sandi yang aman dan tidak mudah ditebak untuk menjaga keamanan akun Anda.
        </p>
    </header>

    <form method="post" action="{{ route('password.update') }}" class="mt-6 space-y-5">
        @csrf
        @method('put')

        <div>
            <label for="update_password_current_password" class="block text-xs font-bold uppercase tracking-wider text-brand-500 mb-1.5">Kata Sandi Saat Ini</label>
            <input id="update_password_current_password" name="current_password" type="password" class="w-full rounded-2xl border-brand-200 px-4 py-2.5 text-sm text-brand-900 focus:border-brand-900 focus:ring-brand-900" autocomplete="current-password" />
            <x-input-error :messages="$errors->updatePassword->get('current_password')" class="mt-2" />
        </div>

        <div>
            <label for="update_password_password" class="block text-xs font-bold uppercase tracking-wider text-brand-500 mb-1.5">Kata Sandi Baru</label>
            <input id="update_password_password" name="password" type="password" class="w-full rounded-2xl border-brand-200 px-4 py-2.5 text-sm text-brand-900 focus:border-brand-900 focus:ring-brand-900" autocomplete="new-password" />
            <x-input-error :messages="$errors->updatePassword->get('password')" class="mt-2" />
        </div>

        <div>
            <label for="update_password_password_confirmation" class="block text-xs font-bold uppercase tracking-wider text-brand-500 mb-1.5">Konfirmasi Kata Sandi Baru</label>
            <input id="update_password_password_confirmation" name="password_confirmation" type="password" class="w-full rounded-2xl border-brand-200 px-4 py-2.5 text-sm text-brand-900 focus:border-brand-900 focus:ring-brand-900" autocomplete="new-password" />
            <x-input-error :messages="$errors->updatePassword->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex items-center gap-4 pt-2">
            <button type="submit" class="rounded-xl bg-brand-900 px-6 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-brand-700 transition">
                Simpan Kata Sandi
            </button>

            @if (session('status') === 'password-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 3000)"
                    class="text-sm font-semibold text-emerald-600"
                >Kata sandi berhasil diperbarui.</p>
            @endif
        </div>
    </form>
</section>
