<x-filament-panels::page>
    <x-fa.wave-divider color="azul-cristal" />

    <form wire:submit="save" class="mt-6">
        {{ $this->form }}

        <div class="mt-6 flex justify-end">
            <x-filament::button type="submit">
                Salvar
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
