
<x-filament-widgets::widget>
    <div class="kanban-container">
        <!-- Header z tytułem i narzędziami -->
        <div class="mb-4 flex justify-between items-center">
            <h2 class="text-xl font-bold text-gray-800">Program Wydarzeń - Kanban</h2>
            <div class="text-sm text-gray-600">
                Przeciągnij i upuść punkty programu, aby zorganizować je w strukturę drzewa podobną do menu WordPress
            </div>
        </div>

        <style>
            .kanban-container {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            }
            
            .kanban-item {
                position: relative;
                transition: all 0.2s ease;
                border-left: 3px solid transparent;
                cursor: move;
            }
            
            .kanban-item:hover {
                background-color: #f8fafc;
                border-left-color: #3b82f6;
                transform: translateX(2px);
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            }
            
            .kanban-item.sortable-ghost {
                opacity: 0.4;
                background-color: #dbeafe;
                border: 2px dashed #3b82f6;
                transform: rotate(2deg);
            }
            
            .kanban-item.sortable-chosen {
                background-color: #eff6ff;
                border-left-color: #1d4ed8;
            }
            
            .kanban-dropzone {
                min-height: 300px;
                transition: background-color 0.2s;
                border-radius: 8px;
            }
            
            .kanban-dropzone.sortable-over {
                background-color: #f0f9ff;
                border: 2px dashed #0ea5e9;
            }
            
            .kanban-children {
                margin-left: 20px;
                border-left: 1px solid #e5e7eb;
                padding-left: 10px;
            }
            
            .kanban-day-header {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                border-radius: 8px 8px 0 0;
            }
            
            .toggle-children {
                transition: transform 0.2s;
            }
            
            .toggle-children.expanded {
                transform: rotate(90deg);
            }
            
            .program-point-actions {
                opacity: 0;
                transition: opacity 0.2s;
            }
            
            .kanban-item:hover .program-point-actions {
                opacity: 1;
            }
        </style>

        <div class="grid grid-cols-{{ count($columns) }} gap-6">
    @foreach($columns as $day => $column)
        <div class="bg-white rounded-lg shadow-sm border" wire:key="kanban-day-{{ $day }}">
            <!-- Header dnia -->
            <div class="kanban-day-header p-4">
                <div class="flex items-center justify-between">
                    <h3 class="font-semibold text-lg">{{ $column['title'] }}</h3>
                    <button 
                        class="px-3 py-1 text-sm bg-white bg-opacity-20 text-white rounded-full hover:bg-opacity-30 transition-all" 
                        wire:click="addPoint({{ $day }})">
                        <span class="mr-1">+</span> Dodaj punkt
                    </button>
                </div>
            </div>
            
            <!-- Dropzone dla punktów programu -->
            <div class="kanban-dropzone p-4" data-day="{{ $day }}">
                @if($column['points']->isEmpty())
                    <div class="text-center text-gray-500 py-8">
                        <div class="mb-2">
                            <svg class="w-12 h-12 mx-auto text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                            </svg>
                        </div>
                        <p>Brak punktów programu</p>
                        <p class="text-xs mt-1">Przeciągnij punkty tutaj lub kliknij "Dodaj punkt"</p>
                    </div>
                @else
                    @foreach($column['points'] as $point)
                        @include('filament.resources.event-template-resource.widgets.kanban-program-point-tree', ['point' => $point, 'level' => 0])
                    @endforeach
                @endif
            </div>
        </div>
        </div>

        <div class="fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center z-50">
    <div class="bg-white rounded shadow-lg p-6 w-full max-w-md relative">
        <h3 class="text-lg font-bold mb-4">{{ $editMode ? 'Edytuj punkt programu' : 'Dodaj punkt programu' }}</h3>
        <form wire:submit.prevent="savePoint">
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700">Punkt programu</label>
                @if(isset($allProgramPoints) && $allProgramPoints->isNotEmpty())
                    <select wire:model="modalData.program_point_id" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-primary-500 focus:border-primary-500 sm:text-sm rounded-md">
                        <option value="">-- Wybierz punkt programu --</option>
                        @foreach($allProgramPoints as $programPoint)
                            <option value="{{ $programPoint->id }}">{{ $programPoint->name }}</option>
                        @endforeach
                    </select>
                @else
                    <div class="text-orange-500 mt-1">
                        Brak dostępnych punktów programu. Dodaj punkty w sekcji "Punkty programu".
                    </div>
                @endif
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700">Dzień</label>
                <input type="number" wire:model="modalData.day" min="1" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500 sm:text-sm">
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700">Kolejność</label>
                <input type="number" wire:model="modalData.order" min="0" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500 sm:text-sm">
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700">Uwagi</label>
                <textarea wire:model="modalData.notes" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500 sm:text-sm"></textarea>
            </div>
            <div class="mb-4">
                <label class="flex items-center">
                    <input type="checkbox" wire:model="modalData.include_in_program" class="rounded border-gray-300 text-primary-600 shadow-sm focus:border-primary-300 focus:ring focus:ring-primary-200 focus:ring-opacity-50">
                    <span class="ml-2 text-sm text-gray-700">Uwzględnij w programie</span>
                </label>
            </div>
            <div class="mb-4">
                <label class="flex items-center">
                    <input type="checkbox" wire:model="modalData.include_in_calculation" class="rounded border-gray-300 text-primary-600 shadow-sm focus:border-primary-300 focus:ring focus:ring-primary-200 focus:ring-opacity-50">
                    <span class="ml-2 text-sm text-gray-700">Uwzględnij w kalkulacji</span>
                </label>
            </div>
            <div class="mb-4">
                <label class="flex items-center">
                    <input type="checkbox" wire:model="modalData.active" class="rounded border-gray-300 text-primary-600 shadow-sm focus:border-primary-300 focus:ring focus:ring-primary-200 focus:ring-opacity-50">
                    <span class="ml-2 text-sm text-gray-700">Aktywny</span>
                </label>
            </div>
            <div class="flex justify-between mt-4">
                <button type="button" wire:click="$set('showModal', false)" class="px-4 py-2 bg-gray-200 rounded">Anuluj</button>
                <button type="submit" class="px-4 py-2 bg-primary-600 text-white rounded">Zapisz</button>
            </div>
        </form>
        </div>
        @endif

        <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js"></script>
        <script>
