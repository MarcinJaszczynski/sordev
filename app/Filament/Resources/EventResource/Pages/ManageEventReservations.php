<?php

namespace App\Filament\Resources\EventResource\Pages;

use App\Filament\Concerns\SingleRelationManagerPage;
use App\Filament\Resources\EventResource;
use App\Filament\Resources\EventResource\Concerns\HasEventOperationsSubNavigation;
use App\Filament\Resources\EventResource\Concerns\HasEventWorkflowContext;
use App\Filament\Resources\EventResource\RelationManagers\ReservationsRelationManager;
use App\Services\HotelStayReservationSync;
use App\Services\ProgramPointReservationSync;
use Filament\Navigation\NavigationItem;

class ManageEventReservations extends SingleRelationManagerPage
{
    use HasEventOperationsSubNavigation;
    use HasEventWorkflowContext;

    protected static string $resource = EventResource::class;

    protected static string $view = 'filament.resources.event-resource.pages.event-operations-relation-managers';

    protected static ?string $navigationLabel = 'Operacje';

    /** H1 = aktywna sekcja nested (primary: Operacje). */
    protected static ?string $title = 'Rezerwacje';

    protected static ?string $navigationIcon = 'heroicon-o-ticket';

    public static function shouldRegisterNavigation(array $parameters = []): bool
    {
        return true;
    }

    /**
     * @param  array<string, mixed>  $urlParameters
     * @return array<NavigationItem>
     */
    public static function getNavigationItems(array $urlParameters = []): array
    {
        return [
            NavigationItem::make(static::getNavigationLabel())
                ->group(static::getNavigationGroup())
                ->parentItem(static::getNavigationParentItem())
                ->icon(static::getNavigationIcon())
                ->activeIcon(static::getActiveNavigationIcon())
                ->isActiveWhen(fn (): bool => collect(static::operationsRouteNames())
                    ->contains(fn (string $routeName): bool => request()->routeIs($routeName)))
                ->sort(static::getNavigationSort())
                ->badge(static::getNavigationBadge(), color: static::getNavigationBadgeColor())
                ->url(EventResource::getUrl('reservations', $urlParameters)),
        ];
    }

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
