<?php

namespace App\Services;

use App\Mail\PilotPanelAccessMail;
use App\Mail\PilotTripSharedMail;
use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

class PilotOnboardingService
{
    public function sendPanelAccessCredentials(User $user, string $plainPassword, ?int $sentBy = null): bool
    {
        if (! $user->hasRole('pilot') || $user->status !== 'active' || ! filled($user->email)) {
            return false;
        }

        if (! filled($plainPassword)) {
            return false;
        }

        Mail::to($user->email)->send(new PilotPanelAccessMail(
            $user,
            $plainPassword,
            url('/pilot/login'),
        ));

        if (Schema::hasColumn('users', 'pilot_panel_access_sent_at')) {
            $payload = ['pilot_panel_access_sent_at' => now()];

            if (Schema::hasColumn('users', 'pilot_panel_access_sent_by')) {
                $payload['pilot_panel_access_sent_by'] = $sentBy;
            }

            $user->update($payload);
        }

        return true;
    }

    public function sendTripSharedEmail(Event $event, User $pilot): bool
    {
        if (! filled($pilot->email)) {
            return false;
        }

        Mail::to($pilot->email)->send(new PilotTripSharedMail(
            $pilot,
            $event->fresh(),
            url('/pilot/login'),
        ));

        if (Schema::hasColumn('events', 'pilot_trip_email_sent_at')) {
            $event->update(['pilot_trip_email_sent_at' => now()]);
        }

        return true;
    }

    /**
     * @deprecated Użyj sendPanelAccessCredentials() — wywoływane ręcznie z panelu admina.
     */
    public function sendCredentialsIfPilot(User $user, ?string $plainPassword): void
    {
        if (! filled($plainPassword)) {
            return;
        }

        $this->sendPanelAccessCredentials($user, $plainPassword);
    }
}
