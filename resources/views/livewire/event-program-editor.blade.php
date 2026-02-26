<div class="space-y-6">
    <!-- Nagłówek -->
    <div class="flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold">Edycja programu imprezy</h2>
            <p class="text-gray-600 dark:text-gray-400">{{ $event->name }} - {{ $event->client_name }}</p>
        </div>
        <div class="flex gap-2">
            <x-filament::button wire:click="openAddModal(1)" color="primary" size="sm">
                Dodaj punkt programu
            </x-filament::button>
        </div>
    </div>

    <!-- Komunikaty -->
    @if (session()->has('message'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
            {{ session('message') }}
        </div>
    @endif

    @if (session()->has('error'))
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
            {{ session('error') }}
        </div>
    @endif

    <!-- Program według dni -->
    @if(count($programData) > 0)
        <div class="space-y-4">
            @foreach($programData as $dayData)
                <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 bg-white dark:bg-gray-900">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold">Dzień {{ $dayData['day'] }}</h3>
                        <x-filament::button wire:click="openAddModal({{ $dayData['day'] }})" color="primary" size="sm">
                            Dodaj do dnia {{ $dayData['day'] }}
                        </x-filament::button>
                    </div>

                    @if(count($dayData['points']) > 0)
                        <div class="space-y-2" wire:sortable="updateOrder" wire:sortable-group="{{ $dayData['day'] }}">
                            @foreach($dayData['points'] as $point)
                                <div class="border border-gray-200 dark:border-gray-600 rounded p-3 bg-gray-50 dark:bg-gray-800 
                                    @if(!$point['active']) opacity-50 @endif"
                                    wire:sortable.item="{{ $point['id'] }}" wire:key="point-{{ $point['id'] }}">
                                    
                                    <div class="flex justify-between items-start">
                                        <div class="flex-1">
                                            <!-- Uchwyt do przeciągania -->
                                            <div class="flex items-center gap-3">
                                                <div wire:sortable.handle class="cursor-move text-gray-400 hover:text-gray-600">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l4-4 4 4m0 6l-4 4-4-4"></path>
                                                    </svg>
                                                </div>
                                                <div class="flex-1">
                                                    <h4 class="font-medium">{{ $point['name'] }}</h4>
                                                    @if($point['description'])
                                                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">{{ $point['description'] }}</p>
                                                    @endif
                                                </div>
                                            </div>

                                            <!-- Szczegóły -->
                                            <div class="mt-2 grid grid-cols-2 md:grid-cols-4 gap-2 text-sm">
                                                <div>
                                                    <span class="text-gray-600 dark:text-gray-400">Kolejność:</span>
                                                    <span class="font-medium">{{ $point['order'] }}</span>
                                                </div>
                                                <div>
                                                    <span class="text-gray-600 dark:text-gray-400">Ilość:</span>
                                                    <span class="font-medium">{{ $point['quantity'] }}</span>
                                                </div>
                                                <div>
                                                    <span class="text-gray-600 dark:text-gray-400">Cena jedn.:</span>
                                                    <span class="font-medium">{{ number_format($point['unit_price'], 2) }} PLN</span>
                                                </div>
                                                <div>
                                                    <span class="text-gray-600 dark:text-gray-400">Cena całk.:</span>
                                                    <span class="font-bold">{{ number_format($point['total_price'], 2) }} PLN</span>
                                                </div>
                                            </div>

                                            <!-- Statusy -->
                                            <div class="flex gap-2 mt-2">
                                                @if($point['include_in_program'])
                                                    <span class="text-xs bg-blue-100 text-blue-800 px-2 py-1 rounded">Program</span>
                                                @endif
                                                @if($point['include_in_calculation'])
                                                    <span class="text-xs bg-green-100 text-green-800 px-2 py-1 rounded">Kalkulacja</span>
                                                @endif
                                                @if(!$point['active'])
                                                    <span class="text-xs bg-red-100 text-red-800 px-2 py-1 rounded">Nieaktywny</span>
                                                @endif
                                            </div>

                                            <!-- Uwagi -->
                                            @if($point['notes'])
                                                <div class="mt-2">
                                                    <span class="text-sm text-gray-600 dark:text-gray-400">Uwagi: </span>
                                                    <span class="text-sm italic">{{ $point['notes'] }}</span>
                                                </div>
                                            @endif
                                        </div>

                                        <!-- Akcje -->
                                        <div class="flex gap-1 ml-4">
                                            <x-filament::button wire:click="openEditModal({{ $point['id'] }})" color="gray" size="sm">
                                                Edytuj
                                            </x-filament::button>
                                            <x-filament::button wire:click="duplicatePoint({{ $point['id'] }})" color="success" size="sm">
                                                Duplikuj
                                            </x-filament::button>
                                            <x-filament::button 
                                                wire:click="deletePoint({{ $point['id'] }})" 
                                                color="danger" 
                                                size="sm"
                                                wire:confirm="Czy na pewno chcesz usunąć ten punkt programu?">
                                                Usuń
                                            </x-filament::button>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                            <p>Brak punktów programu dla tego dnia</p>
                            <x-filament::button wire:click="openAddModal({{ $dayData['day'] }})" color="primary" size="sm" class="mt-2">
                                Dodaj pierwszy punkt
                            </x-filament::button>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @else
        <div class="text-center py-12 bg-gray-50 dark:bg-gray-800 rounded-lg">
            <div class="text-gray-500 dark:text-gray-400 mb-4">
                <svg class="w-12 h-12 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                </svg>
                <p class="text-lg font-medium">Brak punktów programu</p>
                <p>Dodaj pierwszy punkt programu aby rozpocząć</p>
            </div>
            <x-filament::button wire:click="openAddModal(1)" color="primary">
                Dodaj punkt programu
            </x-filament::button>
        </div>
    @endif

    <!-- Modal dodawania/edycji punktu -->
    @if($showModal)
        <div class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50" wire:click="closeModal">
            <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white dark:bg-gray-900" wire:click.stop>
                <div class="mt-3">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
                        @if($editMode)
                            Edytuj punkt programu
                        @else
                            Dodaj punkt programu
                        @endif
                    </h3>

                    <form wire:submit.prevent="savePoint" class="space-y-4">
                        <!-- Wybór punktu programu -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Punkt programu</label>
                            <select wire:model="modalData.event_template_program_point_id" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-100" required>
                                <option value="">Wybierz punkt programu</option>
                                @foreach($availablePoints as $point)
                                    <option value="{{ $point->id }}">{{ $point->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <!-- Dzień -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Dzień</label>
                                <input type="number" wire:model="modalData.day" min="1" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-100" required>
                            </div>

                            <!-- Kolejność -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Kolejność</label>
                                <input type="number" wire:model="modalData.order" min="1" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-100" required>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <!-- Cena jednostkowa -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Cena jednostkowa (PLN)</label>
                                <input type="number" wire:model="modalData.unit_price" step="0.01" min="0" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-100" required>
                            </div>

                            <!-- Ilość -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Ilość</label>
                                <input type="number" wire:model="modalData.quantity" min="1" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-100" required>
                            </div>
                        </div>

                        <!-- Uwagi -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Uwagi</label>
                            <textarea wire:model="modalData.notes" rows="3" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-100"></textarea>
                        </div>

                        <!-- Checkboxy -->
                        <div class="grid grid-cols-3 gap-4">
                            <div class="flex items-center">
                                <input type="checkbox" wire:model="modalData.include_in_program" class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                <label class="ml-2 block text-sm text-gray-900 dark:text-gray-100">Uwzględnij w programie</label>
                            </div>

                            <div class="flex items-center">
                                <input type="checkbox" wire:model="modalData.include_in_calculation" class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                <label class="ml-2 block text-sm text-gray-900 dark:text-gray-100">Uwzględnij w kalkulacji</label>
                            </div>

                            <div class="flex items-center">
                                <input type="checkbox" wire:model="modalData.active" class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                <label class="ml-2 block text-sm text-gray-900 dark:text-gray-100">Aktywny</label>
                            </div>
                        </div>

                        <!-- Przyciski -->
                        <div class="flex gap-3 pt-4">
                            <x-filament::button type="submit" color="primary">
                                @if($editMode)
                                    Zaktualizuj
                                @else
                                    Dodaj
                                @endif
                            </x-filament::button>
                            <x-filament::button wire:click="closeModal" color="gray">
                                Anuluj
                            </x-filament::button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
