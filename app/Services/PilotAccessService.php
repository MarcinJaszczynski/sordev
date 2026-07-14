<?php

namespace App\Services;

use App\Http\Middleware\PilotPreviewMiddleware;
use App\Models\Event;
use App\Models\User;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class PilotAccessService
{
    public function fullAccessDaysAfterEnd(): int
    {
        return max(0, (int) config('pilot.full_access_days_after_end', 14));
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

        if ($user?->hasRole(['admin', 'super_admin', 'biuro']) && ! $user->hasRole('pilot')) {
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

        return PilotPreviewMiddleware::isActive()
            && $user->hasRole(['admin', 'super_admin', 'biuro']);
    }

    /**
     * Imprezy widoczne na liście w panelu pilota (Moje wycieczki, widgety).
     */
    public function visibleTripsQuery(User $user): Builder
    {
        if ($this->isOfficePreview($user)) {
            return Event::query()->where('status', '!=', Event::STATUS_CANCELLED);
        }

        if ($user->hasRole('pilot')) {
            return Event::query()->forPilot($user);
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

        if ($panelId === 'pilot') {
            return false;
        }

        return $user->hasRole(['super_admin', 'admin', 'biuro', 'ksiegowosc'])
            && ! $user->hasRole('pilot');
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

        if ($panelId === 'pilot') {
            if ($this->isOfficePreview($user)) {
                return true;
            }

            return $user->hasRole('pilot');
        }

        if ($user->hasRole(['super_admin', 'admin', 'biuro', 'ksiegowosc']) && ! $user->hasRole('pilot')) {
            return true;
        }

        return $user->hasRole('pilot');
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
            return true;
        }

        if ($user->hasRole('pilot')) {
            $assigned = (int) $event->assigned_to === (int) $user->id;

            if (! Schema::hasColumn('events', 'shared_with_pilot')) {
                return $assigned;
            }

            return $assigned && (bool) $event->shared_with_pilot;
        }

        if ($user->hasRole(['admin', 'super_admin', 'biuro'])) {
            return true;
        }

        return false;
    }

    /**
     * Powód odmowy dostępu — do komunikatów 403 w panelu pilota.
     */
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

        if ($user->hasRole('pilot')) {
            $assigned = (int) $event->assigned_to === (int) $user->id;

            if (! $assigned) {
                return 'Ta impreza nie jest przypisana do Twojego konta pilota. Poproś biuro o przypisanie w zakładce Pilot imprezy.';
            }

            if (Schema::hasColumn('events', 'shared_with_pilot') && ! $event->shared_with_pilot) {
                return 'Impreza jest przypisana, ale biuro jeszcze nie kliknęło «Udostępnij pilotowi» w panelu admina. Bez tego nie zobaczysz szczegółów wycieczki.';
            }
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
