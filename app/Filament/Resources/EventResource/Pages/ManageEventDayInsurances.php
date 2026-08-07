<?php

namespace App\Filament\Resources\EventResource\Pages;

use App\Filament\Concerns\SingleRelationManagerPage;
use App\Filament\Resources\EventResource;
use App\Filament\Resources\EventResource\Concerns\HasEventParticipantsSubNavigation;
use App\Filament\Resources\EventResource\Concerns\HasEventWorkflowContext;
use App\Filament\Resources\EventResource\RelationManagers\DayInsurancesRelationManager;

class ManageEventDayInsurances extends SingleRelationManagerPage
{
    use HasEventParticipantsSubNavigation;
    use HasEventWorkflowContext;

    protected static string $resource = EventResource::class;

    protected static string $view = 'filament.resources.event-resource.pages.event-participants-relation-managers';

    protected static ?string $navigationLabel = 'Ubezpieczenia';

    protected static ?string $title = 'Ubezpieczenia';

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    public static function shouldRegisterNavigation(array $parameters = []): bool
    {
        return false;
    }

    protected static function relationManager(): string
    {
        return DayInsurancesRelationManager::class;
    }
}
