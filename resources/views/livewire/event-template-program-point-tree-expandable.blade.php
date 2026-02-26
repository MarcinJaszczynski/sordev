<div class="filament-tables-container">
    <!-- Nagłówek z przyciskiem dodawania -->
    <div class="flex justify-between items-center mb-4">
        <h2 class="text-lg font-semibold text-gray-900">Punkty programu szablonu wydarzenia</h2>
        <button 
            wire:click="addChild(null)" 
            class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
            </svg>
            Dodaj nowy punkt programu
        </button>
    </div>

    <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm">
        @if(empty($tree))
            <div class="p-8 text-center text-gray-500">
                <p class="text-lg mb-2">Brak punktów programu</p>
                <p class="text-sm">Kliknij przycisk "Dodaj nowy punkt programu" aby stworzyć pierwszy punkt.</p>
            </div>
        @else
            <ul class="divide-y divide-gray-100 filament-tables-table" x-data x-init="window.initProgramPointTreeDnD($el, $wire)" data-parent-id="">
                @foreach ($tree as $node)
                    @include('livewire.event-template-program-point-tree-expandable-node', [
                        'node' => $node,
                        'expanded' => $expanded,
                        'toggle' => 'toggle',
                    ])
                @endforeach
            </ul>
        @endif
    </div>    @if ($showModal)
        <div class="fixed inset-0 flex items-center justify-center z-50 bg-black bg-opacity-40">
            <div class="bg-white rounded shadow-lg p-6 w-full max-w-2xl max-h-[80vh] overflow-y-auto">
                <h2 class="text-lg font-bold mb-4">
                    {{ $editMode ? 'Edytuj punkt programu' : 'Dodaj punkt programu' }}
                </h2>
                
                @if (!$editMode)
                    <!-- Wyszukiwanie istniejących punktów -->
                    <div class="mb-4 p-3 bg-blue-50 rounded-lg">
                        <label class="block text-sm font-medium mb-2">Wyszukaj istniejący punkt (opcjonalnie)</label>
                        <input 
                            type="text" 
                            wire:model.live="modalSearchTerm"
                            placeholder="Wpisz nazwę lub opis punktu... (min. 3 znaki)"
                            class="w-full border rounded px-3 py-2 mb-2"
                        >
                        
                        <!-- Debug info -->
                        <div class="text-xs text-gray-500 mb-2">
                            Debug: Szukane wyrażenie: "{{ $modalSearchTerm }}" | 
                            Liczba wyników: {{ count($modalExistingResults) }}
                        </div>
                        
                        @if (!empty($modalExistingResults))
                            <div class="max-h-32 overflow-y-auto border rounded bg-white" wire:key="search-results">
                                @foreach ($modalExistingResults as $result)
                                    <div 
                                        wire:key="result-{{ $result['id'] }}"
                                        wire:click="selectExisting({{ $result['id'] }})"
                                        class="p-2 hover:bg-blue-100 cursor-pointer border-b last:border-b-0"
                                    >
                                        <div class="font-medium">{{ $result['name'] }}</div>
                                        @if(!empty($result['description']))
                                            <div class="text-sm text-gray-600">{{ $result['description'] }}</div>
                                        @endif
                                        <div class="text-xs text-gray-500">
                                            Czas: {{ $result['duration_hours'] }}h {{ $result['duration_minutes'] }}min | 
                                            Cena: {{ $result['unit_price'] ?? 0 }} zł
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @elseif(strlen($modalSearchTerm) >= 3)
                            <div class="text-sm text-gray-500 italic" wire:key="no-results">
                                Brak wyników dla "{{ $modalSearchTerm }}"
                            </div>
                        @endif
                        
                        @if ($modalSelectedExisting)
                            <div class="mt-2 p-2 bg-green-100 rounded text-sm">
                                ✓ Wybrano istniejący punkt. Możesz teraz zmodyfikować dane przed zapisem.
                            </div>
                        @endif
                    </div>
                @endif
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">Nazwa *</label>
                        <input type="text" wire:model.defer="modalName" class="w-full border rounded px-2 py-1 mb-2" required>
                        <label class="block text-sm font-medium mb-1">Opis</label>
                        <textarea wire:model.defer="modalDescription" class="w-full border rounded px-2 py-1 mb-2"></textarea>
                        <label class="block text-sm font-medium mb-1">Uwagi dla biura</label>
                        <textarea wire:model.defer="modalOfficeNotes" class="w-full border rounded px-2 py-1 mb-2"></textarea>
                        <label class="block text-sm font-medium mb-1">Uwagi dla pilota</label>
                        <textarea wire:model.defer="modalPilotNotes" class="w-full border rounded px-2 py-1 mb-2"></textarea>                        <label class="block text-sm font-medium mb-1">Tagi</label>
                        <select wire:model.defer="modalTags" multiple class="w-full border rounded px-2 py-1 mb-2">
                            @foreach (\App\Models\Tag::where('status', \App\Enums\Status::ACTIVE)->get() as $tag)
                                <option value="{{ $tag->id }}">{{ $tag->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Czas trwania (godziny) *</label>
                        <input type="number" wire:model.defer="modalDurationHours" class="w-full border rounded px-2 py-1 mb-2" min="0" required>
                        <label class="block text-sm font-medium mb-1">Czas trwania (minuty) *</label>
                        <input type="number" wire:model.defer="modalDurationMinutes" class="w-full border rounded px-2 py-1 mb-2" min="0" max="59" required>
                        <label class="block text-sm font-medium mb-1">Cena jednostkowa *</label>
                        <input type="number" wire:model.defer="modalUnitPrice" class="w-full border rounded px-2 py-1 mb-2" min="0" step="0.01" required>
                        <label class="block text-sm font-medium mb-1">Wielkość grupy</label>
                        <input type="number" wire:model.defer="modalGroupSize" class="w-full border rounded px-2 py-1 mb-2" min="1">                        <label class="block text-sm font-medium mb-1">Waluta *</label>
                        <select wire:model.defer="modalCurrencyId" class="w-full border rounded px-2 py-1 mb-2" required>
                            <option value="">Wybierz walutę</option>
                            @foreach (\App\Models\Currency::all() as $currency)
                                <option value="{{ $currency->id }}">{{ $currency->name }} ({{ $currency->symbol }})</option>
                            @endforeach
                        </select>
                        </select>
                        <label class="block text-sm font-medium mb-1">Przeliczaj na złotówki</label>
                        <input type="checkbox" wire:model.defer="modalConvertToPln" class="mr-2">Tak
                        <label class="block text-sm font-medium mb-1 mt-2">Zdjęcie wyróżniające</label>
                        <input type="file" wire:model="modalFeaturedImage" class="w-full border rounded px-2 py-1 mb-2">
                        <label class="block text-sm font-medium mb-1">Zdjęcia do galerii</label>
                        <input type="file" wire:model="modalGalleryImages" multiple class="w-full border rounded px-2 py-1 mb-2">
                    </div>
                </div>
                <div class="flex gap-2 justify-end mt-4">
                    <button wire:click="saveChild" class="px-4 py-1 bg-primary-600 text-white rounded">Zapisz</button>
                    <button wire:click="$set('showModal', false)" class="px-4 py-1 bg-gray-300 rounded">Anuluj</button>
                </div>
            </div>
        </div>
    @endif
</div>
