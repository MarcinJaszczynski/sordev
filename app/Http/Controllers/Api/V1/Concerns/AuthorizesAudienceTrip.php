<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Concerns;

use App\Models\Event;
use App\Services\ClientAccessService;
use App\Services\PilotAccessService;
use Illuminate\Support\Facades\Gate;

trait AuthorizesAudienceTrip
{
    protected function authorizePilotView(Event $event): void
    {
        Gate::authorize('view', $event);
    }

    protected function authorizePilotDetails(Event $event): void
    {
        Gate::authorize('viewPilotDetails', $event);
    }

    protected function assertPilotCanMutate(Event $event): void
    {
        $access = app(PilotAccessService::class);
        $access->assertPilotMutationsAllowed();
        abort_unless($access->hasFullAccess($event, request()->user()), 403, 'Brak pełnego dostępu do tej wycieczki.');
    }

    protected function authorizeClientView(Event $event): void
    {
        Gate::authorize('viewClientPortal', $event);
    }

    protected function authorizeClientDetails(Event $event): void
    {
        Gate::authorize('viewClientPortalDetails', $event);
    }

    protected function assertClientCanMutate(): void
    {
        app(ClientAccessService::class)->assertPortalMutationsAllowed();
    }
}
