<?php

namespace App\Filament\Resources\EventResource\Pages;

use App\Filament\Concerns\SingleRelationManagerPage;
use App\Filament\Resources\EventResource;
use App\Filament\Resources\EventResource\Concerns\HasEventWorkflowContext;
use App\Filament\Resources\EventResource\RelationManagers\AgreementsRelationManager;
use App\Filament\Resources\EventResource\RelationManagers\ContractsRelationManager;
use Illuminate\Support\Facades\Schema;

class ManageEventContracts extends SingleRelationManagerPage
{
    use HasEventWorkflowContext;

    protected static string $resource = EventResource::class;

    protected static ?string $navigationLabel = 'Umowy';

    protected static ?string $title = 'Umowy i płatności';

    protected static ?string $navigationIcon = 'heroicon-o-document-check';

    protected static function relationManager(): string
    {
        return Schema::hasTable('contracts')
            ? ContractsRelationManager::class
            : AgreementsRelationManager::class;
    }
}
