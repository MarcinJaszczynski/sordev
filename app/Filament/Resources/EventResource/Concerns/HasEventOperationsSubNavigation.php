<?php

declare(strict_types=1);

namespace App\Filament\Resources\EventResource\Concerns;

use App\Filament\Resources\EventResource;
use App\Filament\Resources\EventResource\Pages\EventHotelPlanning;
use App\Filament\Resources\EventResource\Pages\ManageEventDayInsurances;
use App\Filament\Resources\EventResource\Pages\ManageEventPilot;
use App\Filament\Resources\EventResource\Pages\ManageEventReservations;
use App\Filament\Resources\EventResource\Pages\ManageEventTransport;
use App\Support\WorkflowModuleNavigation;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\HtmlString;

trait HasEventOperationsSubNavigation
{
    public static function shouldRegisterNavigation(array $parameters = []): bool
    {
        return false;
    }

    public static function operationsSubNavigationActiveTab(): string
    {
        return match (static::class) {
            ManageEventReservations::class => 'reservations',
            ManageEventTransport::class => 'transport',
            EventHotelPlanning::class => 'hotels',
            ManageEventPilot::class => 'pilot',
            ManageEventDayInsurances::class => 'insurances',
            default => 'reservations',
        };
    }

    /**
     * @return array<int, array{key: string, label: string, description: ?string, url: string, icon: string, badge: ?string, active?: bool}>
     */
    public static function operationsSubNavigationTabs(int|string $recordId): array
    {
        $tabs = [
            [
                'key' => 'reservations',
                'label' => 'Rezerwacje',
                'description' => null,
                'icon' => 'heroicon-o-ticket',
                'url' => EventResource::getUrl('reservations', ['record' => $recordId]),
                'badge' => null,
            ],
            [
                'key' => 'transport',
                'label' => 'Transport',
                'description' => null,
                'icon' => 'heroicon-o-truck',
                'url' => EventResource::getUrl('transport', ['record' => $recordId]),
                'badge' => null,
            ],
            [
                'key' => 'hotels',
                'label' => 'Hotele',
                'description' => null,
                'icon' => 'heroicon-o-building-office-2',
                'url' => EventResource::getUrl('hotel-planning', ['record' => $recordId]),
                'badge' => null,
            ],
            [
                'key' => 'pilot',
                'label' => 'Pilot',
                'description' => null,
                'icon' => 'heroicon-o-user-circle',
                'url' => EventResource::getUrl('pilot', ['record' => $recordId]),
                'badge' => null,
            ],
        ];

        if (Schema::hasTable('event_day_insurance')) {
            $tabs[] = [
                'key' => 'insurances',
                'label' => 'Ubezpieczenia',
                'description' => null,
                'icon' => 'heroicon-o-shield-check',
                'url' => EventResource::getUrl('day-insurances', ['record' => $recordId]),
                'badge' => null,
            ];
        }

        return WorkflowModuleNavigation::markActive($tabs, static::operationsSubNavigationActiveTab());
    }

    /**
     * @return array<int, string>
     */
    public static function operationsRouteNames(): array
    {
        $routes = [
            ManageEventReservations::getRouteName(),
            ManageEventTransport::getRouteName(),
            EventHotelPlanning::getRouteName(),
            ManageEventPilot::getRouteName(),
        ];

        if (Schema::hasTable('event_day_insurance')) {
            $routes[] = ManageEventDayInsurances::getRouteName();
        }

        return $routes;
    }

    /**
     * Breadcrumb kończy się na module — aktywna sekcja jest w module-nav (bez duplikatu).
     *
     * @return array<int|string, string>
     */
    protected function buildModuleBreadcrumbs(): array
    {
        $recordId = $this->getRecord()->getKey();

        return $this->eventRecordBreadcrumbs(
            moduleLabel: 'Operacje',
            moduleUrl: EventResource::getUrl('reservations', ['record' => $recordId]),
            sectionLabel: null,
        );
    }

    /**
     * H1 nie powiela aktywnej zakładki module-nav (tytuł zakładki przeglądarki zostaje w $title).
     *
     * Nie zwracamy pustego stringa — Filament wtedy pomija cały header (w tym headerActions).
     * HtmlString jest truthy, więc akcje (np. „Wyślij do kierowcy”) nadal się renderują.
     */
    public function getHeading(): string|Htmlable
    {
        return new HtmlString('');
    }

    public function getSubheading(): ?string
    {
        return null;
    }
}
