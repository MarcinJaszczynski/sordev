<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\EventTemplate;
use App\Models\Markup;

/**
 * Kalkulacja szczegółowa w formacie UI (markup/taxes/PLN.points/hotel_structure).
 * Współdzielona przez widget szablonu i EventPriceTable — bez zależności od Filament.
 *
 * Persist/normalizacja cen: {@see UnifiedPriceCalculator} + {@see EventTemplateCalculationEngine}.
 */
final class EventTemplateUiCalculationService
{
    public function calculate(
        EventTemplate $template,
        ?int $startPlaceId = null,
        ?float $transportKm = null,
        array $variantOverrides = [],
        mixed $busOverride = null,
    ): array {
        // Load all program points and respect per-pivot include_in_calculation flags individually
        // Note: EventTemplateProgramPoint has relations 'currency' and 'children', not 'templatePoint'
        $programPoints = $template->programPoints()->with(['currency', 'children.currency'])->get();

        // build pivot lookup by point id (includes parent pivots)
        $programPointPivotMap = [];
        foreach ($programPoints as $pp) {
            if (isset($pp->pivot)) {
                $programPointPivotMap[$pp->id] = $pp->pivot;
            }
        }

        // Also load per-template child pivot rows (event_template_program_point_child_pivot)
        // so that child include_in_calculation flags are respected independently.
        try {
            $childPivots = $template->programPointChildren()->get();
            foreach ($childPivots as $cp) {
                if (isset($cp->pivot)) {
                    // pivot->program_point_child_id isn't standard on the model, but the returned
                    // model is the child point, so use its id as key
                    $programPointPivotMap[$cp->id] = $cp->pivot;
                }
            }
        } catch (\Throwable $e) {
            // If the relation/table isn't present or fails, ignore and continue.
        }

        $qtyVariants = $variantOverrides !== []
            ? collect($variantOverrides)
                ->map(function (array $variant) {
                    $model = new \App\Models\EventTemplateQty;
                    $model->qty = max(1, (int) ($variant['qty'] ?? 1));
                    $model->gratis = max(0, (int) ($variant['gratis'] ?? 0));
                    $model->staff = max(0, (int) ($variant['staff'] ?? 0));
                    $model->driver = max(0, (int) ($variant['driver'] ?? 0));

                    return $model;
                })
            : \App\Models\EventTemplateQty::all();
        $calculations = [];

        $bus = $busOverride ?? $template->bus;
        $programKm = $template->program_km ?? 0;
        $startPlaceId = $startPlaceId;
        $templateStartId = $template->start_place_id;
        $templateEndId = $template->end_place_id;

        // Pobierz d1 i d2 z place_distances
        $d1 = 0;
        $d2 = 0;
        if ($startPlaceId && $templateStartId) {
            $d1 = \App\Models\PlaceDistance::where('from_place_id', $startPlaceId)
                ->where('to_place_id', $templateStartId)
                ->first()?->distance_km ?? 0;
        }
        if ($templateEndId && $startPlaceId) {
            $d2 = \App\Models\PlaceDistance::where('from_place_id', $templateEndId)
                ->where('to_place_id', $startPlaceId)
                ->first()?->distance_km ?? 0;
        }

        $basicDistance = $d1 + $d2 + $programKm;
        if ($transportKm !== null) {
            $totalKm = $transportKm;
        } else {
            $totalKm = 1.1 * $basicDistance + 50;
        }

        $duration = $template->duration_days ?? 1;
        $busTransportCost = null;
        $busCurrency = null;
        if ($bus) {
            $includedKm = $duration * $bus->package_km_per_day;
            $baseCost = $duration * $bus->package_price_per_day;
            $busCurrency = $bus->currency ?? 'PLN';
            if ($totalKm <= $includedKm) {
                $busTransportCost = $baseCost;
            } else {
                $extraKm = $totalKm - $includedKm;
                $busTransportCost = $baseCost + ($extraKm * $bus->extra_km_price);
            }
        }

        foreach ($qtyVariants as $qtyVariant) {
            $qty = $qtyVariant->qty;
            // For point and ticket calculations we charge for qty + gratis + staff (drivers excluded)
            // use qty only for points/podpunkty (exclude gratis and staff)
            $peopleForPoints = $qty; // keep var for compatibility but it's just qty now
            // Keep full qtyTotal including drivers for transport/hotel calculations
            $qtyTotal = $qty + ($qtyVariant->gratis ?? 0) + ($qtyVariant->staff ?? 0) + ($qtyVariant->driver ?? 0);
            $busMultiplier = 1;
            if ($bus && $bus->capacity > 0 && $qtyTotal > $bus->capacity) {
                $busMultiplier = (int) ceil($qtyTotal / $bus->capacity);
            }
            $calculations[$qty] = [];
            $plnTotal = 0;
            $plnPoints = [];
            $currenciesTotals = [];
            $currenciesPoints = [];
            $hotelStructure = [];
            $hotelTotal = [];

            foreach ($programPoints as $point) {
                $pointPivot = $programPointPivotMap[$point->id] ?? null;
                $pointIncluded = $pointPivot ? (bool) ($pointPivot->include_in_calculation ?? true) : true;

                // Parent point: only render/add to lists when pivot includes it.
                if ($point->currency) {
                    $currencyCode = $point->currency->symbol;
                    $currencySymbol = $point->currency->symbol ?? $currencyCode;
                    $exchangeRate = $point->currency->exchange_rate ?? 1;
                    $groupSize = $point->group_size ?? 1;
                    $unitPrice = $point->unit_price ?? 0;
                    $convertToPln = $point->convert_to_pln ?? false;

                    if ($pointIncluded) {
                        // calculate point cost using qty only
                        $cost = $this->calculatePointCost($qty, $groupSize, $unitPrice);

                        if ($currencyCode === 'PLN') {
                            $plnPoints[] = [
                                'name' => $point->name,
                                'unit_price' => $unitPrice,
                                'group_size' => $groupSize,
                                'cost' => $cost,
                                'is_child' => false,
                                'currency_symbol' => $currencySymbol,
                            ];
                            $plnTotal += $cost;
                        } elseif ($convertToPln) {
                            $plnPoints[] = [
                                'name' => $point->name.' (przeliczone na PLN, kurs: '.$exchangeRate.')',
                                'unit_price' => $unitPrice.' '.$currencySymbol,
                                'group_size' => $groupSize,
                                'cost' => $cost * $exchangeRate,
                                'is_child' => false,
                                'currency_symbol' => 'PLN',
                                'original_currency' => $currencySymbol,
                                'exchange_rate' => $exchangeRate,
                            ];
                            $plnTotal += $cost * $exchangeRate;
                        } else {
                            $currenciesPoints[$currencyCode][] = [
                                'name' => $point->name,
                                'unit_price' => $unitPrice,
                                'group_size' => $groupSize,
                                'cost' => $cost,
                                'is_child' => false,
                                'currency_symbol' => $currencySymbol,
                            ];
                            $currenciesTotals[$currencyCode] = ($currenciesTotals[$currencyCode] ?? 0) + $cost;
                        }
                    }
                }

                // Podpunkty: treat children as independent entities — process them regardless of parent inclusion
                foreach ($point->children as $child) {
                    $childPivot = $programPointPivotMap[$child->id] ?? null;
                    // If there's an explicit pivot for the child, use it. Otherwise default to true
                    // (child points are independent entities and should be included unless explicitly excluded).
                    $childIncluded = $childPivot ? (bool) ($childPivot->include_in_calculation ?? true) : true;

                    if (! $childIncluded) {
                        continue;
                    }
                    if ($child->currency) {
                        $childCurrencyCode = $child->currency->symbol;
                        $childCurrencySymbol = $child->currency->symbol ?? $childCurrencyCode;
                        $childExchangeRate = $child->currency->exchange_rate ?? 1;
                        $childGroupSize = $child->group_size ?? 1;
                        $childUnitPrice = $child->unit_price ?? 0;
                        // child cost uses qty only
                        $childCost = $this->calculatePointCost($qty, $childGroupSize, $childUnitPrice);
                        $childConvertToPln = $child->convert_to_pln ?? false;

                        if ($childCurrencyCode === 'PLN') {
                            $plnPoints[] = [
                                'name' => '→ '.$child->name,
                                'unit_price' => $childUnitPrice,
                                'group_size' => $childGroupSize,
                                'cost' => $childCost,
                                'is_child' => true,
                                'currency_symbol' => $childCurrencySymbol,
                            ];
                            $plnTotal += $childCost;
                        } elseif ($childConvertToPln) {
                            $plnPoints[] = [
                                'name' => '→ '.$child->name.' (przeliczone na PLN, kurs: '.$childExchangeRate.')',
                                'unit_price' => $childUnitPrice.' '.$childCurrencySymbol,
                                'group_size' => $childGroupSize,
                                'cost' => $childCost * $childExchangeRate,
                                'is_child' => true,
                                'currency_symbol' => 'PLN',
                                'original_currency' => $childCurrencySymbol,
                                'exchange_rate' => $childExchangeRate,
                            ];
                            $plnTotal += $childCost * $childExchangeRate;
                        } else {
                            $currenciesPoints[$childCurrencyCode][] = [
                                'name' => '→ '.$child->name,
                                'unit_price' => $childUnitPrice,
                                'group_size' => $childGroupSize,
                                'cost' => $childCost,
                                'is_child' => true,
                                'currency_symbol' => $childCurrencySymbol,
                            ];
                            $currenciesTotals[$childCurrencyCode] = ($currenciesTotals[$childCurrencyCode] ?? 0) + $childCost;
                        }
                    }
                }
            }

            // PLN na pierwszym miejscu — ubezpieczenie NNW (SSoT InsuranceCostCalculator)
            $dayInsurances = $template->dayInsurances ?? collect();
            $insuranceNames = $dayInsurances
                ->map(fn ($dayInsurance) => $dayInsurance->insurance)
                ->filter(fn ($insurance) => InsuranceCostCalculator::isChargeable($insurance))
                ->map(fn ($insurance) => $insurance->name)
                ->unique()
                ->values()
                ->all();
            $insuranceTotal = InsuranceCostCalculator::totalForDayAssignments(
                $dayInsurances,
                (int) $qty,
                (int) ($qtyVariant->gratis ?? 0)
            );
            if ($insuranceTotal > 0) {
                $plnPoints[] = [
                    'name' => 'Ubezpieczenie'.(! empty($insuranceNames) ? ' ('.implode(', ', $insuranceNames).')' : ''),
                    'unit_price' => null,
                    'group_size' => null,
                    'cost' => $insuranceTotal,
                    'is_child' => false,
                    'currency_symbol' => 'PLN',
                ];
                $plnTotal += $insuranceTotal;
            }
            $calculations[$qty]['PLN'] = [
                'total' => $plnTotal,
                'points' => $plnPoints,
            ];
            foreach ($currenciesTotals as $code => $total) {
                if ($code !== 'PLN') {
                    $calculations[$qty][$code] = [
                        'total' => $total,
                        'points' => $currenciesPoints[$code] ?? [],
                    ];
                }
            }                // --- NOCLEGI ---
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
                // Rozbij sumy noclegów na PLN (w tym przeliczone) i inne waluty
                $dayTotalPln = 0;
                $dayTotalForeign = [];
                $dayTotal = [];
                $roomAlloc = [];
                foreach ($roomGroups as $groupType => $groupData) {
                    $peopleCount = $groupData['count'];
                    $roomIds = $groupData['room_ids'];
                    if ($peopleCount <= 0) {
                        continue;
                    }
                    if (empty($roomIds)) {
                        // Dodaj informację o braku pokoi dla tej grupy i noclegu
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
                    $maxCapacity = $rooms->sum('people_count') * ($peopleCount); // duży zapas
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
                        // Nie udało się przydzielić żadnej kombinacji
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

                                // Przydzielaj tylko tyle osób, ile jeszcze potrzeba
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
                                // Obsługa waluty noclegów: PLN lub przeliczenie na PLN wg convert_to_pln, inaczej pozostaje w walucie obcej
                                $roomCurrency = $room->currency;
                                $convertFlag = (bool) ($room->convert_to_pln ?? false);
                                if ($roomCurrency === 'PLN') {
                                    $dayTotalPln += $room->price;
                                } elseif ($convertFlag) {
                                    $rate = \App\Models\Currency::where('symbol', $roomCurrency)->first()?->exchange_rate ?? 1;
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

                // Zbuduj day_total zgodnie z rozbiciem PLN/obce waluty na potrzeby debugowania widżetu
                $dayTotal = array_merge(['PLN' => $dayTotalPln], $dayTotalForeign);

                $hotelStructure[] = [
                    'day' => $hotelDay->day,
                    'rooms' => $roomAlloc,
                    'day_total' => $dayTotal,
                ];

                // Dodaj do ogólnej sumy kosztów noclegów (PLN + waluty obce)
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
            $calculations[$qty]['hotel_structure'] = $hotelStructure;

            // DODAJ KOSZT TRANSPORTU DO WALUTY AUTOKARU - PRZED obliczeniem narzutu
            if ($bus && $busTransportCost !== null) {
                if ($busCurrency === 'PLN') {
                    $plnPoints[] = [
                        'name' => 'Koszt transportu (autokar)',
                        'unit_price' => null,
                        'group_size' => null,
                        'cost' => $busTransportCost * $busMultiplier,
                        'is_child' => false,
                        'currency_symbol' => $busCurrency,
                    ];
                    $plnTotal += $busTransportCost * $busMultiplier;
                } else {
                    if (! isset($currenciesPoints[$busCurrency])) {
                        $currenciesPoints[$busCurrency] = [];
                        $currenciesTotals[$busCurrency] = 0;
                    }
                    $currenciesPoints[$busCurrency][] = [
                        'name' => 'Koszt transportu (autokar)',
                        'unit_price' => null,
                        'group_size' => null,
                        'cost' => $busTransportCost * $busMultiplier,
                        'is_child' => false,
                        'currency_symbol' => $busCurrency,
                    ];
                    $currenciesTotals[$busCurrency] += $busTransportCost * $busMultiplier;
                }
            }            // OBLICZ NARZUT - po dodaniu wszystkich kosztów (włącznie z transportem)
            // MARKUP (PLN): licz tylko od sumy PLN (w tym przeliczeń), bez walut obcych
            $markupAmount = $this->calculateMarkup($template, $plnTotal);
            $markupCalculation = ['amount' => $markupAmount];

            // Oblicz podatki
            $taxes = $template->taxes ?? collect();
            $taxCalculations = [];
            $totalTaxAmount = 0;

            foreach ($taxes as $tax) {
                if (! $tax->is_active) {
                    continue;
                }

                $taxAmount = $tax->calculateTaxAmount($plnTotal, $markupCalculation['amount']);
                if ($taxAmount > 0) {
                    $taxCalculations[] = [
                        'name' => $tax->name,
                        'percentage' => $tax->percentage,
                        'amount' => $taxAmount,
                        'apply_to_base' => $tax->apply_to_base,
                        'apply_to_markup' => $tax->apply_to_markup,
                    ];
                    $totalTaxAmount += $taxAmount;
                }
            }

            // Dodaj narzut do obliczeń
            $calculations[$qty]['markup'] = $markupCalculation;

            // Dodaj podatki do obliczeń
            // Dodaj informacje o narzucie (użyj preferowanego źródła procentu)
            $markupPercent = $this->getMarkupPercent($template);
            $calculations[$qty]['markup'] = [
                'amount' => $markupCalculation['amount'],
                'percent_applied' => $markupPercent,
                'discount_applied' => false, // uproszczona wersja - bez skomplikowanej logiki rabatów
                'discount_percent' => 0,
                'min_daily_applied' => false,
            ];

            $calculations[$qty]['taxes'] = [
                'total_amount' => $totalTaxAmount,
                'breakdown' => $taxCalculations,
            ];

            // PLN na pierwszym miejscu - BEZ narzutu w points
            $plnTotalWithMarkupTax = $plnTotal + $markupCalculation['amount'] + $totalTaxAmount;
            // oblicz raw price per person i zastosuj regułę zaokrąglenia (PLN->5)
            $rawPricePerPerson = $qty > 0 ? $plnTotalWithMarkupTax / $qty : 0;
            $roundedPricePerPerson = PriceRoundingService::roundPerPerson($rawPricePerPerson, 'PLN');
            $calculations[$qty]['PLN'] = [
                'total' => $plnTotalWithMarkupTax,
                'total_before_markup' => $plnTotal,
                'total_before_tax' => $plnTotal + $markupCalculation['amount'],
                'points' => $plnPoints,
                'price_per_person_raw' => round($rawPricePerPerson, 2),
                'price_per_person_rounded' => $roundedPricePerPerson,
            ];

            // Dodaj informacje o narzucie i podatkach dla każdej waluty obcej
            foreach ($currenciesTotals as $code => $total) {
                if ($code !== 'PLN') {
                    $currency = \App\Models\Currency::where('symbol', $code)->first();
                    $exchangeRate = $currency?->exchange_rate ?? 1;

                    // Oblicz narzut osobno dla każdej waluty obcej (percent z kosztów w tej walucie)
                    $currencyMarkup = $total * ($markupPercent / 100);

                    // Podatki tylko dla PLN, więc dla walut obcych tax_amount = 0
                    $currencyTax = 0;

                    \Illuminate\Support\Facades\Log::info("Widget markup for {$code}: total={$total}, percent={$markupPercent}, markup={$currencyMarkup}, tax={$currencyTax}");

                    $foreignTotalWithMarkup = $total + $currencyMarkup + $currencyTax;
                    $rawFpp = $qty > 0 ? $foreignTotalWithMarkup / $qty : 0;
                    $roundedFpp = PriceRoundingService::roundPerPerson($rawFpp, $code);
                    $calculations[$qty][$code] = [
                        'total' => $foreignTotalWithMarkup,
                        'total_before_markup' => $total,
                        'total_before_tax' => $total + $currencyMarkup,
                        'markup_amount' => round($currencyMarkup, 2),
                        'tax_amount' => round($currencyTax, 2),
                        'points' => $currenciesPoints[$code] ?? [],
                        'price_per_person_raw' => round($rawFpp, 2),
                        'price_per_person_rounded' => $roundedFpp,
                    ];
                }
            }
        }

        // Sortuj wyniki po ilości osób (klucz qty)
        ksort($calculations);

        return $calculations;
    }

    private function getMarkupPercent(EventTemplate $template): float
    {
        // If relation loaded
        if (isset($template->markup) && $template->markup?->percent !== null) {
            return (float) $template->markup->percent;
        }

        // If markup_id set, try to resolve
        if (! empty($template->markup_id)) {
            $m = Markup::find($template->markup_id);
            if ($m && $m->percent !== null) {
                return (float) $m->percent;
            }
        }

        // Legacy field on template
        if (isset($template->markup_percent) && $template->markup_percent !== null && $template->markup_percent !== '') {
            return (float) $template->markup_percent;
        }

        // Fallback to default markup record
        $default = Markup::where('is_default', true)->first();

        return (float) ($default?->percent ?? 20);
    }

    private function calculateMarkup(EventTemplate $template, $basePrice): float
    {
        $markupPercent = $this->getMarkupPercent($template);

        return $basePrice * ($markupPercent / 100);
    }

    public function calculatePointCost($qty, $groupSize, $unitPrice): float
    {
        return ProgramPointPricingCalculator::totalPrice(
            (float) $unitPrice,
            (int) $qty,
            (int) ($groupSize ?? 0) > 0 ? (int) $groupSize : 1,
        );
    }
}
