@php
    // $page - instancja strony Livewire (np. EditEventTemplate)
    // $hotelRooms - lista pokoi (id => nazwa)
    $hotelDays = isset($page->hotel_days) ? $page->hotel_days : [];
    $hotelRooms = \App\Models\HotelRoom::pluck('name', 'id')->toArray();
@endphp

<!-- Debug info -->
<div class="mb-2 p-2 bg-yellow-50 border border-yellow-200 rounded text-xs">
    <strong>Debug:</strong> 
    Liczba pokoi: {{ count($hotelRooms) }} | 
    Liczba noclegów: {{ count($hotelDays) }} |
    Pokoje: {{ implode(', ', array_values($hotelRooms)) }}
</div>

<div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
    <!-- Instrukcja obsługi -->
    <div class="mb-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
        <div class="flex items-start">
            <div class="flex-shrink-0">
                <svg class="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                </svg>
            </div>
            <div class="ml-3">
                <h3 class="text-sm font-medium text-blue-800">Jak korzystać z tabeli noclegów</h3>
                <div class="mt-2 text-sm text-blue-700">
                    <ul class="list-disc list-inside space-y-1">
                        <li>Kliknij w select, aby wybrać pokoje dla danej roli</li>
                        <li>Przytrzymaj Ctrl/Cmd, aby wybrać kilka pokoi naraz</li>
                        <li>Użyj przycisku "×" przy tagu, aby usunąć pokój</li>
                        <li>Kopiuj ustawienia do następnego noclegu przyciskiem "Kopiuj"</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Nocleg
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Qty
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Gratis
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Staff
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Driver
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Akcje
                    </th>
                </tr>
            </thead>            <tbody class="bg-white divide-y divide-gray-200">
                @foreach($hotelDays as $i => $day)
                <tr class="table-row" wire:key="hotel-day-{{ $i }}">
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm font-medium text-gray-900">
                            Nocleg {{ $day['day'] ?? ($i+1) }}
                        </div>
                    </td>
                    @foreach(['qty','gratis','staff','driver'] as $role)
                    <td class="px-6 py-4" wire:key="hotel-day-{{ $i }}-{{ $role }}">
                        <div class="space-y-3">
                            @php
                                $selectedIds = $day["hotel_room_ids_{$role}"] ?? [];
                                $fieldName = "hotel_days.{$i}.hotel_room_ids_{$role}";
                            @endphp                            <!-- Wielokrotny select z prostą obsługą -->
                            <div class="relative">
                                <select
                                    multiple
                                    wire:model.live="{{ $fieldName }}"
                                    class="hotel-room-select block w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                    size="6"
                                    name="{{ $fieldName }}"
                                >
                                    @foreach($hotelRooms as $id => $name)
                                        <option value="{{ $id }}">{{ $name }}</option>
                                    @endforeach
                                </select>
                                
                                <!-- Input do wyszukiwania -->
                                <input type="text" 
                                    class="search-input w-full px-3 py-2 mt-2 text-sm border border-gray-300 rounded-md" 
                                    placeholder="Wyszukaj pokój..."
                                    onkeyup="filterOptions(this, this.previousElementSibling)">
                            </div>
                            
                            <!-- Wyświetlanie wybranych pokoi jako tagi -->
                            @if(!empty($selectedIds))
                                <div class="flex flex-wrap gap-1 mt-2">
                                    @foreach($selectedIds as $roomId)
                                        @php
                                            $roomName = $hotelRooms[$roomId] ?? "Pokój #{$roomId}";
                                        @endphp
                                        <span class="room-tag" wire:key="tag-{{ $i }}-{{ $role }}-{{ $roomId }}">
                                            {{ $roomName }}
                                            <span class="remove-btn" 
                                                wire:click="removeRoomFromDay({{ $i }}, '{{ $role }}', {{ $roomId }})"
                                                title="Usuń pokój">
                                                ×
                                            </span>
                                        </span>
                                    @endforeach
                                </div>
                                <div class="text-xs text-gray-600 mt-1">
                                    Wybrano: {{ count($selectedIds) }} {{ count($selectedIds) == 1 ? 'pokój' : 'pokoi' }}
                                </div>
                            @else
                                <div class="text-xs text-gray-400 italic mt-2">
                                    Brak przypisanych pokoi
                                </div>
                            @endif
                        </div>
                    </td>
                    @endforeach
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        <button type="button" 
                            class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-blue-700 bg-blue-100 hover:bg-blue-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors duration-200"
                            wire:click="copyToNextDay({{ $i }})"
                            @if($i >= count($hotelDays) - 1) disabled title="To jest ostatni nocleg" @else title="Kopiuj ustawienia do następnego noclegu" @endif>
                            @if($i >= count($hotelDays) - 1)
                                Ostatni nocleg
                            @else
                                Kopiuj do następnego
                            @endif
                        </button>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
      @if(empty($hotelDays))
        <div class="text-center py-8">
            <p class="text-gray-500">Brak noclegów do wyświetlenia.</p>
            <p class="text-sm text-gray-400 mt-2">Noclegi generują się automatycznie na podstawie długości eventu (dni - 1).</p>
        </div>
    @endif    <!-- Przycisk debug (można usunąć po testach) -->
    <div class="mt-4 p-3 bg-gray-100 rounded">
        <div class="flex gap-2 items-center">
            <button type="button" wire:click="saveHotelDays" 
                class="px-4 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                💾 Zapisz noclegi
            </button>
            <button type="button" wire:click="debugHotelDays" class="text-xs text-gray-600 underline">
                Debug: Pokaż dane noclegów
            </button>
            <button type="button" wire:click="forceRefreshHotelDays" class="text-xs text-blue-600 underline">
                Odśwież noclegi
            </button>
        </div>        <div class="text-xs text-gray-500 mt-2">
            Liczba noclegów: {{ count($hotelDays) }} | 
            Duration days: {{ $page->data['duration_days'] ?? $page->record->duration_days ?? 'N/A' }} |
            Prosta wersja: wire:model.live + Ctrl+Click
        </div>
    </div>
