<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Event;
use App\Models\EventProgramPoint;
use App\Models\EventTemplateQty;
use App\Models\Markup;
use App\Support\ProgramPointCostPricing;
use Illuminate\Support\Collection;

/**
 * Snapshot kalkulacji imprezy bez zależności od Filament/Livewire.
 * Współdzielony przez EventCalculationPresenter (PDF/API) i EventPriceTable.
 *
 * @phpstan-type Snapshot array{
 *     calculations: array<string, mixed>,
 *     transport_cost: float|int,
 *     event_transport_km: float|null,
 *     detailed_calculations: array<int|string, mixed>,
 *     qty_variants: array<int|string, mixed>,
 *     current_variant: array<string, mixed>|null,
 *     nearest_variants: array<int, mixed>,
 *     price_rows: Collection|array,
 *     program_points: Collection|null,
 *     costs_by_day: Collection|null,
 *     event_only_points_for_details: array<string, mixed>
 * }
 */
final class EventCalculationSnapshotBuilder
{
    /**
     * Zamrożony, JSON-bezpieczny zrzut kalkulacji dla bieżącego wariantu imprezy.
     * Do zapisu w event_snapshots.calculations — bez modeli Eloquent / Collection.
     *
     * @return array{
     *     version: int,
     *     summary: array{
     *         total_cost: float,
     *         total_program_cost: float,
     *         transport_cost: float,
     *         price_per_person: float,
     *         price_per_person_rounded: float,
     *         participant_count: int,
     *         cost_per_person: float
     *     },
     *     current_variant: array{qty: int, gratis: int, staff: int, driver: int}|null,
     *     detailed_calculations: array<string, mixed>,
     *     transport_cost: float,
     *     event_transport_km: float|null,
     *     total_program_cost: float,
     *     points_count: int,
     *     active_points_count: int,
     *     included_in_calculation_count: int,
     *     cost_breakdown_by_day: array<string, mixed>
     * }
     */
    public function buildPersistableCalculation(Event $event): array
    {
        $snapshot = $this->build($event);
        $summaryCalc = is_array($snapshot['calculations'] ?? null) ? $snapshot['calculations'] : [];
        $currentVariant = is_array($snapshot['current_variant'] ?? null) ? $snapshot['current_variant'] : null;
        $qty = max(1, (int) ($currentVariant['qty'] ?? $event->participant_count ?? 1));

        $detailedAll = is_array($snapshot['detailed_calculations'] ?? null)
            ? $snapshot['detailed_calculations']
            : [];
        $detailedForVariant = $detailedAll[$qty]
            ?? $detailedAll[(string) $qty]
            ?? [];
        $detailedForVariant = is_array($detailedForVariant) ? $detailedForVariant : [];

        $pln = is_array($detailedForVariant['PLN'] ?? null) ? $detailedForVariant['PLN'] : [];
        $pricePerPersonRaw = (float) ($pln['price_per_person_raw'] ?? $summaryCalc['cost_per_person'] ?? 0);
        $pricePerPersonRounded = (float) ($pln['price_per_person_rounded'] ?? $pricePerPersonRaw);
        $totalFromDetailed = array_key_exists('total', $pln)
            ? (float) $pln['total']
            : null;
        $totalCost = $totalFromDetailed
            ?? (float) ($summaryCalc['total_cost'] ?? $event->total_cost ?? 0);
        $transportCost = (float) ($snapshot['transport_cost'] ?? $summaryCalc['transport_cost'] ?? 0);
        $totalProgramCost = (float) ($summaryCalc['total_program_cost'] ?? 0);

        $programPoints = $snapshot['program_points'] ?? collect();
        if (! $programPoints instanceof Collection) {
            $programPoints = collect($programPoints);
        }

        $activePoints = $programPoints->where('active', true);
        $included = ProgramPointHelper::filterIncluded($activePoints);
        $costBreakdownByDay = $included
            ->groupBy(fn ($point) => (string) ($point->day ?? 0))
            ->map(function ($points) {
                return [
                    'day_total' => (float) $points->sum('total_price'),
                    'points_count' => $points->count(),
                    'points' => $points->map(function ($point) {
                        return [
                            'name' => $point->templatePoint?->name ?? $point->name ?? ('Punkt #'.($point->id ?? '?')),
                            'total_price' => (float) ($point->total_price ?? 0),
                        ];
                    })->values()->all(),
                ];
            })
            ->all();

        $persistableDetailed = $this->toPersistableArray([
            (string) $qty => $detailedForVariant,
        ]);

        return $this->toPersistableArray([
            'version' => 2,
            'summary' => [
                'total_cost' => round($totalCost, 2),
                'total_program_cost' => round($totalProgramCost, 2),
                'transport_cost' => round($transportCost, 2),
                'price_per_person' => round($pricePerPersonRaw, 2),
                'price_per_person_rounded' => round($pricePerPersonRounded, 2),
                'participant_count' => $qty,
                'cost_per_person' => round((float) ($summaryCalc['cost_per_person'] ?? 0), 2),
            ],
            'current_variant' => $currentVariant === null ? null : [
                'qty' => max(1, (int) ($currentVariant['qty'] ?? $qty)),
                'gratis' => max(0, (int) ($currentVariant['gratis'] ?? 0)),
                'staff' => max(0, (int) ($currentVariant['staff'] ?? 1)),
                'driver' => max(0, (int) ($currentVariant['driver'] ?? 1)),
            ],
            'detailed_calculations' => $persistableDetailed,
            'transport_cost' => round($transportCost, 2),
            'event_transport_km' => isset($snapshot['event_transport_km'])
                ? (float) $snapshot['event_transport_km']
                : null,
            // Legacy keys — kompatybilność ze starym UI szczegółów.
            'total_program_cost' => round($totalProgramCost, 2),
            'points_count' => $programPoints->count(),
            'active_points_count' => $activePoints->count(),
            'included_in_calculation_count' => ProgramPointHelper::countIncluded($activePoints),
            'cost_breakdown_by_day' => $costBreakdownByDay,
        ]);
    }

