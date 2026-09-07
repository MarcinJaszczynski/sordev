@props([
    'qty',
    'currencies',
    'variant' => null,
    'isCurrentVariant' => false,
    'showHeading' => true,
])

@php
    $variant = $variant ?? ['qty' => $qty, 'gratis' => 0, 'staff' => 0, 'driver' => 0];
    $totalAll = (int) $variant['qty'] + (int) $variant['gratis'] + (int) $variant['staff'] + (int) $variant['driver'];
@endphp

<div class="mb-6 border rounded-lg p-4 {{ $isCurrentVariant ? 'border-primary-500 bg-blue-50/30' : 'border-gray-200' }}">
    @if($showHeading)
        <h5 class="font-medium text-gray-800 mb-3">
            Wariant: {{ $variant['qty'] }} uczestników (plus {{ $variant['gratis'] }} opiekunów/dodatkowych, {{ $variant['staff'] }} obsługa, {{ $variant['driver'] }} kierowców), razem: {{ $totalAll }} osób
            @if($isCurrentVariant)
                <span class="ml-2 text-xs text-primary-700">(bieżąca grupa imprezy)</span>
            @endif
        </h5>
    @endif

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
                                                        <th class="px-3 py-2 border-b text-right">Pokoje (opiek./dod.)</th>
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
                                                            $roomRaw = $roomInfo['room'] ?? null;
                                                            // Migawki trzymają room jako tablicę (JSON); żywa kalkulacja — jako model/stdClass.
                                                            $roomModel = is_array($roomRaw) ? (object) $roomRaw : $roomRaw;
                                                            if (! $roomModel) {
                                                                $warnings[] = 'Brak typu pokoju (hotel_rooms) dla pozycji w strukturze noclegów. Liczba osób: ' . ($roomInfo['total_people'] ?? '?');
                                                                continue;
                                                            }
                                                            $unitPrice = (float) ($roomInfo['cost'] ?? 0);
                                                            $key = ($roomModel->name ?? '') . '|' . ($roomModel->people_count ?? '') . '|' . $unitPrice . '|' . ($roomInfo['currency'] ?? '');
                                                            if (!isset($roomSummary[$key])) {
                                                                $roomSummary[$key] = [
                                                                    'room' => $roomModel,
                                                                    'qty' => 0,
                                                                    'gratis' => 0,
                                                                    'staff' => 0,
                                                                    'driver' => 0,
                                                                    'cost' => $unitPrice,
                                                                    'line_total' => 0.0,
                                                                    'currency' => $roomInfo['currency'] ?? 'PLN',
                                                                ];
                                                            }

                                                            // room_count = liczba pokoi; fallback 1 (wpis szablonu = 1 pokój).
                                                            // Nie sumujemy alloc jako osób — to psuło trójki (× people_count).
                                                            $roomsInEntry = max(1, (int) ($roomInfo['room_count'] ?? 1));
                                                            $role = (string) ($roomInfo['group_type'] ?? 'qty');
                                                            if (! in_array($role, ['qty', 'gratis', 'staff', 'driver'], true)) {
                                                                $role = 'qty';
                                                            }
                                                            $roomSummary[$key][$role] += $roomsInEntry;
                                                            $roomSummary[$key]['line_total'] += (float) (
                                                                $roomInfo['line_total']
                                                                ?? ($roomsInEntry * $unitPrice)
                                                            );
                                                        }
                                                    @endphp

                                                    @foreach($roomSummary as $room)
                                                        <tr>
                                                            <td class="px-3 py-2 border-b">{{ $room['room']->name ?? '—' }} ({{ $room['room']->people_count ?? '?' }} os.)</td>
                                                            <td class="px-3 py-2 border-b text-right">{{ $room['qty'] }}</td>
                                                            <td class="px-3 py-2 border-b text-right">{{ $room['gratis'] }}</td>
                                                            <td class="px-3 py-2 border-b text-right">{{ $room['staff'] }}</td>
                                                            <td class="px-3 py-2 border-b text-right">{{ $room['driver'] }}</td>
                                                            <td class="px-3 py-2 border-b text-right">{{ number_format($room['cost'], 2) }}</td>
                                                            <td class="px-3 py-2 border-b text-right">{{ number_format($room['line_total'], 2) }}</td>
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
