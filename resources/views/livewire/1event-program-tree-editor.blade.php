<div>
    <!-- Komunikaty -->
    <div id="notifications" class="fixed top-4 right-4 z-50">
        <!-- Tu będą wyświetlane notyfikacje -->
    </div>

    <div class="flex justify-between items-center mb-4">
        <button wire:click="$refresh" class="ml-4 px-3 py-1 bg-green-500 text-white rounded hover:bg-green-600">Test Livewire ($refresh)</button>
        <h2 class="text-xl font-bold">Program wydarzenia: {{ $eventTemplate->name }}</h2>
        <div class="flex items-center space-x-3"> <a
                href="{{ \App\Filament\Resources\EventTemplateResource::getUrl('edit', ['record' => $eventTemplate->id]) }}"
                class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 flex items-center font-medium border-2 border-blue-800">
                ← Wróć do edycji szablonu
            </a> <button wire:click="showAddModal"
                class="px-4 py-2 bg-primary-600 text-white rounded hover:bg-primary-700 flex items-center">
                <x-heroicon-o-plus-circle class="w-5 h-5 mr-2" />
                Dodaj punkt programu
            </button>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6" id="program-days-container">
        @forelse ($programByDays as $day)
            @php
                $dayNumber = $day['day'];
                $points = $day['points'];
            @endphp
            <div class="bg-white shadow rounded-lg p-4 fi-section-content" data-day="{{ $dayNumber }}">
                <div class="mb-3">
                    <h3 class="text-lg font-semibold text-gray-700">
                        @if($dayNumber > $eventTemplate->duration_days)
                            Fakultatywnie
                        @else
                            Dzień {{ $dayNumber }}
                        @endif
                    </h3>
                    <div class="flex flex-wrap gap-4 items-center mt-2">
                        @foreach(App\Models\Insurance::active()->get() as $insurance)
                            <label class="flex items-center gap-2">
                                <input type="checkbox"
                                    wire:click="toggleDayInsurance({{ $dayNumber }}, {{ $insurance->id }})"
                                    @checked($eventTemplate->dayInsurances->where('day', $dayNumber)->pluck('insurance_id')->contains($insurance->id))
                                    class="form-checkbox h-5 w-5 text-blue-600 rounded border-gray-300 focus:border-blue-600 focus:ring-blue-600">
                                <span class="text-sm text-gray-700">{{ $insurance->name }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
                <ul class="program-day-list space-y-2 min-h-[100px] border border-dashed border-gray-300 p-2 rounded-md"
                    data-day-id="{{ $dayNumber }}">
                    @forelse ($points as $point)
                        <li class="program-point-item group flex items-stretch bg-white rounded-2xl shadow-md border border-gray-200 hover:shadow-lg transition overflow-hidden relative"
                            data-pivot-id="{{ $point['pivot_id'] ?? $point['id'] }}" data-point-id="{{ $point['id'] }}">
                            <!-- Drag handle -->
                            <div class="flex flex-col items-center justify-center bg-gray-100 px-2 cursor-grab drag-handle select-none">
                                <x-heroicon-o-bars-3 class="w-6 h-6 text-gray-400" />
                            </div>
                            @php
                                $colors = [
                                    0 => ['bg' => 'bg-orange-400', 'icon' => 'clipboard-document-list'],
                                    1 => ['bg' => 'bg-blue-400', 'icon' => 'calculator'],
                                    2 => ['bg' => 'bg-green-400', 'icon' => 'check-circle'],
                                    3 => ['bg' => 'bg-red-400', 'icon' => 'puzzle-piece'],
                                ];
                                $colorIdx = $loop->index % 4;
                                $color = $colors[$colorIdx];
                            @endphp
                            <!-- Ikona i numer -->
                            <div class="flex flex-col items-center justify-center {{$color['bg']}} px-4 py-6 min-w-[70px]">
                                @if($color['icon']==='clipboard-document-list')
                                    <x-heroicon-o-clipboard-document-list class="w-8 h-8 text-white" />
                                @elseif($color['icon']==='calculator')
                                    <x-heroicon-o-calculator class="w-8 h-8 text-white" />
                                @elseif($color['icon']==='check-circle')
                                    <x-heroicon-o-check-circle class="w-8 h-8 text-white" />
                                @elseif($color['icon']==='puzzle-piece')
                                    <x-heroicon-o-puzzle-piece class="w-8 h-8 text-white" />
                                @endif
                                <span class="text-2xl font-bold text-white mt-2">{{ $loop->iteration }}</span>
                            </div>
                            <!-- Treść bloku -->
                            <div class="flex-1 flex flex-col md:flex-row md:items-center md:justify-between gap-4 bg-white px-6 py-4">
                                <div class="flex-1 min-w-0">
                                    <div class="text-lg font-semibold text-gray-800 truncate">{{ $point['name'] }}</div>
                                    <div class="text-xs text-gray-500 mt-1">Czas trwania: {{ isset($point['duration_hours']) || isset($point['duration_minutes']) ? sprintf('%02d:%02d', $point['duration_hours'] ?? 0, $point['duration_minutes'] ?? 0) : '-' }}</div>
                                    @if(!empty($point['office_notes']))
                                        <div class="text-xs text-blue-600 italic mt-1">Uwagi dla biura: {{ $point['office_notes'] }}</div>
                                    @endif
                                    @if(!empty($point['pivot_notes']))
                                        <div class="text-xs text-purple-600 italic mt-1">Notatki: {{ $point['pivot_notes'] }}</div>
                                    @endif
                                    @if(!empty($point['description']))
                                        <div class="text-xs text-gray-600 mt-1">{{ $point['description'] }}</div>
                                    @endif
                                    @if(!empty($point['tags']) && is_array($point['tags']))
                                        <div class="flex flex-wrap gap-1 mt-2">
                                            @foreach($point['tags'] as $tag)
                                                <span class="inline-block bg-gray-200 rounded-full px-2 py-0.5 text-xs font-semibold text-gray-700">{{ $tag['name'] }}</span>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                                <div class="flex flex-col items-end gap-2">
                                    @if(!empty($point['featured_image']) && !str_contains($point['featured_image'], 'tmp'))
                                        <img src="{{ Storage::url($point['featured_image']) }}" alt="Miniaturka" class="h-12 w-12 object-cover rounded mb-1" onerror="this.style.display='none'">
                                    @endif
                                    @if(!empty($point['gallery_images']) && is_array($point['gallery_images']))
                                        <div class="flex flex-wrap gap-1">
                                            @foreach(array_slice($point['gallery_images'], 0, 4) as $image)
                                                @if(is_string($image) && !str_contains($image, 'tmp'))
                                                    <img src="{{ Storage::url($image) }}" alt="Miniaturka galerii" class="h-8 w-8 object-cover rounded" onerror="this.style.display='none'">
                                                @endif
                                            @endforeach
                                            @if(count($point['gallery_images']) > 4)
                                                <span class="text-xs text-gray-500 self-center bg-gray-200 px-1 rounded">+{{ count($point['gallery_images']) - 4 }}</span>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                                <div class="flex flex-row gap-3 items-center mt-2 md:mt-0">
                                    <label class="flex flex-col items-center cursor-pointer group">
                                        <input type="checkbox" 
                                               wire:click="togglePivotProperty({{ $point['pivot_id'] ?? $point['id'] }}, 'include_in_program')" 
                                               @checked($point['include_in_program'] ?? true) 
                                               class="form-checkbox h-5 w-5 text-orange-500 rounded border-gray-300 focus:border-orange-500 focus:ring-orange-500">
                                        <span class="text-xs mt-1 text-gray-700">Program</span>
                                    </label>
                                    <label class="flex flex-col items-center cursor-pointer group">
                                        <input type="checkbox" 
                                               wire:click="togglePivotProperty({{ $point['pivot_id'] ?? $point['id'] }}, 'include_in_calculation')" 
                                               @checked($point['include_in_calculation'] ?? true) 
                                               class="form-checkbox h-5 w-5 text-blue-500 rounded border-gray-300 focus:border-blue-500 focus:ring-blue-500">
                                        <span class="text-xs mt-1 text-gray-700">Kalkulacja</span>
                                    </label>
                                    <label class="flex flex-col items-center cursor-pointer group">
                                        <input type="checkbox" 
                                               wire:click="togglePivotProperty({{ $point['pivot_id'] ?? $point['id'] }}, 'active')" 
                                               @checked($point['active'] ?? true) 
                                               class="form-checkbox h-5 w-5 text-green-500 rounded border-gray-300 focus:border-green-500 focus:ring-green-500">
                                        <span class="text-xs mt-1 text-gray-700">Aktywny</span>
                                    </label>
                                    <label class="flex flex-col items-center cursor-pointer group">
                                        <input type="checkbox" 
                                               wire:click="togglePivotProperty({{ $point['pivot_id'] ?? $point['id'] }}, 'show_title_style')" 
                                               @checked($point['show_title_style'] ?? true) 
                                               class="form-checkbox h-5 w-5 text-purple-500 rounded border-gray-300 focus:border-purple-500 focus:ring-purple-500">
                                        <span class="text-xs mt-1 text-gray-700">Styl tytułu</span>
                                    </label>
                                    <label class="flex flex-col items-center cursor-pointer group">
                                        <input type="checkbox" 
                                               wire:click="togglePivotProperty({{ $point['pivot_id'] ?? $point['id'] }}, 'show_description')" 
                                               @checked($point['show_description'] ?? true) 
                                               class="form-checkbox h-5 w-5 text-pink-500 rounded border-gray-300 focus:border-pink-500 focus:ring-pink-500">
                                        <span class="text-xs mt-1 text-gray-700">Opis</span>
                                    </label>
                                </div>
                                <!-- Col 6: Actions (przeniesione do wnętrza li) -->
                                <div class="w-20 pl-2 py-1 flex items-center space-x-2 self-center flex-shrink-0">
                                    <button
                                        onclick="if(confirm('Przejść do ekranu edycji punktu programu?')){ window.location.href='/admin/event-template-program-points/{{ $point['id'] }}/edit'; } return false;"
                                        class="text-gray-400 hover:text-primary-600 p-1" title="Edytuj">
                                        <x-heroicon-o-pencil-square class="w-5 h-5" />
                                    </button>
                                    <button wire:click="duplicatePoint({{ $point['pivot_id'] ?? $point['id'] }})"
                                        wire:confirm="Czy na pewno chcesz zduplikować ten punkt programu?"
                                        class="text-gray-400 hover:text-success-600 p-1" title="Duplikuj">
                                        <x-heroicon-o-document-duplicate class="w-5 h-5" />
                                    </button>
                                    <button wire:click="deletePoint({{ $point['pivot_id'] ?? $point['id'] }})"
                                        wire:confirm="Czy na pewno chcesz usunąć ten punkt z programu?"
                                        class="text-gray-400 hover:text-danger-600 p-1" title="Usuń">
                                        <x-heroicon-o-trash class="w-5 h-5" />
                                    </button>
                                </div>
                            </div>
                        </li>
                        @if(!empty($point['children']))
                            <li class="children-container">
                                <ul class="ml-8 mt-1">
                                    @foreach($point['children'] as $child)
                                        <li class="bg-gray-100 p-3 rounded-md shadow-sm border border-gray-100 flex items-start">
                                        <!-- Col 0: Empty space for drag handle -->
                                        <div class="w-12 pr-2 py-1 self-center flex-shrink-0">
                                            <div class="w-5 h-5 opacity-30">
                                                <x-heroicon-o-arrow-right class="w-4 h-4 text-gray-400" />
                                            </div>
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
                                                    Uwagi dla biura: {{ $child['office_notes'] }}
                                                </div>
                                            @endif
                                            <!-- Checkboxy właściwości podpunktu -->
                                            <div class="flex flex-row gap-3 items-center mt-2">
                                                <label class="flex flex-col items-center cursor-pointer group">
                                                    <input type="checkbox"
                                                           wire:click="toggleChildPivotProperty({{ $child['id'] }}, 'include_in_program')"
                                                           @checked($child['include_in_program'] ?? true)
                                                           class="form-checkbox h-5 w-5 text-orange-500 rounded border-gray-300 focus:border-orange-500 focus:ring-orange-500">
                                                    <span class="text-xs mt-1 text-gray-700">Program</span>
                                                </label>
                                                <label class="flex flex-col items-center cursor-pointer group">
                                                    <input type="checkbox"
                                                           wire:click="toggleChildPivotProperty({{ $child['id'] }}, 'include_in_calculation')"
                                                           @checked($child['include_in_calculation'] ?? true)
                                                           class="form-checkbox h-5 w-5 text-blue-500 rounded border-gray-300 focus:border-blue-500 focus:ring-blue-500">
                                                    <span class="text-xs mt-1 text-gray-700">Kalkulacja</span>
                                                </label>
                                                <label class="flex flex-col items-center cursor-pointer group">
                                                    <input type="checkbox"
                                                           wire:click="toggleChildPivotProperty({{ $child['id'] }}, 'active')"
                                                           @checked($child['active'] ?? true)
                                                           class="form-checkbox h-5 w-5 text-green-500 rounded border-gray-300 focus:border-green-500 focus:ring-green-500">
                                                    <span class="text-xs mt-1 text-gray-700">Aktywny</span>
                                                </label>
                                                <label class="flex flex-col items-center cursor-pointer group">
                                                    <input type="checkbox"
                                                           wire:click="toggleChildPivotProperty({{ $child['id'] }}, 'show_title_style')"
                                                           @checked($child['show_title_style'] ?? true)
                                                           class="form-checkbox h-5 w-5 text-purple-500 rounded border-gray-300 focus:border-purple-500 focus:ring-purple-500">
                                                    <span class="text-xs mt-1 text-gray-700">Styl tytułu</span>
                                                </label>
                                                <label class="flex flex-col items-center cursor-pointer group">
                                                    <input type="checkbox"
                                                           wire:click="toggleChildPivotProperty({{ $child['id'] }}, 'show_description')"
                                                           @checked($child['show_description'] ?? true)
                                                           class="form-checkbox h-5 w-5 text-pink-500 rounded border-gray-300 focus:border-pink-500 focus:ring-pink-500">
                                                    <span class="text-xs mt-1 text-gray-700">Opis</span>
                                                </label>
                                            </div>
                                        </div>
                                        <!-- Col 2: Description -->
                                        <div class="w-72 px-2 py-1 border-r border-gray-200 flex-shrink-0">
                                            @if(!empty($child['description']))
                                                <div class="text-xs text-gray-600">{{ $child['description'] }}</div>
                                            @else
                                                <p class="text-xs text-gray-400 italic">Brak opisu.</p>
                                            @endif
                                        </div>
                                        <!-- Col 3: Featured Image, Gallery -->
                                        <div class="w-48 px-2 py-1 border-r border-gray-200 flex-shrink-0">
                                            @if(!empty($child['featured_image']) && !str_contains($child['featured_image'], 'tmp'))
                                                <div class="mb-2">
                                                    <img src="{{ Storage::url($child['featured_image']) }}" alt="Miniaturka" class="h-12 w-12 object-cover rounded" onerror="this.style.display='none'">
                                                </div>
                                            @endif
                                            @if(!empty($child['gallery_images']) && is_array($child['gallery_images']))
                                                <div class="flex flex-wrap gap-1">
                                                    @foreach(array_slice($child['gallery_images'], 0, 4) as $image)
                                                        @if(!str_contains($image, 'tmp'))
                                                            <img src="{{ Storage::url($image) }}" alt="Miniaturka galerii" class="h-8 w-8 object-cover rounded" onerror="this.style.display='none'">
                                                        @endif
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
                                        <!-- Col 5: Checkboxy właściwości podpunktu -->
                                        </li>
                                    @endforeach
                                </ul>
                            </li>
                        @endif
                    </li> <!-- Zamknięcie głównego li dla punktu programu -->
                    @empty
                        <li class="text-center text-gray-400 py-4 italic">Brak punktów programu na ten dzień.</li>
                    @endforelse <!-- endforelse points -->
                </ul>
            </div>
        @empty
            @for ($i = 1; $i <= $duration_days; $i++)
                <div class="bg-white shadow rounded-lg p-4 fi-section-content" data-day="{{ $i }}">
                    <div class="mb-3">
                        <h3 class="text-lg font-semibold text-gray-700">
                            @if($i > $eventTemplate->duration_days)
                                Fakultatywnie
                            @else
                                Dzień {{ $i }}
                            @endif
                        </h3>
                    </div>
                    <ul class="program-day-list space-y-2 min-h-[100px] border border-dashed border-gray-300 p-2 rounded-md"
                        data-day-id="{{ $i }}">
                        <li class="text-center text-gray-400 py-4 italic">Brak punktów programu na ten dzień.</li>
                        <!-- Dodaj ukryty element, aby Sortable.js widział dropzone nawet gdy lista jest pusta -->
                        <li class="program-point-item invisible h-0"></li>
                    </ul>
                </div>
            @endfor
        @endforelse
    </div> <!-- Koniec głównej siatki dni programu -->
    <style>
        [x-cloak] {
            display: none !important;
        }
        
        /* Ładne checkboxy */
        .form-checkbox {
            appearance: none;
            background-color: #fff;
            margin: 0;
            color: currentColor;
            width: 1.25rem;
            height: 1.25rem;
            border: 2px solid #d1d5db;
            border-radius: 0.25rem;
            display: grid;
            place-content: center;
            transition: all 0.2s ease;
        }

        .form-checkbox:checked {
            border-color: currentColor;
            background-color: currentColor;
        }

        .form-checkbox::before {
            content: "";
            width: 0.65em;
            height: 0.65em;
            clip-path: polygon(14% 44%, 0 65%, 50% 100%, 100% 16%, 80% 0%, 43% 62%);
            transform: scale(0);
            transform-origin: bottom left;
            transition: 120ms transform ease-in-out;
            box-shadow: inset 1em 1em white;
        }

        .form-checkbox:checked::before {
            transform: scale(1);
        }

        .form-checkbox:focus {
            outline: 2px solid transparent;
            outline-offset: 2px;
            box-shadow: 0 0 0 2px currentColor;
        }
    </style>
    <div x-data="{ show: @entangle('showModal') }" x-show="show" x-cloak class="fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div x-show="show" class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true">
            </div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div x-show="show"
                class="inline-block align-bottom bg-white rounded-lg px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full sm:p-6">
                <div>
                    <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                        {{ $editPoint ? 'Edytuj punkt programu' : 'Dodaj punkt do programu' }}
                    </h3>
                    <div class="mt-4">
                        <form wire:submit.prevent="savePoint" class="space-y-4">
                            <div x-data="{
                                open: @entangle('searchDropdownOpen').live,
                                searchText: @entangle('searchProgramPoint').live,
                                selectedIndex: -1,
                                get maxIndex() {
                                    return Math.max(0, this.$el.querySelectorAll('[data-search-item]').length - 1);
                                },
                                init() {
                                    const observer = new MutationObserver(() => {
                                        this.checkAndOpenDropdown();
                                    });
                                    observer.observe(this.$el, { childList: true, subtree: true });
                                    
                                    window.addEventListener('livewire:updated', () => {
                                        this.checkAndOpenDropdown();
                                    });
                                    this.$nextTick(() => this.checkAndOpenDropdown());
                                },
                                checkAndOpenDropdown() {
                                    this.$nextTick(() => {
                                        const hasItems = this.$el.querySelectorAll('[data-search-item]').length > 0;
                                        const hasMinLength = this.searchText && this.searchText.length >= 3;
                                        this.open = hasMinLength && hasItems;
                                    });
                                },
                                selectNext() {
                                    if (this.selectedIndex < this.maxIndex) {
                                        this.selectedIndex++;
                                        this.scrollToSelected();
                                    }
                                },
                                selectPrev() {
                                    if (this.selectedIndex > 0) {
                                        this.selectedIndex--;
                                        this.scrollToSelected();
                                    } else if (this.selectedIndex === 0) {
                                        this.selectedIndex = -1;
                                    }
                                },
                                selectCurrent() {
                                    if (this.selectedIndex >= 0) {
                                        const items = this.$el.querySelectorAll('[data-search-item]');
                                        if (items[this.selectedIndex]) {
                                            items[this.selectedIndex].click();
                                        }
                                    }
                                },
                                scrollToSelected() {
                                    if (this.selectedIndex < 0) return;
                                    
                                    this.$nextTick(() => {
                                        const items = this.$el.querySelectorAll('[data-search-item]');
                                        const selectedItem = items[this.selectedIndex];
                                        
                                        if (selectedItem) {
                                            selectedItem.scrollIntoView({ 
                                                block: 'nearest', 
                                                behavior: 'instant'
                                            });
                                        }
                                    });
                                },
                                resetSelection() {
                                    this.selectedIndex = -1;
                                }
                            }" @click.away="open = false; resetSelection()" class="relative">
                                <label for="program_point_id" class="block text-sm font-medium text-gray-700 mb-1">Punkt programu</label>
                                <input type="text" 
                                    x-model="searchText"
                                    x-init="$el.focus()"
                                    @input.debounce.300ms="
                                        $wire.updateSearch($event.target.value);
                                        resetSelection();
                                        requestAnimationFrame(() => {
                                            checkAndOpenDropdown();
                                        });
                                    "
                                    @focus="
                                        checkAndOpenDropdown();
                                    "
                                    @keydown.down.prevent="
                                        if (open && maxIndex >= 0) {
                                            selectNext();
                                        }
                                    "
                                    @keydown.up.prevent="
                                        if (open) {
                                            selectPrev();
                                        }
                                    "
                                    @keydown.enter.prevent="
                                        if (open && selectedIndex >= 0) {
                                            selectCurrent();
                                        }
                                    "
                                    @keydown.escape="
                                        open = false;
                                        resetSelection();
                                        $event.target.blur();
                                    "
                                    placeholder="Wpisz min. 3 znaki. Użyj przecinków dla warunków AND (np. 'kraków, warsztat')..." 
                                    class="block w-full mb-2 rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" 
                                    autocomplete="off" />
                                <input type="hidden" wire:model.defer="modalData.program_point_id" id="program_point_id" />
                                
                                {{-- Debug info --}}
                                @if(config('app.debug'))
                                    <div class="text-xs text-gray-500 mb-2">
                                        Search: "{{ $searchProgramPoint }}" | Results: {{ count($availableProgramPoints) }} | Open: <span x-text="open"></span> | Selected: <span x-text="selectedIndex"></span>
                                    </div>
                                @endif
                                
                                {{-- Lista wyników - pokazuj zawsze gdy są 3+ znaki --}}
                                @if($searchProgramPoint && strlen($searchProgramPoint) >= 3)
                                    <div x-show="open"
                                         x-transition:enter="transition ease-out duration-100"
                                         x-transition:enter-start="opacity-0 scale-95"
                                         x-transition:enter-end="opacity-100 scale-100"
                                         x-transition:leave="transition ease-in duration-75"
                                         x-transition:leave-start="opacity-100 scale-100"
                                         x-transition:leave-end="opacity-0 scale-95"
                                         class="results-container absolute z-50 bg-white border border-gray-300 rounded-lg shadow-lg w-full mt-1 pr-2"
                                         style="min-width: 100%; max-height: 320px; overflow-y: auto;"
                                         wire:key="search-results-{{ md5($searchProgramPoint) }}">
                                        <ul class="divide-y divide-gray-100">
                                            @forelse($availableProgramPoints as $index => $sdkPoint)
                                                <li data-search-item
                                                    data-index="{{ $index }}"
                                                    wire:key="search-item-{{ $sdkPoint->id }}-{{ $index }}"
                                                    @click="$wire.selectProgramPoint({{ $sdkPoint->id }}); open = false; searchText = ''; resetSelection()"
                                                    @mouseenter="selectedIndex = {{ $index }}"
                                                    class="px-4 py-3 cursor-pointer transition-colors"
                                                    :class="{
                                                        'bg-primary-100 font-semibold': {{ $sdkPoint->id == $modalData['program_point_id'] ? 'true' : 'false' }},
                                                        'bg-primary-50': selectedIndex === {{ $index }},
                                                        'hover:bg-primary-50': selectedIndex !== {{ $index }}
                                                    }">
                                                    <div class="flex flex-col">
                                                        <span class="font-medium text-gray-900">{{ $sdkPoint->name }}</span>
                                                        @if($sdkPoint->description)
                                                            <span class="text-xs text-gray-600 mt-1">{{ Str::limit(strip_tags($sdkPoint->description), 60) }}</span>
                                                        @endif
                                                        @if($sdkPoint->tags && $sdkPoint->tags->count() > 0)
                                                            <div class="flex flex-wrap gap-1 mt-2">
                                                                @foreach($sdkPoint->tags->take(3) as $tag)
                                                                    <span class="inline-block px-2 py-1 text-xs bg-blue-100 text-blue-800 rounded">{{ $tag->name }}</span>
                                                                @endforeach
                                                                @if($sdkPoint->tags->count() > 3)
                                                                    <span class="text-xs text-gray-500">+{{ $sdkPoint->tags->count() - 3 }} więcej</span>
                                                                @endif
                                                            </div>
                                                        @endif
                                                    </div>
                                                </li>
                                            @empty
                                                <li class="px-4 py-3 text-gray-500 italic text-center" wire:key="no-results">
                                                    Brak punktów pasujących do wszystkich warunków: "{{ $searchProgramPoint }}"
                                                </li>
                                            @endforelse
                                            @if($availableProgramPoints->count() >= 50)
                                                <li class="px-4 py-2 text-xs text-gray-500 bg-gray-50 text-center border-t" wire:key="limit-info">
                                                    Pokazano pierwszych 50 wyników. Sprecyzuj wyszukiwanie dla lepszych rezultatów.
                                                </li>
                                            @endif
                                            @if($availableProgramPoints->count() > 0)
                                                <li class="px-4 py-2 text-xs text-gray-400 bg-gray-50 text-center border-t" wire:key="keyboard-help">
                                                    ↑↓ przewijaj | Enter wybierz | Esc zamknij
                                                </li>
                                            @endif
                                        </ul>
                                    </div>
                                @endif
                                
                                @if($modalData['program_point_id'])
                                    <div class="text-xs text-green-700 mt-1">Wybrano: <span class="font-semibold">{{ optional($availableProgramPoints->firstWhere('id', (int) $modalData['program_point_id']))->name }}</span></div>
                                @endif
                                @error('modalData.program_point_id') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                            </div>
                            @if($modalData['program_point_id'] && ($selectedPoint = $availableProgramPoints->firstWhere('id', (int) $modalData['program_point_id'])))
                                <div class="p-3 bg-gray-50 rounded border border-gray-200">
                                    <p class="text-sm"><span class="font-semibold">Wybrany punkt:</span>
                                        {{ $selectedPoint->name }}</p>
                                    @if($selectedPoint->description)
                                        <p class="text-xs text-gray-600 mt-1">{{ $selectedPoint->description }}</p>
                                    @endif
                                </div>
                            @endif
                            <div>
                                <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">Notatki</label>
                                <textarea wire:model.defer="modalData.notes" id="notes"
                                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                                    rows="3"></textarea>
                                @error('modalData.notes') <span class="text-red-500 text-sm">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-2">
                                <label class="flex items-center">
                                    <input type="checkbox" wire:model.defer="modalData.include_in_program"
                                        id="include_in_program"
                                        class="rounded border-gray-300 text-primary-600 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                                    <span class="ml-2 text-sm text-gray-700">Uwzględnij w programie</span>
                                </label>
                                <label class="flex items-center">
                                    <input type="checkbox" wire:model.defer="modalData.include_in_calculation"
                                        id="include_in_calculation"
                                        class="rounded border-gray-300 text-primary-600 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                                    <span class="ml-2 text-sm text-gray-700">Uwzględnij w kalkulacji</span>
                                </label>
                                <label class="flex items-center">
                                    <input type="checkbox" wire:model.defer="modalData.active" id="active"
                                        class="rounded border-gray-300 text-primary-600 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                                    <span class="ml-2 text-sm text-gray-700">Aktywny</span>
                                </label>
                            </div>
                            <div class="mt-5 sm:mt-6 sm:grid sm:grid-cols-2 sm:gap-3 sm:grid-flow-row-dense">
                                <button type="submit"
                                    class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-primary-600 text-base font-medium text-white hover:bg-primary-700 sm:col-start-2 sm:text-sm">
                                    Zapisz
                                </button>
                                <button type="button" wire:click="closeModal"
                                    class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 sm:mt-0 sm:col-start-1 sm:text-sm">
                                    Anuluj
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
    document.addEventListener('livewire:navigated', () => {
        initializeSortable();
    });
    document.addEventListener('livewire:updated', () => {
        initializeSortable(); // Re-initialize after Livewire updates, if necessary
    });

    // Obsługa komunikatów z Livewire
    document.addEventListener('livewire:initialized', () => {
        console.log('Livewire initialized - setting up event listeners');
        
        // Global listener for debugging all Livewire events
        Livewire.hook('message.processed', (message, component) => {
            console.log('Livewire message processed:', message, component);
        });

        // Handler for new notify events
        Livewire.on('notify', (event) => {
            console.log('Notify event received:', event);
            const notificationData = event[0] || event; // Handle both array and object formats
            showNotification(notificationData.message, notificationData.type);
        });

        // Legacy handlers for backwards compatibility
        Livewire.on('program-point-saved', (message) => {
            showNotification(message, 'success');
        });

        Livewire.on('program-point-deleted', (message) => {
            showNotification(message, 'success');
        });

        Livewire.on('program-point-error', (message) => {
            showNotification(message, 'error');
        });

        Livewire.on('program-updated', (message) => {
            showNotification(message, 'success');
        });
    });

    function showNotification(message, type = 'info') {
        const container = document.getElementById('notifications');
        const notification = document.createElement('div');
        notification.className = `mb-2 p-3 rounded-md shadow-lg max-w-sm ${type === 'success' ? 'bg-green-50 text-green-800 border border-green-200' :
                type === 'error' ? 'bg-red-50 text-red-800 border border-red-200' :
                    'bg-blue-50 text-blue-800 border border-blue-200'
            }`;
        notification.innerHTML = `
        <div class="flex justify-between items-center">
            <span>${message}</span>
            <button onclick="this.parentElement.parentElement.remove()" class="ml-2 text-gray-500 hover:text-gray-700">×</button>
        </div>
    `;
        container.appendChild(notification);

        // Auto-hide after 5 seconds
        setTimeout(() => {
            if (notification.parentElement) {
                notification.remove();
            }
        }, 5000);
    }

    function initializeSortable() {
        const daysContainer = document.getElementById('program-days-container');
        if (!daysContainer) return;

        const dayLists = daysContainer.querySelectorAll('.program-day-list');

        dayLists.forEach(listEl => {
            if (listEl.sortableInstance) {
                listEl.sortableInstance.destroy(); // Destroy existing instance if any
            }
            listEl.sortableInstance = new Sortable(listEl, {
                group: 'program-points', // Same group name to allow dragging between lists
                animation: 150,
                handle: '.drag-handle', // Class for the drag handle
                draggable: '.program-point-item', // Specifies which items are draggable
                ghostClass: 'sortable-ghost',  // Class for the ghost item
                chosenClass: 'sortable-chosen', // Class for the chosen item
                dragClass: 'sortable-drag', // Class for the dragging item
                onEnd: function (evt) {
                    const targetDayId = evt.to.dataset.dayId;
                    const itemPivotId = evt.item.dataset.pivotId;

                    let orderedPivotsPerDay = {};
                    daysContainer.querySelectorAll('.program-day-list').forEach(dayList => {
                        const dayId = dayList.dataset.dayId;
                        orderedPivotsPerDay[dayId] = [];
                        dayList.querySelectorAll('.program-point-item').forEach(item => {
                            orderedPivotsPerDay[dayId].push(item.dataset.pivotId);
                        });
                    });

                    // Simple Livewire call
                    console.log('Updating program order:', orderedPivotsPerDay);
                    @this.call('updateProgramOrder', orderedPivotsPerDay);
                }
            });
        });
    }
    
    // Initial call
    if (document.readyState === 'complete' || document.readyState === 'interactive') {
        initializeSortable();
    } else {
        document.addEventListener('DOMContentLoaded', initializeSortable);
    }
    
    // Re-initialize after Livewire updates
    document.addEventListener('livewire:updated', function () {
        console.log('Livewire updated, re-initializing sortable');
        setTimeout(initializeSortable, 100); // Small delay to ensure DOM is ready
    });
    
    // Also listen for morphed event (more specific)
    document.addEventListener('livewire:morphed', function () {
        console.log('Livewire morphed, re-initializing sortable');
        setTimeout(initializeSortable, 100);
    });
</script>
@endpush

</div>