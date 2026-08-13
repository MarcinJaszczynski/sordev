<?php

namespace App\Services;

use App\Http\Middleware\ClientPreviewMiddleware;
use App\Models\Contract;
use App\Models\Event;
use App\Models\EventPortalAccess;
use App\Models\EventSettlementParticipantPayment;
use App\Models\User;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class ClientAccessService
{
    public function fullAccessDaysAfterEnd(): int
    {
        return max(0, (int) config('portal.full_access_days_after_end', 90));
    }

    public function accessExpiresAt(Event $event): ?Carbon
    {
        if (! $event->end_date) {
            return null;
        }

        return $event->end_date->copy()->endOfDay()->addDays($this->fullAccessDaysAfterEnd());
    }

    public function isArchived(Event $event): bool
    {
        $expiresAt = $this->accessExpiresAt($event);

        if (! $expiresAt) {
            return false;
        }

        return now()->greaterThan($expiresAt);
    }

    public function hasFullAccess(Event $event, ?User $user = null): bool
    {
        if ($user && $this->isOfficePreview($user)) {
            return true;
        }

        if ($user?->hasRole(['admin', 'super_admin', 'biuro'])
            && ! $user->hasRole(['client_participant', 'client_guardian'])) {
            return true;
        }

        return ! $this->isArchived($event);
    }

    public function isOfficePreview(?User $user = null): bool
    {
        $user ??= auth()->user();

        if (! $user) {
            return false;
        }

        return ClientPreviewMiddleware::isActive()
            && $user->hasRole(['admin', 'super_admin', 'biuro']);
    }

    /**
     * Podgląd jako konkretny EventPortalAccess (sesja biura).
     */
    public function previewAccess(): ?EventPortalAccess
    {
        if (! ClientPreviewMiddleware::isActive() || ! Schema::hasTable('event_portal_accesses')) {
            return null;
        }

        $staff = auth()->user();
        if (! $staff || ! $staff->hasRole(['admin', 'super_admin', 'biuro'])) {
            return null;
        }

        // Konta klientów nie dziedziczą cudzego preview_access_id.
        if ($staff->hasRole(['client_participant', 'client_guardian'])) {
            return null;
        }

        $id = ClientPreviewMiddleware::accessId();
        if (! $id) {
            return null;
        }

        return EventPortalAccess::query()
            ->active()
            ->with(['user', 'event', 'contract'])
            ->find($id);
    }

    public function previewAccessForEvent(Event $event): ?EventPortalAccess
    {
        $access = $this->previewAccess();
        if (! $access || (int) $access->event_id !== (int) $event->id) {
            return null;
        }

        return $access;
    }

    public function isPreviewReadOnly(?User $user = null): bool
    {
        return $this->isOfficePreview($user);
    }

    public function assertPortalMutationsAllowed(): void
    {
        if ($this->isPreviewReadOnly()) {
            abort(403, 'Podgląd portalu jest tylko do odczytu — zapisy i płatności są wyłączone.');
        }
    }

    public function previewBannerContext(): ?array
    {
        if (! $this->isOfficePreview()) {
            return null;
        }

        $access = $this->previewAccess();
        $exitUrl = url('/portal/client-events?exit_preview=1');

        if ($access) {
            $roleLabel = $access->isGuardian() ? 'opiekun' : 'uczestnik';
            $name = $access->user?->name ?: $access->user?->email ?: ('Access #'.$access->id);

            return [
                'title' => 'Podgląd: '.$name.' · '.$roleLabel,
                'description' => 'Tryb tylko do odczytu. Widzisz portal jak to konto klienta — bez płatności i zapisów.',
                'exitUrl' => $exitUrl,
            ];
        }

        return [
            'title' => 'Podgląd portalu klienta',
            'description' => 'Tryb biurowy bez wybranego konta — lista imprez. Wybierz dostęp „Podgląd jako…”, żeby zobaczyć rolę uczestnika/opiekuna.',
            'exitUrl' => $exitUrl,
        ];
    }

    public function visibleTripsQuery(User $user): Builder
    {
        // Konto z rolą uczestnika/opiekuna zawsze widzi tylko swoje accessy.
        if ($this->isOfficePreview($user)
            && ! $user->hasRole(['client_participant', 'client_guardian'])
        ) {
            $access = $this->previewAccess();
            if ($access) {
                return Event::query()->whereKey($access->event_id);
            }

            return Event::query()->where('status', '!=', Event::STATUS_CANCELLED);
        }

        if (! Schema::hasTable('event_portal_accesses')) {
            return Event::query()->whereRaw('1 = 0');
        }

        return Event::query()
            ->where('status', '!=', Event::STATUS_CANCELLED)
            ->whereHas('portalAccesses', function (Builder $query) use ($user): void {
                $query->active()->where('user_id', $user->id);
            });
    }

    public function currentPanelId(): ?string
    {
        if (! Filament::isServing()) {
            return null;
        }

        return Filament::getCurrentPanel()?->getId();
    }

    public function shouldUseOfficeEventAccess(User $user): bool
    {
        $panelId = $this->currentPanelId();

        if ($panelId === 'admin') {
            return true;
        }

        if ($panelId === 'portal') {
            return false;
        }

        return $user->hasRole(['super_admin', 'admin', 'biuro', 'ksiegowosc'])
            && ! $user->hasRole(['client_participant', 'client_guardian']);
    }

    public function officeCanViewEvent(User $user, Event $event): bool
    {
        return $user->hasRole(['super_admin', 'admin', 'biuro', 'ksiegowosc']);
    }

    public function canViewAnyEvents(User $user): bool
    {
        $panelId = $this->currentPanelId();

        if ($panelId === 'admin') {
            return $user->hasRole(['super_admin', 'admin', 'biuro', 'ksiegowosc']);
        }

        if ($panelId === 'portal') {
            if ($this->isOfficePreview($user)) {
                return true;
            }

            return $user->hasRole(['client_participant', 'client_guardian']);
        }

        if ($user->hasRole(['super_admin', 'admin', 'biuro', 'ksiegowosc'])
            && ! $user->hasRole(['client_participant', 'client_guardian'])) {
            return true;
        }

        return $user->hasRole(['client_participant', 'client_guardian']);
    }

    public function canViewEvent(User $user, Event $event): bool
    {
        if ($this->shouldUseOfficeEventAccess($user)) {
            return $this->officeCanViewEvent($user, $event);
        }

        return $this->canViewTrip($user, $event);
    }

    public function canViewTrip(User $user, Event $event): bool
    {
        if ($this->previewAccessForEvent($event)) {
            return true;
        }

        if ($this->isOfficePreview($user)
            && ! $user->hasRole(['client_participant', 'client_guardian'])
        ) {
            return $event->status !== Event::STATUS_CANCELLED;
        }

        return $this->accessFor($user, $event) !== null;
    }

    public function accessFor(User $user, Event $event, ?string $role = null): ?EventPortalAccess
    {
        if ($preview = $this->previewAccessForEvent($event)) {
            if ($role !== null && $preview->role !== $role) {
                return null;
            }

            return $preview;
        }

        if (! Schema::hasTable('event_portal_accesses')) {
            return null;
        }

        $query = EventPortalAccess::query()
            ->active()
            ->where('event_id', $event->id)
            ->where('user_id', $user->id);

        if ($role !== null) {
            return $query->where('role', $role)->first();
        }

        return $query
            ->orderByRaw("CASE role WHEN 'participant' THEN 0 WHEN 'guardian' THEN 1 ELSE 2 END")
            ->first();
    }

    public function accessesFor(User $user, Event $event)
    {
        if ($preview = $this->previewAccessForEvent($event)) {
            return collect([$preview]);
        }

        if (! Schema::hasTable('event_portal_accesses')) {
            return collect();
        }

        return EventPortalAccess::query()
            ->active()
            ->where('event_id', $event->id)
            ->where('user_id', $user->id)
            ->get();
    }

    public function isGuardian(User $user, Event $event): bool
    {
        return $this->accessesFor($user, $event)
            ->contains(fn (EventPortalAccess $access): bool => $access->isGuardian());
    }

    public function isParticipant(User $user, Event $event): bool
    {
        return $this->accessesFor($user, $event)
            ->contains(fn (EventPortalAccess $access): bool => $access->isParticipant());
    }

    public function accessibleContract(User $user, Event $event, ?string $role = null): ?Contract
    {
        $access = $role
            ? $this->accessFor($user, $event, $role)
            : $this->accessFor($user, $event);

        if (! $access?->contract_id || ! Schema::hasTable('contracts')) {
            return null;
        }

        return Contract::query()
            ->where('event_id', $event->id)
            ->whereKey($access->contract_id)
            ->first();
    }

    public function participantContract(User $user, Event $event): ?Contract
    {
        if (! $this->isParticipant($user, $event)) {
            return null;
        }

        return $this->accessibleContract($user, $event, EventPortalAccess::ROLE_PARTICIPANT);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<\App\Models\EventParticipant>
     */
    public function guardianParticipantsQuery(User $user, Event $event): \Illuminate\Database\Eloquent\Builder
    {
        $query = \App\Models\EventParticipant::query()
            ->where('event_id', $event->id)
            ->where('status', \App\Models\EventParticipant::STATUS_ACTIVE);

        $access = $this->accessFor($user, $event, EventPortalAccess::ROLE_GUARDIAN);
        $contractId = $access?->contract_id;

        if ($contractId && Schema::hasColumn('event_participants', 'contract_id')) {
            $scoped = (clone $query)->where('contract_id', $contractId);

            if ($scoped->exists()) {
                return $scoped->orderBy('last_name')->orderBy('first_name');
            }

            $contract = Contract::query()->whereKey($contractId)->first();
            $paymentId = $contract?->participant_payment_id;
            if ($paymentId && Schema::hasColumn('event_participants', 'participant_payment_id')) {
                $byPayment = (clone $query)->where('participant_payment_id', $paymentId);
                if ($byPayment->exists()) {
                    return $byPayment->orderBy('last_name')->orderBy('first_name');
                }
            }
        }

        return $query->orderBy('last_name')->orderBy('first_name');
    }

    public function userOwnsContractSchedule(User $user, Event $event, int $scheduleId): bool
    {
        if (! Schema::hasTable('contract_payment_schedules')) {
            return false;
        }

        $contractIds = collect([
            $this->accessibleContract($user, $event, EventPortalAccess::ROLE_PARTICIPANT)?->id,
            $this->accessibleContract($user, $event, EventPortalAccess::ROLE_GUARDIAN)?->id,
        ])->filter()->unique()->values()->all();

        if ($contractIds === []) {
            return false;
        }

        return \App\Models\ContractPaymentSchedule::query()
            ->whereKey($scheduleId)
            ->whereIn('contract_id', $contractIds)
            ->exists();
    }

    public function participantPayment(User $user, Event $event): ?EventSettlementParticipantPayment
    {
        if (! $this->isParticipant($user, $event)) {
            return null;
        }

        $access = $this->accessesFor($user, $event)
            ->first(fn (EventPortalAccess $row): bool => $row->isParticipant());

        if (! $access) {
            return null;
        }

        $contract = $this->accessibleContract($user, $event, EventPortalAccess::ROLE_PARTICIPANT);
        if ($contract?->participant_payment_id) {
            $fromContract = EventSettlementParticipantPayment::query()
                ->whereKey($contract->participant_payment_id)
                ->first();
            if ($fromContract) {
                return $fromContract;
            }
        }

        if ($access->event_participant_id && Schema::hasTable('event_participants')) {
            $participant = $access->eventParticipant;

            if ($participant?->participant_payment_id) {
                return EventSettlementParticipantPayment::query()
                    ->whereKey($participant->participant_payment_id)
                    ->first();
            }
        }

        return null;
    }

    public function accessDenialReason(User $user, Event $event): ?string
    {
        if ($this->canViewEvent($user, $event)) {
            return null;
        }

        if ($this->isOfficePreview($user)) {
            return null;
        }

        if ($this->shouldUseOfficeEventAccess($user)) {
            return 'Brak uprawnień biurowych do tej imprezy.';
        }

        if ($user->hasRole(['client_participant', 'client_guardian'])) {
            return 'Ta impreza nie została udostępniona na Twoim koncie. Poproś biuro o zaproszenie do portalu klienta.';
        }

        return 'Brak dostępu do tej imprezy.';
    }

    public function archiveMessage(Event $event): string
    {
        $expiresAt = $this->accessExpiresAt($event);
        $days = $this->fullAccessDaysAfterEnd();

        if ($expiresAt && $this->isArchived($event)) {
            return 'Pełny dostęp do tej wycieczki wygasł '.$expiresAt->format('d.m.Y')
                .' ('.$days.' dni po zakończeniu). Widoczne są tylko podstawowe informacje.';
        }

        return '';
    }

    public function accessUntilHint(Event $event): string
    {
        $expiresAt = $this->accessExpiresAt($event);

        if (! $expiresAt || $this->isArchived($event)) {
            return '';
        }

        return 'Dostęp do szczegółów do '.$expiresAt->format('d.m.Y').'.';
    }
}
