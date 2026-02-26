<div>
    <h2>Test Livewire Component</h2>
    <p>EventTemplate: {{ $eventTemplate->name }}</p>
    
    <div class="grid grid-cols-1 gap-6" id="program-days-container">
        @forelse ($programByDays as $dayNumber => $points)
            <div class="bg-white shadow rounded-lg p-4" data-day="{{ $dayNumber }}">
                <h3>Dzień {{ $dayNumber }}</h3>
                <ul class="program-day-list" data-day-id="{{ $dayNumber }}">
                    @forelse ($points as $point)
                        <li class="program-point-item bg-gray-100 p-2 m-1" data-pivot-id="{{ $point['pivot_id'] ?? $point['id'] }}">
                            {{ $point['name'] }}
                        </li>
                    @empty
                        <li>Brak punktów</li>
                    @endforelse
                </ul>
            </div>
        @empty
            <div>Brak dni programu</div>
        @endforelse
    </div>
</div>
