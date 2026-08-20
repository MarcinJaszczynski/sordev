@props([
    'template' => null,
    'participantCount' => 1,
    'gratisCount' => 0,
    'startPlaceId' => null,
    'calculatedTotal' => null,
])

@php
    if (!$template) {
        return;
    }

    $participantCount = max(1, (int) $participantCount);
    $gratisCount = max(0, (int) $gratisCount);
    $calculatedTotal = is_numeric($calculatedTotal) ? (float) $calculatedTotal : null;
    $startPlaceId = $startPlaceId ? (int) $startPlaceId : null;

    $detailedCalculations = [];
    $qtyVariants = [];
    $nearestVariants = [];
    $currentVariant = null;

    if ($startPlaceId) {
        try {
            $templateVariants = $template->qtyVariants()->get(['qty', 'gratis', 'staff', 'driver']);
        } catch (\Throwable $e) {
            $templateVariants = \App\Models\EventTemplateQty::query()->get(['qty', 'gratis', 'staff', 'driver']);
        }

        $closestTemplateVariant = $templateVariants
            ->sortBy(fn ($variant) =>
                abs(((int) ($variant->qty ?? 0)) - $participantCount) +
                abs(((int) ($variant->gratis ?? 0)) - $gratisCount)
            )
            ->first();

        $currentVariant = [
            'qty' => $participantCount,
            'gratis' => $gratisCount,
            'staff' => max(0, (int) ($closestTemplateVariant->staff ?? 1)),
            'driver' => max(0, (int) ($closestTemplateVariant->driver ?? 1)),
        ];

        $nearestVariants = $templateVariants
            ->map(fn ($variant) => [
                'qty' => (int) ($variant->qty ?? 0),
                'gratis' => max(0, (int) ($variant->gratis ?? 0)),
                'staff' => max(0, (int) ($variant->staff ?? 0)),
                'driver' => max(0, (int) ($variant->driver ?? 0)),
            ])
            ->filter(fn ($variant) => $variant['qty'] > 0)
            ->reject(fn ($variant) => $variant['qty'] === $currentVariant['qty'])
            ->sortBy(fn ($variant) =>
                abs($variant['qty'] - $currentVariant['qty']) +
                abs($variant['gratis'] - $currentVariant['gratis'])
            )
            ->take(2)
            ->values()
            ->all();

        $selectedVariants = collect(array_merge([$currentVariant], $nearestVariants))
            ->unique(fn ($variant) => implode(':', [
                $variant['qty'],
                $variant['gratis'],
                $variant['staff'],
                $variant['driver'],
            ]))
            ->values()
            ->all();

        try {
            $sourceWidget = app(\App\Filament\Resources\EventTemplateResource\Widgets\EventTemplatePriceTable::class);
            $sourceWidget->record = $template;
            $sourceWidget->startPlaceId = $startPlaceId;
            $sourceWidget->startPlace = \App\Models\Place::find($startPlaceId);
            $sourceWidget->transportKm = null;
            $sourceWidget->variantOverrides = $selectedVariants;

            $qtyVariants = $sourceWidget->getQtyVariantsProperty();
            $detailedCalculations = $sourceWidget->getDetailedCalculations();

            $orderedDetailedCalculations = [];
            $preferredQtyOrder = array_merge([$currentVariant['qty']], array_map(fn ($variant) => $variant['qty'], $nearestVariants));
            foreach ($preferredQtyOrder as $preferredQty) {
                if (isset($detailedCalculations[$preferredQty])) {
                    $orderedDetailedCalculations[$preferredQty] = $detailedCalculations[$preferredQty];
                }
            }
            foreach ($detailedCalculations as $qty => $currencies) {
                if (!isset($orderedDetailedCalculations[$qty])) {
                    $orderedDetailedCalculations[$qty] = $currencies;
                }
            }
            $detailedCalculations = $orderedDetailedCalculations;
        } catch (\Throwable $e) {
            $detailedCalculations = [];
            $qtyVariants = [];
        }
    }

    $estimatedTotal = $calculatedTotal ?? 0;

    if ($calculatedTotal === null && isset($detailedCalculations[$participantCount]['PLN']['total'])) {
        $estimatedTotal = (float) $detailedCalculations[$participantCount]['PLN']['total'];
    }
@endphp

