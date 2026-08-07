<?php

namespace App\Filament\Resources\EventResource\Pages;

use App\Filament\Concerns\SingleRelationManagerPage;
use App\Filament\Resources\EventResource;
use App\Filament\Resources\EventResource\Concerns\HasEventOperationsSubNavigation;
use App\Filament\Resources\EventResource\Concerns\HasEventWorkflowContext;
use App\Filament\Resources\TaskResource;
use App\Filament\Resources\TaskResource\RelationManagers\TasksRelationManager;
use Filament\Actions;
use Filament\Navigation\NavigationItem;

class ManageEventTasks extends SingleRelationManagerPage
{
    use HasEventOperationsSubNavigation;
    use HasEventWorkflowContext;

    protected static string $resource = EventResource::class;

    protected static string $view = 'filament.resources.event-resource.pages.event-operations-relation-managers';

    protected static ?string $navigationLabel = 'Operacje';

    /** H1 = aktywna sekcja nested (primary: Operacje). */
    protected static ?string $title = 'Zadania';

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    public function getSubheading(): ?string
    {
        return 'W kontekście tej imprezy — skrzynka wszystkich zadań jest w menu bocznym';
    }

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
                ->url(EventResource::getUrl('tasks', $urlParameters)),
        ];
    }

    protected static function relationManager(): string
    {
        return TasksRelationManager::class;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('board')
                ->label('Tablica zadań')
                ->icon('heroicon-m-view-columns')
                ->color('gray')
                ->tooltip('Widok kolumnowy statusów — przeciąganie i szybka zmiana etapu zadania.')
                ->url(TaskResource::getUrl('board').'?event='.$this->getRecord()->getKey()),
        ];
    }
}
