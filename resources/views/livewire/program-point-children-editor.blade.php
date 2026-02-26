@php
    use Illuminate\Support\Facades\Storage;
@endphp

<div wire:init="loadChildren">
    <!-- Komunikaty -->
    <div id="children-notifications" class="fixed top-4 right-4 z-50">
        <!-- Tu będą wyświetlane notyfikacje -->
    </div>

    <div class="bg-white shadow rounded-lg p-4 fi-section-content">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold text-gray-700">Podpunkty programu: {{ $programPoint->name }}</h3>
            <button 
                wire:click="showAddModal"
                class="px-4 py-2 bg-primary-600 text-white rounded hover:bg-primary-700 flex items-center">
                <x-heroicon-o-plus-circle class="w-5 h-5 mr-2" />
                Dodaj podpunkt
            </button>
        </div>

        <ul class="children-list space-y-2 min-h-[100px] border border-dashed border-gray-300 p-2 rounded-md" id="children-container">
            @forelse ($children as $child)
                <li class="bg-gray-100 p-3 rounded-md shadow-sm border border-gray-100 flex items-start" 
                    data-child-id="{{ $child['id'] }}">

                    <!-- Col 0: Drag Handle -->
                    <div class="w-12 pr-2 py-1 cursor-grab drag-handle self-center flex-shrink-0">
                        <x-heroicon-o-bars-3 class="w-5 h-5 text-gray-400" />
                    </div>

                    <!-- Col 1: Name, Duration, Office Notes -->
                    <div class="w-72 px-2 py-1 border-r border-gray-200 flex-shrink-0">
                        <div class="font-medium text-gray-700">{{ $child['name'] }}</div>
                        @if(isset($child['duration_hours']) || isset($child['duration_minutes']))
                            <div class="text-xs text-gray-600 mt-1">
                                Czas trwania: {{ sprintf('%02d:%02d', $child['duration_hours'] ?? 0, $child['duration_minutes'] ?? 0) }}
                            </div>
                        @endif
                        @if(!empty($child['office_notes']))
                            <div class="text-xs text-blue-600 italic mt-1">
                                Uwagi dla biura: {!! $child['office_notes'] !!}
                            </div>
                        @endif
                    </div>

                    <!-- Col 2: Description -->
                    <div class="w-72 px-2 py-1 border-r border-gray-200 flex-shrink-0">
                        @if(!empty($child['description']))
                            <div class="text-xs text-gray-600 line-clamp-3">
                                {!! Str::limit(strip_tags($child['description'], '<p><br><strong><em><b><i>'), 150) !!}
                            </div>
                        @else
                            <p class="text-xs text-gray-400 italic">Brak opisu.</p>
                        @endif
                    </div>

                    <!-- Col 3: Featured Image, Gallery -->
                    <div class="w-48 px-2 py-1 border-r border-gray-200 flex-shrink-0">
                        @if(!empty($child['featured_image']))
                            <div class="mb-2">
                                <img src="{{ Storage::url($child['featured_image']) }}" alt="Miniaturka" class="h-12 w-12 object-cover rounded">
                            </div>
                        @endif
                        @if(!empty($child['gallery_images']) && is_array($child['gallery_images']))
                            <div class="flex flex-wrap gap-1">
                                @foreach(array_slice($child['gallery_images'], 0, 4) as $image)
                                    <img src="{{ Storage::url($image) }}" alt="Miniaturka galerii" class="h-8 w-8 object-cover rounded">
                                @endforeach
                                @if(count($child['gallery_images']) > 4)
                                    <span class="text-xs text-gray-500 self-center bg-gray-200 px-1 rounded">+{{ count($child['gallery_images']) - 4 }}</span>
                                @endif
                            </div>
                        @endif
                    </div>

                    <!-- Col 4: Tags -->
                    <div class="w-48 px-2 py-1 flex-shrink-0">
                        @if(!empty($child['tags']) && is_array($child['tags']))
                            <div class="flex flex-wrap gap-1">
                                @foreach($child['tags'] as $tag)
                                    <span class="inline-block bg-gray-200 rounded-full px-2 py-0.5 text-xs font-semibold text-gray-700">{{ $tag['name'] }}</span>
                                @endforeach
                            </div>
                        @else
                            <p class="text-xs text-gray-400 italic">Brak tagów.</p>
                        @endif
                    </div>

                    <!-- Col 5: Actions -->
                    <div class="w-20 pl-2 py-1 flex items-center space-x-2 self-center flex-shrink-0">
                        <button 
                            wire:click="deleteChild({{ $child['id'] }})" 
                            wire:confirm="Czy na pewno chcesz usunąć ten podpunkt?" 
                            class="text-gray-400 hover:text-danger-600 p-1"
                            title="Usuń">
                            <x-heroicon-o-trash class="w-5 h-5" />
                        </button>
                    </div>
                </li>
            @empty
                <li class="text-center text-gray-400 py-4 italic">Brak podpunktów.</li>
            @endforelse
        </ul>
    </div>    @push('styles')
    <style>
        .line-clamp-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .line-clamp-3 {
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        /* Dodatkowe style dla lepszego wyglądu */
        .fi-modal-backdrop {
            backdrop-filter: blur(1px);
        }
        
        .fi-ta-record:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        .fi-ta-record {
            transition: all 0.2s ease-in-out;
        }
        
        /* Poprawki dla selectów */
        .fi-select[multiple] {
            min-height: 2.5rem;
        }
        
        /* Responsywność */
        @media (max-width: 768px) {
            .fi-modal-window {
                margin: 1rem;
                max-width: calc(100vw - 2rem);
            }
            
            .fi-ta-record-content {
                grid-template-columns: 1fr;
                gap: 2rem;
            }
        }
    </style>
    @endpush

    <!-- Modal dodawania podpunktu - Filament 3 Style -->
    @if($showModal)
        <div class="fi-modal-backdrop fi-modal-backdrop-open fi-overlay fi-overlay-open fixed inset-0 z-40" 
             style="background: rgba(0, 0, 0, 0.5)"></div>
        
        <div class="fi-modal fi-modal-open fi-modal-slide-over-panel fixed inset-0 z-50 flex items-center justify-center overflow-y-auto p-4">
            <div class="fi-modal-window bg-white shadow-2xl rounded-xl w-full max-w-7xl mx-auto max-h-screen overflow-hidden flex flex-col">
                <!-- Header -->
                <div class="fi-modal-header px-6 py-4 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <h2 class="fi-modal-heading text-lg font-semibold text-gray-900">
                            Dodaj podpunkt do: {{ $programPoint->name }}
                        </h2>
                        <button wire:click="closeModal" type="button" 
                                class="fi-icon-btn-base text-gray-400 hover:text-gray-600 p-2 rounded-lg hover:bg-gray-100">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Content -->
                <div class="fi-modal-content flex-1 overflow-hidden flex flex-col">                    <!-- Sekcja filtrów -->
                    <div class="fi-section fi-section-compact bg-gray-50 px-6 py-4">
                        <div class="fi-section-header mb-4">
                            <h3 class="fi-section-header-heading text-sm font-medium text-gray-700">
                                Filtry wyszukiwania
                            </h3>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                            <!-- Wyszukiwanie po nazwie -->
                            <div class="fi-fo-field-wrp">
                                <label class="fi-fo-field-wrp-label text-sm font-medium text-gray-700">
                                    Wyszukaj po nazwie
                                </label>
                                <div class="fi-input-wrp">
                                    <input type="text" wire:model.live="searchTerm" placeholder="Nazwa lub opis..."
                                        class="fi-input block w-full border-gray-300 rounded-lg shadow-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500">
                                </div>
                            </div>

                            <!-- Filtr po tagach -->
                            <div class="fi-fo-field-wrp">
                                <label class="fi-fo-field-wrp-label text-sm font-medium text-gray-700">
                                    Tagi
                                </label>
                                <div class="fi-input-wrp">
                                    <select wire:model.live="selectedTags" multiple 
                                        class="fi-select block w-full border-gray-300 rounded-lg shadow-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500">
                                        @foreach($allTags as $tag)
                                            <option value="{{ $tag->name }}">{{ $tag->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <!-- Filtr po walucie -->
                            <div class="fi-fo-field-wrp">
                                <label class="fi-fo-field-wrp-label text-sm font-medium text-gray-700">
                                    Waluta
                                </label>
                                <div class="fi-input-wrp">
                                    <select wire:model.live="selectedCurrency" 
                                        class="fi-select block w-full border-gray-300 rounded-lg shadow-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500">
                                        <option value="">Wszystkie waluty</option>
                                        @foreach($allCurrencies as $currency)
                                            <option value="{{ $currency->symbol }}">{{ $currency->symbol }} - {{ $currency->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <!-- Filtr przeliczania na PLN -->
                            <div class="fi-fo-field-wrp">
                                <label class="fi-fo-field-wrp-label text-sm font-medium text-gray-700">
                                    Przeliczanie na PLN
                                </label>
                                <div class="fi-input-wrp">
                                    <select wire:model.live="convertToPln" 
                                        class="fi-select block w-full border-gray-300 rounded-lg shadow-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500">
                                        <option value="">Wszystkie</option>
                                        <option value="1">Tak</option>
                                        <option value="0">Nie</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Zakres cen -->
                            <div class="fi-fo-field-wrp">
                                <label class="fi-fo-field-wrp-label text-sm font-medium text-gray-700">
                                    Cena od
                                </label>
                                <div class="fi-input-wrp">
                                    <input type="number" step="0.01" wire:model.live="minPrice" placeholder="0"
                                        class="fi-input block w-full border-gray-300 rounded-lg shadow-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500">
                                </div>
                            </div>

                            <div class="fi-fo-field-wrp">
                                <label class="fi-fo-field-wrp-label text-sm font-medium text-gray-700">
                                    Cena do
                                </label>
                                <div class="fi-input-wrp">
                                    <input type="number" step="0.01" wire:model.live="maxPrice" placeholder="∞"
                                        class="fi-input block w-full border-gray-300 rounded-lg shadow-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500">
                                </div>
                            </div>

                            <!-- Zakres czasu trwania -->
                            <div class="fi-fo-field-wrp">
                                <label class="fi-fo-field-wrp-label text-sm font-medium text-gray-700">
                                    Czas trwania od (min)
                                </label>
                                <div class="fi-input-wrp">
                                    <input type="number" wire:model.live="minDuration" placeholder="0"
                                        class="fi-input block w-full border-gray-300 rounded-lg shadow-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500">
                                </div>
                            </div>

                            <div class="fi-fo-field-wrp">
                                <label class="fi-fo-field-wrp-label text-sm font-medium text-gray-700">
                                    Czas trwania do (min)
                                </label>
                                <div class="fi-input-wrp">
                                    <input type="number" wire:model.live="maxDuration" placeholder="∞"
                                        class="fi-input block w-full border-gray-300 rounded-lg shadow-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500">
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 flex justify-between items-center">
                            <button wire:click="clearFilters" type="button"
                                class="fi-btn-base text-sm text-gray-600 hover:text-gray-800 underline">
                                Wyczyść wszystkie filtry
                            </button>                            <span class="fi-badge fi-badge-size-sm bg-gray-100 text-gray-700">
                                Znaleziono: {{ count($filteredPoints) }} z {{ count($availablePoints) }} punktów
                            </span>
                        </div>
                    </div>                    <!-- Lista wyfiltrowanych punktów -->
                    <div class="flex-1 overflow-y-auto px-6">                        <div class="fi-table-content">
                            @forelse($filteredPoints as $point)
                                <div class="fi-ta-record fi-ta-record-simple border-b border-gray-200 hover:bg-gray-50 cursor-pointer transition-colors duration-200
                                    {{ $modalData['child_program_point_id'] == $point['id'] ? 'bg-primary-50 border-primary-200' : '' }}"
                                    wire:click="$set('modalData.child_program_point_id', {{ $point['id'] }})">
                                    
                                    <div class="fi-ta-record-content grid grid-cols-12 gap-4 p-4">
                                        <!-- Kolumna 1: Radio + Zdjęcie -->
                                        <div class="col-span-1 flex items-center justify-center">
                                            <input type="radio" name="selected_point" value="{{ $point['id'] }}"
                                                {{ $modalData['child_program_point_id'] == $point['id'] ? 'checked' : '' }}
                                                class="fi-radio-input text-primary-600 focus:ring-primary-500 focus:ring-2">
                                        </div>

                                        <div class="col-span-2 flex items-center">
                                            @if($point['featured_image'])
                                                <img src="{{ Storage::url($point['featured_image']) }}" 
                                                     alt="Zdjęcie" class="h-16 w-16 rounded-lg object-cover shadow-sm">
                                            @else
                                                <div class="h-16 w-16 bg-gray-100 rounded-lg flex items-center justify-center border-2 border-dashed border-gray-300">
                                                    <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                                    </svg>
                                                </div>
                                            @endif
                                        </div>

                                        <!-- Kolumna 2: Podstawowe informacje -->
                                        <div class="col-span-4">
                                            <h5 class="fi-ta-text-item-label text-sm font-semibold text-gray-900 mb-2">
                                                {{ $point['name'] }}
                                            </h5>
                                            
                                            <div class="flex flex-wrap gap-3 text-xs text-gray-600 mb-2">
                                                <span class="fi-badge fi-badge-size-xs bg-blue-100 text-blue-800 flex items-center">
                                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                    </svg>
                                                    {{ sprintf('%02d:%02d', $point['duration_hours'] ?? 0, $point['duration_minutes'] ?? 0) }}
                                                </span>
                                                
                                                <span class="fi-badge fi-badge-size-xs bg-green-100 text-green-800 flex items-center">
                                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                                                    </svg>
                                                    {{ number_format($point['unit_price'] ?? 0, 2) }} {{ $point['currency']['symbol'] ?? 'PLN' }}
                                                </span>
                                                
                                                @if($point['group_size'] > 1)
                                                    <span class="fi-badge fi-badge-size-xs bg-purple-100 text-purple-800 flex items-center">
                                                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                                        </svg>
                                                        Grupa: {{ $point['group_size'] }}
                                                    </span>
                                                @endif
                                            </div>

                                            @if($point['convert_to_pln'])
                                                <span class="fi-badge fi-badge-size-xs bg-emerald-100 text-emerald-800">
                                                    Przeliczane na PLN
                                                </span>
                                            @endif                            @if($point['description'])
                                <div class="text-xs text-gray-600 mt-2 line-clamp-2">
                                    {!! Str::limit(strip_tags($point['description'], '<p><br><strong><em><b><i><ul><ol><li>'), 120) !!}
                                </div>
                            @endif
                                        </div>

                                        <!-- Kolumna 3: Uwagi dla biura -->
                                        <div class="col-span-3">
                                            @if($point['office_notes'])
                                                <div class="fi-section fi-section-compact bg-blue-50 rounded-lg p-3 border border-blue-200">
                                                    <div class="fi-section-header-heading text-xs font-medium text-blue-800 mb-1">
                                                        Uwagi dla biura:
                                                    </div>
                                                    <p class="text-xs text-blue-700">
                                                        {{ Str::limit($point['office_notes'], 100) }}
                                                    </p>
                                                </div>
                                            @endif
                                        </div>

                                        <!-- Kolumna 4: Tagi i galeria -->
                                        <div class="col-span-2">
                                            <!-- Tagi -->
                                            @if($point['tags'])
                                                <div class="mb-3">
                                                    <div class="text-xs font-medium text-gray-700 mb-1">Tagi:</div>
                                                    <div class="flex flex-wrap gap-1">
                                                        @foreach(array_slice($point['tags'], 0, 3) as $tag)
                                                            <span class="fi-badge fi-badge-size-xs bg-gray-100 text-gray-700">
                                                                {{ $tag['name'] }}
                                                            </span>
                                                        @endforeach
                                                        @if(count($point['tags']) > 3)
                                                            <span class="fi-badge fi-badge-size-xs bg-gray-200 text-gray-600">
                                                                +{{ count($point['tags']) - 3 }}
                                                            </span>
                                                        @endif
                                                    </div>
                                                </div>
                                            @endif

                                            <!-- Galeria miniatur -->
                                            @if($point['gallery_images'] && is_array($point['gallery_images']))
                                                <div>
                                                    <div class="text-xs font-medium text-gray-700 mb-1">Galeria:</div>
                                                    <div class="flex flex-wrap gap-1">
                                                        @foreach(array_slice($point['gallery_images'], 0, 4) as $image)
                                                            <img src="{{ Storage::url($image) }}" 
                                                                 alt="Galeria" class="h-6 w-6 rounded object-cover border border-gray-200">
                                                        @endforeach
                                                        @if(count($point['gallery_images']) > 4)
                                                            <div class="h-6 w-6 bg-gray-100 rounded flex items-center justify-center text-xs text-gray-600 border border-gray-200">
                                                                +{{ count($point['gallery_images']) - 4 }}
                                                            </div>
                                                        @endif
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <div class="fi-empty-state p-12 text-center">
                                    <div class="fi-empty-state-icon mx-auto h-12 w-12 text-gray-400 mb-4">
                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                        </svg>
                                    </div>
                                    <h3 class="fi-empty-state-heading text-lg font-semibold text-gray-900 mb-2">
                                        Nie znaleziono punktów programu
                                    </h3>
                                    <p class="fi-empty-state-description text-gray-600 mb-4">
                                        Spróbuj zmienić kryteria wyszukiwania lub wyczyść filtry.
                                    </p>
                                    <button wire:click="clearFilters" type="button"
                                        class="fi-btn fi-btn-outlined fi-btn-size-md fi-btn-color-gray">
                                        <span class="fi-btn-label">Wyczyść filtry</span>
                                    </button>
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="fi-modal-footer px-6 py-4 bg-gray-50 border-t border-gray-200">
                    <div class="fi-modal-footer-actions flex justify-end space-x-3">
                        <button type="button" wire:click="closeModal" 
                            class="fi-btn fi-btn-outlined fi-btn-size-md fi-btn-color-gray bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 px-4 py-2 rounded-md font-medium transition-colors">
                            <span class="fi-btn-label">Anuluj</span>
                        </button>
                        <button wire:click="saveChild" type="button"
                            {{ empty($modalData['child_program_point_id']) ? 'disabled' : '' }}
                            class="fi-btn fi-btn-size-md fi-btn-color-primary bg-orange-600 text-white hover:bg-orange-700 focus:ring-2 focus:ring-orange-500 focus:ring-offset-2 px-4 py-2 rounded-md font-medium transition-colors {{ empty($modalData['child_program_point_id']) ? 'opacity-50 cursor-not-allowed bg-gray-400 hover:bg-gray-400' : '' }}">
                            <span class="fi-btn-label">
                                {{ empty($modalData['child_program_point_id']) ? 'Wybierz punkt programu' : 'Dodaj podpunkt' }}
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif    @push('scripts')
    <script>
    document.addEventListener('livewire:navigated', () => {
        initializeChildrenSortable();
        initializeModalKeyboard();
    });
    document.addEventListener('livewire:updated', () => {
        initializeChildrenSortable();
        initializeModalKeyboard();
    });

    // Obsługa komunikatów z Livewire
    document.addEventListener('livewire:initialized', () => {
        Livewire.on('notify', (data) => {
            showChildrenNotification(data.message, data.type);
        });
    });

    // Obsługa klawiatury w modalu
    function initializeModalKeyboard() {
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                // Zamknij modal klawiszem Escape
                @this.call('closeModal');
            }
        });
    }

    function showChildrenNotification(message, type = 'info') {
        // Używamy Filament notify - bardziej spójne z systemem
        new FilamentNotification()
            .title(message)
            .color(type === 'success' ? 'success' : type === 'error' ? 'danger' : 'info')
            .send();
    }    function initializeChildrenSortable() {
        const container = document.getElementById('children-container');
        if (!container) return;

        if (container.sortableInstance) {
            container.sortableInstance.destroy();
        }

        container.sortableInstance = Sortable.create(container, {
            handle: '.drag-handle',
            draggable: 'li[data-child-id]',
            ghostClass: 'opacity-50',
            chosenClass: 'bg-blue-100',
            animation: 150,
            onEnd: function (evt) {
                const childIds = Array.from(container.querySelectorAll('li[data-child-id]')).map(li => li.getAttribute('data-child-id'));
                // Use Livewire.find() method like in EventProgramTreeEditor
                const wireId = container.closest('[wire\\:id]').getAttribute('wire:id');
                Livewire.find(wireId).call('updateChildrenOrder', childIds);
            }
        });
    }
    </script>
    @endpush
</div>
