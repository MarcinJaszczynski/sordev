<?php

namespace App\Filament\Resources\EventResource\Concerns;

use App\Filament\Resources\EventResource;
use App\Filament\Resources\EventResource\Pages\ManageEventClientPortal;
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
                'description' => null,
                'icon' => 'heroicon-o-users',
                'url' => EventResource::getUrl('participants', ['record' => $recordId]),
                'badge' => null,
            ],
            [
                'key' => 'participant-resignations',
                'label' => 'Rezygnacje',
                'description' => null,
                'icon' => 'heroicon-o-user-minus',
                'url' => EventResource::getUrl('participant-resignations', ['record' => $recordId]),
                'badge' => null,
            ],
            [
                'key' => 'participant-portal',
                'label' => 'Portal klienta',
                'description' => null,
                'icon' => 'heroicon-o-user-group',
                'url' => EventResource::getUrl('participant-portal', ['record' => $recordId]),
                'badge' => null,
            ],
        ];

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

        return [
            ManageEventParticipants::getRouteName(),
            ManageEventResignations::getRouteName(),
            ManageEventClientPortal::getRouteName(),
        ];
    }

    /**
     * @return array<int|string, string>
     */
    protected function buildModuleBreadcrumbs(): array
    {
        $recordId = $this->getRecord()->getKey();

        return $this->eventRecordBreadcrumbs(
            moduleLabel: 'Uczestnicy',
            moduleUrl: EventResource::getUrl('participants', ['record' => $recordId]),
            sectionLabel: null,
        );
    }

    public function getHeading(): string|\Illuminate\Contracts\Support\Htmlable
    {
        // Pusty string ukrywa cały header Filament (w tym headerActions). HtmlString jest truthy.
        return new \Illuminate\Support\HtmlString('');
    }

    public function getSubheading(): ?string
    {
        return null;
    }
}
