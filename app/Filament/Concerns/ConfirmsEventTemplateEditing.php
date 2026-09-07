<?php

namespace App\Filament\Concerns;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Edycja szablonu imprezy dla biura: najpierw podgląd, zapis dopiero po świadomym odblokowaniu.
 */
trait ConfirmsEventTemplateEditing
{
    public bool $templateEditingUnlocked = false;

    protected function bootTemplateEditingGate(): void
    {
        $this->templateEditingUnlocked = $this->resolveTemplateEditingUnlocked();
    }

    protected function resolveTemplateEditingUnlocked(): bool
    {
        if ($this->canEditTemplateWithoutConfirmation()) {
            return true;
        }

        $record = $this->getTemplateRecordForEditingGate();

        if (! $record) {
            return false;
        }

        return (bool) session($this->templateEditingSessionKey($record->getKey()));
    }

    protected function getTemplateRecordForEditingGate(): ?Model
    {
        return isset($this->record) && $this->record instanceof Model
            ? $this->record
            : null;
    }

    protected function templateEditingSessionKey(int|string $templateId): string
    {
        return 'event_template_edit_unlocked.'.$templateId;
    }

    protected function canEditTemplateWithoutConfirmation(): bool
    {
        $user = Auth::user();

        return (bool) ($user && $user->hasRole(['admin', 'super_admin']));
    }

    public function canMutateEventTemplateNow(): bool
    {
        if (! $this->userMayMutateEventTemplate()) {
            return false;
        }

        return $this->templateEditingUnlocked;
    }

    protected function userMayMutateEventTemplate(): bool
    {
        if (! static::requiresFullTemplateEdit()) {
            return static::userCanEditEventTemplateProgram()
                || static::userCanMutateEventTemplate();
        }

        return static::userCanMutateEventTemplate();
    }

    public function unlockTemplateEditing(): void
    {
        abort_unless($this->userMayMutateEventTemplate(), 403);

        $record = $this->getTemplateRecordForEditingGate();
        abort_unless($record !== null, 404);

        session([$this->templateEditingSessionKey($record->getKey()) => true]);
        $this->templateEditingUnlocked = true;

        Notification::make()
            ->title('Edycja szablonu włączona')
            ->body('Możesz zapisywać zmiany. Pamiętaj, że to globalny szablon imprezy.')
            ->warning()
            ->send();
    }

    public function lockTemplateEditing(): void
    {
        $record = $this->getTemplateRecordForEditingGate();

        if ($record) {
            session()->forget($this->templateEditingSessionKey($record->getKey()));
        }

        $this->templateEditingUnlocked = $this->canEditTemplateWithoutConfirmation();

        Notification::make()
            ->title('Edycja szablonu wyłączona')
            ->body('Szablon jest znowu w trybie podglądu.')
            ->success()
            ->send();
    }

    /**
     * @return array<int, Action>
     */
    protected function templateEditingHeaderActions(): array
    {
        if ($this->canEditTemplateWithoutConfirmation()) {
            return [];
        }

        if (! $this->userMayMutateEventTemplate()) {
            return [];
        }

        if ($this->templateEditingUnlocked) {
            return [
                Action::make('lock_template_editing')
                    ->label('Zakończ edycję')
                    ->icon('heroicon-o-lock-closed')
                    ->color('gray')
                    ->action(fn () => $this->lockTemplateEditing()),
            ];
        }

        return [
            Action::make('unlock_template_editing')
                ->label('Edytuj szablon')
                ->icon('heroicon-o-lock-open')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Włączyć edycję szablonu imprezy?')
                ->modalDescription('To globalny szablon. Zmiany wpływają na nowe imprezy z tego szablonu oraz na synchronizacje programu/cen. Potwierdź tylko wtedy, gdy świadomie chcesz edytować.')
                ->modalSubmitActionLabel('Tak, włącz edycję')
                ->action(fn () => $this->unlockTemplateEditing()),
        ];
    }

    protected function ensureTemplateEditingAllowed(): void
    {
        abort_unless($this->canMutateEventTemplateNow(), 403, 'Edycja szablonu wymaga potwierdzenia.');
    }
}
