<?php

declare(strict_types=1);

namespace App\Filament\Resources\EventResource\Pages;

use App\Filament\Concerns\SingleRelationManagerPage;
use App\Filament\Resources\EventResource;
use App\Filament\Resources\EventResource\Concerns\HasEventDocumentsSubNavigation;
use App\Filament\Resources\EventResource\Concerns\HasEventWorkflowContext;
use App\Filament\Resources\EventResource\RelationManagers\HistoryRelationManager;
use Filament\Navigation\NavigationItem;
use Illuminate\Support\Facades\Schema;

class EventAuditLogPage extends SingleRelationManagerPage
{
    use HasEventDocumentsSubNavigation;
    use HasEventWorkflowContext;

    protected static string $resource = EventResource::class;

    protected static string $view = 'filament.resources.event-resource.pages.event-documents-relation-managers';

    protected static ?string $navigationLabel = 'Dokumenty';

    /** H1 = aktywna sekcja nested (primary: Dokumenty). */
    protected static ?string $title = 'Historia';

    protected static ?string $navigationIcon = 'heroicon-o-folder';

    public static function shouldRegisterNavigation(array $parameters = []): bool
    {
        return Schema::hasTable('event_histories')
            && ! Schema::hasTable('contracts')
            && ! Schema::hasTable('event_agreements')
            && ! Schema::hasTable('event_documents');
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
                ->isActiveWhen(fn (): bool => collect(static::documentsRouteNames())
                    ->contains(fn (string $routeName): bool => request()->routeIs($routeName)))
                ->sort(static::getNavigationSort())
                ->badge(static::getNavigationBadge(), color: static::getNavigationBadgeColor())
                ->url(EventResource::getUrl('audit', $urlParameters)),
        ];
    }

    protected static function relationManager(): string
    {
        return HistoryRelationManager::class;
    }
}