    /**
     * Rekurencyjnie zamienia Collection / modele / stdClass na tablice JSON-safe.
     */
    public function toPersistableArray(mixed $value): mixed
    {
        if ($value instanceof Collection) {
            return $this->toPersistableArray($value->all());
        }

        if ($value instanceof \JsonSerializable) {
            return $this->toPersistableArray($value->jsonSerialize());
        }

        if ($value instanceof \Illuminate\Contracts\Support\Arrayable) {
            return $this->toPersistableArray($value->toArray());
        }

        if (is_object($value)) {
            return $this->toPersistableArray((array) $value);
        }

        if (is_array($value)) {
            $out = [];
            foreach ($value as $key => $item) {
                $out[$key] = $this->toPersistableArray($item);
            }

            return $out;
        }

        return $value;
    }

    /**
     * @return Snapshot
     */
    public function build(Event $event): array
    {
        $event->loadMissing(['eventTemplate', 'bus', 'markup', 'startPlace']);

        $programPoints = $event->programPoints()
            ->with(['templatePoint', 'currency'])
            ->where('active', true)
            ->orderBy('day')
            ->orderBy('order')
            ->get();

        $currentVariant = [
            'qty' => max(1, (int) ($event->participant_count ?? 1)),
            'gratis' => 0,
            'staff' => 1,
            'driver' => 1,
        ];

        $transportCalculator = new EventTransportCostCalculator($event);
        $eventTransportKm = $transportCalculator->resolveTransportKm();
        $transportCost = $transportCalculator->effectiveTransportCost($currentVariant);

        $costsByDay = $programPoints
            ->groupBy('day')
            ->map(function ($points) {
                $totalCost = ProgramPointHelper::sumIncluded($points, 'total_price');
                $programCost = $points->where('include_in_program', true)->sum('total_price');

                return [
                    'points_count' => $points->count(),
                    'total_cost' => $totalCost,
                    'program_cost' => $programCost,
                    'calculation_points' => $points->filter(function ($p) {
                        return (bool) ($p->include_in_calculation ?? true);
                    })->count(),
                    'program_points' => $points->where('include_in_program', true)->count(),
                    'points' => $points,
                ];
            });

        $totalProgramCost = ProgramPointHelper::sumIncluded($programPoints, 'total_price');
        $totalCostWithTransport = $totalProgramCost + $transportCost;

        $calculations = [
            'total_points' => $programPoints->count(),
            'active_points' => $programPoints->where('active', true)->count(),
            'calculation_points' => ProgramPointHelper::countIncluded($programPoints),
            'program_points' => $programPoints->where('include_in_program', true)->count(),
            'total_program_cost' => $totalProgramCost,
            'transport_cost' => $transportCost,
            'total_cost' => $totalCostWithTransport,
            'program_cost' => $programPoints->where('include_in_program', true)->sum('total_price'),
            'cost_per_person' => $event->participant_count > 0
                ? $totalCostWithTransport / $event->participant_count
                : 0,
            'days_count' => $costsByDay->count(),
            'event_data' => [
                'name' => $event->name,
                'client_name' => $event->client_name,
                'participant_count' => $event->participant_count,
                'start_date' => $event->start_date,
                'end_date' => $event->end_date,
                'duration_days' => $event->duration_days,
                'transfer_km' => $event->transfer_km,
                'program_km' => $event->program_km,
                'status' => $event->status,
                'template_name' => $event->eventTemplate?->name,
                'bus_name' => $event->bus?->name,
                'markup_name' => $event->markup?->name,
            ],
        ];

        $priceRows = $event->pricePerPerson()
            ->with('eventTemplateQty:id,qty,gratis,staff,driver')
            ->orderByDesc('id')
            ->get();

        $detailed = $this->buildDetailedPricing($event, $programPoints, $transportCalculator);

        return [
            'calculations' => $calculations,
            'transport_cost' => $detailed['transport_cost'] ?? $transportCost,
            'event_transport_km' => $eventTransportKm,
            'detailed_calculations' => $detailed['detailed_calculations'],
            'qty_variants' => $detailed['qty_variants'],
            'current_variant' => $detailed['current_variant'] ?? $currentVariant,
            'nearest_variants' => $detailed['nearest_variants'],
            'price_rows' => $priceRows,
            'program_points' => $programPoints,
            'costs_by_day' => $costsByDay,
            'event_only_points_for_details' => $detailed['event_only_points_for_details'],
        ];
    }

