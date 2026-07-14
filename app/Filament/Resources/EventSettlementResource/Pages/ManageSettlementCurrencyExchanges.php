<?php

namespace App\Filament\Resources\EventSettlementResource\Pages;

use App\Filament\Concerns\SingleRelationManagerPage;
use App\Filament\Resources\EventSettlementResource;
use App\Filament\Resources\EventSettlementResource\Concerns\HasEventSettlementWorkflowContext;
use App\Filament\Resources\EventSettlementResource\RelationManagers\PilotCurrencyExchangesRelationManager;

class ManageSettlementCurrencyExchanges extends SingleRelationManagerPage
{
    use HasEventSettlementWorkflowContext;

    protected static string $resource = EventSettlementResource::class;

    protected static ?string $navigationLabel = 'Wymiany walut';

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static function relationManager(): string
    {
        return PilotCurrencyExchangesRelationManager::class;
    }
}
