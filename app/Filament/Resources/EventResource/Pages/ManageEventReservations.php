<?php

namespace App\Filament\Resources\EventResource\Pages;

use App\Filament\Concerns\SingleRelationManagerPage;
use App\Filament\Resources\EventResource;
use App\Filament\Resources\EventResource\Concerns\HasEventOperationsSubNavigation;
use App\Filament\Resources\EventResource\Concerns\HasEventWorkflowContext;
use App\Filament\Resources\EventResource\RelationManagers\ReservationsRelationManager;
use App\Services\HotelStayReservationSync;
use App\Services\ProgramPointReservationSync;

class ManageEventReservations extends SingleRelationManagerPage
{
    use HasEventOperationsSubNavigation;
    use HasEventWorkflowContext;

    protected static string $resource = EventResource::class;

    protected static string $view = 'filament.resources.event-resource.pages.event-operations-relation-managers';

    protected static ?string $navigationLabel = 'Rezerwacje';

    protected static ?string $title = 'Rezerwacje';

    protected static ?string $navigationIcon = 'heroicon-o-ticket';

    protected static function relationManager(): string
    {
        return ReservationsRelationManager::class;
    }

    public function mount(int|string $record): void
    {
        parent::mount($record);

        app(HotelStayReservationSync::class)->backfillForEvent($this->getRecord());
        app(ProgramPointReservationSync::class)->backfillForEvent($this->getRecord());
    }
}
