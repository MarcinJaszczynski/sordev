<x-filament-panels::page>
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
