<x-filament-panels::page>
    @unless ($this->canMutateEventTemplateNow())
        <div class="mb-4 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-700 dark:bg-amber-950/40 dark:text-amber-100">
            Podgląd hoteli szablonu. Żeby zapisać zmiany, kliknij <strong>Edytuj szablon</strong> i potwierdź.
        </div>
    @endunless

    <section class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="fi-section-content p-6">
            @include('filament.components.event-template-hotel-days-table', ['page' => $this])

            @if ($this->canMutateEventTemplateNow())
                <div class="mt-4 flex flex-wrap gap-2 border-t border-gray-100 pt-4 dark:border-gray-800">
                    <x-filament::button type="button" wire:click="saveHotelDays">
                        Zapisz noclegi
                    </x-filament::button>
                    <x-filament::button type="button" wire:click="forceRefreshHotelDays" color="gray">
                        Odśwież z liczby dni
                    </x-filament::button>
                </div>
            @endif
        </div>
    </section>
</x-filament-panels::page>
