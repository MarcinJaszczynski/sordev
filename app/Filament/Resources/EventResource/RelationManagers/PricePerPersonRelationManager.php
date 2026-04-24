<?php

namespace App\Filament\Resources\EventResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;

class PricePerPersonRelationManager extends RelationManager
{
    protected static string $relationship = 'pricePerPerson';

    protected static ?string $title = 'Kalkulacja';

    protected static string $view = 'filament.resources.event-resource.relation-managers.price-per-person-relation-manager';

    protected function getViewData(): array
    {
        return [
            'record' => $this->getOwnerRecord(),
        ];
    }
}
