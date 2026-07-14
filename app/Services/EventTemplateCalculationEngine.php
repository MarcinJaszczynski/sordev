<?php

namespace App\Services;

use App\Models\Currency;
use App\Models\EventTemplate;
use App\Models\EventTemplateQty;
use Illuminate\Support\Facades\DB;

/**
 * EventTemplateCalculationEngine
 *
 * Silnik obliczeń cen dla szablonu wydarzenia (EventTemplate).
 * Zawiera logikę wyliczania kosztów punktów programu, noclegów,
 * transportu i innych elementów składających się na cenę.
 *
 * Metoda `calculateDetailed` zwraca szczegółowe wyniki dla każdej
 * odmiany ilościowej (`EventTemplateQty`) w strukturze pozwalającej
 * na dalsze przetwarzanie (np. przez `UnifiedPriceCalculator`).
 */
class EventTemplateCalculationEngine
{
    /**
     * Zwraca szczegółowe obliczenia keyed by qty
     */
    public function calculateDetailed(EventTemplate $template, ?int $startPlaceId = null, ?float $transportKm = null, bool $debug = false, ?iterable $qtyVariantsOverride = null): array
    {
        // Load all program points for this template (we'll respect per-pivot include_in_calculation
        // flags individually for parents and children). This ensures parent/child inclusion is
        // independent: a child can be included even if its parent is excluded and vice-versa.
        $programPoints = $template->programPoints()
            ->with(['currency', 'children.currency'])
            ->get();

        // We'll rely on ProgramPointHelper to determine include_in_calculation for each point.

        $isForeignTrip = method_exists($template, 'isForeignTrip') ? $template->isForeignTrip() : true;

        $qtyVariants = $qtyVariantsOverride !== null
            ? collect($qtyVariantsOverride)
            : $this->getQtyVariantsForTemplate($template);

        $bus = $template->bus;
        $programKm = $template->program_km ?? 0;
        $templateStartId = $template->start_place_id;
        $templateEndId = $template->end_place_id;

        // distances
        $d1 = 0;
        $d2 = 0;
        if ($startPlaceId && $templateStartId) {
            if ($startPlaceId === $templateStartId) {
                $d1 = 0; // identyczny start -> dystans 0
            } else {
                $d1 = \App\Models\PlaceDistance::where('from_place_id', $startPlaceId)
                    ->where('to_place_id', $templateStartId)
                    ->first()?->distance_km ?? 0;
            }
        }
        if ($templateEndId && $startPlaceId) {
            if ($templateEndId === $startPlaceId) {
                $d2 = 0; // identyczny koniec -> dystans 0
            } else {
                $d2 = \App\Models\PlaceDistance::where('from_place_id', $templateEndId)
                    ->where('to_place_id', $startPlaceId)
                    ->first()?->distance_km ?? 0;
            }
        }

        $basicDistance = $d1 + $d2 + $programKm;
        $effectiveTransportKm = $transportKm !== null
            ? $transportKm
            : (1.1 * $basicDistance + 50);

        $results = [];

        foreach ($qtyVariants as $qtyVariant) {
            $variantId = $qtyVariant->id;
            $qty = $qtyVariant->qty;
            $gratis = $qtyVariant->gratis ?? 0;
            $staff = $qtyVariant->staff ?? 0;
            $driver = $qtyVariant->driver ?? 0;
            $qtyTotal = $qty + $gratis + $staff + $driver;

            $plnTotal = 0;
            $plnPoints = [];
            $currenciesTotals = [];
            $currenciesPoints = [];
            $hotelStructure = [];
            $busTransportCostTotal = null;

            // points
            foreach ($programPoints as $point) {
                // Decide if this parent point is included in calculation according to its pivot
                $pointIncluded = \App\Services\ProgramPointHelper::filterIncluded(collect([$point]))->isNotEmpty();

                // Parent point: count only when pivot requests inclusion
                if ($pointIncluded && $point->currency) {
                    $currencyCode = $point->currency->symbol;
                    $exchangeRate = $point->currency->exchange_rate ?? 1;
                    $groupSize = $point->group_size ?? 1;
                    $unitPrice = $point->unit_price ?? 0;

                    // points/tickets are counted per qty (exclude gratis/staff per request)
                    $cost = $this->calculatePointCost($qty, $groupSize, $unitPrice);
                    $convertToPln = (bool) ($point->convert_to_pln ?? false);
                    if (! $isForeignTrip) {
                        $convertToPln = true;
                    }

                    if ($currencyCode === 'PLN') {
                        $plnPoints[] = ['name' => $point->name, 'cost' => $cost];
                        $plnTotal += $cost;
                    } elseif ($convertToPln) {
                        $plnPoints[] = ['name' => $point->name.' (przeliczone)', 'cost' => $cost * $exchangeRate];
                        $plnTotal += $cost * $exchangeRate;
                    } else {
                        $currenciesPoints[$currencyCode][] = ['name' => $point->name, 'cost' => $cost];
                        $currenciesTotals[$currencyCode] = ($currenciesTotals[$currencyCode] ?? 0) + $cost;
                    }
                }

                // Children: treat each child as an independent entity. Include only if the child's
                // own pivot (event_template <-> program_point) requests it.
                foreach ($point->children as $child) {
                    // child's pivot row (if attached to this template)
                    $childIncluded = \App\Services\ProgramPointHelper::filterIncluded(collect([$child]))->isNotEmpty();

                    if (! $childIncluded) {
                        continue;
                    }
                    if (! $child->currency) {
                        continue;
                    }

                    $childCurrencyCode = $child->currency->symbol;
                    $childExchangeRate = $child->currency->exchange_rate ?? 1;
                    $childGroupSize = $child->group_size ?? 1;
                    $childUnitPrice = $child->unit_price ?? 0;
                    $childCost = $this->calculatePointCost($qty, $childGroupSize, $childUnitPrice);
                    $childConvertToPln = (bool) ($child->convert_to_pln ?? false);
                    if (! $isForeignTrip) {
                        $childConvertToPln = true;
                    }

                    if ($childCurrencyCode === 'PLN') {
                        $plnPoints[] = ['name' => '→ '.$child->name, 'cost' => $childCost];
                        $plnTotal += $childCost;
                    } elseif ($childConvertToPln) {
                        $plnPoints[] = ['name' => '→ '.$child->name.' (przeliczone)', 'cost' => $childCost * $childExchangeRate];
                        $plnTotal += $childCost * $childExchangeRate;
                    } else {
                        $currenciesPoints[$childCurrencyCode][] = ['name' => '→ '.$child->name, 'cost' => $childCost];
                        $currenciesTotals[$childCurrencyCode] = ($currenciesTotals[$childCurrencyCode] ?? 0) + $childCost;
                    }
                }
            }

            // insurance: for each day that has an assigned insurance, charge price_per_person
            // multiplied by (qty + gratis) for that day and sum across days. This handles
            // daily-varying insurance prices correctly.
            $insuranceTotal = 0;
            $dayInsurances = $template->dayInsurances ?? collect();
            foreach ($dayInsurances as $dayInsurance) {
                $insurance = $dayInsurance->insurance;
                if ($insurance && $insurance->insurance_enabled) {
                    // only consider insurances that are charged per-day or per-person
                    if ($insurance->insurance_per_day || $insurance->insurance_per_person) {
                        $countForInsurance = $qty + ($qtyVariant->gratis ?? 0);
                        $insuranceTotal += $insurance->price_per_person * $countForInsurance;
                    }
                }
            }
            if ($insuranceTotal > 0) {
                $plnPoints[] = ['name' => 'Ubezpieczenie', 'cost' => $insuranceTotal];
                $plnTotal += $insuranceTotal;
            }

            // --- NOCLEGI: odtwórz algorytm z widgeta (DP - minimalny koszt kombinacji pokoi)
            $hotelDays = $template->hotelDays()->get();
            foreach ($hotelDays as $hotelDay) {
                $roomGroups = [
                    'qty' => [
                        'count' => $qty,
                        'room_ids' => $hotelDay->hotel_room_ids_qty ?? [],
                    ],
                    'gratis' => [
                        'count' => $qtyVariant->gratis ?? 0,
                        'room_ids' => $hotelDay->hotel_room_ids_gratis ?? [],
                    ],
                    'staff' => [
                        'count' => $qtyVariant->staff ?? 0,
                        'room_ids' => $hotelDay->hotel_room_ids_staff ?? [],
                    ],
                    'driver' => [
                        'count' => $qtyVariant->driver ?? 0,
                        'room_ids' => $hotelDay->hotel_room_ids_driver ?? [],
                    ],
                ];
                // Suma dla tego dnia rozbita na PLN oraz inne waluty (bez konwersji)
                $dayTotalPln = 0;
                $dayTotalForeign = [];
                $roomAlloc = [];
                foreach ($roomGroups as $groupType => $groupData) {
                    $peopleCount = $groupData['count'];
                    $roomIds = $groupData['room_ids'];
                    if ($peopleCount <= 0) {
                        continue;
                    }
                    if (empty($roomIds)) {
                        $roomAlloc[] = [
                            'room' => null,
                            'alloc' => null,
                            'total_people' => $peopleCount,
                            'cost' => 0,
                            'currency' => null,
                            'group_type' => $groupType,
                            'room_count' => 0,
                            'warning' => 'Brak przypisanych pokoi dla tej grupy ('.$groupType.') w noclegu.',
                        ];

                        continue;
                    }
                    $rooms = \App\Models\HotelRoom::whereIn('id', $roomIds)->get();

                    $roomTypeCount = [];
                    foreach ($rooms as $room) {
                        $roomTypeCount[$room->id] = 0;
                    }

                    $maxPeople = $peopleCount;
                    $maxCapacity = $rooms->sum('people_count') * ($peopleCount);
                    $dp = array_fill(0, $maxCapacity + 1, INF);
                    $dp[0] = 0;
                    $choice = array_fill(0, $maxCapacity + 1, null);

                    foreach ($rooms as $room) {
                        for ($i = $room->people_count; $i <= $maxCapacity; $i++) {
                            if ($dp[$i] > $dp[$i - $room->people_count] + $room->price) {
                                $dp[$i] = $dp[$i - $room->people_count] + $room->price;
                                $choice[$i] = $room->id;
                            }
                        }
                    }

                    // Szukaj najtańszego rozwiązania dla liczby miejsc >= liczba osób
                    $minCost = INF;
                    $bestI = null;
                    for ($i = $peopleCount; $i <= $maxCapacity; $i++) {
                        if ($dp[$i] < $minCost) {
                            $minCost = $dp[$i];
                            $bestI = $i;
                        }
                    }

                    if ($minCost === INF) {
                        $roomAlloc[] = [
                            'room' => null,
                            'alloc' => null,
                            'total_people' => $peopleCount,
                            'cost' => 0,
                            'currency' => null,
                            'group_type' => $groupType,
                            'room_count' => 0,
                            'warning' => 'Brak możliwej kombinacji pokoi dla tej grupy ('.$groupType.') w noclegu.',
                        ];
                    } else {
                        // Odtwarzanie wyboru pokoi
                        $allocRooms = [];
                        $i = $bestI;
                        while ($i > 0 && $choice[$i] !== null) {
                            $room = $rooms->firstWhere('id', $choice[$i]);
                            $allocRooms[] = $room;
                            $i -= $room->people_count;
                        }

                        // Zlicz ile razy każdy pokój został użyty
                        $roomCounts = [];
                        foreach ($allocRooms as $room) {
                            $roomCounts[$room->id] = ($roomCounts[$room->id] ?? 0) + 1;
                        }

                        $peopleAssigned = 0;
                        foreach ($roomCounts as $roomId => $count) {
                            $room = $rooms->firstWhere('id', $roomId);
                            for ($j = 0; $j < $count; $j++) {
                                $alloc = [
                                    'qty' => 0,
                                    'gratis' => 0,
                                    'staff' => 0,
                                    'driver' => 0,
                                ];

                                $toAssign = min($room->people_count, $peopleCount - $peopleAssigned);
                                $alloc[$groupType] = $toAssign;

                                $roomAlloc[] = [
                                    'room' => $room,
                                    'alloc' => $alloc,
                                    'total_people' => $toAssign,
                                    'cost' => $room->price,
                                    'currency' => $room->currency,
                                    'group_type' => $groupType,
                                    'room_count' => 1,
                                ];

                                // Waluta i konwersja do PLN jak w punktach programu
                                $roomCurrency = $room->currency;
                                $convertFlag = (bool) ($room->convert_to_pln ?? false);
                                if (! $isForeignTrip) {
                                    $convertFlag = true;
                                }

                                if ($roomCurrency === 'PLN') {
                                    $dayTotalPln += $room->price;
                                } elseif ($convertFlag) {
                                    $rate = Currency::where('symbol', $roomCurrency)->first()?->exchange_rate ?? 1;
                                    $dayTotalPln += $room->price * $rate;
                                } else {
                                    $dayTotalForeign[$roomCurrency] = ($dayTotalForeign[$roomCurrency] ?? 0) + $room->price;
                                }
                                $roomTypeCount[$room->id]++;
                                $peopleAssigned += $toAssign;

                                if ($peopleAssigned >= $peopleCount) {
                                    break 2;
                                }
                            }
                        }
                    }
                }

                // Zbuduj strukturę debug oraz podsumowania dnia z uwzględnieniem konwersji
                $dayTotal = array_merge(['PLN' => $dayTotalPln], $dayTotalForeign);

                $hotelStructure[] = [
                    'day' => $hotelDay->day,
                    'rooms' => $roomAlloc,
                    'day_total' => $dayTotal,
                ];

                // Dodaj do ogólnej sumy kosztów noclegów
                if ($dayTotalPln > 0) {
                    $plnPoints[] = [
                        'name' => 'Hotel - dzień '.$hotelDay->day,
                        'unit_price' => null,
                        'group_size' => null,
                        'cost' => $dayTotalPln,
                        'is_child' => false,
                        'currency_symbol' => 'PLN',
                    ];
                    $plnTotal += $dayTotalPln;
                }
                foreach ($dayTotalForeign as $cur => $val) {
                    $currenciesPoints[$cur][] = [
                        'name' => 'Hotel - dzień '.$hotelDay->day,
                        'unit_price' => null,
                        'group_size' => null,
                        'cost' => $val,
                        'is_child' => false,
                        'currency_symbol' => $cur,
                    ];
                    $currenciesTotals[$cur] = ($currenciesTotals[$cur] ?? 0) + $val;
                }
            }

            // transport: obsługa autokaru (bus) zgodnie z widgetem
            $transportCostPLN = 0;
            if ($startPlaceId !== null && $templateStartId && $templateEndId) {
                $defaultTransportKm = $effectiveTransportKm ?? (1.1 * ($d1 + $d2 + $programKm) + 50);

                // jeśli jest konfiguracja autokaru, policz koszt autokaru w jego walucie
                if ($bus) {
                    $duration = $template->duration_days ?? 1;
                    $includedKm = $duration * $bus->package_km_per_day;
                    $baseCost = $duration * $bus->package_price_per_day;
                    $busCurrency = $bus->currency ?? 'PLN';

                    $totalKm = $defaultTransportKm;
                    if ($totalKm <= $includedKm) {
                        $busTransportCost = $baseCost;
                    } else {
                        $extraKm = $totalKm - $includedKm;
                        $busTransportCost = $baseCost + ($extraKm * $bus->extra_km_price);
                    }

                    // liczba autobusów
                    $busCapacity = $bus->capacity > 0 ? $bus->capacity : 50;
                    $busCount = (int) ceil($qtyTotal / $busCapacity);

                    $busTransportCostTotal = $busTransportCost * $busCount;

                    if ($busCurrency === 'PLN' || ! $isForeignTrip) {
                        $busCostPln = $busTransportCostTotal;
                        if ($busCurrency !== 'PLN') {
                            $busRate = Currency::where('symbol', $busCurrency)->first()?->exchange_rate ?? 1;
                            $busCostPln = $busTransportCostTotal * $busRate;
                        }

                        $plnPoints[] = ['name' => 'Koszt transportu (autokar)', 'cost' => $busCostPln];
                        $plnTotal += $busCostPln;
                        $transportCostPLN = ($transportCostPLN ?? 0) + $busCostPln;
                    } else {
                        $currenciesPoints[$busCurrency][] = ['name' => 'Koszt transportu (autokar)', 'cost' => $busTransportCostTotal];
                        $currenciesTotals[$busCurrency] = ($currenciesTotals[$busCurrency] ?? 0) + $busTransportCostTotal;
                    }
                } else {
                    // brak autokaru: dodaj domyślny koszt transportu do PLN
                    $transportCostPLN = $defaultTransportKm;
                    $plnPoints[] = ['name' => 'Koszt transportu', 'cost' => $transportCostPLN];
                    $plnTotal += $transportCostPLN;
                }
            }

            // MARKUP: apply markup in PLN ONLY to PLN subtotal (including items explicitly converted to PLN).
            // Do NOT include foreign currency buckets here to avoid double-charging when those currencies
            // also receive their own markup in their native currency buckets.
            $markupAmount = $this->calculateMarkupForTemplate($template, $plnTotal);

            // taxes
            $taxes = $template->taxes ?? collect();
            $totalTaxAmount = 0;
            foreach ($taxes as $tax) {
                if (! $tax->is_active) {
                    continue;
                }
                $taxAmount = $tax->calculateTaxAmount($plnTotal, $markupAmount);
                $totalTaxAmount += $taxAmount;
            }

            $priceWithTax = $plnTotal + $markupAmount + $totalTaxAmount;
            $pricePerPersonRaw = $qty > 0 ? round($priceWithTax / $qty, 2) : 0;
            // Rounding (PLN) zostanie zastosowane przy zapisie – tutaj zostawiamy raw aby móc policzyć waluty obce spójnie
            $pricePerPerson = $pricePerPersonRaw;

            // Oblicz narzut i podatki dla każdej waluty obcej
            if (! $isForeignTrip) {
                $currenciesTotals = [];
            }

            $currenciesWithMarkup = [];
            foreach ($currenciesTotals as $code => $total) {
                if ($code === 'PLN') {
                    continue;
                }

                $currency = Currency::where('symbol', $code)->first();
                $exchangeRate = $currency?->exchange_rate ?? 1;

                // Oblicz narzut osobno dla każdej waluty obcej (percent z kosztów w tej walucie)
                $markupPercent = $this->getMarkupPercentForTemplate($template);
                $currencyMarkup = $total * ($markupPercent / 100);

                // Podatki tylko dla PLN, więc dla walut obcych tax_amount = 0
                $currencyTax = 0;

                $currenciesWithMarkup[$code] = [
                    'total_before_markup' => $total,
                    'markup_amount' => round($currencyMarkup, 2),
                    'tax_amount' => round($currencyTax, 2),
                    'total_with_markup_and_tax' => round($total + $currencyMarkup + $currencyTax, 2),
                    'price_per_person' => $qty > 0 ? round(($total + $currencyMarkup + $currencyTax) / $qty, 2) : 0,
                ];
            }

            // Standardize currencies output: ensure each currency has 'raw' and 'final' blocks
            $currenciesStructured = [];

            // PLN entry
            $currenciesStructured['PLN'] = [
                'raw' => [
                    'price_base' => round($plnTotal, 2),
                    'markup_amount' => round($markupAmount, 2),
                    'tax_amount' => round($totalTaxAmount, 2),
                    'price_with_tax' => round($priceWithTax, 2),
                    'price_per_person' => $pricePerPerson,
                    'transport_cost' => $transportCostPLN ? round($transportCostPLN, 2) : null,
                    'tax_breakdown' => [],
                ],
                'final' => [
                    // keep raw final (no rounding here) — rounding happens at persist
                    'price_per_person' => $pricePerPerson,
                ],
            ];

            // Foreign currencies
            foreach ($currenciesWithMarkup as $code => $data) {
                if ($code === 'PLN') {
                    continue;
                }
                $currenciesStructured[$code] = [
                    'raw' => [
                        'price_base' => $data['total_before_markup'] ?? null,
                        'markup_amount' => $data['markup_amount'] ?? null,
                        'tax_amount' => $data['tax_amount'] ?? 0,
                        'price_with_tax' => $data['total_with_markup_and_tax'] ?? null,
                        'price_per_person' => $data['price_per_person'] ?? null,
                        'transport_cost' => $transportCostPLN ? round($transportCostPLN, 2) : null,
                        'tax_breakdown' => [],
                    ],
                    'final' => [
                        'price_per_person' => $data['price_per_person'] ?? null,
                    ],
                ];
            }

            $results[$qty] = [
                'event_template_qty_id' => $variantId,
                'qty' => $qty,
                'gratis' => $gratis,
                'staff' => $staff,
                'driver' => $driver,
                'price_per_person' => $pricePerPerson,
                'price_with_tax' => round($priceWithTax, 2),
                'price_base' => round($plnTotal, 2),
                'markup_amount' => round($markupAmount, 2),
                'tax_amount' => round($totalTaxAmount, 2),
                'transport_cost' => $transportCostPLN ? round($transportCostPLN, 2) : null,
                'currencies' => $currenciesStructured, // standardized structure
            ];

            if ($debug) {
                $results[$qty]['debug'] = [
                    'd1' => $d1,
                    'd2' => $d2,
                    'basicDistance' => $basicDistance,
                    'defaultTransportKm' => $defaultTransportKm ?? null,
                    'busTransportCostTotal' => $busTransportCostTotal,
                    'plnPoints' => $plnPoints,
                    'currenciesTotals' => $currenciesTotals,
                    'hotelStructure' => $hotelStructure,
                ];
            }
        }

        ksort($results);

        return $results;
    }

