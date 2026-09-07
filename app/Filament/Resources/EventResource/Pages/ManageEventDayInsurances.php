<?php

namespace App\Filament\Resources\EventResource\Pages;

use App\Filament\Resources\EventResource;
use App\Filament\Resources\EventResource\Concerns\HasEventOperationsSubNavigation;
use App\Filament\Resources\EventResource\Concerns\HasEventWorkflowContext;
use App\Filament\Resources\EventResource\RelationManagers\DayInsurancesRelationManager;
use Filament\Forms\Form;
use Filament\Resources\Pages\Concerns\HasRelationManagers;
use Filament\Resources\Pages\EditRecord;

class ManageEventDayInsurances extends EditRecord
{
    use HasEventOperationsSubNavigation;
    use HasEventWorkflowContext;
    use HasRelationManagers;

    protected static string $resource = EventResource::class;

    protected static string $view = 'filament.resources.event-resource.pages.manage-event-operations-insurances';

    protected static ?string $navigationLabel = 'Ubezpieczenia';

    protected static ?string $title = 'Ubezpieczenia';

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    /**
     * Polisy i produkty są w relation managerze (jeden modal „Dodaj ubezpieczenia”).
     */
    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    protected function getFormActions(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    /**
     * @return array<class-string<\Filament\Resources\RelationManagers\RelationManager>>
     */
    protected function getAllRelationManagers(): array
    {
        return [DayInsurancesRelationManager::class];
    }

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return false;
    }
}
