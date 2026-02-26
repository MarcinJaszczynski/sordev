@php
    use Illuminate\Support\Facades\Storage;
    
    // Security validation - Check user authentication
    if (!auth()->check()) {
        abort(401, 'Unauthorized access');
    }
    
    // Security headers for XSS protection
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
@endphp

<div wire:init="loadChildren" x-data="{ 
    csrfToken: '{{ csrf_token() }}',
    rateLimitExceeded: false,
    lastActionTime: 0
}"
x-init="
    // Rate limiting protection
    $watch('rateLimitExceeded', value => {
        if (value) {
            setTimeout(() => { rateLimitExceeded = false; }, 60000);
        }
    });
"
class="security-protected-component">
    <!-- Notifications container -->
    <div id="children-notifications" class="fixed top-4 right-4 z-50" role="alert" aria-live="polite"></div>

    <!-- Security validation warnings -->
    @if(session('security_warning'))
        <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-md mb-4" role="alert">
            <div class="flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.5 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                </svg>
                <strong>Ostrzeżenie bezpieczeństwa:</strong> {{ session('security_warning') }}
            </div>
        </div>
    @endif

    <div class="bg-white shadow rounded-lg p-4 fi-section-content">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold text-gray-700">
                Podpunkty programu: {{ Str::limit(e($programPoint->name), 50) }}
                <span class="text-sm text-gray-500 ml-2">({{ count($children) }} {{ Str::plural('element', count($children), ['element', 'elementy', 'elementów']) }})</span>
            </h3>
            <button 
                wire:click="showAddModal"
                x-on:click="
                    if (rateLimitExceeded) {
                        $dispatch('notify', { message: 'Zbyt częste żądania. Spróbuj ponownie za chwilę.', type: 'error' });
                        return;
                    }
                    if (Date.now() - lastActionTime < 1000) {
                        rateLimitExceeded = true;
                        $dispatch('notify', { message: 'Zbyt częste żądania. Spróbuj ponownie za chwilę.', type: 'error' });
                        return;
                    }
                    lastActionTime = Date.now();
                "
                class="fi-btn fi-btn-color-primary hover:bg-primary-700 transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2"
                aria-label="Dodaj nowy podpunkt programu">
                <x-heroicon-o-plus-circle class="w-5 h-5 mr-2" />
                Dodaj podpunkt
            </button>
        </div>

        <ul class="children-list space-y-2 min-h-[100px] border border-dashed border-gray-300 p-2 rounded-md" 
            id="children-container" 
            data-csrf-token="{{ csrf_token() }}">
            @forelse ($children as $index => $child)
                <li class="bg-gray-100 p-3 rounded-md shadow-sm border border-gray-100 flex items-start" 
                    data-child-id="{{ (int)$child['id'] }}"
                    data-index="{{ $index }}">

                    <!-- Drag Handle -->
                    <div class="w-12 pr-2 py-1 cursor-grab drag-handle self-center flex-shrink-0">
                        <x-heroicon-o-bars-3 class="w-5 h-5 text-gray-400" />
                    </div>                    <!-- Featured Image -->
                    <div class="w-20 pr-2 py-1 border-r border-gray-200 flex-shrink-0">
                        @if(!empty($child['featured_image']) && is_string($child['featured_image']) && \Illuminate\Support\Facades\Storage::exists($child['featured_image']))
                            @php
                                $imageUrl = Storage::url($child['featured_image']);
                                $safeImageUrl = filter_var($imageUrl, FILTER_SANITIZE_URL);
                            @endphp
                            <img src="{{ $safeImageUrl }}?t={{ time() }}" 
                                 alt="Zdjęcie wyróżniające dla: {{ e($child['name']) }}" 
                                 class="h-16 w-16 object-cover rounded-lg shadow-sm border border-gray-200"
                                 loading="lazy">
                        @else
                            <div class="h-16 w-16 bg-gradient-to-br from-gray-100 to-gray-200 rounded-lg flex items-center justify-center border border-gray-200">
                                <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                </svg>
                            </div>
                        @endif
                    </div>

                    <!-- Col 2: Name, Duration, Office Notes -->
                    <div class="w-72 px-2 py-1 border-r border-gray-200 flex-shrink-0">
                        <div class="font-medium text-gray-700">{{ e($child['name']) }}</div>
                        @if(isset($child['duration_hours']) || isset($child['duration_minutes']))
                            <div class="text-xs text-gray-600 mt-1">
                                Czas trwania: {{ sprintf('%02d:%02d', (int)($child['duration_hours'] ?? 0), (int)($child['duration_minutes'] ?? 0)) }}
                            </div>
                        @endif
                        @if(!empty($child['office_notes']) && is_string($child['office_notes']))
                            <div class="text-xs text-blue-600 italic mt-1">
                                Uwagi dla biura: {!! strip_tags(Str::limit($child['office_notes'], 100)) !!}
                            </div>
                        @endif
                    </div>

                    <!-- Description -->
                    <div class="w-72 px-2 py-1 border-r border-gray-200 flex-shrink-0">
                        @if(!empty($child['description']) && is_string($child['description']))
                            <div class="text-xs text-gray-600">{!! Str::limit(strip_tags($child['description']), 150) !!}</div>
                        @else
                            <p class="text-xs text-gray-400 italic">Brak opisu.</p>
                        @endif
                    </div>                    <!-- Gallery -->
                    <div class="w-48 px-2 py-1 border-r border-gray-200 flex-shrink-0">
                        @if(!empty($child['gallery_images']) && is_array($child['gallery_images']))
                            <div class="flex flex-wrap gap-1">
                                @foreach(array_slice($child['gallery_images'], 0, 4) as $imageIndex => $image)
                                    @if(is_string($image) && \Illuminate\Support\Facades\Storage::exists($image))
                                        @php
                                            $galleryImageUrl = Storage::url($image);
                                            $safeGalleryImageUrl = filter_var($galleryImageUrl, FILTER_SANITIZE_URL);
                                        @endphp
                                        <img src="{{ $safeGalleryImageUrl }}?t={{ time() }}" 
                                             alt="Miniaturka galerii {{ $imageIndex + 1 }}" 
                                             class="h-8 w-8 object-cover rounded"
                                             loading="lazy">
                                    @endif
                                @endforeach
                                @if(count($child['gallery_images']) > 4)
                                    <span class="text-xs text-gray-500 self-center bg-gray-200 px-1 rounded">
                                        +{{ count($child['gallery_images']) - 4 }}
                                    </span>
                                @endif
                            </div>
                        @else
                            <p class="text-xs text-gray-400 italic">Brak galerii.</p>
                        @endif
                    </div>                    <!-- Tags -->
                    <div class="w-48 px-2 py-1 border-r border-gray-200 flex-shrink-0">
                        @if(!empty($child['tags']) && is_array($child['tags']))
                            <div class="flex flex-wrap gap-1">
                                @foreach($child['tags'] as $tag)
                                    @if(is_array($tag) && isset($tag['name']) && is_string($tag['name']))
                                        <span class="inline-block bg-orange-100 text-orange-800 rounded-full px-2 py-0.5 text-xs font-semibold">
                                            {{ e($tag['name']) }}
                                        </span>
                                    @endif
                                @endforeach
                            </div>
                        @else
                            <p class="text-xs text-gray-400 italic">Brak tagów.</p>
                        @endif
                    </div>

                    <!-- Actions -->
                    <div class="w-20 pl-2 py-1 flex items-center space-x-2 self-center flex-shrink-0">
                        <button 
                            wire:click="deleteChild({{ (int)$child['id'] }})" 
                            wire:confirm="Czy na pewno chcesz usunąć podpunkt '{{ e($child['name']) }}'?" 
                            x-on:click="
                                if (rateLimitExceeded) {
                                    $dispatch('notify', { message: 'Zbyt częste żądania.', type: 'error' });
                                    $event.stopPropagation();
                                    return false;
                                }
                                if (Date.now() - lastActionTime < 2000) {
                                    rateLimitExceeded = true;
                                    $dispatch('notify', { message: 'Zbyt częste żądania.', type: 'error' });
                                    $event.stopPropagation();
                                    return false;
                                }
                                lastActionTime = Date.now();
                            "
                            class="text-gray-400 hover:text-red-600 p-1 transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 rounded"
                            title="Usuń podpunkt: {{ e($child['name']) }}">
                            <x-heroicon-o-trash class="w-5 h-5" />
                        </button>
                    </div>
                </li>
            @empty
                <li class="text-center text-gray-400 py-4 italic">
                    <div class="flex flex-col items-center">
                        <svg class="w-12 h-12 text-gray-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        <p>Brak podpunktów programu.</p>
                        <p class="text-xs mt-1">Kliknij "Dodaj podpunkt" aby rozpocząć.</p>
                    </div>
                </li>
            @endforelse
        </ul>
    </div>    <!-- Modal -->
    @if($showModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" 
             role="dialog" 
             aria-modal="true"
             x-data="{ loadingChild: false }"
             x-init="document.body.style.overflow = 'hidden';"
             x-on:keydown.escape="$wire.closeModal()">
            <!-- Backdrop -->
            <div class="fixed inset-0 bg-black bg-opacity-50" wire:click="closeModal"></div>
            
            <!-- Modal Content -->
            <div class="flex min-h-screen items-center justify-center p-4">
                <div class="relative w-full max-w-6xl bg-white rounded-lg shadow-xl max-h-[95vh] flex flex-col">
                    <!-- Header -->
                    <div class="bg-white border-b px-6 py-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <h2 class="text-xl font-semibold text-gray-900">Dodaj podpunkt</h2>
                                <p class="text-sm text-gray-600 mt-1">
                                    Do punktu: <span class="font-medium">{{ e(Str::limit($programPoint->name, 60)) }}</span>
                                </p>
                            </div>
                            <button wire:click="closeModal" 
                                    class="text-gray-400 hover:text-gray-600 p-2 rounded-lg hover:bg-gray-100"
                                    type="button">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>
                    </div>
                    <!-- Search Section -->
                    <div class="bg-gradient-to-r from-blue-50 to-indigo-50 border-b px-6 py-4">
                        <div class="mb-3">
                            <label for="search-input" class="text-sm font-medium text-gray-700 mb-2 block">Wyszukaj punkt programu</label>
                            <input type="text" 
                                   id="search-input"
                                   wire:model.live.debounce.500ms="searchTerm" 
                                   placeholder="Wpisz nazwę, opis lub tag punktu programu..."
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg text-sm focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
                                   maxlength="100">
                        </div>
                        <div class="flex items-center justify-between text-xs text-gray-600">
                            <span>Szukaj po nazwie, opisie lub tagach</span>
                            <span class="bg-white px-2 py-1 rounded text-orange-700 font-medium border border-orange-200">
                                {{ count($filteredPoints) }} z {{ count($availablePoints) }} punktów
                            </span>
                        </div>
                    </div>

                    <!-- Points List -->
                    <div class="flex-1 overflow-y-auto px-6 py-4 bg-gray-50" style="max-height: 60vh;">
                        <p class="text-xs text-gray-500 mb-2">Kliknij wybrany punkt z listy, aby dodać go jako podpunkt.</p>
                        @forelse($filteredPoints as $point)
                            @if(is_array($point) && isset($point['id']) && is_numeric($point['id']))
                                <div class="bg-white border-2 rounded-xl mb-4 p-5 hover:shadow-lg transition-all cursor-pointer duration-200
                                    {{ $modalData['child_program_point_id'] == $point['id'] ? 'border-blue-500 shadow-lg bg-blue-50' : 'border-gray-200 hover:border-blue-300' }}"
                                    wire:click="$set('modalData.child_program_point_id', {{ (int)$point['id'] }})">
                                        
                                        <div class="flex items-start space-x-4">
                                            <!-- Radio button -->
                                            <div class="pt-1">
                                                <input type="radio" 
                                                       name="selected_point" 
                                                       value="{{ (int)$point['id'] }}"
                                                       {{ $modalData['child_program_point_id'] == $point['id'] ? 'checked' : '' }}
                                                       class="w-4 h-4 text-orange-600 border-2 border-gray-300"
                                                       readonly>
                                            </div>
                                            
                                            <!-- Image -->
                                            <div class="flex-shrink-0">
                                                @if(isset($point['featured_image']) && is_string($point['featured_image']) && $point['featured_image'] && \Illuminate\Support\Facades\Storage::exists($point['featured_image']))
                                                    @php
                                                        $pointImageUrl = Storage::url($point['featured_image']);
                                                        $safePointImageUrl = filter_var($pointImageUrl, FILTER_SANITIZE_URL);
                                                    @endphp
                                                    <img src="{{ $safePointImageUrl }}?t={{ time() }}" 
                                                         alt="Zdjęcie dla: {{ e($point['name'] ?? '') }}" 
                                                         class="h-16 w-16 rounded-lg object-cover border-2 border-gray-200"
                                                         loading="lazy">
                                                @else
                                                    <div class="h-16 w-16 bg-gradient-to-br from-gray-100 to-gray-200 rounded-lg flex items-center justify-center border-2 border-gray-200">
                                                        <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                                        </svg>
                                                    </div>
                                                @endif
                                            </div>

                                        <!-- Information -->
                                        <div class="flex-1 min-w-0">
                                            <!-- Name -->
                                            <h4 class="text-lg font-semibold text-gray-900 mb-3">
                                                {{ e($point['name'] ?? 'Nazwa niedostępna') }}
                                            </h4>
                                            
                                            <!-- Metadata -->
                                            <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-3">
                                                <!-- Time -->
                                                <div class="flex items-center space-x-2 bg-blue-50 px-3 py-2 rounded-lg">
                                                    <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                    </svg>
                                                    <span class="text-sm font-medium text-blue-800">
                                                        {{ sprintf('%02d:%02d', (int)($point['duration_hours'] ?? 0), (int)($point['duration_minutes'] ?? 0)) }}
                                                    </span>
                                                </div>
                                                
                                                <!-- Price -->
                                                <div class="flex items-center space-x-2 bg-green-50 px-3 py-2 rounded-lg">
                                                    <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                                                    </svg>
                                                    <span class="text-sm font-medium text-green-800">
                                                        {{ number_format((float)($point['unit_price'] ?? 0), 2) }} 
                                                        {{ e($point['currency']['symbol'] ?? 'PLN') }}
                                                    </span>
                                                </div>
                                                
                                                <!-- Group -->
                                                @if(isset($point['group_size']) && (int)$point['group_size'] > 1)
                                                    <div class="flex items-center space-x-2 bg-purple-50 px-3 py-2 rounded-lg">
                                                        <svg class="w-4 h-4 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                                        </svg>
                                                        <span class="text-sm font-medium text-purple-800">
                                                            Grupa {{ (int)$point['group_size'] }} osób
                                                        </span>
                                                    </div>
                                                @else
                                                    <div class="flex items-center space-x-2 bg-gray-50 px-3 py-2 rounded-lg">
                                                        <span class="text-sm font-medium text-gray-700">Cena za osobę</span>
                                                    </div>
                                                @endif
                                            </div>

                                            <!-- Description -->
                                            @if(isset($point['description']) && is_string($point['description']) && trim($point['description']))
                                                <div class="mb-3">
                                                    <div class="text-sm text-gray-700 bg-gray-50 p-3 rounded-lg border-l-4 border-gray-300">
                                                        {!! Str::limit(strip_tags($point['description']), 200) !!}
                                                    </div>
                                                </div>
                                            @endif

                                            <!-- Tags -->
                                            @if(isset($point['tags']) && is_array($point['tags']) && count($point['tags']) > 0)
                                                <div class="flex flex-wrap gap-2">
                                                    @foreach(array_slice($point['tags'], 0, 6) as $tag)
                                                        @if(is_array($tag) && isset($tag['name']) && is_string($tag['name']))
                                                            <span class="inline-flex items-center px-2.5 py-1 bg-orange-100 text-orange-800 rounded-md text-xs font-medium">
                                                                {{ e($tag['name']) }}
                                                            </span>
                                                        @endif
                                                    @endforeach
                                                    @if(count($point['tags']) > 6)
                                                        <span class="inline-flex items-center px-2.5 py-1 bg-gray-200 text-gray-700 rounded-md text-xs font-medium">
                                                            +{{ count($point['tags']) - 6 }} więcej
                                                        </span>
                                                    @endif
                                                </div>
                                            @endif
                                        </div>
                                        </div>
                                    </div>
                                @endif
                        @empty
                            <div class="text-center py-12 bg-white rounded-xl border-2 border-dashed border-gray-300">
                                <h3 class="text-lg font-medium text-gray-900 mb-2">Nie znaleziono punktów programu</h3>
                                <p class="text-gray-500 mb-4">
                                    @if($searchTerm && is_string($searchTerm))
                                        Brak wyników dla: <span class="font-medium">"{{ e(Str::limit($searchTerm, 50)) }}"</span>
                                    @else
                                        Brak dostępnych punktów programu
                                    @endif
                                </p>
                                @if($searchTerm)
                                    <button wire:click="$set('searchTerm', '')" 
                                            type="button" 
                                            class="inline-flex items-center px-4 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700">
                                        Wyczyść wyszukiwanie
                                    </button>
                                @endif
                            </div>
                        @endforelse
                    </div>

                    <!-- Footer -->
                    <div class="border-t bg-white px-6 py-4 flex justify-end items-center gap-4">
                        <button type="button" wire:click="closeModal" class="fi-btn fi-btn-outlined fi-btn-color-gray">
                            Anuluj
                        </button>
                        <button wire:click="saveChild" type="button"
                            x-bind:disabled="!$wire.modalData.child_program_point_id || loadingChild"
                            x-on:click="
                                if (loadingChild) return;
                                if (!$wire.modalData.child_program_point_id) return;
                                loadingChild = true;
                                </svg>
                            </template>
                            <span x-text="loadingChild ? 'Dodawanie...' : 'Dodaj podpunkt'">Dodaj podpunkt</span>
                        </button>
                    </div>

                </div>
            </div>
        </div>
    @endif

    @push('scripts')
    <script>
    // Prosty system z odświeżaniem przez Livewire
    document.addEventListener('livewire:initialized', () => {
        // Inicjalizacja Sortable po załadowaniu
        initializeChildrenSortable();
        
        // Nasłuchiwanie powiadomień - odśwież przez Livewire
        Livewire.on('notify', (data) => {
            const message = data && data.message ? data.message.replace(/<[^>]*>/g, '') : 'Brak treści';
            const type = ['success', 'error', 'warning', 'info'].includes(data.type) ? data.type : 'info';
            
            showNotification(message, type);
            
            // Po każdej operacji odśwież komponent
            if (type === 'success') {
                // Wymuś pełne odświeżenie komponentu
                setTimeout(() => {
                    window.Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id')).call('$refresh');
                }, 200);
            }
        });
        
        // Odśwież Sortable po aktualizacji Livewire
        Livewire.hook('morph.updated', () => {
            setTimeout(initializeChildrenSortable, 100);
        });
    });

    // Funkcja powiadomień
    function showNotification(message, type = 'info') {
        const container = document.getElementById('children-notifications');
        if (!container) return;

        const colors = {
            success: 'bg-green-50 text-green-800 border-green-200',
            error: 'bg-red-50 text-red-800 border-red-200',
            warning: 'bg-yellow-50 text-yellow-800 border-yellow-200',
            info: 'bg-blue-50 text-blue-800 border-blue-200'
        };

        const notification = document.createElement('div');
        notification.className = `mb-2 p-3 rounded-md border ${colors[type]} shadow-lg max-w-sm`;
        notification.innerHTML = `
            <div class="flex justify-between items-center">
                <span>${message}</span>
                <button onclick="this.parentElement.parentElement.remove()" class="ml-2 text-current opacity-50 hover:opacity-100">×</button>
            </div>
        `;
        
        container.appendChild(notification);
        
        setTimeout(() => {
            if (notification.parentElement) {
                notification.remove();
            }
        }, 5000);
    }

    // Inicjalizacja Sortable
    function initializeChildrenSortable() {
        const container = document.getElementById('children-container');
        if (!container) return;

        // Usuń poprzednią instancję
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
                const childElements = container.querySelectorAll('li[data-child-id]');
                const childIds = Array.from(childElements)
                    .map(li => parseInt(li.getAttribute('data-child-id'), 10))
                    .filter(id => id && id > 0);

                if (childIds.length > 0) {
                    const wireElement = container.closest('[wire\\:id]');
                    if (wireElement) {
                        const wireId = wireElement.getAttribute('wire:id');
                        const livewireComponent = Livewire.find(wireId);
                        if (livewireComponent) {
                            livewireComponent.call('updateChildrenOrder', childIds);
                        }
                    }
                }
            }
        });
    }
    </script>
    @endpush
</div>
