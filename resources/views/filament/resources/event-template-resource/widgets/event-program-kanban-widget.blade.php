<x-filament-widgets::widget>
<div class="grid grid-cols-1 md:grid-cols-{{ count($columns) }} gap-6" x-data x-init="
    $nextTick(() => {
        document.querySelectorAll('.kanban-day').forEach(col => {
            new Sortable(col, {
                group: 'program-points',
                animation: 150,
                handle: '.drag-handle',
                onEnd: function (evt) {
                    const item = evt.item;
                    const newDay = parseInt(evt.to.dataset.day);
                    const pivotId = item.dataset.pivotId;
                    const newOrder = evt.newIndex;
                    window.livewire.emitTo('event-program-kanban', 'movePoint', pivotId, newDay, newOrder);
                }
            });
        });
    });
">
    @foreach ($columns as $column)
        <div class="kanban-column bg-gray-50 rounded-lg p-4">
            <div class="flex items-center justify-between mb-4">
                <h4 class="font-medium text-gray-900">{{ $column['title'] }}</h4>
            </div>
            <div class="kanban-day min-h-[200px] space-y-2" data-day="{{ $column['day'] }}">
                @foreach($column['points'] as $point)
                    <div class="kanban-item bg-white border border-gray-200 rounded-lg p-3 flex items-center justify-between" data-pivot-id="{{ $point->pivot->id }}">
                        <span class="drag-handle cursor-move mr-2"><i class="fa fa-bars"></i></span>
                        <span>{{ $point->name }}</span>
                        <span class="text-xs text-gray-400">#{{ $point->pivot->order }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach
    </div>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
</x-filament-widgets::widget>
