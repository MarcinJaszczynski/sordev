<?php

namespace App\Filament\Pages;

use App\Filament\Resources\ContractorResource;
use App\Filament\Resources\EventResource;
use App\Services\ExecutiveStatisticsService;
use App\Support\ExecutiveAccess;
use App\Support\ExecutiveModuleNavigation;
use App\Support\FilamentNavigation;
use Filament\Pages\Page;

class ExecutiveStatisticsPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static string $view = 'filament.pages.executive-statistics';

    protected static ?string $navigationGroup = FilamentNavigation::GROUP_EXECUTIVE;

    protected static ?string $navigationLabel = 'Statystyki biura';

    protected static ?int $navigationSort = 2;

    public static function canAccess(): bool
    {
        return ExecutiveAccess::canAccessStatisticsPanel();
    }

    public function getTitle(): string
    {
        return 'Panel statystyczny';
    }

    public function getNavigationTabs(): array
    {
        return ExecutiveModuleNavigation::tabs('statistics');
    }

    /** @return array<string, int|float> */
    public function getOverview(): array
    {
        return app(ExecutiveStatisticsService::class)->overview();
    }

    /** @return array<int, array<string, mixed>> */
    public function getEventsByStatus(): array
    {
        return app(ExecutiveStatisticsService::class)->eventsByStatus()->all();
    }

    /** @return array<int, array<string, mixed>> */
    public function getEventsPerMonth(): array
    {
        return app(ExecutiveStatisticsService::class)->eventsPerMonth();
    }

    /** @return array<int, array<string, mixed>> */
    public function getSettlementsByStatus(): array
    {
        return app(ExecutiveStatisticsService::class)->settlementsByStatus()->all();
    }

    public function profitLossUrl(array $query = []): string
    {
        $base = ExecutiveProfitLossPage::getUrl();

        return $query === [] ? $base : $base.'?'.http_build_query($query);
    }

    public function eventsIndexUrl(?string $status = null): string
    {
        $params = [];
        if (filled($status)) {
            $params['tableFilters']['status']['value'] = $status;
        }

        return EventResource::getUrl('index', $params);
    }

    public function contractorsUrl(): string
    {
        return ContractorResource::getUrl('index');
    }
}
