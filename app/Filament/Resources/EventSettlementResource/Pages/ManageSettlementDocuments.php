<?php

namespace App\Filament\Resources\EventSettlementResource\Pages;

use App\Filament\Concerns\SingleRelationManagerPage;
use App\Filament\Resources\EventSettlementResource;
use App\Filament\Resources\EventSettlementResource\Concerns\HasEventSettlementWorkflowContext;
use App\Filament\Resources\EventSettlementResource\RelationManagers\DocumentsRelationManager;

class ManageSettlementDocuments extends SingleRelationManagerPage
{
    use HasEventSettlementWorkflowContext;

    protected static string $resource = EventSettlementResource::class;

    protected static ?string $navigationLabel = 'Dokumenty';

    protected static ?string $navigationIcon = 'heroicon-o-folder';

    protected static function relationManager(): string
    {
        return DocumentsRelationManager::class;
    }
}