    /**
     * Podsumowania wariantów z katalogu EventTemplateQty (SSoT: EventCostCalculator).
     * Gratis/staff/driver: dokładny EventQty imprezy, inaczej wartości z katalogu.
     *
     * @return list<array{
     *     qty: int,
     *     gratis: int,
     *     staff: int,
     *     driver: int,
     *     price_per_person: float,
     *     price_per_person_rounded: float,
     *     total_pln: float,
     *     base_pln: float,
     *     markup_pln: float,
     *     tax_pln: float,
     *     from_event_qty: bool
     * }>
     */
    public function buildCatalogVariantSummaries(Event $event): array
    {
        $event->loadMissing(['qtyVariants']);

        $eventVariants = $event->relationLoaded('qtyVariants')
            ? $event->qtyVariants
            : $event->qtyVariants()->get(['qty', 'gratis', 'staff', 'driver']);

        $catalog = EventTemplateQty::query()
            ->orderBy('qty')
            ->orderBy('id')
            ->get(['id', 'qty', 'gratis', 'staff', 'driver'])
            ->unique('qty')
            ->values();

        if ($catalog->isEmpty()) {
            return [];
        }

        $calculator = EventCostCalculator::for($event);
        $rows = [];

        foreach ($catalog as $catalogQty) {
            $qty = max(1, (int) $catalogQty->qty);
            $exactEvent = $eventVariants->firstWhere('qty', $qty);
            $fromEvent = $exactEvent !== null;

            $gratis = max(0, (int) ($exactEvent->gratis ?? $catalogQty->gratis ?? 0));
            $staff = max(0, (int) ($exactEvent->staff ?? $catalogQty->staff ?? 1));
            $driver = max(0, (int) ($exactEvent->driver ?? $catalogQty->driver ?? 1));

            try {
                $calc = $calculator->calculate($qty, $gratis, $staff, $driver);
            } catch (\Throwable $e) {
                report($e);

                continue;
            }

            $rows[] = [
                'qty' => $qty,
                'gratis' => $gratis,
                'staff' => $staff,
                'driver' => $driver,
                'price_per_person' => (float) ($calc['price_per_person'] ?? 0),
                'price_per_person_rounded' => (float) ($calc['price_per_person_rounded'] ?? $calc['price_per_person'] ?? 0),
                'total_pln' => (float) ($calc['total_pln'] ?? 0),
                'base_pln' => (float) ($calc['base_pln'] ?? 0),
                'markup_pln' => (float) ($calc['markup_pln'] ?? 0),
                'tax_pln' => (float) ($calc['tax_pln'] ?? 0),
                'from_event_qty' => $fromEvent,
            ];
        }

        return $rows;
    }

