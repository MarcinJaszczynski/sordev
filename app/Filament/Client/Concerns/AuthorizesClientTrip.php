<?php

namespace App\Filament\Client\Concerns;

use App\Models\Event;
use App\Services\ClientAccessService;
use Illuminate\Support\Facades\Auth;

trait AuthorizesClientTrip
{
    protected function authorizeClientTrip(Event $event, bool $requireFullAccess = false): void
    {
        $user = Auth::user();
        $service = app(ClientAccessService::class);

        if ($requireFullAccess) {
            abort_unless($user?->can('viewClientPortalDetails', $event), 403, $this->clientTripDenialMessage($event, true));

            return;
        }

        abort_unless($user?->can('viewClientPortal', $event), 403, $this->clientTripDenialMessage($event, false));
    }

    protected function clientTripDenialMessage(Event $event, bool $fullAccess): string
    {
        $user = Auth::user();
        $service = app(ClientAccessService::class);

        if ($fullAccess && $user?->can('viewClientPortal', $event) && ! $user->can('viewClientPortalDetails', $event)) {
            return $service->archiveMessage($event) ?: 'Pełny dostęp do tej wycieczki wygasł.';
        }

        return $service->accessDenialReason($user, $event)
            ?? 'Brak dostępu do tej imprezy.';
    }
}
