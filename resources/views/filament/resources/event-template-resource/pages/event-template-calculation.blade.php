<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">
            Miejsce startu (lokalizacja wyjazdu)
        </x-slot>
        <x-slot name="description">
            Kalkulacja zależy od lokalizacji startu. Możesz też wejść tu z zakładki Transport (link przy wierszu).
        </x-slot>

        <select
            id="calculation-start-place"
            wire:change="selectCalculationStartPlace($event.target.value)"
            class="fi-select-input block w-full max-w-md"
        >
            <option value="" @selected(! $startPlaceId)>— Wybierz miejsce startu —</option>
            @foreach($availableStartPlaces as $id => $name)
                <option value="{{ $id }}" @selected((int) $startPlaceId === (int) $id)>{{ $name }}</option>
            @endforeach
        </select>

        @if(empty($availableStartPlaces))
            <p class="mt-2 text-xs text-amber-700">
                Brak dostępnych miejsc startu. Ustaw dostępność w zakładce Transport.
            </p>
        @endif
    </x-filament::section>

    @if($startPlace && $transportKm)
        <x-filament::section class="mt-6">
            <x-slot name="heading">
                Kalkulacja transportu
            </x-slot>

            <div class="grid grid-cols-1 gap-4 text-sm md:grid-cols-3">
                <div>
                    <span class="font-medium text-gray-700">Miejsce startu:</span>
                    <span class="text-gray-900">{{ $startPlace->name }}</span>
                </div>
                <div>
                    <span class="font-medium text-gray-700">Podstawowa odległość:</span>
                    <span class="text-gray-900">{{ number_format($transportKm, 2, ',', ' ') }} km</span>
                </div>
                <div>
                    <span class="font-medium text-gray-700">Obliczona odległość:</span>
                    <span class="text-base font-bold text-gray-900">{{ number_format($calculatedKm, 2, ',', ' ') }} km</span>
                </div>
            </div>
            <p class="mt-3 text-xs text-gray-600">
                Wzór: 1,1 × {{ number_format($transportKm, 2, ',', ' ') }} km + 50 km = {{ number_format($calculatedKm, 2, ',', ' ') }} km
            </p>
        </x-filament::section>
    @elseif($startPlaceId && ! $startPlace)
        <div class="mt-6 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            Nie znaleziono wybranego miejsca startu.
        </div>
    @elseif(! $startPlaceId)
        <div class="mt-6 rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-600">
            Wybierz miejsce startu powyżej, aby zobaczyć kalkulację dla konkretnej lokalizacji wyjazdu.
        </div>
    @endif

    <section class="fi-section mt-6 rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5">
        <div class="fi-section-content-ctn">
            <div class="fi-section-content space-y-6 p-6">
                @foreach($this->getWidgets() as $widget)
                    @livewire($widget, [
                        'record' => $record,
                        'startPlace' => $startPlace,
                        'transportKm' => $calculatedKm,
                    ])
                @endforeach
            </div>
        </div>
    </section>
</x-filament-panels::page>
