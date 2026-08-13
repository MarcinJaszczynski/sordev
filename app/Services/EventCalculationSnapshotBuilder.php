<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Event;
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

        $template = $event->eventTemplate;
        if (! $template) {
            return $result;
        }

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

        try {
            $resolvedKm = $transportCalculator->resolveTransportKm();
            $qtyVariants = [
                $customVariant['qty'] => $customVariant,
            ];
            $detailedCalculations = app(EventTemplateUiCalculationService::class)->calculate(
                template: $template,
                startPlaceId: $event->start_place_id,
                transportKm: $resolvedKm > 0 ? $resolvedKm : null,
                variantOverrides: [$customVariant],
                busOverride: $event->bus ?? $template->bus,
            );

            if ($event->hotelStays()->exists()) {
                app(EventHotelPlanService::class)
                    ->applyEventHotelStructureToCalculations($detailedCalculations, $event);
            }

            $transportCalculator->syncTransportInDetailedCalculations(
                $detailedCalculations,
                $qtyVariants,
                $customVariant,
                function (int|string $qty, float $delta) use (&$detailedCalculations): void {
                    $this->applyPlnDeltaToDetailedTotals($detailedCalculations, $qty, $delta);
                },
            );

            $eventOnlyPoints = [];
            $this->appendEventOnlyPointsToDetailedCalculations(
                $event,
                $programPoints,
                $detailedCalculations,
                $eventOnlyPoints,
            );

            $this->recomputePerPersonInDetailedCalculations($detailedCalculations);

            $result['qty_variants'] = $qtyVariants;
            $result['detailed_calculations'] = $detailedCalculations;
            $result['event_only_points_for_details'] = $eventOnlyPoints;
            $result['transport_cost'] = $transportCalculator->effectiveTransportCost($customVariant);
        } catch (\Throwable $e) {
            report($e);
        }

        return $result;
    }

    /**
     * @param  Collection<int, mixed>  $programPoints
     * @param  array<int|string, mixed>  $detailedCalculations
     * @param  array<string, mixed>  $eventOnlyPointsForDetails
     */
    private function appendEventOnlyPointsToDetailedCalculations(
        Event $event,
        Collection $programPoints,
        array &$detailedCalculations,
        array &$eventOnlyPointsForDetails,
    ): void {
        $hasHotelPlan = $event->hotelStays()->exists();

        $eventPoints = $programPoints
            ->filter(function ($point) use ($hasHotelPlan) {
                if (! (bool) ($point->active ?? true) || ! (bool) ($point->include_in_calculation ?? true)) {
                    return false;
                }

                if ($hasHotelPlan && $this->isAccommodationProgramPoint($point)) {
                    return false;
                }

                return true;
            })
            ->sortBy(['day', 'order'])
            ->values();

        if ($eventPoints->isEmpty() || empty($detailedCalculations)) {
            return;
        }

        foreach ($detailedCalculations as $qty => $currencies) {
            $plnPoints = collect($currencies['PLN']['points'] ?? []);
            $existingNames = $plnPoints
                ->map(fn ($point) => $this->normalizePointName((string) ($point['name'] ?? '')))
                ->filter()
                ->values();

            $missingForPln = collect();

            $pointsForVariant = $eventPoints
                ->map(function ($point) use ($existingNames, $missingForPln) {
                    $name = (string) ($point->templatePoint?->name ?? $point->name ?? 'Bez nazwy');

                    $normalized = $this->normalizePointName($name);
                    $isMissingInPln = $normalized !== '' && ! $existingNames->contains($normalized);

                    if ($isMissingInPln) {
                        $missingForPln->push([
                            'name' => $name,
                            'unit_price' => (float) ($point->unit_price ?? 0),
                            'group_size' => (float) ($point->group_size ?? 1),
                            'cost' => (float) ($point->total_price ?? 0),
                            'is_child' => (bool) ($point->parent_id ?? false),
                            'currency_symbol' => $point->currency?->symbol ?? 'PLN',
                        ]);
                    }

                    return [
                        'name' => $name,
                        'day' => (int) ($point->day ?? 0),
                        'order' => (float) ($point->order ?? 0),
                        'unit_price' => (float) ($point->unit_price ?? 0),
                        'quantity' => (float) ($point->quantity ?? 1),
                        'cost' => (float) ($point->total_price ?? 0),
                        'currency_symbol' => $point->currency?->symbol ?? 'PLN',
                    ];
                })
                ->values()
                ->all();

            if (! empty($pointsForVariant)) {
                $eventOnlyPointsForDetails[(string) $qty] = $pointsForVariant;
            }

            if ($missingForPln->isNotEmpty()) {
                $baseDelta = (float) $missingForPln
                    ->filter(fn ($point) => ($point['currency_symbol'] ?? 'PLN') === 'PLN')
                    ->sum('cost');

                $mergedPlnPoints = $plnPoints
                    ->concat($missingForPln->filter(fn ($point) => ($point['currency_symbol'] ?? 'PLN') === 'PLN')->values())
                    ->values()
                    ->all();

                $detailedCalculations[$qty]['PLN']['points'] = $mergedPlnPoints;

                if ($baseDelta > 0) {
                    $this->applyPlnDeltaToDetailedTotals($detailedCalculations, $qty, $baseDelta);
                }
            }
        }
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

    private function normalizePointName(string $name): string
    {
        $name = trim($name);
        $name = ltrim($name, "\xE2\x86\x92 ");
        $name = rtrim($name, '.');

        return mb_strtolower(trim($name));
    }

    /**
     * @param  array<int|string, mixed>  $detailedCalculations
     */
    private function applyPlnDeltaToDetailedTotals(array &$detailedCalculations, int|string $qty, float $baseDelta): void
    {
        $pln = $detailedCalculations[$qty]['PLN'] ?? null;
        if (! is_array($pln)) {
            return;
        }

        $markupPercent = (float) ($detailedCalculations[$qty]['markup']['percent_applied'] ?? 0);
        $markupDelta = round($baseDelta * ($markupPercent / 100), 2);

        if (isset($detailedCalculations[$qty]['markup']['amount'])) {
            $detailedCalculations[$qty]['markup']['amount'] = round(
                (float) $detailedCalculations[$qty]['markup']['amount'] + $markupDelta,
                2
            );
        }

        $taxDeltaTotal = 0.0;
        if (! empty($detailedCalculations[$qty]['taxes']['breakdown']) && is_array($detailedCalculations[$qty]['taxes']['breakdown'])) {
            foreach ($detailedCalculations[$qty]['taxes']['breakdown'] as $idx => $tax) {
                $percent = (float) ($tax['percentage'] ?? 0);
                $applyToBase = (bool) ($tax['apply_to_base'] ?? false);
                $applyToMarkup = (bool) ($tax['apply_to_markup'] ?? false);

                $taxDelta = 0.0;
                if ($applyToBase) {
                    $taxDelta += $baseDelta * ($percent / 100);
                }
                if ($applyToMarkup) {
                    $taxDelta += $markupDelta * ($percent / 100);
                }

                if ($taxDelta > 0) {
                    $taxDelta = round($taxDelta, 2);
                    $taxDeltaTotal += $taxDelta;
                    $detailedCalculations[$qty]['taxes']['breakdown'][$idx]['amount'] = round(
                        (float) ($tax['amount'] ?? 0) + $taxDelta,
                        2
                    );
                }
            }
        }

        if (isset($detailedCalculations[$qty]['taxes']['total_amount'])) {
            $detailedCalculations[$qty]['taxes']['total_amount'] = round(
                (float) $detailedCalculations[$qty]['taxes']['total_amount'] + $taxDeltaTotal,
                2
            );
        }

        $detailedCalculations[$qty]['PLN']['total_before_markup'] = round(
            (float) ($pln['total_before_markup'] ?? 0) + $baseDelta,
            2
        );
        $detailedCalculations[$qty]['PLN']['total_before_tax'] = round(
            (float) ($pln['total_before_tax'] ?? 0) + $baseDelta + $markupDelta,
            2
        );
        $detailedCalculations[$qty]['PLN']['total'] = round(
            (float) ($pln['total'] ?? 0) + $baseDelta + $markupDelta + $taxDeltaTotal,
            2
        );
    }
}
