<div class="pp-dnd-root">
    <div class="pp-dnd-list">
        @foreach($points as $p)
            <div class="pp-row" data-pp-id="{{ $p['id'] }}" data-parent-id="{{ $p['parent_id'] }}" data-day="{{ $p['day'] }}" data-order="{{ $p['order'] }}">
                <div class="flex items-center justify-between p-2 border rounded mb-1 bg-white">
                    <div class="flex items-center gap-4">
                        <div class="font-medium">{{ $p['name'] }}</div>
                        <div class="text-xs text-gray-500">(Dzień {{ $p['day'] }})</div>
                        <div>
                            <input type="number" min="1" step="1" value="{{ $p['day'] }}" class="w-20 text-sm rounded border-gray-200" wire:change="setDay({{ $p['id'] }}, $event.target.value)">
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <button type="button" wire:click.prevent="moveUp({{ $p['id'] }})" class="pp-move-up">↑</button>
                        <button type="button" wire:click.prevent="moveDown({{ $p['id'] }})" class="pp-move-down">↓</button>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="mt-2">
        <button type="button" class="filament-button-primary pp-dnd-save">Zapisz kolejność</button>
    </div>

    {{-- Load SortableJS from CDN for nicer drag & drop (graceful if already loaded) --}}
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
</div>
