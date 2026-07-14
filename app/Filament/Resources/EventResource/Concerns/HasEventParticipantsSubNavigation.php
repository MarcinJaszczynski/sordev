<?php

namespace App\Filament\Resources\EventResource\Concerns;

use App\Filament\Resources\EventResource;
use App\Filament\Resources\EventResource\Pages\ManageEventClientPortal;
use App\Filament\Resources\EventResource\Pages\ManageEventParticipants;
use App\Filament\Resources\EventResource\Pages\ManageEventResignations;
use App\Filament\Resources\EventResource\Pages\ManageEventSettlementPayments;
use Illuminate\Support\Facades\Schema;

trait HasEventParticipantsSubNavigation
{
    public static function participantsSubNavigationActiveTab(): string
    {
        return match (static::class) {
            ManageEventParticipants::class => 'participants-list',
            ManageEventSettlementPayments::class => 'participant-payments',
            ManageEventResignations::class => 'participant-resignations',
            ManageEventClientPortal::class => 'participant-portal',
            default => 'participants-list',
        };
    }

    /**
     * @return array<int, array{key: string, label: string, icon: string, url: string}>
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
                'icon' => 'heroicon-o-users',
                'url' => EventResource::getUrl('participants', ['record' => $recordId]),
            ],
            [
                'key' => 'participant-payments',
                'label' => 'Zapłacono',
                'icon' => 'heroicon-o-credit-card',
                'url' => EventResource::getUrl('participant-payments', ['record' => $recordId]),
            ],
            [
                'key' => 'participant-resignations',
                'label' => 'Rezygnacje',
                'icon' => 'heroicon-o-user-minus',
                'url' => EventResource::getUrl('participant-resignations', ['record' => $recordId]),
            ],
            [
                'key' => 'participant-portal',
                'label' => 'Portal klienta',
                'icon' => 'heroicon-o-user-group',
                'url' => EventResource::getUrl('participant-portal', ['record' => $recordId]),
            ],
        ];

        return $tabs;
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
            ManageEventSettlementPayments::getRouteName(),
            ManageEventResignations::getRouteName(),
            ManageEventClientPortal::getRouteName(),
        ];
    }
}
