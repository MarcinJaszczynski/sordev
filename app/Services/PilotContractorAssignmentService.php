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

    /**
     * Zapis PESEL/daty z konta pilota: kanonicznie na Contractor (gdy jest karta), potem kopia na User.
     * Bez kontrahenta — tylko profil User.
     */
    public function persistPilotDemographicsFromPortalUser(User $user, $birthDate, ?string $pesel, ?string $phone = null): void
    {
        $contractor = $this->findPilotContractorByEmail($user->email);

        if ($contractor) {
            $this->syncContractorDemographics(
                (int) $contractor->getKey(),
                filled($birthDate) ? (is_string($birthDate) ? $birthDate : $birthDate->format('Y-m-d')) : null,
                $pesel,
            );

            if (Schema::hasColumn('contractors', 'phone') && $phone !== null) {
                Contractor::query()->whereKey($contractor->getKey())->update([
                    'phone' => filled($phone) ? trim($phone) : null,
                ]);
            }

            $this->syncPortalUserDemographicsFromContractor((int) $contractor->getKey(), (int) $user->getKey());

            return;
        }

        User::syncPilotDemographics((int) $user->getKey(), $birthDate, $pesel, $phone);
    }

    /**
     * Po edycji karty kontrahenta-pilota — skopiuj dane osobowe na powiązane konto portalu.
     */
    public function syncPortalUserDemographicsFromContractorRecord(Contractor $contractor): void
    {
        if (! $contractor->hasPilotType()) {
            return;
        }

        $userId = $this->resolvePortalUserId($contractor);

        if (! $userId) {
            return;
        }

        $this->syncPortalUserDemographicsFromContractor((int) $contractor->getKey(), $userId);
    }

    public function findPilotContractorByEmail(?string $email): ?Contractor
    {
        if (! filled($email)) {
            return null;
        }

        return Contractor::query()
            ->where('email', $email)
            ->withAnyTypeName(['pilot'])
            ->first();
    }

    /**
     * @return array{birth_date: mixed, pesel: ?string, contractor_label: ?string}
     */
    public function demographicsFormStateForPortalUser(User $user): array
    {
        $contractor = $this->findPilotContractorByEmail($user->email);

        if ($contractor) {
            return [
                'birth_date' => $contractor->birth_date?->format('Y-m-d'),
                'pesel' => $contractor->pesel,
                'contractor_label' => $contractor->name,
            ];
        }

        return [
            'birth_date' => $user->birth_date?->format('Y-m-d'),
            'pesel' => $user->pesel,
            'contractor_label' => null,
        ];
    }

    public function assignedUserLabel(?Contractor $contractor): string
    {
        if (! $contractor) {
            return '—';
        }

        $userId = $this->resolvePortalUserId($contractor);

        if (! $userId) {
            return 'Brak konta użytkownika — utwórz w «Piloci» z tym samym e-mailem, aby pilot miał dostęp do panelu.';
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

    /**
     * Konto portalu pilota dla imprezy — kontrahent (pilot_contractor_id) ma pierwszeństwo
     * przed legacy assigned_to, żeby podgląd biura zgadzał się z przypisaniem w formularzu.
     */
    public function resolvePortalUserIdForEvent(Event $event): ?int
    {
        $event->loadMissing(['pilotContractor', 'assignedUser']);

        if (Schema::hasColumn('events', 'pilot_contractor_id') && filled($event->pilot_contractor_id)) {
            $fromContractor = $this->resolvePortalUserId($event->pilotContractor);

            if ($fromContractor) {
                return $fromContractor;
            }
        }

        return filled($event->assigned_to) ? (int) $event->assigned_to : null;
    }

    /**
     * Etykieta pilota w UI — nazwa kontrahenta, gdy wybrany w polu „Pilot”.
     */
    public function pilotDisplayNameForEvent(Event $event): ?string
    {
        $event->loadMissing(['pilotContractor', 'assignedUser']);

        $name = $event->pilotContractor?->name ?? $event->assignedUser?->name;

        return filled($name) ? trim((string) $name) : null;
    }

    /**
     * Czy impreza ma pilota operacyjnego (osoba na wyjeździe).
     * Kanonicznie: kontrahent; legacy: samo assigned_to bez kontrahenta.
     * Konto portalu NIE jest wymagane.
     */
    public function eventHasAssignedPilot(Event $event): bool
    {
        if (Schema::hasColumn('events', 'pilot_contractor_id') && filled($event->pilot_contractor_id)) {
            return true;
        }

        return filled($event->assigned_to);
    }

    /**
     * Czy da się otworzyć / udostępnić panel pilota (opcjonalne).
     */
    public function eventHasPortalAccount(Event $event): bool
    {
        return $this->resolvePortalUserIdForEvent($event) !== null;
    }

    /**
     * Scope SQL: imprezy z przypisanym pilotem (kontrahent i/lub legacy User).
     *
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\Event>|\Illuminate\Database\Query\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder<\App\Models\Event>|\Illuminate\Database\Query\Builder
     */
    public function constrainEventsWithAssignedPilot($query)
    {
        return $query->where(function ($inner): void {
            if (Schema::hasColumn('events', 'pilot_contractor_id')) {
                $inner->whereNotNull('pilot_contractor_id')
                    ->orWhereNotNull('assigned_to');

                return;
            }

            $inner->whereNotNull('assigned_to');
        });
    }
}
