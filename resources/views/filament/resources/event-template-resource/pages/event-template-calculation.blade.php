<x-filament-panels::page>
    <div class="mb-6 rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
        <label for="calculation-start-place" class="mb-1 block text-sm font-medium text-gray-700">
            Miejsce startu (lokalizacja wyjazdu)
        </label>
        <p class="mb-3 text-xs text-gray-500">
            Kalkulacja zależy od lokalizacji startu. Możesz też wejść tu z zakładki Transport (link przy wierszu).
        </p>
        <select
            id="calculation-start-place"
            wire:change="selectCalculationStartPlace($event.target.value)"
            class="block w-full max-w-md rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500"
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
    </div>

    @if($startPlace && $transportKm)
        <div class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
            <h3 class="text-lg font-semibold text-blue-900 mb-2">Kalkulacja transportu</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                <div>
                    <span class="font-medium text-blue-800">Miejsce startu:</span>
                    <span class="text-blue-600">{{ $startPlace->name }}</span>
                </div>
                <div>
                    <span class="font-medium text-blue-800">Podstawowa odległość:</span>
                    <span class="text-blue-600">{{ number_format($transportKm, 2, ',', ' ') }} km</span>
                </div>
                <div>
                    <span class="font-medium text-blue-800">Obliczona odległość:</span>
                    <span class="text-lg font-bold text-blue-900">{{ number_format($calculatedKm, 2, ',', ' ') }} km</span>
                </div>
            </div>
            <div class="mt-3 text-xs text-blue-700">
                Wzór: 1,1 × {{ number_format($transportKm, 2, ',', ' ') }} km + 50 km = {{ number_format($calculatedKm, 2, ',', ' ') }} km
            </div>
        </div>
    @elseif($startPlaceId && ! $startPlace)
        <div class="mb-6 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
            Nie znaleziono wybranego miejsca startu.
        </div>
    @elseif(! $startPlaceId)
        <div class="mb-6 rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm text-gray-600">
            Wybierz miejsce startu powyżej, aby zobaczyć kalkulację dla konkretnej lokalizacji wyjazdu.
        </div>
    @endif

    <div class="space-y-6">
        @foreach($this->getWidgets() as $widget)
            @livewire($widget, [
                'record' => $record, 
                'startPlace' => $startPlace,
                'transportKm' => $calculatedKm
            ])
        @endforeach
    </div>
</x-filament-panels::page>