</div>

<style>    /* Proste style dla selectów */
    .hotel-room-select {
        min-height: 150px;
        font-size: 14px;
        appearance: none !important;
        -webkit-appearance: none !important;
        -moz-appearance: none !important;
        background-image: none !important;
    }
    
    .hotel-room-select option {
        padding: 6px 10px;
        cursor: pointer;
    }
    
    .hotel-room-select option:checked {
        background-color: #3b82f6 !important;
        color: white !important;
    }
    
    .hotel-room-select option:hover {
        background-color: #eff6ff !important;
    }
    
    .search-input:focus {
        border-color: #3b82f6;
        box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.2);
    }
    
    select[multiple]:focus {
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        outline: none;
    }
    
    select[multiple] option {
        padding: 8px 12px;
        margin-bottom: 2px;
        border-radius: 4px;
        cursor: pointer;
        font-size: 14px;
        line-height: 1.4;
        background-color: white;
        border: none;
    }
    
    select[multiple] option:checked {
        background-color: #3b82f6 !important;
        color: white !important;
        font-weight: 500;
    }
    
    select[multiple] option:hover {
        background-color: #eff6ff;
    }
    
    select[multiple] option:checked:hover {
        background-color: #2563eb !important;
    }    /* Ukrycie domyślnych strzałek - usunięte, bo jest w głównej definicji wyżej */
    
    /* Fallback dla starszych przeglądarek */
    select::-ms-expand {
        display: none;
    }
    
    /* Webkit browsers (Chrome, Safari, Edge) */
    .hotel-room-select::-webkit-scrollbar {
        width: 8px;
    }
    
    .hotel-room-select::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 4px;
    }
    
    .hotel-room-select::-webkit-scrollbar-thumb {
        background: #c1c1c1;
        border-radius: 4px;
    }
    
    .hotel-room-select::-webkit-scrollbar-thumb:hover {
        background: #a1a1a1;
    }
    
    /* Poprawa wyglądu tagów */
    .room-tag {
        display: inline-flex;
        align-items: center;
        padding: 4px 8px;
        margin: 2px;
        background-color: #e0f2fe;
        color: #0277bd;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 500;
        border: 1px solid #b3e5fc;
    }
    
    .room-tag .remove-btn {
        margin-left: 6px;
        color: #0277bd;
        cursor: pointer;
        font-weight: bold;
        font-size: 14px;
        line-height: 1;
        padding: 0 2px;
        border-radius: 50%;
        transition: background-color 0.2s;
    }
    
    .room-tag .remove-btn:hover {
        background-color: #01579b;
        color: white;
    }
      /* Animacje */
    .table-row {
        transition: background-color 0.2s ease;
    }
    
    .table-row:hover {
        background-color: #f8fafc;
    }
    
    /* Loading states */
    .hotel-room-select[style*="opacity"] {
        transition: opacity 0.3s ease;
        cursor: wait;
    }
    
    .select-loading {
        position: relative;
    }
    
    .select-loading::after {
        content: "Zapisywanie...";
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background: rgba(255, 255, 255, 0.9);
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 12px;</style>

@push('scripts')
<script>
// Prosta funkcja wyszukiwania pokoi
function filterOptions(searchInput, selectElement) {
    const searchTerm = searchInput.value.toLowerCase();
    const options = selectElement.querySelectorAll('option');
    
    options.forEach(option => {
        const text = option.textContent.toLowerCase();
        option.style.display = text.includes(searchTerm) ? '' : 'none';
    });
}

document.addEventListener('DOMContentLoaded', function() {
    console.log('Initializing hotel room selects...');
    initializeSelects();
});

function initializeSelects() {
    const selects = document.querySelectorAll('.hotel-room-select');
    console.log('Found selects:', selects.length);
    
    selects.forEach((select, index) => {
        console.log('Setting up select', index);
        
        // Prosta obsługa Ctrl+Click
        select.addEventListener('mousedown', function(e) {
            if (e.target.tagName === 'OPTION') {
                if (e.ctrlKey || e.metaKey) {
                    e.preventDefault();
                    e.target.selected = !e.target.selected;
                    
                    // Natychmiast wywołaj event change dla Livewire
                    setTimeout(() => {
                        this.dispatchEvent(new Event('change', { bubbles: true }));
                    }, 10);
                    
                    console.log('Ctrl+click:', e.target.textContent, 'selected:', e.target.selected);
                    return false;
                }
            }
        });
        
        // Hover effects
        select.addEventListener('mouseover', function(e) {
            if (e.target.tagName === 'OPTION') {
                e.target.style.backgroundColor = '#eff6ff';
            }
        });
        
        select.addEventListener('mouseout', function(e) {
            if (e.target.tagName === 'OPTION' && !e.target.selected) {
                e.target.style.backgroundColor = '';
            }
        });
        
        // Log changes
        select.addEventListener('change', function() {
            const selected = Array.from(this.selectedOptions).map(o => o.value);
            console.log('Select changed:', this.name, 'selected:', selected);
        });
    });
}

// Re-initialize after Livewire updates
document.addEventListener('livewire:updated', function() {
    console.log('Livewire updated - reinitializing...');
    setTimeout(initializeSelects, 100);
});
</script>
@endpush