    /**
     * Pełna (poglądowa) kalkulacja dla jednego wariantu qty — lazy load z UI.
     *
     * @param  array{qty: int, gratis?: int, staff?: int, driver?: int}  $variant
     * @return array{
     *     detailed_calculations: array<int|string, mixed>,
     *     qty_variants: array<int|string, mixed>,
     *     event_only_points_for_details: array<string, mixed>,
     *     transport_cost: float|int|null
     * }
     */
    public function buildDetailedForVariant(Event $event, array $variant): array
    {
        $event->loadMissing(['eventTemplate', 'bus', 'startPlace']);

        $programPoints = $event->programPoints()
            ->with(['templatePoint', 'currency'])
            ->where('active', true)
            ->orderBy('day')
            ->orderBy('order')
            ->get();

        $normalized = [
            'qty' => max(1, (int) ($variant['qty'] ?? 1)),
            'gratis' => max(0, (int) ($variant['gratis'] ?? 0)),
            'staff' => max(0, (int) ($variant['staff'] ?? 1)),
            'driver' => max(0, (int) ($variant['driver'] ?? 1)),
        ];

        $transportCalculator = new EventTransportCostCalculator($event);

        return $this->computeDetailedForVariants(
            $event,
            $programPoints,
            $transportCalculator,
            [$normalized],
            $normalized,
        );
    }

    /**
     * @param  Collection<int, mixed>  $programPoints
     * @return array{
     *     detailed_calculations: array<int|string, mixed>,
     *     qty_variants: array<int|string, mixed>,
     *     current_variant: array<string, mixed>|null,
     *     nearest_variants: array<int, mixed>,
     *     event_only_points_for_details: array<string, mixed>,
     *     transport_cost: float|int|null
     * }
     */
    private function buildDetailedPricing(
        Event $event,
        Collection $programPoints,
        EventTransportCostCalculator $transportCalculator,
    ): array {
        $result = [
            'detailed_calculations' => [],
            'qty_variants' => [],
            'current_variant' => null,
            'nearest_variants' => [],
            'event_only_points_for_details' => [],
            'transport_cost' => null,
        ];

        $participantCount = max(1, (int) ($event->participant_count ?? 1));
        $eventVariants = $event->qtyVariants()->get(['qty', 'gratis', 'staff', 'driver']);

        $exactEventVariant = $eventVariants->firstWhere('qty', $participantCount);
        $closestEventVariant = $eventVariants
            ->sortBy(fn ($variant) => abs(((int) ($variant->qty ?? 0)) - $participantCount))
            ->first();

        $customVariant = [
            'qty' => $participantCount,
            'gratis' => max(0, (int) ($exactEventVariant->gratis ?? $closestEventVariant->gratis ?? 0)),
            'staff' => max(0, (int) ($exactEventVariant->staff ?? $closestEventVariant->staff ?? 1)),
            'driver' => max(0, (int) ($exactEventVariant->driver ?? $closestEventVariant->driver ?? 1)),
        ];

        $result['current_variant'] = $customVariant;

        $computed = $this->computeDetailedForVariants(
            $event,
            $programPoints,
            $transportCalculator,
            [$customVariant],
            $customVariant,
        );

        return array_merge($result, $computed);
    }

