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
        // Pilot z samym podglądem nie edytuje imprezy (API / legacy HTTP).
        if ($user->hasRole('pilot') && ! app(PilotAccessService::class)->shouldUseOfficeEventAccess($user)) {
            return false;
        }

        if ($user->hasRole(['admin', 'super_admin', 'biuro', 'ksiegowosc'])) {
            return $this->view($user, $event);
        }

        return $user->can('edit event') && $this->view($user, $event);
    }

    /**
     * Krytyczne zapisy finansowe (wpłaty kosztów/uczestników, plan kosztów, dokumenty).
     * Pilot ma osobny zakres przez EventSettlementPolicy — tu biuro/admin.
     */
    public function manageFinance(User $user, Event $event): bool
    {
        if ($user->hasRole(['admin', 'super_admin'])) {
            return true;
        }

        if ($user->hasRole('pilot') && ! app(PilotAccessService::class)->shouldUseOfficeEventAccess($user)) {
            return false;
        }

        return $this->update($user, $event);
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
