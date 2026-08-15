<section>
    <header>
        <h2 class="text-lg font-extrabold text-brand-900">
            Informasi Profil
        </h2>

        <p class="mt-1 text-sm text-brand-500">
            Perbarui nama akun dan alamat email Anda.
        </p>
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-5">
        @csrf
        @method('patch')

        <div>
            <label for="name" class="block text-xs font-bold uppercase tracking-wider text-brand-500 mb-1.5">Nama Lengkap</label>
            <input id="name" name="name" type="text" class="w-full rounded-2xl border-brand-200 px-4 py-2.5 text-sm text-brand-900 focus:border-brand-900 focus:ring-brand-900" :value="old('name', $user->name)" required autofocus autocomplete="name" />
            <x-input-error class="mt-2" :messages="$errors->get('name')" />
        </div>

        <div>
            <label for="email" class="block text-xs font-bold uppercase tracking-wider text-brand-500 mb-1.5">Alamat Email</label>
            <input id="email" name="email" type="email" class="w-full rounded-2xl border-brand-200 px-4 py-2.5 text-sm text-brand-900 focus:border-brand-900 focus:ring-brand-900" :value="old('email', $user->email)" required autocomplete="username" />
            <x-input-error class="mt-2" :messages="$errors->get('email')" />

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div>
                    <p class="text-sm mt-2 text-brand-700">
                        Alamat email Anda belum diverifikasi.

                        <button form="send-verification" class="underline text-sm text-brand-900 hover:text-brand-700 font-medium">
                            Klik di sini untuk mengirim ulang email verifikasi.
                        </button>
                    </p>

                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-2 font-medium text-sm text-emerald-600">
                            Tautan verifikasi baru telah dikirim ke alamat email Anda.
                        </p>
                    @endif
                </div>
            @endif
        </div>

        <div class="flex items-center gap-4 pt-2">
            <button type="submit" class="rounded-xl bg-brand-900 px-6 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-brand-700 transition">
                Simpan Perubahan
            </button>

            @if (session('status') === 'profile-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 3000)"
                    class="text-sm font-semibold text-emerald-600"
                >Profil berhasil diperbarui.</p>
            @endif
        </div>
    </form>
</section>
