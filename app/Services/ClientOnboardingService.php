<?php

namespace App\Services;

use App\Mail\ClientPortalAccessMail;
use App\Mail\ClientTripSharedMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

class ClientOnboardingService
{
    public function sendPanelAccessCredentials(User $user, string $plainPassword, ?int $sentBy = null): bool
    {
        if (! $this->isPortalUser($user) || $user->status !== 'active' || ! filled($user->email)) {
            return false;
        }

        if (! filled($plainPassword)) {
            return false;
        }

        Mail::to($user->email)->send(new ClientPortalAccessMail(
            $user,
            $plainPassword,
            url('/portal/login'),
        ));

        return true;
    }

    public function sendTripSharedEmail(User $user, \App\Models\Event $event): bool
    {
        if (! filled($user->email)) {
            return false;
        }

        Mail::to($user->email)->send(new ClientTripSharedMail(
            $user,
            $event->fresh(),
            url('/portal/login'),
        ));

        return true;
    }

    protected function isPortalUser(User $user): bool
    {
        return $user->hasRole(['client_participant', 'client_guardian']);
    }
}
