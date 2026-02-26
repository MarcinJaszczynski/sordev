@php
    $eventChanges = $comparison['event_changes'] ?? [];
    $programChanges = $comparison['program_changes'] ?? [];
    $costChanges = $comparison['cost_changes'] ?? [];
@endphp

<div class="space-y-6">
    {{-- Nagłówek porównania --}}
    <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
        <h3 class="text-lg font-semibold mb-2">Porównanie snapshotów</h3>
        <div class="grid grid-cols-2 gap-4 text-sm">
            <div>
                <span class="font-medium text-gray-600 dark:text-gray-400">Snapshot:</span>
                <p class="font-medium">{{ $snapshot->name }}</p>
                <p class="text-gray-600 dark:text-gray-400">{{ $snapshot->snapshot_date->format('d.m.Y H:i:s') }}</p>
            </div>
            <div>
                <span class="font-medium text-gray-600 dark:text-gray-400">Obecny stan:</span>
                <p class="font-medium">{{ now()->format('d.m.Y H:i:s') }}</p>
            </div>
        </div>
    </div>

    {{-- Podsumowanie zmian --}}
    <div class="bg-white dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
        <h3 class="text-lg font-semibold mb-3">Podsumowanie zmian</h3>
        <div class="grid grid-cols-3 gap-4">
            <div class="text-center p-3 border border-gray-200 dark:border-gray-700 rounded">
                <p class="text-sm text-gray-600 dark:text-gray-400">Zmienione dane</p>
                <p class="text-2xl font-bold {{ count($eventChanges) > 0 ? 'text-orange-600 dark:text-orange-400' : 'text-green-600 dark:text-green-400' }}">
                    {{ count($eventChanges) }}
                </p>
            </div>
            <div class="text-center p-3 border border-gray-200 dark:border-gray-700 rounded">
                <p class="text-sm text-gray-600 dark:text-gray-400">Zmienione punkty</p>
                <p class="text-2xl font-bold {{ count($programChanges['modified'] ?? []) > 0 ? 'text-orange-600 dark:text-orange-400' : 'text-green-600 dark:text-green-400' }}">
                    {{ count($programChanges['modified'] ?? []) }}
                </p>
            </div>
            <div class="text-center p-3 border border-gray-200 dark:border-gray-700 rounded">
                <p class="text-sm text-gray-600 dark:text-gray-400">Różnica kosztów</p>
                <p class="text-2xl font-bold {{ abs($costChanges['difference'] ?? 0) > 0.01 ? 'text-orange-600 dark:text-orange-400' : 'text-green-600 dark:text-green-400' }}">
                    {{ ($costChanges['difference'] ?? 0) >= 0 ? '+' : '' }}{{ number_format($costChanges['difference'] ?? 0, 2) }} PLN
                </p>
            </div>
        </div>
    </div>

    {{-- Zmiany w danych imprezy --}}
    @if(count($eventChanges) > 0)
        <div class="bg-white dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
            <h3 class="text-lg font-semibold mb-3">Zmiany w danych imprezy</h3>
            <div class="space-y-3">
                @foreach($eventChanges as $field => $change)
                    <div class="border border-orange-200 dark:border-orange-700 bg-orange-50 dark:bg-orange-900/20 rounded p-3">
                        <h4 class="font-medium capitalize">{{ str_replace('_', ' ', $field) }}</h4>
                        <div class="grid grid-cols-2 gap-4 mt-2 text-sm">
                            <div>
                                <span class="text-red-600 dark:text-red-400 font-medium">Poprzednia wartość:</span>
                                <p class="text-gray-700 dark:text-gray-300">{{ $change['old'] ?? 'Brak' }}</p>
                            </div>
                            <div>
                                <span class="text-green-600 dark:text-green-400 font-medium">Obecna wartość:</span>
                                <p class="text-gray-700 dark:text-gray-300">{{ $change['new'] ?? 'Brak' }}</p>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Zmiany kosztów --}}
    @if(abs($costChanges['difference'] ?? 0) > 0.01)
        <div class="bg-white dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
            <h3 class="text-lg font-semibold mb-3">Zmiany kosztów</h3>
            <div class="grid grid-cols-3 gap-4">
                <div class="text-center p-3 bg-red-50 dark:bg-red-900/20 rounded">
                    <p class="text-sm text-gray-600 dark:text-gray-400">Koszt ze snapshotu</p>
                    <p class="text-xl font-bold text-red-600 dark:text-red-400">{{ number_format($costChanges['old_total'] ?? 0, 2) }} PLN</p>
                </div>
                <div class="text-center p-3 bg-green-50 dark:bg-green-900/20 rounded">
                    <p class="text-sm text-gray-600 dark:text-gray-400">Obecny koszt</p>
                    <p class="text-xl font-bold text-green-600 dark:text-green-400">{{ number_format($costChanges['new_total'] ?? 0, 2) }} PLN</p>
                </div>
                <div class="text-center p-3 bg-blue-50 dark:bg-blue-900/20 rounded">
                    <p class="text-sm text-gray-600 dark:text-gray-400">Różnica</p>
                    <p class="text-xl font-bold {{ ($costChanges['difference'] ?? 0) >= 0 ? 'text-blue-600 dark:text-blue-400' : 'text-orange-600 dark:text-orange-400' }}">
                        {{ ($costChanges['difference'] ?? 0) >= 0 ? '+' : '' }}{{ number_format($costChanges['difference'] ?? 0, 2) }} PLN
                    </p>
                </div>
            </div>
        </div>
    @endif

    {{-- Zmiany w punktach programu --}}
    @if(count($programChanges['added'] ?? []) > 0 || count($programChanges['removed'] ?? []) > 0 || count($programChanges['modified'] ?? []) > 0)
        <div class="bg-white dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
            <h3 class="text-lg font-semibold mb-3">Zmiany w punktach programu</h3>

            {{-- Dodane punkty --}}
            @if(count($programChanges['added'] ?? []) > 0)
                <div class="mb-4">
                    <h4 class="font-medium text-green-600 dark:text-green-400 mb-2">
                        Dodane punkty ({{ count($programChanges['added']) }})
                    </h4>
                    <div class="space-y-2">
                        @foreach($programChanges['added'] as $point)
                            <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-700 rounded p-3">
                                <div class="flex justify-between">
                                    <span class="font-medium">{{ $point['template_point_name'] ?? 'Brak nazwy' }}</span>
                                    <span class="font-bold">{{ number_format($point['total_price'] ?? 0, 2) }} PLN</span>
                                </div>
                                <p class="text-sm text-gray-600 dark:text-gray-400">Dzień {{ $point['day'] ?? 'Brak' }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Usunięte punkty --}}
            @if(count($programChanges['removed'] ?? []) > 0)
                <div class="mb-4">
                    <h4 class="font-medium text-red-600 dark:text-red-400 mb-2">
                        Usunięte punkty ({{ count($programChanges['removed']) }})
                    </h4>
                    <div class="space-y-2">
                        @foreach($programChanges['removed'] as $point)
                            <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-700 rounded p-3">
                                <div class="flex justify-between">
                                    <span class="font-medium">{{ $point['template_point_name'] ?? 'Brak nazwy' }}</span>
                                    <span class="font-bold">{{ number_format($point['total_price'] ?? 0, 2) }} PLN</span>
                                </div>
                                <p class="text-sm text-gray-600 dark:text-gray-400">Dzień {{ $point['day'] ?? 'Brak' }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Zmodyfikowane punkty --}}
            @if(count($programChanges['modified'] ?? []) > 0)
                <div>
                    <h4 class="font-medium text-orange-600 dark:text-orange-400 mb-2">
                        Zmodyfikowane punkty ({{ count($programChanges['modified']) }})
                    </h4>
                    <div class="space-y-3">
                        @foreach($programChanges['modified'] as $point)
                            <div class="bg-orange-50 dark:bg-orange-900/20 border border-orange-200 dark:border-orange-700 rounded p-3">
                                <h5 class="font-medium mb-2">{{ $point['point_name'] ?? 'Brak nazwy' }}</h5>
                                @foreach($point['changes'] as $field => $change)
                                    <div class="grid grid-cols-2 gap-4 mb-2 text-sm">
                                        <div>
                                            <span class="text-red-600 dark:text-red-400 font-medium">{{ ucfirst($field) }} (poprzedni):</span>
                                            <p>{{ is_numeric($change['old']) ? number_format($change['old'], 2) . ' PLN' : $change['old'] }}</p>
                                        </div>
                                        <div>
                                            <span class="text-green-600 dark:text-green-400 font-medium">{{ ucfirst($field) }} (obecny):</span>
                                            <p>{{ is_numeric($change['new']) ? number_format($change['new'], 2) . ' PLN' : $change['new'] }}</p>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    @endif

    {{-- Brak zmian --}}
    @if(count($eventChanges) === 0 && count($programChanges['added'] ?? []) === 0 && count($programChanges['removed'] ?? []) === 0 && count($programChanges['modified'] ?? []) === 0 && abs($costChanges['difference'] ?? 0) <= 0.01)
        <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-700 rounded-lg p-6 text-center">
            <div class="text-green-600 dark:text-green-400 mb-2">
                <svg class="w-12 h-12 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
            </div>
            <h3 class="text-lg font-semibold text-green-800 dark:text-green-200 mb-1">Brak zmian</h3>
            <p class="text-green-600 dark:text-green-400">Impreza jest w takim samym stanie jak w snapszorze</p>
        </div>
    @endif
</div>
