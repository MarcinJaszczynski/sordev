<?php

namespace App\Filament\Resources\EventResource\Pages;

use App\Filament\Resources\EventSettlementResource\RelationManagers\ProgramPointsCostsRelationManager;
use App\Filament\Resources\EventSettlementResource\RelationManagers\SettlementCostsRelationManager;

class ManageEventSettlementCosts extends ManageEventSettlementFinanceSection
{
    protected static ?string $navigationLabel = 'Koszty';

    protected static ?string $title = 'Koszty rozliczenia';

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static function settlementRelationManagers(): array
    {
        return [
            SettlementCostsRelationManager::class,
            ProgramPointsCostsRelationManager::class,
        ];
    }
}
