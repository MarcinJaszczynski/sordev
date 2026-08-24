<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}

        <div class="mt-6">
            <x-filament::button type="submit">
                Zapisz ustawienia SEO
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
