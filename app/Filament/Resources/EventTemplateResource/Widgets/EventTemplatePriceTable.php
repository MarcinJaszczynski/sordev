<?php

namespace App\Filament\Resources\EventTemplateResource\Widgets;

use Filament\Widgets\Widget;
use App\Models\EventTemplate;
use App\Models\EventTemplatePricePerPerson;
use App\Jobs\RecalculateAllEventTemplatePricesJob;
use Filament\Notifications\Notification;
use App\Services\PriceRoundingService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use function auth;

class EventTemplatePriceTable extends Widget
{
    /**
     * Zaokrągla wartość w górę do najbliższych 5 zł
     */
    private function ceilTo5($value): float
    {
        return ceil($value / 5) * 5;
    }
    protected static string $view = 'filament.resources.event-template-resource.widgets.event-template-price-table';
    public ?EventTemplate $record = null;
    public ?\App\Models\Place $startPlace = null;
    public ?int $startPlaceId = null;
    public ?float $transportKm = null;
    protected int | string | array $columnSpan = 'full';

    public $prices = [];
    public $detailedCalculations = [];
    public $qtyVariants = [];
    public array $variantOverrides = [];

    public function mount()
    {
        // Pobierz start_place_id z parametru URL lub z właściwości
        if (!$this->startPlaceId) {
            $this->startPlaceId = request()->get('start_place');
        }

        // Wczytaj start place jeśli jest ustawiony
        if ($this->startPlaceId) {
            $this->startPlace = \App\Models\Place::find($this->startPlaceId);
        }

        // Dodaj debugging
        \Illuminate\Support\Facades\Log::info("EventTemplatePriceTable mount - startPlaceId: " . ($this->startPlaceId ?? 'NULL') . ", startPlace: " . ($this->startPlace ? $this->startPlace->name : 'NULL'));

        // Wczytaj markup i podatki wraz z rekordem
        if ($this->record) {
            $this->record->load(['markup', 'taxes']);
        }
        $this->prices = $this->getPricesProperty();
        $this->qtyVariants = $this->getQtyVariantsProperty();
        $this->detailedCalculations = $this->getDetailedCalculations();

        // Dodaj dodatkowe debugging
        \Illuminate\Support\Facades\Log::info("EventTemplatePriceTable mount - prices count: " . (is_array($this->prices) ? count($this->prices) : $this->prices->count()));
    }