    /**
     * Szczegółowa kalkulacja UI z żywych punktów imprezy (SSoT jak EventCostCalculator).
     * Nie startuje od programu szablonu — usunięte/zmienione punkty imprezy nie „zostają”.
     *
     * @param  Collection<int, mixed>  $programPoints
     * @param  list<array{qty: int, gratis: int, staff: int, driver: int}>  $variants
     * @param  array{qty: int, gratis: int, staff: int, driver: int}  $transportReferenceVariant
     * @return array{
     *     detailed_calculations: array<int|string, mixed>,
     *     qty_variants: array<int|string, mixed>,
     *     event_only_points_for_details: array<string, mixed>,
     *     transport_cost: float|int|null
     * }
     */
    private function computeDetailedForVariants(
        Event $event,
        Collection $programPoints,
        EventTransportCostCalculator $transportCalculator,
        array $variants,
        array $transportReferenceVariant,
    ): array {
        $empty = [
            'detailed_calculations' => [],
            'qty_variants' => [],
            'event_only_points_for_details' => [],
            'transport_cost' => null,
        ];

        if ($variants === []) {
            return $empty;
        }

        try {
            $event->loadMissing([
                'markup',
                'eventTemplate.markup',
                'eventTemplate.taxes',
                'dayInsurances.insurance',
                'hotelStays.roomLines.currency',
                'hotelStays.roomLines.hotelRoom',
            ]);

            $qtyVariants = [];
            foreach ($variants as $variant) {
                $qtyVariants[(int) $variant['qty']] = $variant;
            }

            $hasHotelPlan = $event->hotelStays()->exists();
            $forceConvertForeign = ! ($event->eventTemplate?->isForeignTrip() ?? true);
            $markupPercent = $this->resolveMarkupPercent($event);

            $billablePoints = $programPoints
                ->filter(function ($point) use ($hasHotelPlan): bool {
                    if (! $point instanceof EventProgramPoint) {
                        return false;
                    }
                    if (! (bool) ($point->active ?? true) || ! (bool) ($point->include_in_calculation ?? true)) {
                        return false;
                    }

                    $isHotelService = (bool) ($point->is_hotel_service ?? false);
                    if (! $isHotelService && $this->isTransportProgramPoint($point)) {
                        return false;
                    }
                    if (! $isHotelService && $hasHotelPlan && $this->isAccommodationProgramPoint($point)) {
                        return false;
                    }

                    return true;
                })
                ->sortBy(['day', 'order'])
                ->values();

            $detailedCalculations = [];
            $eventOnlyPoints = [];

            foreach ($qtyVariants as $qty => $variant) {
                $paying = max(1, (int) $variant['qty']);
                $gratis = max(0, (int) ($variant['gratis'] ?? 0));
                $staff = max(0, (int) ($variant['staff'] ?? 1));
                $driver = max(0, (int) ($variant['driver'] ?? 1));

                $plnPoints = [];
                $plnBase = 0.0;
                $foreignPoints = [];
                $foreignTotals = [];

                foreach ($billablePoints as $point) {
                    $line = $this->programPointDetailLine(
                        $point,
                        $paying,
                        $gratis,
                        $staff,
                        $driver,
                        $forceConvertForeign,
                    );
                    if ($line === null) {
                        continue;
                    }

                    if ($line['bucket'] === 'PLN') {
                        $plnPoints[] = $line['point'];
                        $plnBase += (float) $line['point']['cost'];
                    } else {
                        $code = $line['bucket'];
                        $foreignPoints[$code][] = $line['point'];
                        $foreignTotals[$code] = ($foreignTotals[$code] ?? 0.0) + (float) $line['point']['cost'];
                    }
                }

                $insurance = round((float) $event->insuranceCostPln($paying, $gratis), 2);
                if ($insurance > 0) {
                    $insuranceNames = $event->dayInsurances
                        ->map(fn ($dayInsurance) => $dayInsurance->insurance)
                        ->filter(fn ($insuranceModel) => InsuranceCostCalculator::isChargeable($insuranceModel))
                        ->map(fn ($insuranceModel) => $insuranceModel->name)
                        ->unique()
                        ->values()
                        ->all();

                    $plnPoints[] = [
                        'name' => 'Ubezpieczenie'.(! empty($insuranceNames) ? ' ('.implode(', ', $insuranceNames).')' : ''),
                        'unit_price' => null,
                        'group_size' => null,
                        'cost' => $insurance,
                        'is_child' => false,
                        'currency_symbol' => 'PLN',
                    ];
                    $plnBase += $insurance;
                }

                $detailedCalculations[$qty] = [
                    'PLN' => [
                        'total' => round($plnBase, 2),
                        'points' => $plnPoints,
                    ],
                ];

                foreach ($foreignTotals as $code => $total) {
                    $detailedCalculations[$qty][$code] = [
                        'total' => round($total, 2),
                        'points' => $foreignPoints[$code] ?? [],
                    ];
                }

                $eventOnlyPoints[(string) $qty] = $billablePoints
                    ->map(function (EventProgramPoint $point) use ($paying, $gratis, $staff, $driver): array {
                        $name = (string) ($point->templatePoint?->name ?? $point->name ?? 'Bez nazwy');
                        $costHeadcount = ProgramPointCostPricing::applyIncludedExtras(
                            $paying,
                            $gratis,
                            $staff,
                            $driver,
                            (bool) ($point->include_gratis_in_cost ?? false),
                            (bool) ($point->include_pilot_in_cost ?? false),
                            (bool) ($point->include_driver_in_cost ?? false),
                        );

                        return [
                            'name' => $name,
                            'day' => (int) ($point->day ?? 0),
                            'order' => (float) ($point->order ?? 0),
                            'unit_price' => (float) ($point->unit_price ?? 0),
                            'quantity' => (float) ($point->quantity ?? 1),
                            'cost' => (float) $point->resolveEffectiveTotalPrice($costHeadcount),
                            'currency_symbol' => $point->currency?->symbol ?? 'PLN',
                        ];
                    })
                    ->values()
                    ->all();
            }

            if ($hasHotelPlan) {
                app(EventHotelPlanService::class)
                    ->applyEventHotelStructureToCalculations($detailedCalculations, $event);
            }

            foreach ($qtyVariants as $qty => $variant) {
                $transportCost = round((float) $transportCalculator->effectiveTransportCost($variant), 2);
                $pln = $detailedCalculations[$qty]['PLN'] ?? ['total' => 0.0, 'points' => []];
                $points = collect($pln['points'] ?? [])
                    ->reject(fn (array $point): bool => EventTransportCostCalculator::isTransportPointName(
                        (string) ($point['name'] ?? '')
                    ))
                    ->values();

                $baseWithoutTransport = round((float) $points->sum(
                    fn (array $point): float => (float) ($point['cost'] ?? 0)
                ), 2);

                if ($transportCost > 0) {
                    $points->push($transportCalculator->transportPointLine(
                        $transportCost,
                        $transportCalculator->usesManualTransportCost(),
                    ));
                }

                $detailedCalculations[$qty]['PLN']['points'] = $points->all();
                $detailedCalculations[$qty]['PLN']['total'] = round($baseWithoutTransport + $transportCost, 2);

                $this->finalizeDetailedVariantTotals(
                    $detailedCalculations,
                    $qty,
                    $markupPercent,
                    $event,
                );
            }

            $this->recomputePerPersonInDetailedCalculations($detailedCalculations);

            return [
                'qty_variants' => $qtyVariants,
                'detailed_calculations' => $detailedCalculations,
                'event_only_points_for_details' => $eventOnlyPoints,
                'transport_cost' => $transportCalculator->effectiveTransportCost($transportReferenceVariant),
            ];
        } catch (\Throwable $e) {
            report($e);

            return $empty;
        }
    }

