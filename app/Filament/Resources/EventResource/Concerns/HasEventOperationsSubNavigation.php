<?php

declare(strict_types=1);

namespace App\Filament\Resources\EventResource\Concerns;

use App\Filament\Resources\EventResource;
use App\Filament\Resources\EventResource\Pages\EventHotelPlanning;
use App\Filament\Resources\EventResource\Pages\ManageEventPilot;
use App\Filament\Resources\EventResource\Pages\ManageEventReservations;
use App\Filament\Resources\EventResource\Pages\ManageEventTasks;
use App\Filament\Resources\EventResource\Pages\ManageEventTransport;
use App\Support\WorkflowModuleNavigation;

trait HasEventOperationsSubNavigation
{
    public static function shouldRegisterNavigation(array $parameters = []): bool
    {
        return false;
    }

    public static function operationsSubNavigationActiveTab(): string
    {
        return match (static::class) {
            ManageEventTasks::class => 'tasks',
            ManageEventReservations::class => 'reservations',
            ManageEventTransport::class => 'transport',
            EventHotelPlanning::class => 'hotels',
            ManageEventPilot::class => 'pilot',
            default => 'tasks',
        };
    }

    /**
     * @return array<int, array{key: string, label: string, description: string, url: string, icon: string, badge: ?string, active?: bool}>
     */
    public static function operationsSubNavigationTabs(int|string $recordId): array
    {
        $tabs = [
            [
                'key' => 'tasks',
                'label' => 'Zadania',
                'description' => 'Zadania tej imprezy',
                'icon' => 'heroicon-o-clipboard-document-list',
                'url' => EventResource::getUrl('tasks', ['record' => $recordId]),
                'badge' => null,
            ],
            [
                'key' => 'reservations',
                'label' => 'Rezerwacje',
                'description' => 'Rezerwacje tej imprezy',
                'icon' => 'heroicon-o-ticket',
                'url' => EventResource::getUrl('reservations', ['record' => $recordId]),
                'badge' => null,
            ],
            [
                'key' => 'transport',
                'label' => 'Transport',
                'description' => 'Autokar, kierowca, trasa',
                'icon' => 'heroicon-o-truck',
                'url' => EventResource::getUrl('transport', ['record' => $recordId]),
                'badge' => null,
            ],
            [
                'key' => 'hotels',
                'label' => 'Hotele',
                'description' => 'Plan zakwaterowania',
                'icon' => 'heroicon-o-building-office-2',
                'url' => EventResource::getUrl('hotel-planning', ['record' => $recordId]),
                'badge' => null,
            ],
            [
                'key' => 'pilot',
                'label' => 'Pilot wycieczki',
                'description' => 'Przydział i zaliczka',
                'icon' => 'heroicon-o-user-circle',
                'url' => EventResource::getUrl('pilot', ['record' => $recordId]),
                'badge' => null,
            ],
        ];

        return WorkflowModuleNavigation::markActive($tabs, static::operationsSubNavigationActiveTab());
    }

    /**
     * @return array<int, string>
     */
    public static function operationsRouteNames(): array
    {
        return [
            ManageEventTasks::getRouteName(),
            ManageEventReservations::getRouteName(),
            ManageEventTransport::getRouteName(),
            EventHotelPlanning::getRouteName(),
            ManageEventPilot::getRouteName(),
        ];
    }

    /**
     * @return array<int|string, string>
     */
    protected function buildModuleBreadcrumbs(): array
    {
        $recordId = $this->getRecord()->getKey();
        $section = match (static::operationsSubNavigationActiveTab()) {
            'tasks' => 'Zadania',
            'reservations' => 'Rezerwacje',
            'transport' => 'Transport',
            'hotels' => 'Hotele',
            'pilot' => 'Pilot wycieczki',
            default => 'Operacje',
        };

        return $this->eventRecordBreadcrumbs(
            moduleLabel: 'Operacje',
            moduleUrl: EventResource::getUrl('tasks', ['record' => $recordId]),
            sectionLabel: $section,
        );
    }
}
