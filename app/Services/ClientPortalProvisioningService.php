<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\Event;
use App\Models\EventAgreement;
use App\Models\EventParticipant;
use App\Models\EventPortalAccess;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class ClientPortalProvisioningService
{
    public function __construct(
        protected ClientOnboardingService $onboarding,
        protected EventParticipantPropagationService $participantPropagation,
    ) {}

    public function provisionFromAgreement(Contract|EventAgreement $agreement, ?int $sharedBy = null): ?EventPortalAccess
    {
        $agreement->loadMissing('event');

        $event = $agreement->event;

        if (! $event || ! Schema::hasTable('event_portal_accesses')) {
            return null;
        }

        [$role, $email, $name] = $this->resolveRoleEmailName($agreement);

        if (! filled($email)) {
            return null;
        }

        [$user, $plainPassword, $isNew] = $this->findOrCreateUser($email, $name, $role);

        $participantId = $this->resolveParticipantId($agreement, $event);
        $contractId = $agreement instanceof Contract ? $agreement->id : null;

        $access = EventPortalAccess::query()->updateOrCreate(
            [
                'event_id' => $event->id,
                'user_id' => $user->id,
                'role' => $role,
            ],
            [
                'contract_id' => $contractId,
                'event_participant_id' => $participantId,
                'source' => EventPortalAccess::SOURCE_AGREEMENT_FLOW,
                'shared_at' => now(),
                'shared_by' => $sharedBy,
                'revoked_at' => null,
            ],
        );

        if ($isNew && filled($plainPassword)) {
            $this->onboarding->sendPanelAccessCredentials($user, $plainPassword, $sharedBy);
        } else {
            $this->onboarding->sendTripSharedEmail($user, $event);
        }

        return $access;
    }

    public function grantAccess(
        Event $event,
        User $user,
        string $role,
        ?int $contractId = null,
        ?int $eventParticipantId = null,
        ?int $sharedBy = null,
        string $source = EventPortalAccess::SOURCE_ADMIN,
        bool $sendCredentials = false,
        ?string $plainPassword = null,
    ): EventPortalAccess {
        if (! $user->hasRole($this->roleNameFor($role))) {
            $user->assignRole($this->roleNameFor($role));
        }

        $access = EventPortalAccess::query()->updateOrCreate(
            [
                'event_id' => $event->id,
                'user_id' => $user->id,
                'role' => $role,
            ],
            [
                'contract_id' => $contractId,
                'event_participant_id' => $eventParticipantId,
                'source' => $source,
                'shared_at' => now(),
                'shared_by' => $sharedBy,
                'revoked_at' => null,
            ],
        );

        if ($sendCredentials && filled($plainPassword)) {
            $this->onboarding->sendPanelAccessCredentials($user, $plainPassword, $sharedBy);
        } else {
            $this->onboarding->sendTripSharedEmail($user, $event);
        }

        return $access;
    }

    public function revokeAccess(EventPortalAccess $access): void
    {
        $access->update(['revoked_at' => now()]);
    }

    /**
     * @return array{0: string, 1: string|null, 2: string|null}
     */
    protected function resolveRoleEmailName(Contract|EventAgreement $agreement): array
    {
        if ($agreement->isGroup()) {
            return [
                EventPortalAccess::ROLE_GUARDIAN,
                $agreement->signer_email ?: $agreement->customer_email,
                $agreement->signer_name ?: $agreement->customer_name,
            ];
        }

        return [
            EventPortalAccess::ROLE_PARTICIPANT,
            $agreement->participant_email ?: $agreement->signer_email ?: $agreement->customer_email,
            $agreement->participant_name ?: $agreement->signer_name,
        ];
    }

    /**
     * @return array{0: User, 1: string|null, 2: bool}
     */
    protected function findOrCreateUser(string $email, ?string $name, string $role): array
    {
        $existing = User::query()->where('email', $email)->first();

        if ($existing) {
            if (! $existing->hasRole($this->roleNameFor($role))) {
                $existing->assignRole($this->roleNameFor($role));
            }

            return [$existing, null, false];
        }

        $plainPassword = Str::password(12);

        Role::firstOrCreate(['name' => $this->roleNameFor($role), 'guard_name' => 'web']);

        $user = User::query()->create([
            'name' => $name ?: Str::before($email, '@'),
            'email' => $email,
            'password' => Hash::make($plainPassword),
            'status' => 'active',
        ]);

        $user->assignRole($this->roleNameFor($role));

        return [$user, $plainPassword, true];
    }

    protected function roleNameFor(string $portalRole): string
    {
        return match ($portalRole) {
            EventPortalAccess::ROLE_GUARDIAN => 'client_guardian',
            default => 'client_participant',
        };
    }

    protected function resolveParticipantId(Contract|EventAgreement $agreement, Event $event): ?int
    {
        if (! Schema::hasTable('event_participants')) {
            return null;
        }

        $this->participantPropagation->syncFromAgreements($event);

        $participant = EventParticipant::query()
            ->where('event_id', $event->id)
            ->when($agreement instanceof Contract, fn ($q) => $q->where('contract_id', $agreement->id))
            ->when($agreement instanceof EventAgreement, fn ($q) => $q->where('event_agreement_id', $agreement->id))
            ->first();

        return $participant?->id;
    }
}
