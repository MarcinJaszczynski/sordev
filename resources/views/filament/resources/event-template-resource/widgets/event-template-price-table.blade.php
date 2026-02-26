<div class="overflow-x-auto mt-8">
    <div class="mb-4 flex items-center gap-4">
        <h3 class="text-lg font-bold mb-2">Kalkulacja cen za osobę</h3>
        <x-filament::button wire:click="recalculatePrices" color="primary" size="sm">
            Przelicz ceny
        </x-filament::button>
    </div>

    <!-- Szczegółowa kalkulacja -->
    @if(!empty($this->detailedCalculations))
        <div class="mb-8">
            <h4 class="text-md font-semibold mb-4">Szczegółowa kalkulacja kosztów</h4>
            <div class="mb-4 p-3 bg-blue-50 border border-blue-200 rounded text-xs text-blue-900">
                <b>Wyjaśnienie:</b> Koszt całkowity liczony jest dla sumy: <b>uczestnicy + gratis + obsługa + kierowcy</b>.<br>
                <b>Cena za osobę</b> to koszt całkowity podzielony przez liczbę uczestników (bez gratis, obsługi i kierowców).<br>
                <b>Wielkość grupy</b> oznacza ile osób przypada na jedną jednostkę ceny punktu programu.
            </div>
            @foreach($this->detailedCalculations as $qty => $currencies)
                @php
                    $variant = $this->qtyVariants[$qty] ?? ['qty' => $qty, 'gratis' => 0, 'staff' => 0, 'driver' => 0];
                    $totalAll = $variant['qty'] + $variant['gratis'] + $variant['staff'] + $variant['driver'];
                @endphp
                <div class="mb-6 border border-gray-200 rounded-lg p-4">
                    <h5 class="font-medium text-gray-800 mb-3">
                        Wariant: {{ $variant['qty'] }} uczestników (plus {{ $variant['gratis'] }} gratis, {{ $variant['staff'] }} obsługa, {{ $variant['driver'] }} kierowców), razem: {{ $totalAll }} osób                    </h5>

                    {{-- Struktura noclegów --}}
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
                                                        if ($roomInfo['alloc']['qty'] > 0) $roomSummary[$key]['qty']++;
                                                        if ($roomInfo['alloc']['gratis'] > 0) $roomSummary[$key]['gratis']++;
                                                        if ($roomInfo['alloc']['staff'] > 0) $roomSummary[$key]['staff']++;
                                                        if ($roomInfo['alloc']['driver'] > 0) $roomSummary[$key]['driver']++;
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

                    {{-- Punkty programu --}}
                    @php
                        // Przygotuj listę walut do iteracji: pomiń klucze globalne
                        $currencyKeys = array_filter(array_keys($currencies), function($k) {
                            return !in_array($k, ['markup', 'taxes', 'hotel_structure']);
                        });
                    @endphp
                    @foreach($currencyKeys as $currencyCode)
                        @php($data = $currencies[$currencyCode] ?? [])
                        @php($currencySymbol = (isset($data['points']) && is_array($data['points']) && count($data['points']) > 0) ? ($data['points'][0]['currency_symbol'] ?? $currencyCode) : $currencyCode)
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
                                        @endif                                        <tr class="bg-gray-100 font-bold">
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
                                                {{ number_format(($data['total'] ?? 0) / $qty, 2) }} {{ $currencySymbol }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>                    @endforeach
                </div>
            @endforeach
        </div>
    @endif

    <!-- Tabela cen zapisanych w bazie danych -->
    @if($this->prices && $this->prices->count() > 0)
        <div class="mt-8">
            <h4 class="text-md font-semibold mb-4">Ceny zapisane w bazie danych</h4>
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white border border-gray-200 text-sm">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="px-4 py-3 border-b text-left">Ilość uczestników</th>
                            <th class="px-4 py-3 border-b text-left">Waluta</th>
                            <th class="px-4 py-3 border-b text-right">Cena bazowa</th>
                            <th class="px-4 py-3 border-b text-right">Narzut</th>
                            <th class="px-4 py-3 border-b text-right">Podatki</th>
                            <th class="px-4 py-3 border-b text-right">Cena z podatkiem</th>
                            <th class="px-4 py-3 border-b text-right">Cena za osobę</th>
                            <th class="px-4 py-3 border-b text-right">Transport</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($this->prices as $price)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 border-b">
                                    {{ $price->eventTemplateQty->qty ?? 'N/A' }} osób
                                </td>
                                <td class="px-4 py-3 border-b">
                                    {{ $price->currency->symbol ?? 'N/A' }}
                                </td>
                                <td class="px-4 py-3 border-b text-right">
                                    {{ number_format($price->price_base ?? 0, 2) }}
                                </td>
                                <td class="px-4 py-3 border-b text-right">
                                    {{ number_format($price->markup_amount ?? 0, 2) }}
                                </td>
                                <td class="px-4 py-3 border-b text-right">
                                    {{ number_format($price->tax_amount ?? 0, 2) }}
                                </td>
                                <td class="px-4 py-3 border-b text-right font-semibold">
                                    {{ number_format($price->price_with_tax ?? 0, 2) }}
                                </td>
                                <td class="px-4 py-3 border-b text-right font-bold text-blue-600">
                                    {{ number_format($price->price_per_person ?? 0, 2) }}
                                </td>
                                <td class="px-4 py-3 border-b text-right">
                                    {{ number_format($price->transport_cost ?? 0, 2) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
