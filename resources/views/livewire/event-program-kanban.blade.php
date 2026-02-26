<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <h3 class="text-lg font-medium text-gray-900">Program wydarzenia</h3>
            <p class="text-sm text-gray-500">Zarządzaj punktami programu w strukturze podobnej do menu WordPress</p>
        </div>
        <div class="flex space-x-2">
            <button wire:click="addPoint(1)" class="px-4 py-2 bg-primary-600 text-white rounded-md hover:bg-primary-700">Dodaj punkt</button>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-{{ count($columns) }} gap-6">
        @foreach ($columns as $column)
            <div class="kanban-column bg-gray-50 rounded-lg p-4">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Ubezpieczenie dla dnia</label>
                    <select wire:model="dayInsurances.{{ $column['day'] }}" class="block w-full border-gray-300 rounded-md shadow-sm">
                        <option value="">-- Brak ubezpieczenia --</option>
                        @foreach($allInsurances as $insurance)
                            <option value="{{ $insurance->id }}">{{ $insurance->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-center justify-between mb-4">
                    <h4 class="font-medium text-gray-900">{{ $column['title'] }}</h4>
                    <button wire:click="addPoint({{ $column['day'] }})" class="text-sm bg-primary-100 text-primary-700 px-2 py-1 rounded hover:bg-primary-200">+ Dodaj</button>
                </div>
                <div class="kanban-day space-y-2 min-h-[200px]" data-day="{{ $column['day'] }}">
                    @if($column['points']->isNotEmpty())
                        @include('livewire.kanban-program-point-tree', ['points' => $column['points'], 'level' => 0])
                    @else
                        <div class="text-gray-400 text-sm text-center py-8">Brak punktów programu</div>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    {{-- Modal --}}
    <div x-data="{ show: @entangle('showModal') }" x-show="show" x-cloak class="fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div x-show="show" class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div x-show="show" class="inline-block align-bottom bg-white rounded-lg px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full sm:p-6">
                <div>
                    <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                        {{ $editMode ? 'Edytuj punkt programu' : 'Dodaj punkt programu' }}
                    </h3>
                    <div class="mt-4 space-y-4">
                        <div>
                            <label for="program_point_id" class="block text-sm font-medium text-gray-700">Punkt programu</label>
                            <select wire:model="modalData.program_point_id" id="program_point_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                                <option value="">Wybierz punkt programu</option>
                                @foreach ($programPoints as $point)
                                    <option value="{{ $point->id }}">{{ $point->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="day" class="block text-sm font-medium text-gray-700">Dzień</label>
                            <input type="number" wire:model="modalData.day" id="day" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                        </div>
                        <div>
                            <label for="order" class="block text-sm font-medium text-gray-700">Kolejność</label>
                            <input type="number" wire:model="modalData.order" id="order" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                        </div>
                        <div>
                            <label for="notes" class="block text-sm font-medium text-gray-700">Uwagi</label>
                            <textarea wire:model="modalData.notes" id="notes" rows="3" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"></textarea>
                        </div>
                        <div class="flex items-center">
                            <input type="checkbox" wire:model="modalData.include_in_program" id="include_in_program" class="h-4 w-4 text-primary-600 border-gray-300 rounded">
                            <label for="include_in_program" class="ml-2 block text-sm text-gray-900">Uwzględnij w programie</label>
                        </div>
                        <div class="flex items-center">
                            <input type="checkbox" wire:model="modalData.include_in_calculation" id="include_in_calculation" class="h-4 w-4 text-primary-600 border-gray-300 rounded">
                            <label for="include_in_calculation" class="ml-2 block text-sm text-gray-900">Uwzględnij w kalkulacji</label>
                        </div>
                        <div class="flex items-center">
                            <input type="checkbox" wire:model="modalData.active" id="active" class="h-4 w-4 text-primary-600 border-gray-300 rounded">
                            <label for="active" class="ml-2 block text-sm text-gray-900">Aktywny</label>
                        </div>
                    </div>
                </div>
                <div class="mt-5 sm:mt-6 sm:grid sm:grid-cols-2 sm:gap-3 sm:grid-flow-row-dense">
                    <button wire:click="savePoint" type="button" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-primary-600 text-base font-medium text-white hover:bg-primary-700 sm:col-start-2 sm:text-sm">Zapisz</button>
                    <button wire:click="$set('showModal', false)" type="button" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 sm:mt-0 sm:col-start-1 sm:text-sm">Anuluj</button>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    <script>
        document.addEventListener('livewire:initialized', function () {
            const sortableInstances = [];
            function initSortable() {
                sortableInstances.forEach(instance => { if (instance && typeof instance.destroy === 'function') instance.destroy(); });
                sortableInstances.length = 0;
                document.querySelectorAll('.kanban-day').forEach(column => {
                    const instance = new Sortable(column, {
                        group: 'program-points',
                        animation: 150,
                        handle: '.drag-handle',
                        onEnd: function (evt) {
                            const item = evt.item;
                            const newColumn = evt.to;
                            const pivotId = item.dataset.pivotId;
                            const newDay = parseInt(newColumn.dataset.day);
                            let newParentPivotId = null;
                            // Sprawdź, czy element jest zagnieżdżony
                            const parent = item.closest('.kanban-children');
                            if (parent) {
                                const parentItem = parent.closest('.kanban-item');
                                if (parentItem) {
                                    newParentPivotId = parentItem.getAttribute('data-pivot-id');
                                }
                            }
                            // Wywołanie metody Livewire
                            if (pivotId && newDay) {
                                @this.movePoint(pivotId, newDay, newParentPivotId, evt.newIndex);
                            }
                        }
                    });
                    sortableInstances.push(instance);
                });
            }
            initSortable();
            window.Livewire.on('refresh', function () { setTimeout(initSortable, 500); });
        });
    </script>
    @endpush
</div>