    /**
     * Dokładna kalkulacja dla niestandardowej liczby uczestników/gratisów.
     */
    public function calculateDetailedForCustomGroup(
        EventTemplate $template,
        int $participantCount,
        int $gratisCount = 0,
        ?int $startPlaceId = null,
        ?float $transportKm = null,
        bool $debug = false,
        ?int $staffCount = null,
        ?int $driverCount = null
    ): array {
        $participantCount = max(1, $participantCount);
        $gratisCount = max(0, $gratisCount);

        $closestVariant = $this->resolveClosestQtyVariant($template, $participantCount, $gratisCount);

        $customVariant = new EventTemplateQty;
        $customVariant->event_template_id = $template->id;
        $customVariant->qty = $participantCount;
        $customVariant->gratis = $gratisCount;
        $customVariant->staff = $staffCount ?? (int) ($closestVariant?->staff ?? 1);
        $customVariant->driver = $driverCount ?? (int) ($closestVariant?->driver ?? 1);

        $results = $this->calculateDetailed(
            $template,
            $startPlaceId,
            $transportKm,
            $debug,
            [$customVariant]
        );

        return $results[$participantCount] ?? [];
    }

    private function resolveClosestQtyVariant(EventTemplate $template, int $participantCount, int $gratisCount): ?EventTemplateQty
    {
        $variants = $this->getQtyVariantsForTemplate($template);

        if ($variants->isEmpty()) {
            return null;
        }

        return $variants
            ->sortBy(fn ($row) => abs(((int) ($row->qty ?? 0)) - $participantCount) +
                abs(((int) ($row->gratis ?? 0)) - $gratisCount)
            )
            ->first();
    }

