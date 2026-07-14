<?php

namespace App\Filament\Resources\EventResource\Pages;

use App\Filament\Resources\EventSettlementResource\RelationManagers\PilotCashRelationManager;

class ManageEventSettlementPilotCash extends ManageEventSettlementFinanceSection
{
    protected static ?string $navigationLabel = 'Gotówka pilota';

    protected static ?string $title = 'Gotówka pilota';

    protected static ?string $navigationIcon = 'heroicon-o-wallet';

    protected static function settlementRelationManagers(): array
    {
        return [
            PilotCashRelationManager::class,
        ];
    }
}
