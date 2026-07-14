<x-filament-panels::page>
    <section class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <header class="fi-section-header flex flex-col gap-3 px-6 py-4">
            <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                Hotele w szablonie
            </h3>
            <p class="text-sm text-gray-600 dark:text-gray-400">
                Ustal typy pokoi i ilości na każdą noc. Zmiany długości imprezy (dni) wprowadź w zakładce „Dane”, potem odśwież hotele tutaj.
            </p>
        </header>
        <div class="fi-section-content-ctn border-t border-gray-200 dark:border-white/10">
            <div class="fi-section-content p-6">
                @include('filament.components.event-template-hotel-days-table', ['page' => $this])
                <div class="mt-4 flex flex-wrap gap-2">
                    <x-filament::button type="button" wire:click="saveHotelDays">
                        Zapisz noclegi
                    </x-filament::button>
                    <x-filament::button type="button" wire:click="forceRefreshHotelDays" color="gray">
                        Odśwież z liczby dni
                    </x-filament::button>
                </div>
            </div>
        </div>
    </section>
</x-filament-panels::page>