    /**
     * @return array{bucket: string, point: array<string, mixed>}|null
     */
    private function programPointDetailLine(
        EventProgramPoint $point,
        int $paying,
        int $gratis,
        int $staff,
        int $driver,
        bool $forceConvertForeign,
    ): ?array {
        $costHeadcount = ProgramPointCostPricing::applyIncludedExtras(
            $paying,
            $gratis,
            $staff,
            $driver,
            (bool) ($point->include_gratis_in_cost ?? false),
            (bool) ($point->include_pilot_in_cost ?? false),
            (bool) ($point->include_driver_in_cost ?? false),
        );
        $cost = (float) $point->resolveEffectiveTotalPrice($costHeadcount);
        if ($cost <= 0) {
            return null;
        }

        $name = (string) ($point->templatePoint?->name ?? $point->name ?? 'Pozycja');
        if ($point->parent_id) {
            $name = '→ '.$name;
        }

        $code = strtoupper((string) ($point->currency?->code ?? $point->currency?->symbol ?? 'PLN'));
        if ($code === '') {
            $code = 'PLN';
        }
        $symbol = (string) ($point->currency?->symbol ?? $code);
        $rate = (float) ($point->currency?->exchange_rate ?? 0);
        $convert = $forceConvertForeign || (bool) ($point->convert_to_pln ?? false);

        if ($code === 'PLN') {
            return [
                'bucket' => 'PLN',
                'point' => [
                    'name' => $name,
                    'unit_price' => (float) ($point->unit_price ?? 0),
                    'group_size' => (float) ($point->group_size ?? 1),
                    'cost' => round($cost, 2),
                    'is_child' => (bool) ($point->parent_id ?? false),
                    'currency_symbol' => 'PLN',
                ],
            ];
        }

        if ($convert) {
            $plnCost = $rate > 0 ? round($cost * $rate, 2) : round($cost, 2);

            return [
                'bucket' => 'PLN',
                'point' => [
                    'name' => $name.' (przeliczone na PLN, kurs: '.$rate.')',
                    'unit_price' => ($point->unit_price ?? 0).' '.$symbol,
                    'group_size' => (float) ($point->group_size ?? 1),
                    'cost' => $plnCost,
                    'is_child' => (bool) ($point->parent_id ?? false),
                    'currency_symbol' => 'PLN',
                    'original_currency' => $symbol,
                    'exchange_rate' => $rate,
                ],
            ];
        }

        return [
            'bucket' => $code,
            'point' => [
                'name' => $name,
                'unit_price' => (float) ($point->unit_price ?? 0),
                'group_size' => (float) ($point->group_size ?? 1),
                'cost' => round($cost, 2),
                'is_child' => (bool) ($point->parent_id ?? false),
                'currency_symbol' => $symbol,
            ],
        ];
    }

