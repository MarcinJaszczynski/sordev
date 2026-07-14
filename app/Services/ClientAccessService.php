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

    public function visibleTripsQuery(User $user): Builder
    {
        if ($this->isOfficePreview($user)) {
            return Event::query()->where('status', '!=', Event::STATUS_CANCELLED);
        }

        if (! Schema::hasTable('event_portal_accesses')) {
            return Event::query()->whereRaw('1 = 0');
        }

        if ($user->hasRole(['client_participant', 'client_guardian'])) {
            return Event::query()
                ->where('status', '!=', Event::STATUS_CANCELLED)
                ->whereHas('portalAccesses', function (Builder $query) use ($user): void {
                    $query->active()->where('user_id', $user->id);
                });
        }

        if ($user->hasRole(['admin', 'super_admin', 'biuro'])) {
            return Event::query()->where('status', '!=', Event::STATUS_CANCELLED);
        }

        return Event::query()->whereRaw('1 = 0');
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
        if ($this->isOfficePreview($user)) {
            return $event->status !== Event::STATUS_CANCELLED;
        }

        if ($user->hasRole(['client_participant', 'client_guardian'])) {
            return $this->accessFor($user, $event) !== null;
        }

        if ($user->hasRole(['admin', 'super_admin', 'biuro'])) {
            return true;
        }

        return false;
    }

    public function accessFor(User $user, Event $event, ?string $role = null): ?EventPortalAccess
    {
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

        if ($access->event_participant_id && Schema::hasTable('event_participants')) {
            $participant = $access->eventParticipant;

            if ($participant?->participant_payment_id) {
                return EventSettlementParticipantPayment::query()
                    ->whereKey($participant->participant_payment_id)
                    ->first();
            }
        }

        $contract = $this->accessibleContract($user, $event);

        if ($contract?->participant_payment_id) {
            return EventSettlementParticipantPayment::query()
                ->whereKey($contract->participant_payment_id)
                ->first();
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

        if ($expiresAt) {
            return 'Pełny dostęp do wycieczki do '.$expiresAt->format('d.m.Y').'.';
        }

        return '';
    }
}