document.addEventListener('DOMContentLoaded', function () {
    let sortableInstances = [];

    function initializeKanban() {
        // Wyczyść poprzednie instancje
        sortableInstances.forEach(instance => instance.destroy());
        sortableInstances = [];

        // Inicjalizuj sortowanie dla każdej kolumny (dnia)
        document.querySelectorAll('.kanban-dropzone').forEach(function(dropzone) {
            const sortable = new Sortable(dropzone, {
                group: 'kanban-shared',
                animation: 200,
                ghostClass: 'sortable-ghost',
                chosenClass: 'sortable-chosen',
                dragClass: 'sortable-drag',
                draggable: '.kanban-item',
                fallbackOnBody: true,
                swapThreshold: 0.65,
                
                onStart: function(evt) {
                    // Dodaj klasę do wszystkich dropzon podczas przeciągania
                    document.querySelectorAll('.kanban-dropzone').forEach(dz => {
                        dz.classList.add('sortable-over');
                    });
                },
                
                onEnd: function(evt) {
                    // Usuń klasy po zakończeniu przeciągania
                    document.querySelectorAll('.kanban-dropzone').forEach(dz => {
                        dz.classList.remove('sortable-over');
                    });
                    
                    // Pobierz informacje o przenoszeniu
                    const pivotId = evt.item.getAttribute('data-pivot-id');
                    const pointId = evt.item.getAttribute('data-point-id');
                    const newDay = evt.to.getAttribute('data-day');
                    const newOrder = evt.newIndex;
                    
                    // Znajdź parent ID (jeśli element został przeniesiony do zagnieżdżonej struktury)
                    let newParentPivotId = null;
                    const parentChildren = evt.to.closest('.kanban-children');
                    if (parentChildren) {
                        const parentItem = parentChildren.closest('.kanban-item');
                        if (parentItem) {
                            newParentPivotId = parentItem.getAttribute('data-pivot-id');
                        }
                    }
                    
                    // Wywołaj metodę Livewire
                    if (pivotId && newDay) {
                        @this.movePoint(pivotId, parseInt(newDay), newParentPivotId, newOrder)
                            .then(() => {
                                console.log('Punkt przeniesiony pomyślnie');
                                // Wymuś odświeżenie komponentu po zmianie
                                @this.$refresh();
                            })
                            .catch(error => {
                                console.error('Błąd podczas przenoszenia punktu:', error);
                                // Przywróć poprzednią pozycję w przypadku błędu
                                if (evt.from !== evt.to) {
                                    evt.from.insertBefore(evt.item, evt.from.children[evt.oldIndex] || null);
                                }
                            });
                    }
                }
            });
            
            sortableInstances.push(sortable);
        });
        
        // Inicjalizuj sortowanie dla zagnieżdżonych kontenerów (dzieci)
        document.querySelectorAll('.kanban-children').forEach(function(childrenContainer) {
            const sortable = new Sortable(childrenContainer, {
                group: 'kanban-shared',
                animation: 200,
                ghostClass: 'sortable-ghost',
                chosenClass: 'sortable-chosen',
                draggable: '.kanban-item',
                fallbackOnBody: true,
                swapThreshold: 0.65,
                
                onEnd: function(evt) {
                    // Znajdź dzień nadrzędny
                    const dayDropzone = evt.to.closest('.kanban-dropzone');
                    const newDay = dayDropzone ? dayDropzone.getAttribute('data-day') : null;
                    
                    // Pobierz strukturę całego drzewa dla tego dnia
                    if (newDay) {
                        const treeData = buildTreeStructure(dayDropzone);
                        
                        // Wywołaj metodę zapisywania całej struktury
                        @this.saveTreeStructure({[newDay]: {points: treeData}})
                            .then(() => {
                                console.log('Struktura drzewa zapisana pomyślnie');
                            })
                            .catch(error => {
                                console.error('Błąd podczas zapisywania struktury:', error);
                            });
                    }
                }
            });
            
            sortableInstances.push(sortable);
        });
    }
    
    // Funkcja do budowania struktury drzewa z DOM
    function buildTreeStructure(container) {
        const items = [];
        
        // Pobierz tylko bezpośrednie dzieci (nie zagnieżdżone)
        const directChildren = Array.from(container.children).filter(child => 
            child.classList.contains('kanban-item')
        );
        
        directChildren.forEach((item, index) => {
            const pivotId = item.getAttribute('data-pivot-id');
            const pointId = item.getAttribute('data-point-id');
            const childrenContainer = item.querySelector(':scope > .kanban-children');
            
            const itemData = {
                pivot_id: pivotId,
                point_id: pointId,
                order: index + 1,
                children: childrenContainer ? buildTreeStructure(childrenContainer) : []
            };
            
            items.push(itemData);
        });
        
        return items;
    }
    
    // Obsługa przełączania rozwijania/zwijania dzieci
    document.addEventListener('click', function(e) {
        if (e.target.closest('.toggle-children')) {
            e.preventDefault();
            const button = e.target.closest('.toggle-children');
            const pointId = button.getAttribute('data-point-id');
            const childrenContainer = button.closest('.kanban-item').querySelector('.kanban-children');
            
            if (childrenContainer) {
                if (childrenContainer.style.display === 'none') {
                    childrenContainer.style.display = '';
                    button.classList.add('expanded');
                } else {
                    childrenContainer.style.display = 'none';
                    button.classList.remove('expanded');
                }
            }
        }
    });
    
    // Inicjalizuj Kanban
    initializeKanban();
    
    // Ponownie inicjalizuj po aktualizacji Livewire
    document.addEventListener('livewire:navigated', initializeKanban);
    document.addEventListener('livewire:update', initializeKanban);
        </script>
    </div>
</x-filament-widgets::widget>
