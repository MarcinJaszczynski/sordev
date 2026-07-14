<?php

namespace App\Filament\Resources\EventResource\Pages;

use App\Filament\Concerns\SingleRelationManagerPage;
use App\Filament\Resources\EventResource;
use App\Filament\Resources\EventResource\Concerns\HasEventWorkflowContext;
use App\Filament\Resources\EventResource\RelationManagers\DayInsurancesRelationManager;

class ManageEventDayInsurances extends SingleRelationManagerPage
{
    use HasEventWorkflowContext;

    protected static string $resource = EventResource::class;

    protected static ?string $navigationLabel = 'Ubezpieczenia';

    protected static ?string $title = 'Ubezpieczenia dzienne';

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static function relationManager(): string
    {
        return DayInsurancesRelationManager::class;
    }
}