    public function getPricesProperty()
    {
        if (!$this->record) return collect();

        // Znajdź wszystkie polskie waluty (może być duplikatów)
        $polishCurrencyIds = \App\Models\Currency::where(function ($q) {
            $q->where('name', 'like', '%polski%złoty%')
                ->orWhere('name', 'like', '%złoty%polski%')
                ->orWhere('name', '=', 'Polski złoty')
                ->orWhere('name', '=', 'Złoty polski')
                ->orWhere('code', '=', 'PLN');
        })->pluck('id')->toArray();

        $query = EventTemplatePricePerPerson::with(['eventTemplateQty', 'currency', 'startPlace'])
            ->where('event_template_id', $this->record->id)
            ->whereIn('currency_id', $polishCurrencyIds); // Tylko polskie waluty

        // Jeśli jest wybrany start place, filtruj według niego
        if ($this->startPlaceId) {
            $query->where('start_place_id', $this->startPlaceId);
        } else {
            // Jeśli nie ma start place, pokazuj ceny bez miejsca startowego (backward compatibility)
            $query->whereNull('start_place_id');
        }

        $allPrices = $query->orderBy('event_template_qty_id')
            ->orderBy('currency_id')
            ->get();

        // Grupuj po qty i sumuj ceny z różnych walut PLN (tak jak w EventTemplateTransport::getPricesData())
        $groupedPrices = $allPrices->groupBy('event_template_qty_id');

        $results = collect();
        foreach ($groupedPrices as $qtyId => $pricesForQty) {
            // Sumuj wszystkie ceny dla tej samej ilości uczestników (z różnych walut PLN)
            $totalPriceBase = $pricesForQty->sum('price_base') ?: 0;
            $totalMarkup = $pricesForQty->sum('markup_amount') ?: 0;
            $totalTax = $pricesForQty->sum('tax_amount') ?: 0;
            $totalPriceWithTax = $pricesForQty->sum('price_with_tax') ?: $pricesForQty->sum('price_per_person') ?: 0;
            $totalTransportCost = $pricesForQty->sum('transport_cost') ?: 0;

            // Weź qty i inne dane z pierwszego rekordu
            $firstPrice = $pricesForQty->first();

            // Znajdź najlepszą polską walutę dla tego rekordu
            $bestCurrency = $this->findBestPolishCurrency();

            // Utwórz kombinowany obiekt cenowy
            $combinedPrice = new \stdClass();
            $combinedPrice->id = $firstPrice->id;
            $combinedPrice->event_template_id = $firstPrice->event_template_id;
            $combinedPrice->event_template_qty_id = $qtyId;
            $combinedPrice->start_place_id = $firstPrice->start_place_id;
            $combinedPrice->currency_id = $bestCurrency ? $bestCurrency->id : $firstPrice->currency_id;
            $combinedPrice->price_base = $totalPriceBase;
            $combinedPrice->markup_amount = $totalMarkup;
            $combinedPrice->tax_amount = $totalTax;
            $combinedPrice->price_with_tax = $totalPriceWithTax;
            $combinedPrice->price_per_person = $totalPriceWithTax; // Alias
            $combinedPrice->transport_cost = $totalTransportCost;

            // Zachowaj relacje
            $combinedPrice->eventTemplateQty = $firstPrice->eventTemplateQty;
            $combinedPrice->currency = $bestCurrency ?: $firstPrice->currency;
            $combinedPrice->startPlace = $firstPrice->startPlace;

            // Dodaj podatki breakdown jeśli istnieją
            $taxBreakdown = [];
            foreach ($pricesForQty as $price) {
                if ($price->tax_breakdown && is_array($price->tax_breakdown)) {
                    foreach ($price->tax_breakdown as $tax) {
                        $taxName = $tax['tax_name'] ?? 'Nieznany podatek';
                        if (!isset($taxBreakdown[$taxName])) {
                            $taxBreakdown[$taxName] = 0;
                        }
                        $taxBreakdown[$taxName] += floatval($tax['tax_amount'] ?? 0);
                    }
                }
            }

            // Konwertuj breakdown z powrotem do formatu
            $combinedPrice->tax_breakdown = [];
            foreach ($taxBreakdown as $taxName => $taxAmount) {
                $combinedPrice->tax_breakdown[] = [
                    'tax_name' => $taxName,
                    'tax_amount' => $taxAmount
                ];
            }

            $results->push($combinedPrice);
        }

        return $results;
    }

    /**
     * Znajdź najlepszą polską walutę w systemie (zabezpieczone przed duplikatami)
     */
    private function findBestPolishCurrency(): ?\App\Models\Currency
    {
        // Najpierw znajdź polskie waluty, które faktycznie mają dane cenowe dla tego szablonu
        $polishCurrenciesWithData = \App\Models\Currency::where(function ($q) {
            $q->where('name', 'like', '%polski%złoty%')
                ->orWhere('name', 'like', '%złoty%polski%')
                ->orWhere('name', '=', 'Polski złoty')
                ->orWhere('name', '=', 'Złoty polski')
                ->orWhere('code', '=', 'PLN');
        })
            ->whereHas('eventTemplatePrices', function ($q) {
                $q->where('event_template_id', $this->record->id);
            })
            ->orderBy('id') // Preferuj najniższe ID
            ->get();

        // Jeśli są waluty z danymi, zwróć pierwszą
        if ($polishCurrenciesWithData->isNotEmpty()) {
            return $polishCurrenciesWithData->first();
        }

        // Fallback: zwróć pierwszą polską walutę (nawet bez danych)
        return \App\Models\Currency::where(function ($q) {
            $q->where('name', 'like', '%polski%złoty%')
                ->orWhere('name', 'like', '%złoty%polski%')
                ->orWhere('name', '=', 'Polski złoty')
                ->orWhere('name', '=', 'Złoty polski')
                ->orWhere('code', '=', 'PLN');
        })
            ->orderBy('id')
            ->first();
    }

