<x-filament-panels::page>
    @unless ($this->canMutateEventTemplateNow())
        <div class="mb-4 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-700 dark:bg-amber-950/40 dark:text-amber-100">
            Podgląd szablonu. Żeby zapisać zmiany, kliknij <strong>Edytuj szablon</strong> i potwierdź.
        </div>
    @endunless

    <form wire:submit="save" class="fi-form space-y-6">
        {{ $this->form }}

        @if ($this->canMutateEventTemplateNow())
            <div class="fi-form-actions flex justify-start gap-3">
                <x-filament::button type="submit">
                    Zapisz zmiany
                </x-filament::button>
            </div>
        @endif
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
