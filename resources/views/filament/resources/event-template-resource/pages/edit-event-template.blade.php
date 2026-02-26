<x-filament-panels::page>
    {{-- Formularz Filament z jawnym renderowaniem --}}
    <form wire:submit="save" class="fi-form space-y-6">
        {{ $this->form }}
        
        <div class="fi-form-actions flex justify-start gap-3">
            <x-filament::button type="submit">
                Zapisz zmiany
            </x-filament::button>
        </div>
    </form>

    {{-- Sekcja hotelowa po formularzu --}}
    <section x-data="{ isCollapsed: false }" class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 mt-8" id="data.hotel">
        <header class="fi-section-header flex flex-col gap-3 px-6 py-4">
            <div class="flex items-center gap-3">
                <div class="grid flex-1 gap-y-1">
                    <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                        Hotel
                    </h3>
                </div>
            </div>
        </header>
        <div class="fi-section-content-ctn border-t border-gray-200 dark:border-white/10">
            <div class="fi-section-content p-6">
                @include('filament.components.event-template-hotel-days-table', ['page' => $this])
                <div class="mt-4 flex gap-2">
                    <button type="button" wire:click="saveHotelDays" class="px-4 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        💾 Zapisz noclegi
                    </button>
                    <button type="button" wire:click="forceRefreshHotelDays" class="text-xs text-blue-600 underline">
                        Odśwież noclegi
                    </button>
                </div>
            </div>
        </div>
    </section>
</x-filament-panels::page>