    private function getQtyVariantsForTemplate(EventTemplate $template)
    {
        try {
            $qtyVariants = $template->qtyVariants()->get();
        } catch (\Illuminate\Database\QueryException $e) {
            $qtyIds = DB::table('event_template_price_per_person')
                ->where('event_template_id', $template->id)
                ->distinct()
                ->pluck('event_template_qty_id')
                ->filter()
                ->values()
                ->all();

            if (! empty($qtyIds)) {
                $qtyVariants = EventTemplateQty::whereIn('id', $qtyIds)->get();
            } else {
                $common = [20, 25, 30, 35, 40];
                $qtyVariants = EventTemplateQty::whereIn('qty', $common)->get();
                if ($qtyVariants->isEmpty()) {
                    $qtyVariants = EventTemplateQty::all();
                }
            }
        }

        return $qtyVariants;
    }

    private function calculatePointCost($qty, $groupSize, $unitPrice)
    {
        return ProgramPointPricingCalculator::totalPrice(
            (float) $unitPrice,
            (int) $qty,
            (int) ($groupSize ?? 0) > 0 ? (int) $groupSize : 1,
        );
    }

    private function calculateTotalInPLN(array $tempCalculation): float
    {
        $total = 0;
        foreach ($tempCalculation as $code => $data) {
            if ($code === 'PLN') {
                $total += $data['total'];
            } else {
                $currency = Currency::where('symbol', $code)->first();
                $rate = $currency?->exchange_rate ?? 1;
                $total += $data['total'] * $rate;
            }
        }

        return $total;
    }

    private function getMarkupPercentForTemplate(EventTemplate $template): float
    {
        // Prefer explicitly assigned Markup model if present
        $percent = null;

        // If relation is loaded or available, prefer it
        if (isset($template->markup) && $template->markup?->percent !== null) {
            $percent = $template->markup->percent;
        }

        // If markup_id is set but relation not loaded, try to resolve it
        if ($percent === null && ! empty($template->markup_id)) {
            $markup = \App\Models\Markup::find($template->markup_id);
            $percent = $markup?->percent;
        }

        // Fallback to legacy field on template
        if ($percent === null && isset($template->markup_percent) && $template->markup_percent !== null) {
            $percent = $template->markup_percent;
        }

        // Final fallback: system default markup (ensure some value)
        if ($percent === null) {
            $default = \App\Models\Markup::where('is_default', true)->first();
            $percent = $default?->percent ?? 20;
        }

        return $percent;
    }

    private function calculateMarkupForTemplate(EventTemplate $template, float $base): float
    {
        $percent = $this->getMarkupPercentForTemplate($template);
        \Illuminate\Support\Facades\Log::info("[MARKUP] Using markup percent={$percent} for event_template_id={$template->id}");

        return $base * ($percent / 100);
    }
}
