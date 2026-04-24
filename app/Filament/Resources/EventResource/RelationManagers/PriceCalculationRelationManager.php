<?php

namespace App\Filament\Resources\EventResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;

class PriceCalculationRelationManager extends RelationManager
{
    protected static string $relationship = 'pricePerPerson';

    protected static ?string $title = 'Obliczanie ceny';

    protected static string $view = 'filament.resources.event-resource.relation-managers.price-calculation-relation-manager';

    protected function getViewData(): array
    {
        $event = $this->getOwnerRecord();
        $participantCount = max(1, (int) ($event->participant_count ?? 1));
        $gratisCount = 0;

        try {
            $variant = $event->qtyVariants()
                ->where('qty', $participantCount)
                ->orderBy('id')
                ->first();

            if (! $variant) {
                $variant = $event->qtyVariants()
                    ->get()
                    ->sortBy(fn ($row) => abs(((int) ($row->qty ?? 0)) - $participantCount))
                    ->first();
            }

            $gratisCount = (int) ($variant->gratis ?? 0);
        } catch (\Throwable $e) {
            $gratisCount = 0;
        }

        return [
            'template' => $event->eventTemplate,
            'participantCount' => $participantCount,
            'gratisCount' => $gratisCount,
            'startPlaceId' => $event->start_place_id,
            'calculatedTotal' => $event->total_cost,
        ];
    }
}
