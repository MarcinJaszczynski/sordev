<?php

namespace App\Filament\Resources\EventResource\Pages;

use App\Filament\Concerns\SingleRelationManagerPage;
use App\Filament\Resources\EventResource;
use App\Filament\Resources\EventResource\Concerns\HasEventOperationsSubNavigation;
use App\Filament\Resources\EventResource\Concerns\HasEventWorkflowContext;
use App\Filament\Resources\EventResource\RelationManagers\ReservationsRelationManager;

class ManageEventReservations extends SingleRelationManagerPage
{
    use HasEventOperationsSubNavigation;
    use HasEventWorkflowContext;

    protected static string $resource = EventResource::class;

    protected static string $view = 'filament.resources.event-resource.pages.event-operations-relation-managers';

    protected static ?string $navigationLabel = 'Rezerwacje';

    protected static ?string $title = 'Rezerwacje';

    protected static ?string $navigationIcon = 'heroicon-o-ticket';

    public function getSubheading(): ?string
    {
        return 'W kontekście tej imprezy — skrzynka wszystkich rezerwacji jest w menu bocznym';
    }

    protected static function relationManager(): string
    {
        return ReservationsRelationManager::class;
    }
}
