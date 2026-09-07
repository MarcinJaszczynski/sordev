<?php

namespace App\Services;

use App\Models\Currency;
use App\Models\Event;
use App\Models\EventPricePerPerson;
use App\Models\EventQty;
use App\Models\EventTemplateQty;

class EventPriceCalculator
{
    /**
     * Przeliczenie cen per-person dla imprezy w oparciu o jeden autorytatywny
     * kalkulator (EventCostCalculator): każdy koszt raz, marża, podatki, cena/os.
     * Wiersze oznaczone is_manual=true nie są nadpisywane.
     */
    public function calculateForEvent(Event $event): void
    {
        $manualRows = EventPricePerPerson::query()
            ->where('event_id', $event->id)
            ->where('is_manual', true)
            ->get();

        EventPricePerPerson::query()
            ->where('event_id', $event->id)
            ->where('is_manual', false)
            ->delete();

        $plnCurrencyId = $this->plnCurrencyId();
        $calculator = EventCostCalculator::for($event);

        // Wiersze ręczne zachowują cenę za osobę, ale koszty (baza/marża/podatek/transport)
        // są zawsze przenoszone z autorytatywnej kalkulacji.
        $this->syncManualRowCosts($event, $calculator, $manualRows, $plnCurrencyId);

        $qtys = $event->qtyVariants()->get();

        if ($qtys->isEmpty()) {
            if ($manualRows->isNotEmpty()) {
                return;
            }

            $this->storeRow(
                $event,
                null,
                $plnCurrencyId,
                $calculator->calculate((int) ($event->participant_count ?? 1)),
            );

            return;
        }

        foreach ($qtys as $qty) {
            $templateQtyId = $this->resolveTemplateQtyId($qty);

            $hasManualForQty = $manualRows->contains(function (EventPricePerPerson $row) use ($qty, $templateQtyId) {
                if ($templateQtyId !== null && (int) ($row->event_template_qty_id ?? 0) === $templateQtyId) {
                    return true;
                }

                return (int) optional($row->eventTemplateQty)->qty === (int) $qty->qty;
            });

            if ($hasManualForQty) {
                continue;
            }

            $result = $calculator->calculate(
                (int) $qty->qty,
                (int) ($qty->gratis ?? 0),
                (int) ($qty->staff ?? 0),
                (int) ($qty->driver ?? 0),
            );

            $this->storeRow($event, $templateQtyId, $plnCurrencyId, $result);
        }
    }

    /**
     * Odśwież koszty wierszy ręcznych z autorytatywnej kalkulacji (bez zmiany ceny za osobę).
     *
     * @param  \Illuminate\Support\Collection<int, EventPricePerPerson>  $manualRows
     */
    private function syncManualRowCosts(Event $event, EventCostCalculator $calculator, $manualRows, ?int $plnCurrencyId): void
    {
        foreach ($manualRows as $row) {
            $qtyModel = $event->qtyVariants
                ->first(fn (EventQty $q) => (int) $q->qty === (int) (optional($row->eventTemplateQty)->qty ?? 0));

            $qty = (int) (optional($row->eventTemplateQty)->qty ?? $event->participant_count ?? 1);
            $result = $calculator->calculate(
                $qty,
                $qtyModel !== null ? (int) ($qtyModel->gratis ?? 0) : null,
                $qtyModel !== null ? (int) ($qtyModel->staff ?? 0) : null,
                $qtyModel !== null ? (int) ($qtyModel->driver ?? 0) : null,
            );

            $transportCost = (float) collect($result['lines'] ?? [])
                ->where('category', 'transport')
                ->sum('cost_pln');

            $row->update([
                'currency_id' => $row->currency_id ?? $plnCurrencyId,
                'transport_cost' => round($transportCost, 2),
                'price_base' => round((float) ($result['base_pln'] ?? 0), 2),
                'markup_amount' => round((float) ($result['markup_pln'] ?? 0), 2),
                'tax_amount' => round((float) ($result['tax_pln'] ?? 0), 2),
                'price_with_tax' => round((float) ($result['total_pln'] ?? 0), 2),
                'tax_breakdown' => $result['tax_breakdown'] ?? null,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function storeRow(Event $event, ?int $templateQtyId, ?int $currencyId, array $result): void
    {
        $transportCost = (float) collect($result['lines'] ?? [])
            ->where('category', 'transport')
            ->sum('cost_pln');

        EventPricePerPerson::create([
            'event_id' => $event->id,
            'event_template_qty_id' => $templateQtyId,
            'currency_id' => $currencyId,
            'start_place_id' => $event->start_place_id ?? null,
            'price_per_person' => $result['price_per_person'] ?? 0,
            'transport_cost' => round($transportCost, 2),
            'price_base' => $result['base_pln'] ?? 0,
            'markup_amount' => $result['markup_pln'] ?? 0,
            'tax_amount' => $result['tax_pln'] ?? 0,
            'price_with_tax' => $result['total_pln'] ?? 0,
            'tax_breakdown' => $result['tax_breakdown'] ?? null,
            'is_manual' => false,
        ]);
    }

    private function resolveTemplateQtyId(EventQty $qty): ?int
    {
        $id = EventTemplateQty::query()
            ->where('qty', (int) $qty->qty)
            ->value('id');

        return $id !== null ? (int) $id : null;
    }

    private function plnCurrencyId(): ?int
    {
        return Currency::query()
            ->where('code', 'PLN')
            ->orWhere('symbol', 'PLN')
            ->orWhere('symbol', 'zł')
            ->value('id');
    }
}
