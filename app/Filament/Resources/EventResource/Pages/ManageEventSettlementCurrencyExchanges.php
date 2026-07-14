<?php

namespace App\Filament\Resources\EventResource\Pages;

use App\Filament\Resources\EventSettlementResource\RelationManagers\PilotCurrencyExchangesRelationManager;

class ManageEventSettlementCurrencyExchanges extends ManageEventSettlementFinanceSection
{
    protected static ?string $navigationLabel = 'Wymiany walut';

    protected static ?string $title = 'Wymiany walut';

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static function settlementRelationManagers(): array
    {
        return [
            PilotCurrencyExchangesRelationManager::class,
        ];
    }
}
