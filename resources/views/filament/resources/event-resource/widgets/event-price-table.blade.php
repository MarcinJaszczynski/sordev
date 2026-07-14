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
                Cena dotyczy uczestników płacących (bez gratisów, pilota i obsługi). Działa analogicznie do ręcznego kosztu transportu —
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
                {{ $currentVariant['qty'] }} uczestników + {{ $currentVariant['gratis'] }} gratis
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
                <h4 class="text-md font-semibold mb-1">Szczegółowa kalkulacja kosztów (poglądowo, wg szablonu)</h4>
                <p class="mb-4 text-xs text-gray-500 dark:text-gray-400">
                    Rozbicie na dni/pozycje na bazie cen szablonu. Wiążąca jest „Kalkulacja (oficjalna)" na górze
                    oraz pozycje cennika — uwzględniają ceny ustalone dla tej imprezy i plan hotelowy.
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
                        $totalAll = $variant['qty'] + $variant['gratis'] + $variant['staff'] + $variant['driver'];
                        $isCurrentVariant = $currentVariant
                            && (int) $currentVariant['qty'] === (int) $variant['qty']
                            && (int) $currentVariant['gratis'] === (int) $variant['gratis'];
                    @endphp

                    <div class="mb-6 border rounded-lg p-4 {{ $isCurrentVariant ? 'border-primary-500 bg-blue-50/30' : 'border-gray-200' }}">
                        <h5 class="font-medium text-gray-800 mb-3">
                            Wariant: {{ $variant['qty'] }} uczestników (plus {{ $variant['gratis'] }} gratis, {{ $variant['staff'] }} obsługa, {{ $variant['driver'] }} kierowców), razem: {{ $totalAll }} osób
                            @if($isCurrentVariant)
                                <span class="ml-2 text-xs text-primary-700">(bieżąca grupa imprezy)</span>
                            @endif
                        </h5>



                        @if(isset($currencies['hotel_structure']))
                            <div class="mb-4">
                                <h6 class="font-medium text-green-700 mb-2">Hotele:</h6>
                                @foreach($currencies['hotel_structure'] as $hotelDay)
                                    <div class="mb-2">
                                        <b>Nocleg {{ $hotelDay['day'] }}:</b>
                                        <div class="overflow-x-auto">
                                            <table class="min-w-full bg-white border border-gray-200 text-sm mb-2">
                                                <thead>
                                                    <tr class="bg-green-100">
                                                        <th class="px-3 py-2 border-b text-left">Pokój</th>
                                                        <th class="px-3 py-2 border-b text-right">Pokoje (ucz.)</th>
                                                        <th class="px-3 py-2 border-b text-right">Pokoje (gratis)</th>
                                                        <th class="px-3 py-2 border-b text-right">Pokoje (obsługa)</th>
                                                        <th class="px-3 py-2 border-b text-right">Pokoje (kier.)</th>
                                                        <th class="px-3 py-2 border-b text-right">Cena (za pokój)</th>
                                                        <th class="px-3 py-2 border-b text-right">Łącznie</th>
                                                        <th class="px-3 py-2 border-b text-right">Waluta</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @php
                                                        $roomSummary = [];
                                                        $warnings = [];
                                                        foreach($hotelDay['rooms'] as $roomInfo) {
                                                            if (isset($roomInfo['warning'])) {
                                                                $warnings[] = $roomInfo['warning'] . ' Liczba osób: ' . $roomInfo['total_people'];
                                                                continue;
                                                            }
                                                            $roomModel = $roomInfo['room'] ?? null;
                                                            if (! $roomModel) {
                                                                $warnings[] = 'Brak typu pokoju (hotel_rooms) dla pozycji w strukturze noclegów. Liczba osób: ' . ($roomInfo['total_people'] ?? '?');
                                                                continue;
                                                            }
                                                            $key = $roomModel->name . '|' . $roomModel->people_count . '|' . $roomInfo['cost'] . '|' . $roomInfo['currency'];
                                                            if (!isset($roomSummary[$key])) {
                                                                $roomSummary[$key] = [
                                                                    'room' => $roomModel,
                                                                    'qty' => 0,
                                                                    'gratis' => 0,
                                                                    'staff' => 0,
                                                                    'driver' => 0,
                                                                    'cost' => $roomInfo['cost'],
                                                                    'currency' => $roomInfo['currency'],
                                                                ];
                                                            }
                                                            $roomSummary[$key]['qty'] += (int) ($roomInfo['alloc']['qty'] ?? 0);
                                                            $roomSummary[$key]['gratis'] += (int) ($roomInfo['alloc']['gratis'] ?? 0);
                                                            $roomSummary[$key]['staff'] += (int) ($roomInfo['alloc']['staff'] ?? 0);
                                                            $roomSummary[$key]['driver'] += (int) ($roomInfo['alloc']['driver'] ?? 0);
                                                        }
                                                    @endphp

                                                    @foreach($roomSummary as $room)
                                                        @php
                                                            $totalRooms = $room['qty'] + $room['gratis'] + $room['staff'] + $room['driver'];
                                                            $totalCost = $totalRooms * $room['cost'];
                                                        @endphp
                                                        <tr>
                                                            <td class="px-3 py-2 border-b">{{ $room['room']->name ?? '—' }} ({{ $room['room']->people_count ?? '?' }} os.)</td>
                                                            <td class="px-3 py-2 border-b text-right">{{ $room['qty'] }}</td>
                                                            <td class="px-3 py-2 border-b text-right">{{ $room['gratis'] }}</td>
                                                            <td class="px-3 py-2 border-b text-right">{{ $room['staff'] }}</td>
                                                            <td class="px-3 py-2 border-b text-right">{{ $room['driver'] }}</td>
                                                            <td class="px-3 py-2 border-b text-right">{{ number_format($room['cost'], 2) }}</td>
                                                            <td class="px-3 py-2 border-b text-right">{{ number_format($totalCost, 2) }}</td>
                                                            <td class="px-3 py-2 border-b text-right">{{ $room['currency'] }}</td>
                                                        </tr>
                                                    @endforeach

                                                    @if(!empty($warnings))
                                                        @foreach($warnings as $warning)
                                                            <tr>
                                                                <td colspan="8" class="px-3 py-2 border-b text-red-700 bg-red-50 text-center font-semibold">{{ $warning }}</td>
                                                            </tr>
                                                        @endforeach
                                                    @endif
                                                </tbody>
                                            </table>
                                        </div>
                                        <div class="text-xs text-gray-600 mt-1">Suma za nocleg:
                                            @foreach($hotelDay['day_total'] as $cur => $val)
                                                <span class="mr-2">{{ number_format($val, 2) }} {{ $cur }}</span>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        @php
                            $currencyKeys = array_filter(array_keys($currencies), function($k) {
                                return !in_array($k, ['markup', 'taxes', 'hotel_structure']);
                            });
                        @endphp

                        @foreach($currencyKeys as $currencyCode)
                            @php
                                $data = $currencies[$currencyCode] ?? [];
                                $currencySymbol = (isset($data['points']) && is_array($data['points']) && count($data['points']) > 0)
                                    ? ($data['points'][0]['currency_symbol'] ?? $currencyCode)
                                    : $currencyCode;
                            @endphp
                            <div class="mb-4">
                                <h6 class="font-medium text-blue-600 mb-2">Waluta: {{ $currencyCode }} ({{ $currencySymbol }})</h6>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full bg-gray-50 border border-gray-200 text-sm">
                                        <thead>
                                            <tr class="bg-gray-100">
                                                <th class="px-3 py-2 border-b text-left">Punkt programu</th>
                                                <th class="px-3 py-2 border-b text-right">Cena jednostkowa (za grupę)</th>
                                                <th class="px-3 py-2 border-b text-right">dla grupy</th>
                                                <th class="px-3 py-2 border-b text-right">Koszt całkowity (dla wszystkich)</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @if(isset($data['points']) && is_array($data['points']) && count($data['points']) > 0)
                                                @foreach($data['points'] as $point)
                                                    <tr class="{{ $point['is_child'] ? 'bg-blue-50' : '' }}">
                                                        <td class="px-3 py-2 border-b {{ $point['is_child'] ? 'text-blue-700 pl-6' : 'font-medium' }}">
                                                            {{ $point['name'] }}
                                                        </td>
                                                        <td class="px-3 py-2 border-b text-right">
                                                            @php $unitPrice = $point['unit_price'] ?? null; @endphp
                                                            @if($unitPrice !== null && $unitPrice !== '' && is_numeric($unitPrice))
                                                                {{ number_format((float) $unitPrice, 2) }} {{ $point['currency_symbol'] ?? $currencyCode }}
                                                            @elseif($unitPrice !== null && $unitPrice !== '')
                                                                {{ $unitPrice }}
                                                            @else
                                                                —
                                                            @endif
                                                        </td>
                                                        <td class="px-3 py-2 border-b text-right">
                                                            @if(isset($point['group_size']) && $point['group_size'] !== null && $point['group_size'] !== '')
                                                                {{ $point['group_size'] }} osób
                                                            @else
                                                                —
                                                            @endif
                                                        </td>
                                                        <td class="px-3 py-2 border-b text-right font-semibold">
                                                            {{ number_format($point['cost'], 2) }} {{ $point['currency_symbol'] ?? $currencyCode }}
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            @else
                                                <tr>
                                                    <td class="px-3 py-2 border-b text-center text-gray-400" colspan="4">
                                                        Brak pozycji kosztowych dla tej waluty.
                                                    </td>
                                                </tr>
                                            @endif

                                            <tr class="bg-gray-100 font-bold">
                                                <td class="px-3 py-2 border-t-2 border-gray-400" colspan="3">SUMA dla {{ $currencyCode }} (bez narzutu):</td>
                                                <td class="px-3 py-2 border-t-2 border-gray-400 text-right">
                                                    {{ number_format($data['total_before_markup'] ?? $data['total'] ?? 0, 2) }} {{ $currencySymbol }}
                                                </td>
                                            </tr>

                                            @if($currencyCode === 'PLN' && isset($currencies['markup']) && $currencies['markup']['amount'] > 0)
                                                <tr class="bg-yellow-100 font-semibold">
                                                    <td class="px-3 py-2" colspan="3">
                                                        Narzut ({{ number_format($currencies['markup']['percent_applied'], 2) }}%):
                                                        @if($currencies['markup']['discount_applied'])
                                                            <span class="text-green-600 text-sm">(z rabatem {{ $currencies['markup']['discount_percent'] }}%)</span>
                                                        @endif
                                                        @if($currencies['markup']['min_daily_applied'])
                                                            <span class="text-orange-600 text-sm">(minimum dzienne)</span>
                                                        @endif
                                                    </td>
                                                    <td class="px-3 py-2 text-right">
                                                        {{ number_format($currencies['markup']['amount'], 2) }} PLN
                                                    </td>
                                                </tr>

                                                @if(isset($currencies['taxes']) && $currencies['taxes']['total_amount'] > 0)
                                                    @foreach($currencies['taxes']['breakdown'] as $tax)
                                                        <tr class="bg-orange-50 font-medium">
                                                            <td class="px-3 py-2" colspan="3">
                                                                {{ $tax['name'] }} ({{ number_format($tax['percentage'], 2) }}%):
                                                                <span class="text-xs text-gray-600">
                                                                    @if($tax['apply_to_base'] && $tax['apply_to_markup'])
                                                                        (od podstawy i narzutu)
                                                                    @elseif($tax['apply_to_base'])
                                                                        (od podstawy)
                                                                    @elseif($tax['apply_to_markup'])
                                                                        (od narzutu)
                                                                    @endif
                                                                </span>
                                                            </td>
                                                            <td class="px-3 py-2 text-right">
                                                                {{ number_format($tax['amount'], 2) }} PLN
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                    <tr class="bg-orange-100 font-semibold">
                                                        <td class="px-3 py-2" colspan="3">Suma podatków:</td>
                                                        <td class="px-3 py-2 text-right">
                                                            {{ number_format($currencies['taxes']['total_amount'], 2) }} PLN
                                                        </td>
                                                    </tr>
                                                @endif

                                                <tr class="bg-green-100 font-bold">
                                                    <td class="px-3 py-2" colspan="3">SUMA KOŃCOWA dla {{ $currencyCode }}:</td>
                                                    <td class="px-3 py-2 text-right">
                                                        {{ number_format($data['total'] ?? 0, 2) }} {{ $currencySymbol }}
                                                    </td>
                                                </tr>
                                            @elseif($currencyCode !== 'PLN' && isset($data['markup_amount']) && $data['markup_amount'] > 0)
                                                <tr class="bg-yellow-100 font-semibold">
                                                    <td class="px-3 py-2" colspan="3">
                                                        Narzut ({{ number_format($currencies['markup']['percent_applied'] ?? 15, 2) }}%):
                                                    </td>
                                                    <td class="px-3 py-2 text-right">
                                                        {{ number_format($data['markup_amount'], 2) }} {{ $currencySymbol }}
                                                    </td>
                                                </tr>
                                                @if(isset($data['tax_amount']) && $data['tax_amount'] > 0)
                                                    <tr class="bg-orange-50 font-medium">
                                                        <td class="px-3 py-2" colspan="3">
                                                            Podatki:
                                                        </td>
                                                        <td class="px-3 py-2 text-right">
                                                            {{ number_format($data['tax_amount'], 2) }} {{ $currencySymbol }}
                                                        </td>
                                                    </tr>
                                                @endif
                                                <tr class="bg-green-100 font-bold">
                                                    <td class="px-3 py-2" colspan="3">SUMA KOŃCOWA dla {{ $currencyCode }}:</td>
                                                    <td class="px-3 py-2 text-right">
                                                        {{ number_format($data['total'] ?? 0, 2) }} {{ $currencySymbol }}
                                                    </td>
                                                </tr>
                                            @else
                                                <tr class="bg-green-100 font-bold">
                                                    <td class="px-3 py-2" colspan="3">SUMA KOŃCOWA dla {{ $currencyCode }}:</td>
                                                    <td class="px-3 py-2 text-right">
                                                        {{ number_format($data['total'] ?? 0, 2) }} {{ $currencySymbol }}
                                                    </td>
                                                </tr>
                                            @endif

                                            <tr class="bg-blue-100 font-bold">
                                                <td class="px-3 py-2" colspan="3">Cena za osobę (uczestnik):</td>
                                                <td class="px-3 py-2 text-right">
                                                    {{ number_format(($data['total'] ?? 0) / max(1, $qty), 2) }} {{ $currencySymbol }}
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>
        @else
            <div class="rounded border border-yellow-300 bg-yellow-50 p-3 text-sm text-yellow-800">
                Brak szczegółowej kalkulacji dla tego eventu.
            </div>
        @endif
    @else
        <div class="text-center py-8">
            <p class="text-gray-600 dark:text-gray-400">Brak danych do wyświetlenia</p>
        </div>
    @endif
</div>
</x-filament-widgets::widget>
