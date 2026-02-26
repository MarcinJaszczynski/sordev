<div class="kanban-item bg-white border border-gray-200 rounded-lg p-3 mb-2 shadow-sm flex items-start justify-between group" data-pivot-id="{{ $point['pivot_id'] }}">
    <div class="flex-1">
        <div class="flex items-center">
            <span class="drag-handle mr-2 cursor-move text-gray-400 hover:text-gray-600"><i class="fa fa-bars"></i></span>
            <h4 class="font-medium text-gray-900 flex-1">{{ $point['name'] }}</h4>
        </div>
        @if(isset($point['children']) && is_array($point['children']) && count($point['children']) > 0)
            <div class="kanban-children mt-3 pl-4 border-l-2 border-gray-100" data-parent-id="{{ $point['pivot_id'] }}">
                @foreach($point['children'] as $child)
                    @include('livewire.program-point-tree', ['point' => $child])
                @endforeach
            </div>
        @endif
    </div>
    <div class="ml-2 flex flex-col gap-1 items-end">
        <div class="text-xs text-gray-400">#{{ $point['order'] }}</div>
        <label class="flex items-center space-x-1 text-xs">
            <input type="checkbox"
                @click.stop="window.livewire.emit('togglePivotProperty', {{ $point['pivot_id'] }}, 'include_in_program')"
                {{ $point['include_in_program'] ? 'checked' : '' }}>
            <span>Program</span>
        </label>
        <label class="flex items-center space-x-1 text-xs">
            <input type="checkbox"
                @click.stop="window.livewire.emit('togglePivotProperty', {{ $point['pivot_id'] }}, 'include_in_calculation')"
                {{ $point['include_in_calculation'] ? 'checked' : '' }}>
            <span>Kalkulacja</span>
        </label>
        <label class="flex items-center space-x-1 text-xs">
            <input type="checkbox"
                @click.stop="window.livewire.emit('togglePivotProperty', {{ $point['pivot_id'] }}, 'active')"
                {{ $point['active'] ? 'checked' : '' }}>
            <span>Aktywny</span>
        </label>
    </div>
</div>
