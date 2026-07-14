<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;
use App\Services\ClientAccessService;
use App\Services\PilotAccessService;

class EventPolicy
{
    public function viewAny(User $user): bool
    {
        return app(PilotAccessService::class)->canViewAnyEvents($user);
    }

    public function view(User $user, Event $event): bool
    {
        return app(PilotAccessService::class)->canViewEvent($user, $event);
    }

    public function viewPilotDetails(User $user, Event $event): bool
    {
        $service = app(PilotAccessService::class);

        if (! $service->canViewEvent($user, $event)) {
            return false;
        }

        if ($service->shouldUseOfficeEventAccess($user)) {
            return true;
        }

        return $service->hasFullAccess($event, $user);
    }

    public function update(User $user, Event $event): bool
    {
        return $this->view($user, $event);
    }

    public function viewClientPortal(User $user, Event $event): bool
    {
        return app(ClientAccessService::class)->canViewEvent($user, $event);
    }

    public function viewClientPortalDetails(User $user, Event $event): bool
    {
        $service = app(ClientAccessService::class);

        if (! $service->canViewEvent($user, $event)) {
            return false;
        }

        if ($service->shouldUseOfficeEventAccess($user)) {
            return true;
        }

        return $service->hasFullAccess($event, $user);
    }
}
