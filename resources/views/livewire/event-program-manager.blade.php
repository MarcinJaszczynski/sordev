<div>
    @foreach($pointsByDay as $day => $points)
        <h3 class="mt-6 text-lg font-semibold">Dzień {{ $day }}</h3>
        <ul class="list-none p-0 space-y-2" x-data @drop.prevent="$dispatch('dropevent', { day: {{ $day }}, order: Array.from($el.children).indexOf($event.target.closest('li')) + 1, id: $event.dataTransfer.getData('text') })" @dragover.prevent>
            @foreach($points as $point)
                <li class="p-2 border rounded" draggable="true"
                    @dragstart="$event.dataTransfer.setData('text', '{{ $point['id'] }}')">
                    <div class="flex justify-between">
                        <div>
                            <span class="font-medium">{{ $point['name'] }}</span>
                        </div>
                        <div>
                            <button class="text-blue-600 text-sm">Edycja</button>
                        </div>
                    </div>
                    @if(!empty($point['children']))
                        <ul class="ml-4 mt-2 space-y-1">
                            @foreach($point['children'] as $child)
                                <li class="p-2 border rounded bg-gray-50">
                                    {{ $child['name'] }}
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </li>
            @endforeach
        </ul>
    @endforeach

    <script>
        document.addEventListener('dropevent', function(e) {
            Livewire.emit('updateOrder', { id: e.detail.id, parent: null, day: e.detail.day, order: e.detail.order });
        });
    </script>
</div>
