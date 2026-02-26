<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventPricePerPerson;

class EventPriceCalculator
{
    /**
     * Proste przeliczenie cen per-person dla danej imprezy.
     * Kopiuje obecne sumy z punktów programu i rozdziela na warianty qty.
     */
    public function calculateForEvent(Event $event): void
    {
        // Kasujemy istniejące wpisy event_price_per_person dla tego eventu
        EventPricePerPerson::where('event_id', $event->id)->delete();

        // Load points and sum only those rows that have include_in_calculation=true and active=true
    $points = $event->programPoints()->where('active', true)->get();
    $totalProgramCost = \App\Services\ProgramPointHelper::sumIncluded($points, 'total_price');

        $qtys = $event->qtyVariants()->get();

        if ($qtys->isEmpty()) {
            // jeśli brak wariantów, tworzymy jedną pozycję domyślną
            EventPricePerPerson::create([
                'event_id' => $event->id,
                'event_template_qty_id' => null,
                'currency_id' => null,
                'start_place_id' => $event->start_place_id ?? null,
                'price_per_person' => $totalProgramCost / max(1, $event->participant_count),
                'transport_cost' => 0,
                'price_base' => $totalProgramCost,
                'markup_amount' => 0,
                'tax_amount' => 0,
                'price_with_tax' => $totalProgramCost,
                'tax_breakdown' => null,
            ]);

            return;
        }

        foreach ($qtys as $qty) {
            $perPerson = $totalProgramCost / max(1, $qty->qty);

            EventPricePerPerson::create([
                'event_id' => $event->id,
                'event_template_qty_id' => null,
                'currency_id' => null,
                'start_place_id' => $event->start_place_id ?? null,
                'price_per_person' => $perPerson,
                'transport_cost' => 0,
                'price_base' => $totalProgramCost,
                'markup_amount' => 0,
                'tax_amount' => 0,
                'price_with_tax' => $perPerson * $qty->qty,
                'tax_breakdown' => null,
            ]);
        }
    }
}
