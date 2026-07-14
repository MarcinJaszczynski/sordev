<?php

namespace App\Services;

use App\Filament\Resources\EventResource\Widgets\EventPriceTable;
use App\Models\Event;

/**
 * Wspólne dane kalkulacji imprezy (widżet, PDF, Excel, API).
 */
class EventCalculationPresenter
{
    /** @var array<string, mixed>|null */
    private ?array $memoizedWidgetState = null;

    public function __construct(
        public Event $event,
    ) {}

    public static function for(Event $event): self
    {
        return new self($event->loadMissing([
            'eventTemplate',
            'bus',
            'markup',
            'startPlace',
            'activeSettlement',
        ]));
    }

    public function widgetState(): array
    {
        return $this->memoizedWidgetState ??= $this->loadWidgetState();
    }

    private function loadWidgetState(): array
    {
        $widget = app(EventPriceTable::class);
        $widget->record = $this->event;
        $widget->loadCalculations();

        return [
            'calculations' => $widget->calculations,
            'transport_cost' => $widget->transportCost,
            'event_transport_km' => $widget->eventTransportKm,
            'detailed_calculations' => $widget->detailedCalculations,
            'qty_variants' => $widget->qtyVariants,
            'current_variant' => $widget->currentVariant,
            'price_rows' => $widget->priceRows,
            'program_points' => $widget->programPoints,
        ];
    }

    public function plannedTotalPln(): float
    {
        $state = $this->widgetState();
        $qty = max(1, (int) ($this->event->participant_count ?? 1));
        $pln = $state['detailed_calculations'][$qty]['PLN'] ?? null;

        if (is_array($pln) && ($pln['total'] ?? 0) > 0) {
            return (float) $pln['total'];
        }

        return (float) ($state['calculations']['total_cost'] ?? $this->event->total_cost ?? 0);
    }

    public function settlementPlannedPln(): float
    {
        return (float) ($this->event->activeSettlement?->planned_cost_pln ?? 0);
    }

    public function marginDeltaPln(): float
    {
        return $this->settlementPlannedPln() - $this->plannedTotalPln();
    }

    public function marginDeltaPercent(): ?float
    {
        $plan = $this->plannedTotalPln();
        if ($plan <= 0) {
            return null;
        }

        return round(($this->marginDeltaPln() / $plan) * 100, 1);
    }

    public function hasSignificantDiscrepancy(float $thresholdPercent = 5.0): bool
    {
        $settlement = $this->settlementPlannedPln();
        if ($settlement <= 0) {
            return false;
        }

        $percent = $this->marginDeltaPercent();
        if ($percent === null) {
            return false;
        }

        return abs($percent) >= $thresholdPercent;
    }

    public function toApiArray(): array
    {
        $state = $this->widgetState();

        return [
            'event_id' => $this->event->id,
            'event_name' => $this->event->name,
            'participant_count' => (int) ($this->event->participant_count ?? 0),
            'transfer_km' => (float) ($this->event->transfer_km ?? 0),
            'program_km' => (float) ($this->event->program_km ?? 0),
            'transport_cost' => (float) ($state['transport_cost'] ?? 0),
            'transport_km' => $state['event_transport_km'],
            'summary' => $state['calculations'],
            'detailed_calculations' => $state['detailed_calculations'],
            'qty_variants' => $state['qty_variants'],
            'current_variant' => $state['current_variant'],
            'planned_total_pln' => $this->plannedTotalPln(),
            'settlement_planned_pln' => $this->settlementPlannedPln(),
            'margin_delta_pln' => $this->marginDeltaPln(),
            'margin_delta_percent' => $this->marginDeltaPercent(),
            'price_per_person_rows' => collect($state['price_rows'] ?? [])->map(fn ($row) => [
                'id' => $row->id,
                'qty' => $row->eventTemplateQty?->qty,
                'price_per_person' => $row->price_per_person,
                'transport_cost' => $row->transport_cost,
                'price_with_tax' => $row->price_with_tax,
            ])->values()->all(),
        ];
    }
}