    /**
     * @param  array<int|string, mixed>  $detailedCalculations
     */
    private function finalizeDetailedVariantTotals(
        array &$detailedCalculations,
        int|string $qty,
        float $markupPercent,
        Event $event,
    ): void {
        $plnBase = round((float) ($detailedCalculations[$qty]['PLN']['total'] ?? 0), 2);
        $markupAmount = round($plnBase * ($markupPercent / 100), 2);

        $taxCalculations = [];
        $totalTaxAmount = 0.0;
        $taxes = $event->eventTemplate?->taxes ?? collect();
        foreach ($taxes as $tax) {
            if (! $tax->is_active) {
                continue;
            }

            $taxAmount = round((float) $tax->calculateTaxAmount($plnBase, $markupAmount), 2);
            if ($taxAmount <= 0) {
                continue;
            }

            $taxCalculations[] = [
                'name' => $tax->name,
                'percentage' => $tax->percentage,
                'amount' => $taxAmount,
                'apply_to_base' => (bool) ($tax->apply_to_base ?? false),
                'apply_to_markup' => (bool) ($tax->apply_to_markup ?? false),
            ];
            $totalTaxAmount += $taxAmount;
        }

        $detailedCalculations[$qty]['markup'] = [
            'amount' => $markupAmount,
            'percent_applied' => $markupPercent,
            'discount_applied' => false,
            'discount_percent' => 0,
            'min_daily_applied' => false,
        ];
        $detailedCalculations[$qty]['taxes'] = [
            'total_amount' => round($totalTaxAmount, 2),
            'breakdown' => $taxCalculations,
        ];

        $plnTotal = round($plnBase + $markupAmount + $totalTaxAmount, 2);
        $detailedCalculations[$qty]['PLN']['total_before_markup'] = $plnBase;
        $detailedCalculations[$qty]['PLN']['total_before_tax'] = round($plnBase + $markupAmount, 2);
        $detailedCalculations[$qty]['PLN']['total'] = $plnTotal;

        foreach ($detailedCalculations[$qty] as $code => $data) {
            if ($code === 'PLN' || $code === 'markup' || $code === 'taxes' || $code === 'hotel_structure') {
                continue;
            }
            if (! is_array($data) || ! array_key_exists('total', $data)) {
                continue;
            }

            $foreignBase = round((float) ($data['total'] ?? 0), 2);
            // Po applyEventHotelStructure total może już zawierać hotel — traktujemy to jako bazę.
            $pointsSum = round((float) collect($data['points'] ?? [])
                ->sum(fn (array $point): float => (float) ($point['cost'] ?? 0)), 2);
            $foreignBase = $pointsSum > 0 ? $pointsSum : $foreignBase;
            $foreignMarkup = round($foreignBase * ($markupPercent / 100), 2);
            $foreignTotal = round($foreignBase + $foreignMarkup, 2);

            $detailedCalculations[$qty][$code]['total_before_markup'] = $foreignBase;
            $detailedCalculations[$qty][$code]['total_before_tax'] = $foreignTotal;
            $detailedCalculations[$qty][$code]['markup_amount'] = $foreignMarkup;
            $detailedCalculations[$qty][$code]['tax_amount'] = 0.0;
            $detailedCalculations[$qty][$code]['total'] = $foreignTotal;
        }
    }

