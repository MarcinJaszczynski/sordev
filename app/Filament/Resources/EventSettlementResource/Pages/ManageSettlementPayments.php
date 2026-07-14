<?php

namespace App\Filament\Resources\EventSettlementResource\Pages;

use App\Filament\Concerns\SingleRelationManagerPage;
use App\Filament\Resources\EventSettlementResource;
use App\Filament\Resources\EventSettlementResource\Concerns\HasEventSettlementWorkflowContext;
use App\Filament\Resources\EventSettlementResource\RelationManagers\ParticipantPaymentsRelationManager;

class ManageSettlementPayments extends SingleRelationManagerPage
{
    use HasEventSettlementWorkflowContext;

    protected static string $resource = EventSettlementResource::class;

    protected static ?string $navigationLabel = 'Wpłaty uczestników';

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static function relationManager(): string
    {
        return ParticipantPaymentsRelationManager::class;
    }
}
