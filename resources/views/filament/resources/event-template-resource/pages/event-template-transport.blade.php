<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}

        <div class="flex justify-end mt-6">
            <x-filament::button
                type="submit"
                size="lg"
            >
                Zapisz zmiany
            </x-filament::button>
        </div>
    </form>


    <div class="mt-10">
        <h2 class="text-lg font-bold mb-4">Lista par odległości</h2>
        <table class="min-w-full bg-white border border-gray-200">
            <thead>
                <tr>
                    <th class="px-2 py-2 border text-sm">Dostępność</th>
                    <th class="px-2 py-2 border text-sm">Od (Startowe)</th>
                    <th class="px-2 py-2 border text-sm">Do (Początkowe)</th>
                    <th class="px-2 py-2 border text-sm">Odległość (km)</th>
                    <th class="px-2 py-2 border text-sm">Program km</th>
                    <th class="px-2 py-2 border text-sm">Od (Końcowe)</th>
                    <th class="px-2 py-2 border text-sm">Do (Startowe)</th>
                    <th class="px-2 py-2 border text-sm">Odległość (km)</th>
                    <th class="px-2 py-2 border text-sm">Suma (km)</th>
                    <th class="px-2 py-2 border text-sm">Ceny za osobę</th>
                    <th class="px-2 py-2 border text-sm">Notatki</th>
                    <th class="px-2 py-2 border text-sm">Kalkulacja</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $maxRows = max(count($toStartPairs), count($fromEndPairs));
                @endphp
                @for($i = 0; $i < $maxRows; $i++)
                    @php
                        $rawD1 = $toStartPairs[$i]['distance'] ?? null;
                        $rawD2 = $fromEndPairs[$i]['distance'] ?? null;
                        $d1 = (is_numeric($rawD1) ? (float)$rawD1 : 0);
                        $d2 = (is_numeric($rawD2) ? (float)$rawD2 : 0);
                        $programKm = (isset($record) && $record->program_km !== null && $record->program_km !== '') ? (float)$record->program_km : 0;
                        $sum = $d1 + $d2 + $programKm;
                        // "Posiadanie danych" rozumiemy jako: istnieje wpis (choćby 0 km) albo program_km jest zdefiniowane (może być 0)
                        // Poprzednio przy sumie 0 (dystans własny + brak program_km) przycisk kalkulacji zanikał mimo dostępności.
                        $hasAny = (
                            ($rawD1 !== null) || ($rawD2 !== null) || ($record?->program_km !== null)
                        );
                        $days = (isset($record) && is_numeric($record->duration_days) && $record->duration_days > 0) ? (int)$record->duration_days : null;
                        $kmPerDay = ($days && $sum) ? $sum / $days : null;
                        $availability = $toStartPairs[$i]['availability'] ?? null;
                    @endphp
                    <tr>
                        <td class="px-2 py-2 border text-center align-top">
                            <input type="checkbox" 
                                id="availability_{{ $record->id }}_{{ $toStartPairs[$i]['from']->id ?? 'null' }}_{{ $toStartPairs[$i]['to']->id ?? 'null' }}"
                                name="availability[{{ $record->id }}][{{ $toStartPairs[$i]['from']->id ?? 'null' }}][{{ $toStartPairs[$i]['to']->id ?? 'null' }}]"
                                @if($availability && $availability->available) checked @endif
                                wire:change="toggleAvailability({{ $record->id }}, {{ $toStartPairs[$i]['from']->id ?? 'null' }}, {{ $toStartPairs[$i]['to']->id ?? 'null' }}, $event.target.checked)"
                            >
                        </td>

                        @if(isset($toStartPairs[$i]))
                            <td class="px-2 py-2 border text-sm">{{ $toStartPairs[$i]['from']?->name ?? '-' }}</td>
                            <td class="px-2 py-2 border text-sm">{{ $toStartPairs[$i]['to']?->name ?? '-' }}</td>
                            <td class="px-2 py-2 border text-center text-sm">{{ $toStartPairs[$i]['distance'] ?? '-' }}</td>
                        @else
                            <td class="px-2 py-2 border text-sm">-</td>
                            <td class="px-2 py-2 border text-sm">-</td>
                            <td class="px-2 py-2 border text-center text-sm">-</td>
                        @endif

                        <td class="px-2 py-2 border text-center text-sm">{{ isset($record) && is_numeric($record->program_km) ? number_format($record->program_km, 2, ',', ' ') : '-' }}</td>

                        @if(isset($fromEndPairs[$i]))
                            <td class="px-2 py-2 border text-sm">{{ $fromEndPairs[$i]['from']?->name ?? '-' }}</td>
                            <td class="px-2 py-2 border text-sm">{{ $fromEndPairs[$i]['to']?->name ?? '-' }}</td>
                            <td class="px-2 py-2 border text-center text-sm">{{ $fromEndPairs[$i]['distance'] ?? '-' }}</td>
                        @else
                            <td class="px-2 py-2 border text-sm">-</td>
                            <td class="px-2 py-2 border text-sm">-</td>
                            <td class="px-2 py-2 border text-center text-sm">-</td>
                        @endif

                        <td class="px-2 py-2 border text-center text-sm font-semibold">
                            @if($hasAny)
                                @if($days)
                                    {{ number_format($sum, 2, ',', ' ') }} km/{{ $days }} dni<br>
                                    <span class="text-xs text-gray-600">{{ $sum > 0 ? number_format($kmPerDay, 2, ',', ' ') . ' km dziennie' : '0 km dziennie' }}</span>
                                @else
                                    {{ number_format($sum, 2, ',', ' ') }} km/- dni<br>
                                    <span class="text-xs text-gray-400">- km dziennie</span>
                                @endif
                            @else
                                -
                            @endif
                        </td>
                        
                        <td class="px-2 py-2 border text-xs">
                            @php
                                $startPlace = $toStartPairs[$i]['from'] ?? null;
                                $startPlaceId = $startPlace?->id;
                                $prices = ($startPlace && $startPlace->starting_place) ? ($pricesData[$startPlaceId] ?? []) : [];
                            @endphp
                            
                            @if(!empty($prices))
                                @foreach($prices as $priceInfo)
                                    <div class="mb-1">
                                        {{ $priceInfo['qty'] }} osób: {{ number_format($priceInfo['price_per_person'], 2) }} PLN
                                    </div>
                                @endforeach
                            @else
                                {{-- Pusto jeśli nie ma ceny --}}
                            @endif
                        </td>
                        
                        <td class="px-2 py-2 border">
                            <textarea class="w-full border rounded text-xs" rows="2" placeholder="Notatka..."
                                id="note_{{ $record->id }}_{{ $toStartPairs[$i]['from']->id ?? 'null' }}_{{ $toStartPairs[$i]['to']->id ?? 'null' }}"
                                name="note[{{ $record->id }}][{{ $toStartPairs[$i]['from']->id ?? 'null' }}][{{ $toStartPairs[$i]['to']->id ?? 'null' }}]"
                                wire:change="updateAvailabilityNote({{ $record->id }}, {{ $toStartPairs[$i]['from']->id ?? 'null' }}, {{ $toStartPairs[$i]['to']->id ?? 'null' }}, $event.target.value)"
                            >{{ $availability?->note }}</textarea>
                        </td>
                        
                        <td class="px-2 py-2 border text-center">
                            @if($availability && $availability->available)
                                @php
                                    // Jeśli brak realnych km (sum=0) nadal pokazujemy minimalną bazę kalkulacji (50 km narzutu) dla spójności UX
                                    $calculatedKm = 1.1 * $sum + 50; // zachowujemy dotychczasową formułę
                                @endphp
                                <a href="{{ static::getResource()::getUrl('calculation', ['record' => $record->id, 'start_place' => $toStartPairs[$i]['from']->id ?? null]) }}" 
                                   class="inline-flex items-center px-3 py-1 {{ $sum > 0 ? 'bg-blue-600 hover:bg-blue-700' : 'bg-slate-500 hover:bg-slate-600' }} text-white text-xs rounded transition-colors">
                                    {{ number_format($calculatedKm, 2, ',', ' ') }} km
                                </a>
                            @else
                                <span class="text-gray-300 text-xs">Niedostępne</span>
                            @endif
                        </td>
                    </tr>
                @endfor
                @if($maxRows == 0)
                    <tr>
                        <td colspan="12" class="px-4 py-2 border text-center text-gray-500">Brak danych do wyświetlenia</td>
                    </tr>
                @endif
            </tbody>
        </table>
    </div>


    <div id="distance-progress" class="mt-6 flex flex-col gap-2" style="display:none">
        <div class="flex items-center gap-4">
            <span id="distance-progress-status" class="text-sm text-gray-700">Status...</span>
        </div>
        <div id="distance-error-list" class="text-xs text-red-700"></div>
    </div>

    <x-filament-actions::modals />

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            let isCalculating = false;

            function showProgress(message, isVisible = true) {
                const progressDiv = document.getElementById('distance-progress');
                const statusSpan = document.getElementById('distance-progress-status');
                
                if (progressDiv) progressDiv.style.display = isVisible ? 'flex' : 'none';
                if (statusSpan) statusSpan.textContent = message;
            }

            // Słuchaj notyfikacji Filament
            window.addEventListener('filament-notify', function (e) {
                if (e.detail?.title && (e.detail.title.includes('Przeliczono kompletnie!') || e.detail.title.includes('Batch ukończony'))) {
                    let data = {};
                    try { 
                        data = JSON.parse(e.detail.body); 
                    } catch (ex) {
                        console.log('Błąd parsowania JSON:', ex);
                        return;
                    }
                    
                    console.log('Wyniki przeliczania:', data);
                    
                    // Pokaż wyniki
                    let message = `✅ Ukończono batch! Zapisano: ${data.updated || 0} odległości`;
                    if (data.skipped > 0) message += `, Pominięto: ${data.skipped}`;
                    if (data.errors && data.errors.length > 0) message += `, Błędy: ${data.errors.length}`;
                    if (data.success_rate) message += ` (${data.success_rate}% sukces)`;
                    if (data.remaining > 0) message += `, POZOSTAŁO: ${data.remaining}`;
                    if (data.timeout_reached) message += ` ⏰ Limit czasu`;
                    
                    showProgress(message, true);
                    
                    // Wyświetl błędy jeśli są
                    const errorList = document.getElementById('distance-error-list');
                    if (errorList && data.errors && data.errors.length > 0) {
                        errorList.innerHTML = '<div class="mb-2"><b>Błędy API:</b><ul style="margin:0;padding-left:1em">' + 
                            data.errors.map(e => `<li>${e.from} → ${e.to}: ${e.error}</li>`).join('') + '</ul></div>';
                    } else if (errorList) {
                        errorList.innerHTML = '';
                    }
                    
                    // Jeśli został przekroczony limit czasu i są pozostałe zadania, sugeruj kontynuację
                    if (data.timeout_reached && data.remaining > 0) {
                        setTimeout(() => {
                            showProgress(`⚠️ Pozostało ${data.remaining} odległości. Kliknij ponownie przycisk aby kontynuować.`, true);
                        }, 3000);
                    } else {
                        // Schowaj po 10 sekundach jeśli wszystko ukończone
                        setTimeout(() => {
                            showProgress('', false);
                        }, 10000);
                    }
                    
                    isCalculating = false;
                }
            });

            // Znajdź przycisk "Przelicz odległości" i dodaj obsługę
            setTimeout(() => {
                const origBtn = Array.from(document.querySelectorAll('button')).find(btn => 
                    btn.textContent && btn.textContent.includes('Przelicz odległości')
                );
                
                if (origBtn) {
                    // Dodaj obsługę kliknięcia
                    origBtn.addEventListener('click', function() {
                        if (!isCalculating) {
                            isCalculating = true;
                            showProgress('🔄 Przeliczam batch odległości (max 10 par na raz)...', true);
                        }
                    });
                    
                    // Zmień tekst przycisku
                    origBtn.textContent = 'Przelicz odległości (BATCH)';
                    origBtn.style.background = '#dc2626';
                    origBtn.style.color = 'white';
                }
            }, 500);
        });
    </script>
</x-filament-panels::page>
