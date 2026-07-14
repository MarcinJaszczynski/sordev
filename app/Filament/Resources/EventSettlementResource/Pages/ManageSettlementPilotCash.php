<?php

namespace App\Filament\Resources\EventSettlementResource\Pages;

use App\Filament\Concerns\SingleRelationManagerPage;
use App\Filament\Resources\EventSettlementResource;
use App\Filament\Resources\EventSettlementResource\Concerns\HasEventSettlementWorkflowContext;
use App\Filament\Resources\EventSettlementResource\RelationManagers\PilotCashRelationManager;

class ManageSettlementPilotCash extends SingleRelationManagerPage
{
    use HasEventSettlementWorkflowContext;

    protected static string $resource = EventSettlementResource::class;

    protected static ?string $navigationLabel = 'Gotówka pilota';

    protected static ?string $navigationIcon = 'heroicon-o-wallet';

    protected static function relationManager(): string
    {
        return PilotCashRelationManager::class;
    }
}
