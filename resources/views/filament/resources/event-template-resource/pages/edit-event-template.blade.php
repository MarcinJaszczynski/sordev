<x-filament-panels::page>
    <form wire:submit="save" class="fi-form space-y-6">
        {{ $this->form }}

        <div class="fi-form-actions flex justify-start gap-3">
            <x-filament::button type="submit">
                Zapisz zmiany
            </x-filament::button>
        </div>
    </form>

    <x-filament::section class="mt-6">
        <x-slot name="heading">Notatki szablonu</x-slot>
        @include('filament.components.sticky-notes-stack', [
            'notableType' => \App\Models\EventTemplate::class,
            'notableId' => $this->record->id,
            'title' => 'Notatki szablonu',
        ])
    </x-filament::section>
</x-filament-panels::page>