    private function resolveMarkupPercent(Event $event): float
    {
        if ($event->markup?->percent !== null) {
            return (float) $event->markup->percent;
        }

        if ($event->eventTemplate?->markup?->percent !== null) {
            return (float) $event->eventTemplate->markup->percent;
        }

        $default = Markup::query()->where('is_default', true)->first();

        return (float) ($default?->percent ?? 0);
    }

    private function isAccommodationProgramPoint(mixed $point): bool
    {
        if ((bool) ($point->is_hotel ?? false)) {
            return true;
        }

        $name = mb_strtolower((string) ($point->templatePoint?->name ?? $point->name ?? ''));

        foreach (['nocleg', 'zakwaterowanie', 'hotel', 'pobyt'] as $keyword) {
            if (str_contains($name, $keyword)) {
                return true;
            }
        }

        return false;
    }

    private function isTransportProgramPoint(mixed $point): bool
    {
        if ((bool) ($point->is_transport ?? false)) {
            return true;
        }

        return EventTransportCostCalculator::isTransportPointName(
            (string) ($point->templatePoint?->name ?? $point->name ?? '')
        );
    }

    /**
     * @param  array<int|string, mixed>  $detailedCalculations
     */
    private function recomputePerPersonInDetailedCalculations(array &$detailedCalculations): void
    {
        if (empty($detailedCalculations)) {
            return;
        }

        foreach ($detailedCalculations as $qty => $currencies) {
            $divisor = (int) $qty;
            if ($divisor <= 0) {
                continue;
            }

            foreach ($currencies as $code => $data) {
                if (! is_array($data) || ! array_key_exists('total', $data)) {
                    continue;
                }

                $raw = (float) ($data['total'] ?? 0) / $divisor;
                $detailedCalculations[$qty][$code]['price_per_person_raw'] = round($raw, 2);
                $detailedCalculations[$qty][$code]['price_per_person_rounded'] =
                    PriceRoundingService::roundPerPerson($raw, (string) $code);
            }
        }
    }
}