    public function getQtyVariantsProperty()
    {
        if (!empty($this->variantOverrides)) {
            $variants = [];
            foreach ($this->variantOverrides as $variant) {
                $qty = (int) ($variant['qty'] ?? 0);
                if ($qty < 1) {
                    continue;
                }

                $variants[$qty] = [
                    'qty' => $qty,
                    'gratis' => max(0, (int) ($variant['gratis'] ?? 0)),
                    'staff' => max(0, (int) ($variant['staff'] ?? 0)),
                    'driver' => max(0, (int) ($variant['driver'] ?? 0)),
                ];
            }

            return $variants;
        }

        // Zwraca tablicę wariantów qty z kluczem qty
        $variants = [];
        foreach (\App\Models\EventTemplateQty::all() as $variant) {
            $variants[$variant->qty] = [
                'qty' => $variant->qty,
                'gratis' => $variant->gratis ?? 0,
                'staff' => $variant->staff ?? 0,
                'driver' => $variant->driver ?? 0,
            ];
        }
        return $variants;
    }

    public function getDetailedCalculations()
    {
        if (!$this->record) return [];

        // Load all program points and respect per-pivot include_in_calculation flags individually
        // Note: EventTemplateProgramPoint has relations 'currency' and 'children', not 'templatePoint'
        $programPoints = $this->record->programPoints()->with(['currency', 'children.currency'])->get();

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
            $childPivots = $this->record->programPointChildren()->get();
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

        $qtyVariants = !empty($this->variantOverrides)
            ? collect($this->variantOverrides)
                ->map(function (array $variant) {
                    $model = new \App\Models\EventTemplateQty();
                    $model->qty = max(1, (int) ($variant['qty'] ?? 1));
                    $model->gratis = max(0, (int) ($variant['gratis'] ?? 0));
                    $model->staff = max(0, (int) ($variant['staff'] ?? 0));
                    $model->driver = max(0, (int) ($variant['driver'] ?? 0));
                    return $model;
                })
            : \App\Models\EventTemplateQty::all();
        $calculations = [];

        $bus = $this->record->bus;
        $programKm = $this->record->program_km ?? 0;
        $startPlaceId = $this->startPlaceId;
        $templateStartId = $this->record->start_place_id;
        $templateEndId = $this->record->end_place_id;

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
        if ($this->transportKm !== null) {
            $totalKm = $this->transportKm;
        } else {
            $totalKm = 1.1 * $basicDistance + 50;
        }

        $duration = $this->record->duration_days ?? 1;
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
                $pointIncluded = $pointPivot ? (bool)($pointPivot->include_in_calculation ?? true) : true;

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
                                'currency_symbol' => $currencySymbol
                            ];
                            $plnTotal += $cost;
                        } elseif ($convertToPln) {
                            $plnPoints[] = [
                                'name' => $point->name . ' (przeliczone na PLN, kurs: ' . $exchangeRate . ')',
                                'unit_price' => $unitPrice . ' ' . $currencySymbol,
                                'group_size' => $groupSize,
                                'cost' => $cost * $exchangeRate,
                                'is_child' => false,
                                'currency_symbol' => 'PLN',
                                'original_currency' => $currencySymbol,
                                'exchange_rate' => $exchangeRate
                            ];
                            $plnTotal += $cost * $exchangeRate;
                        } else {
                            $currenciesPoints[$currencyCode][] = [
                                'name' => $point->name,
                                'unit_price' => $unitPrice,
                                'group_size' => $groupSize,
                                'cost' => $cost,
                                'is_child' => false,
                                'currency_symbol' => $currencySymbol
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
                    $childIncluded = $childPivot ? (bool)($childPivot->include_in_calculation ?? true) : true;

                    if (!$childIncluded) continue;
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
                                'name' => '→ ' . $child->name,
                                'unit_price' => $childUnitPrice,
                                'group_size' => $childGroupSize,
                                'cost' => $childCost,
                                'is_child' => true,
                                'currency_symbol' => $childCurrencySymbol
                            ];
                            $plnTotal += $childCost;
                        } elseif ($childConvertToPln) {
                            $plnPoints[] = [
                                'name' => '→ ' . $child->name . ' (przeliczone na PLN, kurs: ' . $childExchangeRate . ')',
                                'unit_price' => $childUnitPrice . ' ' . $childCurrencySymbol,
                                'group_size' => $childGroupSize,
                                'cost' => $childCost * $childExchangeRate,
                                'is_child' => true,
                                'currency_symbol' => 'PLN',
                                'original_currency' => $childCurrencySymbol,
                                'exchange_rate' => $childExchangeRate
                            ];
                            $plnTotal += $childCost * $childExchangeRate;
                        } else {
                            $currenciesPoints[$childCurrencyCode][] = [
                                'name' => '→ ' . $child->name,
                                'unit_price' => $childUnitPrice,
                                'group_size' => $childGroupSize,
                                'cost' => $childCost,
                                'is_child' => true,
                                'currency_symbol' => $childCurrencySymbol
                            ];
                            $currenciesTotals[$childCurrencyCode] = ($currenciesTotals[$childCurrencyCode] ?? 0) + $childCost;
                        }
                    }
                }
            }

            // PLN na pierwszym miejscu
            // DODAJ KOSZT UBEZPIECZENIA DO PLN
            $insuranceTotal = 0;
            $insuranceNames = [];
            $dayInsurances = $this->record->dayInsurances ?? collect();
            foreach ($dayInsurances as $dayInsurance) {
                $insurance = $dayInsurance->insurance;
                if ($insurance && $insurance->insurance_enabled) {
                    $insuranceNames[] = $insurance->name;
                    // naliczamy ubezpieczenie dla każdego dnia jako price_per_person * (qty + gratis)
                    if ($insurance->insurance_per_day || $insurance->insurance_per_person) {
                        $countForInsurance = $qty + ($qtyVariant->gratis ?? 0);
                        $insuranceTotal += $insurance->price_per_person * $countForInsurance;
                    }
                }
            }
            if ($insuranceTotal > 0) {
                $plnPoints[] = [
                    'name' => 'Ubezpieczenie' . (!empty($insuranceNames) ? ' (' . implode(', ', $insuranceNames) . ')' : ''),
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
                'points' => $plnPoints
            ];
            foreach ($currenciesTotals as $code => $total) {
                if ($code !== 'PLN') {
                    $calculations[$qty][$code] = [
                        'total' => $total,
                        'points' => $currenciesPoints[$code] ?? []
                    ];
                }
            }                // --- NOCLEGI ---
            $hotelDays = $this->record->hotelDays()->get();
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
                    if ($peopleCount <= 0) continue;
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
                            'warning' => 'Brak przypisanych pokoi dla tej grupy (' . $groupType . ') w noclegu.'
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
                            if ($dp[$i - $room->people_count] + $room->price < $dp[$i]) {
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
                            'warning' => 'Brak możliwej kombinacji pokoi dla tej grupy (' . $groupType . ') w noclegu.'
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

                                if ($peopleAssigned >= $peopleCount) break 2;
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
                        'name' => 'Noclegi - dzień ' . $hotelDay->day,
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
                        'name' => 'Noclegi - dzień ' . $hotelDay->day,
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
                        'currency_symbol' => $busCurrency
                    ];
                    $plnTotal += $busTransportCost * $busMultiplier;
                } else {
                    if (!isset($currenciesPoints[$busCurrency])) {
                        $currenciesPoints[$busCurrency] = [];
                        $currenciesTotals[$busCurrency] = 0;
                    }
                    $currenciesPoints[$busCurrency][] = [
                        'name' => 'Koszt transportu (autokar)',
                        'unit_price' => null,
                        'group_size' => null,
                        'cost' => $busTransportCost * $busMultiplier,
                        'is_child' => false,
                        'currency_symbol' => $busCurrency
                    ];
                    $currenciesTotals[$busCurrency] += $busTransportCost * $busMultiplier;
                }
            }            // OBLICZ NARZUT - po dodaniu wszystkich kosztów (włącznie z transportem)
            // MARKUP (PLN): licz tylko od sumy PLN (w tym przeliczeń), bez walut obcych
            $markupAmount = $this->calculateMarkup($plnTotal);
            $markupCalculation = ['amount' => $markupAmount];

            // Oblicz podatki
            $taxes = $this->record->taxes ?? collect();
            $taxCalculations = [];
            $totalTaxAmount = 0;

            foreach ($taxes as $tax) {
                if (!$tax->is_active) continue;

                $taxAmount = $tax->calculateTaxAmount($plnTotal, $markupCalculation['amount']);
                if ($taxAmount > 0) {
                    $taxCalculations[] = [
                        'name' => $tax->name,
                        'percentage' => $tax->percentage,
                        'amount' => $taxAmount,
                        'apply_to_base' => $tax->apply_to_base,
                        'apply_to_markup' => $tax->apply_to_markup
                    ];
                    $totalTaxAmount += $taxAmount;
                }
            }

            // Dodaj narzut do obliczeń
            $calculations[$qty]['markup'] = $markupCalculation;

            // Dodaj podatki do obliczeń
            // Dodaj informacje o narzucie (użyj preferowanego źródła procentu)
            $markupPercent = $this->getMarkupPercent();
            $calculations[$qty]['markup'] = [
                'amount' => $markupCalculation['amount'],
                'percent_applied' => $markupPercent,
                'discount_applied' => false, // uproszczona wersja - bez skomplikowanej logiki rabatów
                'discount_percent' => 0,
                'min_daily_applied' => false
            ];

            $calculations[$qty]['taxes'] = [
                'total_amount' => $totalTaxAmount,
                'breakdown' => $taxCalculations
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


    public function recalculatePrices(): void
    {
        Log::info('Wywołano recalculatePrices przez Livewire');
        $userId = null;
        if (Auth::check()) {
            $userId = Auth::id();
        } elseif (method_exists(filament(), 'auth') && filament()->auth()?->user()) {
            $userId = filament()->auth()->user()->id;
        }

        if (!$userId) {
            Notification::make()
                ->title('Błąd')
                ->body('Nie można pobrać ID użytkownika do powiadomienia.')
                ->danger()
                ->send();
            return;
        }

        // Zainicjuj progres natychmiast (pokazuje 0 z total) — total to liczba wszystkich szablonów
        try {
            \App\Services\PriceRecalcProgress::start($userId, \App\Models\EventTemplate::count());
            // powiadom widgety na stronie, jeśli są (Livewire emit)
            if (method_exists($this, 'emit')) {
                $this->emit('priceRecalcStarted');
            }
        } catch (\Throwable $e) {
            // ignore
        }

        \App\Jobs\RecalculateAllEventTemplatePricesJob::dispatch($userId)->afterResponse();

        Notification::make()
            ->title('Przeliczanie cen zostało zlecone')
            ->body('Proces przeliczania cen został dodany do kolejki i wykona się w tle. Otrzymasz powiadomienie po zakończeniu.')
            ->success()
            ->send();
    }

    /**
     * Usuwa duplikaty cen z bazy danych - zachowuje tylko najnowsze rekordy (globalnie)
     */
    public static function removeDuplicatePrices(): void
    {
        \Illuminate\Support\Facades\Log::info("Removing duplicate prices globally");

        // Znajdź duplikaty - rekordy z tą samą kombinacją kluczy (dla wszystkich szablonów)
        $duplicateGroups = EventTemplatePricePerPerson::select('event_template_id', 'event_template_qty_id', 'currency_id', 'start_place_id')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('event_template_id', 'event_template_qty_id', 'currency_id', 'start_place_id')
            ->having('count', '>', 1)
            ->get();

        $removedCount = 0;
        foreach ($duplicateGroups as $group) {
            // Dla każdej grupy duplikatów, zachowaj tylko najnowszy rekord
            $records = EventTemplatePricePerPerson::where([
                'event_template_id' => $group->event_template_id,
                'event_template_qty_id' => $group->event_template_qty_id,
                'currency_id' => $group->currency_id,
                'start_place_id' => $group->start_place_id
            ])->orderBy('created_at', 'desc')->get();

            // Usuń wszystkie oprócz pierwszego (najnowszego)
            for ($i = 1; $i < $records->count(); $i++) {
                $records[$i]->delete();
                $removedCount++;
            }
        }

        if ($removedCount > 0) {
            \Illuminate\Support\Facades\Log::info("Removed {$removedCount} duplicate price records globally");
        }
    }

    /**
     * Pomocnicze metody do kalkulacji cen
     */
    private function getQtyId($qty): int
    {
        $qtyRecord = \App\Models\EventTemplateQty::where('qty', $qty)->first();
        return $qtyRecord ? $qtyRecord->id : 0;
    }

    private function calculateMarkup($basePrice): float
    {
        $markupPercent = $this->getMarkupPercent();
        return $basePrice * ($markupPercent / 100);
    }

    /**
     * Prefer markup percent from related Markup model, then markup_id, then template field, then default markup.
     */
    private function getMarkupPercent(): float
    {
        // If relation loaded
        if (isset($this->record->markup) && $this->record->markup?->percent !== null) {
            return (float) $this->record->markup->percent;
        }

        // If markup_id set, try to resolve
        if (!empty($this->record->markup_id)) {
            $m = \App\Models\Markup::find($this->record->markup_id);
            if ($m && $m->percent !== null) return (float) $m->percent;
        }

        // Legacy field on template
        if (isset($this->record->markup_percent) && $this->record->markup_percent !== null && $this->record->markup_percent !== '') {
            return (float) $this->record->markup_percent;
        }

        // Fallback to default markup record
        $default = \App\Models\Markup::where('is_default', true)->first();
        return (float) ($default?->percent ?? 20);
    }

    // Public helper for diagnostics/tests
    public function debugGetMarkupPercent(): float
    {
        return $this->getMarkupPercent();
    }

    private function calculateTax($basePrice): float
    {
        $taxPercent = 23; // VAT 23%
        $markupAmount = $this->calculateMarkup($basePrice);
        return ($basePrice + $markupAmount) * ($taxPercent / 100);
    }

    private function calculatePriceWithTax($basePrice): float
    {
        return $basePrice + $this->calculateMarkup($basePrice) + $this->calculateTax($basePrice);
    }

    private function calculatePricePerPerson($totalPrice, $qty): float
    {
        return $qty > 0 ? $totalPrice / $qty : 0;
    }

    private function calculateTransportCost($qty): float
    {
        // Algorytm: (dojazd + program + powrót) * 1.1 + 50 km, liczba autobusów, limity km, nadmiarowe km
        $bus = $this->record->bus;
        if (!$bus) return 0;
        $duration = $this->record->duration_days ?? 1;
        $qtyVariant = \App\Models\EventTemplateQty::where('qty', $qty)->first();
        $totalPeople = $qty;
        if ($qtyVariant) {
            $totalPeople += ($qtyVariant->gratis ?? 0) + ($qtyVariant->staff ?? 0) + ($qtyVariant->driver ?? 0);
        }
        $busCapacity = $bus->capacity > 0 ? $bus->capacity : 50;
        $busCount = (int) ceil($totalPeople / $busCapacity);
        // Suma km: dojazd + program + powrót
        $transferKm = $this->record->transfer_km ?? 0;
        $programKm = $this->record->program_km ?? 0;
        $totalKm = ($transferKm * 2) + $programKm;
        $totalKm = $totalKm * 1.1 + 50;
        $includedKm = $duration * $bus->package_km_per_day;
        $baseCost = $duration * $bus->package_price_per_day;
        if ($totalKm <= $includedKm) {
            return $baseCost * $busCount;
        } else {
            $extraKm = $totalKm - $includedKm;
            return ($baseCost + ($extraKm * $bus->extra_km_price)) * $busCount;
        }
    }

    private function prepareTaxBreakdown(): array
    {
        return [
            ['tax_name' => 'VAT 23%', 'tax_amount' => 23]
        ];
    }
    public function calculatePointCost($qty, $groupSize, $unitPrice)
    {
        return ceil($qty / $groupSize) * $unitPrice;
    }

    /**
     * Oblicza całkowitą sumę w PLN dla danego wariantu qty
     */
    private function calculateTotalInPLN($qtyCalculation)
    {
        $totalPLN = 0;

        // Dodaj PLN bezpośrednio
        if (isset($qtyCalculation['PLN']['total'])) {
            $totalPLN += $qtyCalculation['PLN']['total'];
        } elseif (isset($qtyCalculation['PLN']) && is_numeric($qtyCalculation['PLN'])) {
            // Obsługa gdy przekazano bezpośrednio wartość
            $totalPLN += $qtyCalculation['PLN'];
        }

        // Przelicz inne waluty na PLN używając kursów z tabeli currencies
        foreach ($qtyCalculation as $currencyCode => $data) {
            if ($currencyCode === 'PLN' || $currencyCode === 'hotel_structure') {
                continue;
            }

            $amount = 0;
            if (is_array($data) && isset($data['total'])) {
                $amount = $data['total'];
            } elseif (is_numeric($data)) {
                $amount = $data;
            }

            if ($amount > 0) {
                // Znajdź kurs dla tej waluty
                $currency = \App\Models\Currency::where('symbol', $currencyCode)->first();
                if ($currency && $currency->exchange_rate) {
                    $totalPLN += $amount * $currency->exchange_rate;
                }
            }
        }
        return $totalPLN;
    }
}
