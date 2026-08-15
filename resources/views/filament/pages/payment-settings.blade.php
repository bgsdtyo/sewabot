<x-filament-panels::page>
    <form wire:submit="save" class="space-y-6">
        {{ $this->form }}

        <x-filament::button type="submit">
            Simpan Settings
        </x-filament::button>
    </form>

    <div class="mt-8 rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="border-b border-gray-200 px-6 py-4 dark:border-white/10">
            <h3 class="text-base font-semibold text-gray-950 dark:text-white">
                Testing Generate Nominal
            </h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Generate QRIS dinamis dengan nominal uji. Pakai string di form di atas (belum perlu disimpan).
            </p>
        </div>

        <div class="space-y-6 p-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-end">
                <div class="flex-1">
                    <label for="testAmount" class="fi-fo-field-wrp-label inline-flex items-center gap-x-1 text-sm font-medium text-gray-950 dark:text-white">
                        Nominal test (Rp)
                    </label>
                    <input
                        id="testAmount"
                        type="number"
                        min="100"
                        step="100"
                        wire:model="testAmount"
                        class="fi-input mt-2 block w-full rounded-lg border-none bg-white px-3 py-2 text-base text-gray-950 shadow-sm outline outline-1 -outline-offset-1 outline-gray-950/10 placeholder:text-gray-400 focus:outline focus:outline-2 focus:-outline-offset-2 focus:outline-primary-600 dark:bg-white/5 dark:text-white dark:outline-white/10 sm:text-sm"
                        placeholder="150000"
                    >
                    @error('testAmount')
                        <p class="mt-1 text-sm text-danger-600">{{ $message }}</p>
                    @enderror
                </div>

                <x-filament::button type="button" wire:click="generateTestQris" color="gray">
                    Generate QRIS Test
                </x-filament::button>
            </div>

            @if ($testQrDataUri)
                <div class="grid gap-6 lg:grid-cols-2">
                    <div class="flex flex-col items-center justify-center rounded-xl border border-dashed border-gray-300 bg-gray-50 p-6 dark:border-white/10 dark:bg-white/5">
                        <img
                            src="{{ $testQrDataUri }}"
                            alt="QRIS test"
                            class="h-64 w-64 rounded-lg bg-white p-2 shadow-sm"
                        >
                        <p class="mt-4 text-sm font-medium text-gray-950 dark:text-white">
                            Rp{{ number_format((int) $testAmount, 0, ',', '.') }}
                        </p>
                        @if ($testMerchant)
                            <p class="mt-1 text-xs text-gray-500">{{ $testMerchant }}</p>
                        @endif
                        <p class="mt-2 text-center text-xs text-gray-500">
                            Scan dengan GoPay / DANA / OVO / bank untuk verifikasi nominal.
                        </p>
                    </div>

                    <div>
                        <label class="fi-fo-field-wrp-label mb-2 inline-flex text-sm font-medium text-gray-950 dark:text-white">
                            Dynamic payload
                        </label>
                        <textarea
                            readonly
                            rows="10"
                            class="fi-textarea block w-full rounded-lg border-none bg-white px-3 py-2 font-mono text-xs text-gray-950 shadow-sm outline outline-1 -outline-offset-1 outline-gray-950/10 dark:bg-white/5 dark:text-white dark:outline-white/10"
                        >{{ $testDynamicPayload }}</textarea>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
