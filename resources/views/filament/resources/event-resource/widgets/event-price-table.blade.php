<x-filament-widgets::widget>
<div class="overflow-x-auto mt-2">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
        <h3 class="text-lg font-bold">Kalkulacja imprezy</h3>
        <div class="flex flex-wrap items-center gap-2">
            @if($record)
                <a href="{{ route('admin.events.calculation.pdf', $record) }}" target="_blank" class="fi-btn relative grid-flow-col items-center justify-center font-semibold outline-none transition duration-75 focus-visible:ring-2 rounded-lg fi-btn-size-sm fi-btn-color-gray px-3 py-2 text-sm inline-flex gap-1.5">
                    PDF
                </a>
                <a href="{{ route('admin.events.calculation.excel', $record) }}" target="_blank" class="fi-btn relative grid-flow-col items-center justify-center font-semibold outline-none transition duration-75 focus-visible:ring-2 rounded-lg fi-btn-size-sm fi-btn-color-gray px-3 py-2 text-sm inline-flex gap-1.5">
                    Excel
                </a>
            @endif
            <x-filament::button wire:click="refreshCalculations" color="primary" size="sm">
                Odśwież kalkulacje
            </x-filament::button>
        </div>
    </div>



    @if($record)
        <div class="mb-6 rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-amber-500/30 dark:bg-amber-500/10">
            <label class="flex items-center gap-3">
                <input type="checkbox" wire:model.live="useManualPricePerPerson"
                       class="h-5 w-5 rounded border-gray-300 text-amber-600 focus:ring-amber-500">
                <span class="text-sm font-semibold text-gray-900 dark:text-gray-100">Ustal ręcznie cenę za płacącego uczestnika</span>
            </label>
            <p class="mt-1 text-xs text-gray-600 dark:text-gray-400">
                Cena dotyczy uczestników płacących (bez opiekunów/dodatkowych, pilota i obsługi). Działa analogicznie do ręcznego kosztu transportu —
                wpisana kwota zastępuje cenę z kalkulacji i nie zostanie nadpisana przy „Przelicz”.
                @if(!empty($authoritativeCalc['current']['paying']))
                    Liczba płacących w bieżącej kalkulacji: <strong>{{ (int) $authoritativeCalc['current']['paying'] }}</strong>.
                @endif
            </p>

            <div class="mt-3 flex flex-wrap items-end gap-3">
                @if($useManualPricePerPerson)
                    <div>
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400">Cena za płacącego uczestnika (PLN)</label>
                        <input type="number" step="0.01" min="0" wire:model.live.debounce.500ms="manualPricePerPerson"
                               class="mt-1 w-40 rounded-lg border-gray-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500"
                               placeholder="np. 499.99">
                    </div>
                @endif
                <x-filament::button wire:click="saveManualPricePerPerson" color="warning" size="sm">
                    {{ $useManualPricePerPerson ? 'Zapisz cenę ręczną' : 'Przywróć cenę z kalkulacji' }}
                </x-filament::button>
            </div>
        </div>



        @php
            $includedEventPoints = ($programPoints ?? collect())
                ->filter(fn ($p) => (bool) ($p->include_in_calculation ?? true))
                ->values();
        @endphp

        <div class="mb-4 p-3 bg-blue-50 border border-blue-200 rounded text-xs text-blue-900">
            <b>Grupa bieżąca:</b>
            @if($currentVariant)
                {{ $currentVariant['qty'] }} uczestników + {{ $currentVariant['gratis'] }} opiek./dod.
            @else
                {{ (int) ($record->participant_count ?? 0) }} uczestników
            @endif
            @if(!empty($nearestVariants))
                <br><b>Najbliższe predefiniowane:</b>
                {{ collect($nearestVariants)->map(fn ($v) => $v['qty'] . '+' . $v['gratis'])->join(', ') }}
            @endif
        </div>

        @if(!empty($detailedCalculations))
            <div class="mb-8">
                <h4 class="text-md font-semibold mb-1">Szczegółowa kalkulacja kosztów (program imprezy)</h4>
                <p class="mb-4 text-xs text-gray-500 dark:text-gray-400">
                    Rozbicie na dni/pozycje z aktualnego programu imprezy (ceny i punkty tej imprezy).
                    Usunięte punkty nie wchodzą do kalkulacji. Plan hotelowy i transport liczone osobno —
                    zgodnie z oficjalnym kalkulatorem kosztów.
                </p>
                @include('partials.event-calculation-explanation')

                @include('partials.event-transport-summary', [
                    'record' => $record,
                    'transportCost' => $transportCost,
                    'eventTransportKm' => $eventTransportKm,
                ])

                @foreach($detailedCalculations as $qty => $currencies)
                    @php
                        $variant = $qtyVariants[$qty] ?? ['qty' => $qty, 'gratis' => 0, 'staff' => 0, 'driver' => 0];
                        $isCurrentVariant = $currentVariant
                            && (int) $currentVariant['qty'] === (int) $variant['qty']
                            && (int) $currentVariant['gratis'] === (int) $variant['gratis'];
                    @endphp

                    @include('partials.event-detailed-calculation-variant', [
                        'qty' => $qty,
                        'currencies' => $currencies,
                        'variant' => $variant,
                        'isCurrentVariant' => $isCurrentVariant,
                    ])
                @endforeach
            </div>
        @else
            <div class="rounded border border-yellow-300 bg-yellow-50 p-3 text-sm text-yellow-800">
                Brak szczegółowej kalkulacji dla tego eventu.
            </div>
        @endif

        @if(!empty($catalogVariantSummaries))
            <div class="mb-8 mt-8">
                <h4 class="text-md font-semibold mb-1">Porównanie wariantów ilości (katalog)</h4>
                <p class="mb-4 text-xs text-gray-500 dark:text-gray-400">
                    Warianty z globalnego katalogu (jak przy szablonach: 20, 25, 30…).
                    Podsumowanie liczone oficjalnym kalkulatorem imprezy. Pełne rozbicie ładuje się dopiero po rozwinięciu wiersza.
                </p>

                <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-800/60">
                            <tr>
                                <th class="px-3 py-2 text-left font-medium">Uczestnicy</th>
                                <th class="px-3 py-2 text-right font-medium">Opiek./obsł./kier.</th>
                                <th class="px-3 py-2 text-right font-medium">Cena / os.</th>
                                <th class="px-3 py-2 text-right font-medium">Suma grupy</th>
                                <th class="px-3 py-2 text-right font-medium w-28"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($catalogVariantSummaries as $row)
                                @php
                                    $rowQty = (int) $row['qty'];
                                    $isExpanded = $expandedCatalogQty === $rowQty;
                                    $extras = (int) $row['gratis'] + (int) $row['staff'] + (int) $row['driver'];
                                @endphp
                                <tr class="border-t border-gray-200 dark:border-gray-700 {{ $isExpanded ? 'bg-primary-50/40 dark:bg-primary-950/20' : '' }}">
                                    <td class="px-3 py-2 font-medium tabular-nums">
                                        {{ $rowQty }}
                                        @if(!empty($row['from_event_qty']))
                                            <span class="ml-1 text-xs font-normal text-gray-500">(z imprezy)</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-right tabular-nums text-gray-600 dark:text-gray-400">
                                        {{ (int) $row['gratis'] }} / {{ (int) $row['staff'] }} / {{ (int) $row['driver'] }}
                                        <span class="text-xs text-gray-400">(+{{ $extras }})</span>
                                    </td>
                                    <td class="px-3 py-2 text-right tabular-nums font-semibold">
                                        {{ \App\Support\MoneyFormatter::format($row['price_per_person_rounded'] ?? $row['price_per_person'], 'PLN') }}
                                    </td>
                                    <td class="px-3 py-2 text-right tabular-nums">
                                        {{ \App\Support\MoneyFormatter::format($row['total_pln'], 'PLN') }}
                                    </td>
                                    <td class="px-3 py-2 text-right">
                                        <x-filament::button
                                            wire:click="toggleCatalogVariant({{ $rowQty }})"
                                            color="gray"
                                            size="xs"
                                            wire:loading.attr="disabled"
                                        >
                                            {{ $isExpanded ? 'Zwiń' : 'Rozwiń' }}
                                        </x-filament::button>
                                    </td>
                                </tr>
                                @if($isExpanded)
                                    <tr class="border-t border-gray-100 dark:border-gray-800">
                                        <td colspan="5" class="px-3 py-4 bg-white dark:bg-gray-900">
                                            <div wire:loading wire:target="toggleCatalogVariant({{ $rowQty }})" class="mb-2 text-xs text-gray-500">
                                                Ładowanie szczegółów…
                                            </div>
                                            @if(!empty($catalogVariantDetails[$rowQty]))
                                                @include('partials.event-detailed-calculation-variant', [
                                                    'qty' => $rowQty,
                                                    'currencies' => $catalogVariantDetails[$rowQty],
                                                    'variant' => $catalogQtyVariants[$rowQty] ?? [
                                                        'qty' => $rowQty,
                                                        'gratis' => (int) $row['gratis'],
                                                        'staff' => (int) $row['staff'],
                                                        'driver' => (int) $row['driver'],
                                                    ],
                                                    'isCurrentVariant' => false,
                                                ])
                                            @else
                                                <p class="text-sm text-amber-700 dark:text-amber-300">
                                                    Brak szczegółowej kalkulacji dla tego wariantu (wymagany szablon imprezy).
                                                </p>
                                            @endif
                                        </td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    @else
        <div class="text-center py-8">
            <p class="text-gray-600 dark:text-gray-400">Brak danych do wyświetlenia</p>
        </div>
    @endif
</div>
</x-filament-widgets::widget>
