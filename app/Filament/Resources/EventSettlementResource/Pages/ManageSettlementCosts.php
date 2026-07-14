<?php

namespace App\Filament\Resources\EventSettlementResource\Pages;

use App\Filament\Concerns\SingleRelationManagerPage;
use App\Filament\Resources\EventSettlementResource;
use App\Filament\Resources\EventSettlementResource\Concerns\HasEventSettlementWorkflowContext;
use App\Filament\Resources\EventSettlementResource\RelationManagers\ProgramPointsCostsRelationManager;
use App\Filament\Resources\EventSettlementResource\RelationManagers\SettlementCostsRelationManager;

class ManageSettlementCosts extends SingleRelationManagerPage
{
    use HasEventSettlementWorkflowContext;

    protected static string $resource = EventSettlementResource::class;

    protected static string $view = 'filament.resources.event-settlement-resource.pages.manage-settlement-costs';

    protected static ?string $navigationLabel = 'Koszty';

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static function relationManager(): string
    {
        return SettlementCostsRelationManager::class;
    }

    /**
     * @return array<class-string>
     */
    protected function getAllRelationManagers(): array
    {
        return [
            SettlementCostsRelationManager::class,
            ProgramPointsCostsRelationManager::class,
        ];
    }
}
