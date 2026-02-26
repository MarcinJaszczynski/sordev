<div>
    <h2 class="text-xl font-bold mb-4">Program wydarzenia: {{ $eventTemplate->name }}</h2>
    
    <div class="grid grid-cols-1 gap-6" id="program-days-container">
        @forelse ($programByDays as $dayNumber => $points)
            <div class="bg-white shadow rounded-lg p-4" data-day="{{ $dayNumber }}">
                <h3 class="text-lg font-semibold mb-2">Dzień {{ $dayNumber }}</h3>
                <ul class="program-day-list space-y-2" data-day-id="{{ $dayNumber }}">
                    @forelse ($points as $point)
                        <li class="program-point-item bg-gray-100 p-2 rounded" data-pivot-id="{{ $point['pivot_id'] ?? $point['id'] }}">
                            <div class="font-semibold">{{ $point['name'] }}</div>
                            <div class="text-sm text-gray-600">{{ $point['description'] }}</div>
                        </li>
                    @empty
                        <li class="text-gray-400">Brak punktów programu na ten dzień</li>
                    @endforelse
                </ul>
            </div>
        @empty
            <div class="text-center py-8">
                <p class="text-gray-500">Brak dni programu do wyświetlenia</p>
            </div>
        @endforelse
    </div>
</div>
