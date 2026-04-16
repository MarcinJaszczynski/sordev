<x-filament-widgets::widget>
<div class="overflow-x-auto mt-2">
    <div class="mb-4 flex items-center justify-between">
        <h3 class="text-lg font-bold">Kalkulacja imprezy — algorytm jak w kalkulacji szablonu</h3>
        <x-filament::button wire:click="refreshCalculations" color="primary" size="sm">
            Odśwież kalkulacje
        </x-filament::button>
    </div>

    @if($record)
        <div class="mb-6 border border-gray-200 rounded-lg overflow-hidden">
            <div class="px-4 py-3 bg-gray-100 border-b border-gray-200 flex items-center justify-between gap-3">
                <div>
                    <h4 class="text-md font-semibold">Kalkulacja — pozycje cenowe</h4>
                    <p class="text-xs text-gray-600 mt-1">Dodawaj i edytuj ceny oraz koszty bezpośrednio w tej zakładce.</p>
                    <p class="text-xs text-gray-600 mt-1">
                        @if($currentVariant)
                            Grupa bieżąca: {{ $currentVariant['qty'] }} uczestników, {{ $currentVariant['gratis'] }} gratis, {{ $currentVariant['staff'] }} obsługa, {{ $currentVariant['driver'] }} kierowcy.
                        @else
                            Grupa bieżąca: {{ (int) ($record->participant_count ?? 0) }} uczestników.
                        @endif
                    </p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full bg-white text-sm">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="px-3 py-2 border-b text-left">ID</th>
                            <th class="px-3 py-2 border-b text-right">Ucz.</th>
                            <th class="px-3 py-2 border-b text-right">Gratis</th>
                            <th class="px-3 py-2 border-b text-right">Obsł.</th>
                            <th class="px-3 py-2 border-b text-right">Kier.</th>
                            <th class="px-3 py-2 border-b text-right">Cena/os</th>
                            <th class="px-3 py-2 border-b text-right">Transport</th>
                            <th class="px-3 py-2 border-b text-right">Baza</th>
                            <th class="px-3 py-2 border-b text-right">Narzut</th>
                            <th class="px-3 py-2 border-b text-right">Podatek</th>
                            <th class="px-3 py-2 border-b text-right">Cena z VAT</th>
                            <th class="px-3 py-2 border-b text-center">Akcje</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($priceRows as $priceRow)
                            @php
                                $isEditing = is_array($editingPrice) && (int) ($editingPrice['id'] ?? 0) === (int) $priceRow->id;
                                $variantQty = (int) ($priceRow->eventTemplateQty->qty ?? $currentVariant['qty'] ?? $record->participant_count ?? 0);
                                $variantGratis = (int) ($priceRow->eventTemplateQty->gratis ?? $currentVariant['gratis'] ?? 0);
                                $variantStaff = (int) ($priceRow->eventTemplateQty->staff ?? $currentVariant['staff'] ?? 0);
                                $variantDriver = (int) ($priceRow->eventTemplateQty->driver ?? $currentVariant['driver'] ?? 0);
                            @endphp
                            <tr>
                                <td class="px-3 py-2 border-b">{{ $priceRow->id }}</td>
                                <td class="px-3 py-2 border-b text-right">{{ $variantQty }}</td>
                                <td class="px-3 py-2 border-b text-right">{{ $variantGratis }}</td>
                                <td class="px-3 py-2 border-b text-right">{{ $variantStaff }}</td>
                                <td class="px-3 py-2 border-b text-right">{{ $variantDriver }}</td>

                                @if($isEditing)
                                    <td class="px-3 py-2 border-b"><input type="number" step="0.01" class="w-28 rounded border-gray-300" wire:model.defer="editingPrice.price_per_person"></td>
                                    <td class="px-3 py-2 border-b"><input type="number" step="0.01" class="w-28 rounded border-gray-300" wire:model.defer="editingPrice.transport_cost"></td>
                                    <td class="px-3 py-2 border-b"><input type="number" step="0.01" class="w-28 rounded border-gray-300" wire:model.defer="editingPrice.price_base"></td>
                                    <td class="px-3 py-2 border-b"><input type="number" step="0.01" class="w-28 rounded border-gray-300" wire:model.defer="editingPrice.markup_amount"></td>
                                    <td class="px-3 py-2 border-b"><input type="number" step="0.01" class="w-28 rounded border-gray-300" wire:model.defer="editingPrice.tax_amount"></td>
                                    <td class="px-3 py-2 border-b"><input type="number" step="0.01" class="w-28 rounded border-gray-300" wire:model.defer="editingPrice.price_with_tax"></td>
                                    <td class="px-3 py-2 border-b text-center">
                                        <div class="inline-flex items-center gap-1">
                                            <x-filament::button wire:click="saveEditingPrice" color="success" size="xs">Zapisz</x-filament::button>
                                            <x-filament::button wire:click="$set('editingPrice', null)" color="gray" size="xs">Anuluj</x-filament::button>
                                        </div>
                                    </td>
                                @else
                                    <td class="px-3 py-2 border-b text-right">{{ number_format((float) ($priceRow->price_per_person ?? 0), 2) }}</td>
                                    <td class="px-3 py-2 border-b text-right">{{ $priceRow->transport_cost !== null ? number_format((float) $priceRow->transport_cost, 2) : '—' }}</td>
                                    <td class="px-3 py-2 border-b text-right">{{ $priceRow->price_base !== null ? number_format((float) $priceRow->price_base, 2) : '—' }}</td>
                                    <td class="px-3 py-2 border-b text-right">{{ $priceRow->markup_amount !== null ? number_format((float) $priceRow->markup_amount, 2) : '—' }}</td>
                                    <td class="px-3 py-2 border-b text-right">{{ $priceRow->tax_amount !== null ? number_format((float) $priceRow->tax_amount, 2) : '—' }}</td>
                                    <td class="px-3 py-2 border-b text-right font-semibold">{{ $priceRow->price_with_tax !== null ? number_format((float) $priceRow->price_with_tax, 2) : '—' }}</td>
                                    <td class="px-3 py-2 border-b text-center">
                                        <div class="inline-flex items-center gap-1">
                                            <x-filament::button wire:click="editPrice({{ $priceRow->id }})" color="warning" size="xs">Edytuj</x-filament::button>
                                            <x-filament::button wire:click="deletePrice({{ $priceRow->id }})" color="danger" size="xs">Usuń</x-filament::button>
                                        </div>
                                    </td>
                                @endif
                            </tr>

                            @if($isEditing)
                                <tr>
                                    <td colspan="12" class="px-3 py-2 border-b bg-gray-50">
                                        <label class="text-xs font-medium text-gray-600">Rozbicie VAT (JSON)</label>
                                        <textarea rows="2" class="w-full rounded border-gray-300 mt-1" wire:model.defer="editingPrice.tax_breakdown"></textarea>
                                    </td>
                                </tr>
                            @endif
                        @empty
                            <tr>
                                <td colspan="12" class="px-3 py-4 border-b text-center text-gray-500">Brak pozycji kalkulacji.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
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
                <h4 class="text-md font-semibold mb-4">Szczegółowa kalkulacja kosztów</h4>
                <div class="mb-4 p-3 bg-blue-50 border border-blue-200 rounded text-xs text-blue-900">
                    <b>Wyjaśnienie:</b> Koszt całkowity liczony jest dla sumy: <b>uczestnicy + gratis + obsługa + kierowcy</b>.<br>
                    <b>Cena za osobę</b> to koszt całkowity podzielony przez liczbę uczestników (bez gratis, obsługi i kierowców).<br>
                    <b>Wielkość grupy</b> oznacza ile osób przypada na jedną jednostkę ceny punktu programu.
                </div>

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
                                <h6 class="font-medium text-green-700 mb-2">Noclegi:</h6>
                                @foreach($currencies['hotel_structure'] as $hotelDay)
                                    <div class="mb-2">
                                        <b>Nocleg {{ $hotelDay['day'] }}:</b>
                                        <div class="overflow-x-auto">
                                            <table class="min-w-full bg-white border border-gray-200 text-sm mb-2">
                                                <thead>
                                                    <tr class="bg-green-100">
                                                        <th class="px-3 py-2 border-b text-left">Pokój</th>
                                                        <th class="px-3 py-2 border-b text-right">Uczestnicy</th>
                                                        <th class="px-3 py-2 border-b text-right">Gratis</th>
                                                        <th class="px-3 py-2 border-b text-right">Obsługa</th>
                                                        <th class="px-3 py-2 border-b text-right">Kierowcy</th>
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
                                                            $key = $roomInfo['room']->name . '|' . $roomInfo['room']->people_count . '|' . $roomInfo['cost'] . '|' . $roomInfo['currency'];
                                                            if (!isset($roomSummary[$key])) {
                                                                $roomSummary[$key] = [
                                                                    'room' => $roomInfo['room'],
                                                                    'qty' => 0,
                                                                    'gratis' => 0,
                                                                    'staff' => 0,
                                                                    'driver' => 0,
                                                                    'cost' => $roomInfo['cost'],
                                                                    'currency' => $roomInfo['currency'],
                                                                ];
                                                            }
                                                            if (($roomInfo['alloc']['qty'] ?? 0) > 0) $roomSummary[$key]['qty']++;
                                                            if (($roomInfo['alloc']['gratis'] ?? 0) > 0) $roomSummary[$key]['gratis']++;
                                                            if (($roomInfo['alloc']['staff'] ?? 0) > 0) $roomSummary[$key]['staff']++;
                                                            if (($roomInfo['alloc']['driver'] ?? 0) > 0) $roomSummary[$key]['driver']++;
                                                        }
                                                    @endphp

                                                    @foreach($roomSummary as $room)
                                                        @php
                                                            $totalRooms = $room['qty'] + $room['gratis'] + $room['staff'] + $room['driver'];
                                                            $totalCost = $totalRooms * $room['cost'];
                                                        @endphp
                                                        <tr>
                                                            <td class="px-3 py-2 border-b">{{ $room['room']->name }} ({{ $room['room']->people_count }} os.)</td>
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
                                                            @if(is_numeric($point['unit_price']))
                                                                {{ number_format($point['unit_price'], 2) }} {{ $point['currency_symbol'] ?? $currencyCode }}
                                                            @else
                                                                {{ $point['unit_price'] }}
                                                            @endif
                                                        </td>
                                                        <td class="px-3 py-2 border-b text-right">{{ $point['group_size'] }} osób</td>
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