<div class="space-y-4">
    @if(!$startPlaceId)
        <div class="rounded border border-yellow-300 bg-yellow-50 p-3 text-sm text-yellow-800">
            Wybierz miejsce wyjazdu oraz podaj liczbę uczestników i opiekunów/dodatkowych, aby obliczyć cenę.
        </div>
    @elseif(!empty($detailedCalculations))
        <div class="rounded border border-blue-200 bg-blue-50 p-3 text-xs text-blue-900">
            <b>Grupa bieżąca:</b>
            @if($currentVariant)
                {{ $currentVariant['qty'] }} uczestników + {{ $currentVariant['gratis'] }} opiek./dod.
            @else
                {{ $participantCount }} uczestników + {{ $gratisCount }} opiek./dod.
            @endif
            @if(!empty($nearestVariants))
                <br><b>Najbliższe predefiniowane:</b>
                {{ collect($nearestVariants)->map(fn ($variant) => $variant['qty'] . '+' . $variant['gratis'])->join(', ') }}
            @endif
        </div>

        <div class="rounded border border-primary-200 bg-primary-50/30 p-3 text-sm font-semibold text-primary-700">
            Szacowany koszt całkowity (grupa bieżąca): {{ number_format($estimatedTotal, 2, ',', ' ') }} PLN
        </div>

        @foreach($detailedCalculations as $qty => $currencies)
            @php
                $variant = $qtyVariants[$qty] ?? ['qty' => $qty, 'gratis' => 0, 'staff' => 0, 'driver' => 0];
                $totalAll = $variant['qty'] + $variant['gratis'] + $variant['staff'] + $variant['driver'];
                $isCurrentVariant = $currentVariant
                    && (int) $currentVariant['qty'] === (int) $variant['qty']
                    && (int) $currentVariant['gratis'] === (int) $variant['gratis'];
            @endphp

            <div class="mb-6 rounded-lg border p-4 {{ $isCurrentVariant ? 'border-primary-500 bg-blue-50/30' : 'border-gray-200' }}">
                <h5 class="mb-3 font-medium text-gray-800">
                    Wariant: {{ $variant['qty'] }} uczestników (plus {{ $variant['gratis'] }} opiekunów/dodatkowych, {{ $variant['staff'] }} obsługa, {{ $variant['driver'] }} kierowców), razem: {{ $totalAll }} osób
                    @if($isCurrentVariant)
                        <span class="ml-2 text-xs text-primary-700">(bieżąca grupa)</span>
                    @endif
                </h5>

                @if(isset($currencies['hotel_structure']))
                    <div class="mb-4">
                        <h6 class="mb-2 font-medium text-green-700">Noclegi:</h6>
                        @foreach($currencies['hotel_structure'] as $hotelDay)
                            <div class="mb-2">
                                <b>Nocleg {{ $hotelDay['day'] }}:</b>
                                <div class="overflow-x-auto">
                                    <table class="mb-2 min-w-full border border-gray-200 bg-white text-sm">
                                        <thead>
                                            <tr class="bg-green-100">
                                                <th class="border-b px-3 py-2 text-left">Pokój</th>
                                                <th class="border-b px-3 py-2 text-right">Uczestnicy</th>
                                                <th class="border-b px-3 py-2 text-right">{{ \App\Support\EventParticipantGroupLabels::GRATIS }}</th>
                                                <th class="border-b px-3 py-2 text-right">Obsługa</th>
                                                <th class="border-b px-3 py-2 text-right">Kierowcy</th>
                                                <th class="border-b px-3 py-2 text-right">Cena (za pokój)</th>
                                                <th class="border-b px-3 py-2 text-right">Łącznie</th>
                                                <th class="border-b px-3 py-2 text-right">Waluta</th>
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
                                                    <td class="border-b px-3 py-2">{{ $room['room']->name }} ({{ $room['room']->people_count }} os.)</td>
                                                    <td class="border-b px-3 py-2 text-right">{{ $room['qty'] }}</td>
                                                    <td class="border-b px-3 py-2 text-right">{{ $room['gratis'] }}</td>
                                                    <td class="border-b px-3 py-2 text-right">{{ $room['staff'] }}</td>
                                                    <td class="border-b px-3 py-2 text-right">{{ $room['driver'] }}</td>
                                                    <td class="border-b px-3 py-2 text-right">{{ number_format($room['cost'], 2) }}</td>
                                                    <td class="border-b px-3 py-2 text-right">{{ number_format($totalCost, 2) }}</td>
                                                    <td class="border-b px-3 py-2 text-right">{{ $room['currency'] }}</td>
                                                </tr>
                                            @endforeach

                                            @if(!empty($warnings))
                                                @foreach($warnings as $warning)
                                                    <tr>
                                                        <td colspan="8" class="border-b bg-red-50 px-3 py-2 text-center font-semibold text-red-700">{{ $warning }}</td>
                                                    </tr>
                                                @endforeach
                                            @endif
                                        </tbody>
                                    </table>
                                </div>
                                <div class="mt-1 text-xs text-gray-600">Suma za nocleg:
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
                    @php($data = $currencies[$currencyCode] ?? [])
                    @php($currencySymbol = (isset($data['points']) && is_array($data['points']) && count($data['points']) > 0) ? ($data['points'][0]['currency_symbol'] ?? $currencyCode) : $currencyCode)
                    <div class="mb-4">
                        <h6 class="mb-2 font-medium text-blue-600">Waluta: {{ $currencyCode }} ({{ $currencySymbol }})</h6>
                        <div class="overflow-x-auto">
                            <table class="min-w-full border border-gray-200 bg-gray-50 text-sm">
                                <thead>
                                    <tr class="bg-gray-100">
                                        <th class="border-b px-3 py-2 text-left">Punkt programu</th>
                                        <th class="border-b px-3 py-2 text-right">Cena jednostkowa (za grupę)</th>
                                        <th class="border-b px-3 py-2 text-right">dla grupy</th>
                                        <th class="border-b px-3 py-2 text-right">Koszt całkowity (dla wszystkich)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @if(isset($data['points']) && is_array($data['points']) && count($data['points']) > 0)
                                        @foreach($data['points'] as $point)
                                            <tr class="{{ $point['is_child'] ? 'bg-blue-50' : '' }}">
                                                <td class="border-b px-3 py-2 {{ $point['is_child'] ? 'pl-6 text-blue-700' : 'font-medium' }}">
                                                    {{ $point['name'] }}
                                                </td>
                                                <td class="border-b px-3 py-2 text-right">
                                                    @if(is_numeric($point['unit_price']))
                                                        {{ number_format($point['unit_price'], 2) }} {{ $point['currency_symbol'] ?? $currencyCode }}
                                                    @else
                                                        {{ $point['unit_price'] }}
                                                    @endif
                                                </td>
                                                <td class="border-b px-3 py-2 text-right">{{ $point['group_size'] }} osób</td>
                                                <td class="border-b px-3 py-2 text-right font-semibold">
                                                    {{ number_format($point['cost'], 2) }} {{ $point['currency_symbol'] ?? $currencyCode }}
                                                </td>
                                            </tr>
                                        @endforeach
                                    @else
                                        <tr>
                                            <td class="border-b px-3 py-2 text-center text-gray-400" colspan="4">Brak pozycji kosztowych dla tej waluty.</td>
                                        </tr>
                                    @endif

                                    <tr class="bg-gray-100 font-bold">
                                        <td class="border-t-2 border-gray-400 px-3 py-2" colspan="3">SUMA dla {{ $currencyCode }} (bez narzutu):</td>
                                        <td class="border-t-2 border-gray-400 px-3 py-2 text-right">
                                            {{ number_format($data['total_before_markup'] ?? $data['total'] ?? 0, 2) }} {{ $currencySymbol }}
                                        </td>
                                    </tr>

                                    @if($currencyCode === 'PLN' && isset($currencies['markup']) && $currencies['markup']['amount'] > 0)
                                        <tr class="bg-yellow-100 font-semibold">
                                            <td class="px-3 py-2" colspan="3">Narzut ({{ number_format($currencies['markup']['percent_applied'], 2) }}%):</td>
                                            <td class="px-3 py-2 text-right">{{ number_format($currencies['markup']['amount'], 2) }} PLN</td>
                                        </tr>
                                        @if(isset($currencies['taxes']) && $currencies['taxes']['total_amount'] > 0)
                                            @foreach($currencies['taxes']['breakdown'] as $tax)
                                                <tr class="bg-orange-50 font-medium">
                                                    <td class="px-3 py-2" colspan="3">
                                                        {{ $tax['name'] }} ({{ number_format($tax['percentage'], 2) }}%)
                                                    </td>
                                                    <td class="px-3 py-2 text-right">{{ number_format($tax['amount'], 2) }} PLN</td>
                                                </tr>
                                            @endforeach
                                            <tr class="bg-orange-100 font-semibold">
                                                <td class="px-3 py-2" colspan="3">Suma podatków:</td>
                                                <td class="px-3 py-2 text-right">{{ number_format($currencies['taxes']['total_amount'], 2) }} PLN</td>
                                            </tr>
                                        @endif
                                        <tr class="bg-green-100 font-bold">
                                            <td class="px-3 py-2" colspan="3">SUMA KOŃCOWA dla {{ $currencyCode }}:</td>
                                            <td class="px-3 py-2 text-right">{{ number_format($data['total'] ?? 0, 2) }} {{ $currencySymbol }}</td>
                                        </tr>
                                    @else
                                        <tr class="bg-green-100 font-bold">
                                            <td class="px-3 py-2" colspan="3">SUMA KOŃCOWA dla {{ $currencyCode }}:</td>
                                            <td class="px-3 py-2 text-right">{{ number_format($data['total'] ?? 0, 2) }} {{ $currencySymbol }}</td>
                                        </tr>
                                    @endif

                                    <tr class="bg-blue-100 font-bold">
                                        <td class="px-3 py-2" colspan="3">Cena za osobę (uczestnik):</td>
                                        <td class="px-3 py-2 text-right">{{ number_format(($data['total'] ?? 0) / max(1, $qty), 2) }} {{ $currencySymbol }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endforeach
            </div>
        @endforeach

    @else
        <div class="rounded border border-yellow-300 bg-yellow-50 p-3 text-sm text-yellow-800">
            Brak szczegółowej kalkulacji dla wybranego szablonu i miasta wyjazdu.
        </div>
    @endif
</div>
