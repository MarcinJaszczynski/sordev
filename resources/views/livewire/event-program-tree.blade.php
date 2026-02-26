<div class="grid grid-cols-1 md:grid-cols-{{ count($pointsByDay) }} gap-6" x-data x-init="
    $nextTick(() => {
        document.querySelectorAll('.kanban-day').forEach(col => {
            new Sortable(col, {
                group: 'program-points',
                animation: 150,
                handle: '.drag-handle',
                fallbackOnBody: true,
                swapThreshold: 0.65,
                onEnd: function (evt) {
                    const item = evt.item;
                    const newDay = parseInt(evt.to.dataset.day);
                    const pivotId = item.dataset.pivotId;
                    const newOrder = evt.newIndex;
                    let newParentPivotId = null;
                    const parent = item.closest('.kanban-children');
                    if (parent) {
                        const parentItem = parent.closest('.kanban-item');
                        if (parentItem) {
                            newParentPivotId = parentItem.getAttribute('data-pivot-id');
                        }
                    }
                    window.livewire.emit('movePoint', pivotId, newDay, newParentPivotId, newOrder);
                }
            });
        });
    });
">
    @foreach ($pointsByDay as $day => $points)
        <div class="kanban-column bg-gray-50 rounded-lg p-4">
            <div class="flex items-center justify-between mb-4">
                <h4 class="font-medium text-gray-900">Dzień {{ $day }}</h4>
            </div>
            <div class="kanban-day min-h-[200px] space-y-2" data-day="{{ $day }}">
                @foreach($points as $point)
                    @include('livewire.program-point-tree', ['point' => $point])
                @endforeach
            </div>
        </div>
    @endforeach
</div>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>