<?php

namespace App\Filament\Resources\EventResource\Concerns;

use App\Filament\Resources\EventResource;
use App\Filament\Resources\EventResource\Pages\ManageEventClientPortal;
use App\Filament\Resources\EventResource\Pages\ManageEventDayInsurances;
use App\Filament\Resources\EventResource\Pages\ManageEventParticipants;
use App\Filament\Resources\EventResource\Pages\ManageEventResignations;
use App\Support\WorkflowModuleNavigation;
use Illuminate\Support\Facades\Schema;

trait HasEventParticipantsSubNavigation
{
    public static function participantsSubNavigationActiveTab(): string
    {
        return match (static::class) {
            ManageEventParticipants::class => 'participants-list',
            ManageEventResignations::class => 'participant-resignations',
            ManageEventClientPortal::class => 'participant-portal',
            ManageEventDayInsurances::class => 'day-insurances',
            default => 'participants-list',
        };
    }

    /**
     * @return array<int, array{key: string, label: string, description: string, url: string, icon: string, badge: ?string, active?: bool}>
     */
    public static function participantsSubNavigationTabs(int|string $recordId): array
    {
        if (! Schema::hasTable('event_participants')) {
            return [];
        }

        $tabs = [
            [
                'key' => 'participants-list',
                'label' => 'Lista',
                'description' => 'Rejestr uczestników imprezy',
                'icon' => 'heroicon-o-users',
                'url' => EventResource::getUrl('participants', ['record' => $recordId]),
                'badge' => null,
            ],
            [
                'key' => 'participant-resignations',
                'label' => 'Rezygnacje',
                'description' => 'Rezygnacje i korekty listy',
                'icon' => 'heroicon-o-user-minus',
                'url' => EventResource::getUrl('participant-resignations', ['record' => $recordId]),
                'badge' => null,
            ],
            [
                'key' => 'participant-portal',
                'label' => 'Portal klienta',
                'description' => 'Dostęp opiekunów i uczestników',
                'icon' => 'heroicon-o-user-group',
                'url' => EventResource::getUrl('participant-portal', ['record' => $recordId]),
                'badge' => null,
            ],
        ];

        if (Schema::hasTable('event_day_insurance')) {
            $tabs[] = [
                'key' => 'day-insurances',
                'label' => 'Ubezpieczenia',
                'description' => 'Polisy dzienne uczestników',
                'icon' => 'heroicon-o-shield-check',
                'url' => EventResource::getUrl('day-insurances', ['record' => $recordId]),
                'badge' => null,
            ];
        }

        return WorkflowModuleNavigation::markActive($tabs, static::participantsSubNavigationActiveTab());
    }

    /**
     * @return array<int, string>
     */
    public static function participantsRouteNames(): array
    {
        if (! Schema::hasTable('event_participants')) {
            return [];
        }

        $routes = [
            ManageEventParticipants::getRouteName(),
            ManageEventResignations::getRouteName(),
            ManageEventClientPortal::getRouteName(),
        ];

        if (Schema::hasTable('event_day_insurance')) {
            $routes[] = ManageEventDayInsurances::getRouteName();
        }

        return $routes;
    }

    /**
     * @return array<int|string, string>
     */
    protected function buildModuleBreadcrumbs(): array
    {
        $recordId = $this->getRecord()->getKey();
        $section = match (static::participantsSubNavigationActiveTab()) {
            'participants-list' => 'Lista',
            'participant-resignations' => 'Rezygnacje',
            'participant-portal' => 'Portal klienta',
            'day-insurances' => 'Ubezpieczenia',
            default => 'Uczestnicy',
        };

        return $this->eventRecordBreadcrumbs(
            moduleLabel: 'Uczestnicy',
            moduleUrl: EventResource::getUrl('participants', ['record' => $recordId]),
            sectionLabel: $section,
        );
    }
}
