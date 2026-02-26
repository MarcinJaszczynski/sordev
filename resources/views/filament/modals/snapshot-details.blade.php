@php
    $calculations = $snapshot->calculations ?? [];
    $eventData = $snapshot->event_data ?? [];
    $programPoints = $snapshot->program_points ?? [];
    $currencyRates = $snapshot->currency_rates ?? [];
@endphp

<div class="space-y-6">
    {{-- Podstawowe informacje --}}
    <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
        <h3 class="text-lg font-semibold mb-3">Informacje o snapszoczie</h3>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Nazwa:</span>
                <p class="text-sm">{{ $snapshot->name }}</p>
            </div>
            <div>
                <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Typ:</span>
                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium
                    @if($snapshot->type === 'original') bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200
                    @elseif($snapshot->type === 'manual') bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200
                    @elseif($snapshot->type === 'status_change') bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200
                    @else bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200 @endif">
                    {{ $snapshot->readable_type }}
                </span>
            </div>
            <div>
                <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Data utworzenia:</span>
                <p class="text-sm">{{ $snapshot->snapshot_date->format('d.m.Y H:i:s') }}</p>
            </div>
            <div>
                <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Utworzył:</span>
                <p class="text-sm">{{ $snapshot->creator?->name ?? 'System' }}</p>
            </div>
        </div>
        @if($snapshot->description)
            <div class="mt-3">
                <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Opis:</span>
                <p class="text-sm">{{ $snapshot->description }}</p>
            </div>
        @endif
    </div>

    {{-- Dane imprezy --}}
    <div class="bg-white dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
        <h3 class="text-lg font-semibold mb-3">Dane imprezy</h3>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Nazwa:</span>
                <p class="text-sm font-medium">{{ $eventData['name'] ?? 'Brak danych' }}</p>
            </div>
            <div>
                <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Klient:</span>
                <p class="text-sm">{{ $eventData['client_name'] ?? 'Brak danych' }}</p>
            </div>
            <div>
                <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Liczba uczestników:</span>
                <p class="text-sm">{{ $eventData['participant_count'] ?? 'Brak danych' }}</p>
            </div>
            <div>
                <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Status:</span>
                <p class="text-sm">{{ ucfirst($eventData['status'] ?? 'Brak danych') }}</p>
            </div>
            <div>
                <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Data rozpoczęcia:</span>
                <p class="text-sm">{{ isset($eventData['start_date']) ? \Carbon\Carbon::parse($eventData['start_date'])->format('d.m.Y') : 'Brak danych' }}</p>
            </div>
            <div>
                <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Data zakończenia:</span>
                <p class="text-sm">{{ isset($eventData['end_date']) ? \Carbon\Carbon::parse($eventData['end_date'])->format('d.m.Y') : 'Brak danych' }}</p>
            </div>
        </div>
        @if(isset($eventData['notes']) && $eventData['notes'])
            <div class="mt-4">
                <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Uwagi:</span>
                <p class="text-sm mt-1">{{ $eventData['notes'] }}</p>
            </div>
        @endif
    </div>

    {{-- Kalkulacje --}}
    <div class="bg-white dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
        <h3 class="text-lg font-semibold mb-3">Kalkulacje i koszty</h3>
        <div class="grid grid-cols-3 gap-4 mb-4">
            <div class="text-center p-3 bg-blue-50 dark:bg-blue-900/20 rounded">
                <p class="text-sm text-gray-600 dark:text-gray-400">Koszt całkowity</p>
                <p class="text-xl font-bold text-blue-600 dark:text-blue-400">{{ number_format($snapshot->total_cost_snapshot, 2) }} PLN</p>
            </div>
            <div class="text-center p-3 bg-green-50 dark:bg-green-900/20 rounded">
                <p class="text-sm text-gray-600 dark:text-gray-400">Punkty programu</p>
                <p class="text-xl font-bold text-green-600 dark:text-green-400">{{ $calculations['points_count'] ?? 0 }}</p>
            </div>
            <div class="text-center p-3 bg-purple-50 dark:bg-purple-900/20 rounded">
                <p class="text-sm text-gray-600 dark:text-gray-400">Aktywne punkty</p>
                <p class="text-xl font-bold text-purple-600 dark:text-purple-400">{{ $calculations['active_points_count'] ?? 0 }}</p>
            </div>
        </div>

        @if(isset($calculations['cost_breakdown_by_day']) && count($calculations['cost_breakdown_by_day']) > 0)
            <div class="mt-4">
                <h4 class="font-medium mb-2">Podział kosztów według dni:</h4>
                <div class="space-y-2">
                    @foreach($calculations['cost_breakdown_by_day'] as $day => $dayData)
                        <div class="bg-gray-50 dark:bg-gray-800 p-3 rounded">
                            <div class="flex justify-between items-center">
                                <span class="font-medium">Dzień {{ $day }}</span>
                                <span class="font-bold">{{ number_format($dayData['day_total'], 2) }} PLN</span>
                            </div>
                            <p class="text-sm text-gray-600 dark:text-gray-400">{{ $dayData['points_count'] }} punktów</p>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    {{-- Punkty programu --}}
    @if(count($programPoints) > 0)
        <div class="bg-white dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
            <h3 class="text-lg font-semibold mb-3">Punkty programu ({{ count($programPoints) }})</h3>
            <div class="space-y-3 max-h-96 overflow-y-auto">
                @foreach($programPoints as $point)
                    <div class="border border-gray-200 dark:border-gray-700 rounded p-3
                        @if(!$point['active']) opacity-50 @endif">
                        <div class="flex justify-between items-start">
                            <div class="flex-1">
                                <h4 class="font-medium">{{ $point['template_point_name'] ?? 'Brak nazwy' }}</h4>
                                @if(isset($point['template_point_description']) && $point['template_point_description'])
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">{{ $point['template_point_description'] }}</p>
                                @endif
                                <div class="flex gap-4 mt-2 text-sm">
                                    <span class="text-gray-600 dark:text-gray-400">Dzień: {{ $point['day'] }}</span>
                                    <span class="text-gray-600 dark:text-gray-400">Kolejność: {{ $point['order'] }}</span>
                                    <span class="text-gray-600 dark:text-gray-400">Ilość: {{ $point['quantity'] }}</span>
                                </div>
                                @if(isset($point['notes']) && $point['notes'])
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1 italic">{{ $point['notes'] }}</p>
                                @endif
                            </div>
                            <div class="text-right ml-4">
                                <p class="font-bold">{{ number_format($point['total_price'], 2) }} PLN</p>
                                <p class="text-sm text-gray-600 dark:text-gray-400">{{ number_format($point['unit_price'], 2) }} PLN/szt</p>
                                <div class="flex gap-2 mt-1">
                                    @if($point['include_in_program'])
                                        <span class="text-xs bg-blue-100 text-blue-800 px-2 py-1 rounded">Program</span>
                                    @endif
                                    @if($point['include_in_calculation'])
                                        <span class="text-xs bg-green-100 text-green-800 px-2 py-1 rounded">Kalkulacja</span>
                                    @endif
                                    @if(!$point['active'])
                                        <span class="text-xs bg-red-100 text-red-800 px-2 py-1 rounded">Nieaktywny</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Kursy walut --}}
    @if(count($currencyRates) > 0)
        <div class="bg-white dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
            <h3 class="text-lg font-semibold mb-3">Kursy walut w momencie snapszotu</h3>
            <div class="grid grid-cols-3 gap-4">
                @foreach($currencyRates as $symbol => $rateData)
                    <div class="text-center p-2 border border-gray-200 dark:border-gray-700 rounded">
                        <p class="font-medium">{{ $symbol }}</p>
                        <p class="text-sm">{{ $rateData['rate'] ?? 'Brak' }}</p>
                        <p class="text-xs text-gray-600 dark:text-gray-400">{{ $rateData['name'] ?? '' }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
