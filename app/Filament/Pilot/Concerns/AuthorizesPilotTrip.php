<?php

namespace App\Filament\Pilot\Concerns;

use App\Models\Event;
use App\Services\PilotAccessService;
use Illuminate\Support\Facades\Auth;

trait AuthorizesPilotTrip
{
    protected function authorizePilotTrip(Event $event, bool $requireFullAccess = false): void
    {
        $user = Auth::user();
        $service = app(PilotAccessService::class);

        if ($requireFullAccess) {
            abort_unless($user?->can('viewPilotDetails', $event), 403, $this->pilotTripDenialMessage($event, true));

            return;
        }

        abort_unless($user?->can('view', $event), 403, $this->pilotTripDenialMessage($event, false));
    }

    protected function pilotTripDenialMessage(Event $event, bool $fullAccess): string
    {
        $user = Auth::user();
        $service = app(PilotAccessService::class);

        if ($fullAccess && $user?->can('view', $event) && ! $user->can('viewPilotDetails', $event)) {
            return $service->archiveMessage($event) ?: 'Pełny dostęp do tej wycieczki wygasł.';
        }

        return $service->accessDenialReason($user, $event)
            ?? 'Brak dostępu do tej imprezy.';
    }
}
