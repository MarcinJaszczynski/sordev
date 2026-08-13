<?php

namespace App\Services;

use App\Models\Contractor;
use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

class PilotContractorAssignmentService
{
    public function applyContactFieldsToForm(?int $contractorId, callable $set): void
    {
        if (! $contractorId) {
            $set('pilot_birth_date', null);
            $set('pilot_pesel', null);

            return;
        }

        $contractor = Contractor::query()->find($contractorId);
        $set('pilot_birth_date', $contractor?->birth_date?->format('Y-m-d'));
        $set('pilot_pesel', $contractor?->pesel);
    }

    public function resolveContractorIdForEvent(Event $event): ?int
    {
        if (Schema::hasColumn('events', 'pilot_contractor_id') && filled($event->pilot_contractor_id)) {
            return (int) $event->pilot_contractor_id;
        }

        $email = $event->assignedUser?->email;

        if (! filled($email)) {
            return null;
        }

        $contractorId = Contractor::query()
            ->where('email', $email)
            ->withAnyTypeName(['pilot'])
            ->value('id');

        return $contractorId ? (int) $contractorId : null;
    }

    public function resolvePortalUserId(?Contractor $contractor): ?int
    {
        if (! $contractor || ! filled($contractor->email)) {
            return null;
        }

        $pilotUserId = User::query()
            ->where('email', $contractor->email)
            ->whereHas('roles', fn ($query) => $query->where('name', 'pilot'))
            ->value('id');

        if ($pilotUserId) {
            return (int) $pilotUserId;
        }

        $userId = User::query()
            ->where('email', $contractor->email)
            ->value('id');

        return $userId ? (int) $userId : null;
    }

    public function syncContractorDemographics(?int $contractorId, ?string $birthDate, ?string $pesel): void
    {
        if (! $contractorId) {
            return;
        }

        $payload = [];

        if (Schema::hasColumn('contractors', 'birth_date')) {
            $payload['birth_date'] = filled($birthDate) ? $birthDate : null;
        }

        if (Schema::hasColumn('contractors', 'pesel')) {
            $payload['pesel'] = filled($pesel) ? $pesel : null;
        }

        if ($payload !== []) {
            Contractor::query()->whereKey($contractorId)->update($payload);
        }
    }

    public function syncPortalUserDemographicsFromContractor(?int $contractorId, ?int $assignedUserId): void
    {
        if (! $assignedUserId) {
            return;
        }

        $contractor = $contractorId ? Contractor::query()->find($contractorId) : null;

        User::syncPilotDemographics(
            $assignedUserId,
            $contractor?->birth_date,
            $contractor?->pesel,
            $contractor?->phone,
        );
    }

    public function assignedUserLabel(?Contractor $contractor): string
    {
        if (! $contractor) {
            return '—';
        }

        $userId = $this->resolvePortalUserId($contractor);

        if (! $userId) {
            return 'Brak konta użytkownika — utwórz w «Zespół (piloci)» z tym samym e-mailem, aby pilot miał dostęp do panelu.';
        }

        $user = User::query()->find($userId);

        if (! $user) {
            return '—';
        }

        $details = array_filter([
            $user->name,
            filled($user->email) ? '('.$user->email.')' : null,
        ]);

        return implode(' ', $details);
    }
}
