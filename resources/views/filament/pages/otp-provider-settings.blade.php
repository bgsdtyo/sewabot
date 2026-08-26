<x-filament-panels::page>
    <form wire:submit="save" class="space-y-6">
        {{ $this->form }}

        <div class="flex flex-wrap items-center gap-3">
            <x-filament::button type="submit">
                Simpan Pengaturan
            </x-filament::button>

            <x-filament::button type="button" color="gray" wire:click="syncKopken">
                Sync KOPKEN (EngineUnicorn)
            </x-filament::button>

            <x-filament::button type="button" color="info" wire:click="syncWahub">
                Sync WAHub (Provider 2)
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
