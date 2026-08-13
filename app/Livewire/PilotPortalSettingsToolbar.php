<?php

namespace App\Livewire;

use App\Filament\Pilot\Resources\PilotEventResource;
use App\Models\Event;
use App\Services\PilotOnboardingService;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;

class PilotPortalSettingsToolbar extends Component implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    public int $eventId;

    public function mount(int $eventId): void
    {
        $this->eventId = $eventId;
    }

    public function render()
    {
        return view('livewire.pilot-portal-settings-toolbar', [
            'event' => $this->event(),
        ]);
    }

    public function shareWithPilotAction(): Action
    {
        return Action::make('shareWithPilot')
            ->label('Udostępnij pilotowi')
            ->icon('heroicon-o-share')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Udostępnić imprezę pilotowi?')
            ->modalDescription('Pilot zobaczy wycieczkę w swoim panelu. E-mail wyślesz osobnym przyciskiem po udostępnieniu.')
            ->visible(fn (): bool => Schema::hasColumn('events', 'shared_with_pilot')
                && filled($this->event()->assigned_to)
                && ! $this->event()->shared_with_pilot)
            ->action(function (): void {
                $event = $this->event();
                $event->update([
                    'shared_with_pilot' => true,
                    'shared_with_pilot_at' => now(),
                    'shared_with_pilot_by' => Auth::id(),
                ]);

                Notification::make()
                    ->title('Impreza udostępniona pilotowi')
                    ->body('Wycieczka jest widoczna w panelu pilota. Kliknij «Wyślij e-mail do pilota», aby wysłać powiadomienie.')
                    ->success()
                    ->send();

                $this->dispatch('$refresh');
            });
    }

    public function sendPilotEmailAction(): Action
    {
        return Action::make('sendPilotEmail')
            ->label(fn (): string => $this->event()->pilot_trip_email_sent_at
                ? 'Wyślij ponownie e-mail'
                : 'Wyślij e-mail do pilota')
            ->icon('heroicon-o-envelope')
            ->color('primary')
            ->requiresConfirmation()
            ->modalHeading('Wysłać powiadomienie e-mail do pilota?')
            ->modalDescription('Pilot otrzyma wiadomość z informacją o wycieczce i linkiem do panelu.')
            ->visible(fn (): bool => Schema::hasColumn('events', 'shared_with_pilot')
                && (bool) $this->event()->shared_with_pilot
                && filled($this->event()->assigned_to))
            ->action(function (): void {
                $event = $this->event();
                $pilot = $event->assignedUser;

                if (! $pilot?->email) {
                    Notification::make()
                        ->title('Brak adresu e-mail pilota')
                        ->warning()
                        ->send();

                    return;
                }

                $sent = app(PilotOnboardingService::class)->sendTripSharedEmail($event, $pilot);

                if (! $sent) {
                    Notification::make()
                        ->title('Nie udało się wysłać e-maila')
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title('Wysłano powiadomienie e-mail')
                    ->body('Pilot otrzymał wiadomość o wycieczce.')
                    ->success()
                    ->send();

                $this->dispatch('$refresh');
            });
    }

    public function sharedStatusAction(): Action
    {
        return Action::make('sharedStatus')
            ->label(fn (): string => 'Udostępniona '.$this->event()->shared_with_pilot_at?->format('d.m.Y H:i'))
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Cofnąć udostępnienie pilotowi?')
            ->modalDescription('Pilot przestanie widzieć tę wycieczkę w swoim panelu. Możesz udostępnić ją ponownie w każdej chwili.')
            ->modalSubmitActionLabel('Cofnij udostępnienie')
            ->tooltip('Kliknij, aby cofnąć udostępnienie w panelu pilota.')
            ->visible(fn (): bool => Schema::hasColumn('events', 'shared_with_pilot')
                && (bool) $this->event()->shared_with_pilot)
            ->action(function (): void {
                $this->revokePilotSharing();
            });
    }

    public function revokePilotSharing(): void
    {
        $event = $this->event();
        $payload = [
            'shared_with_pilot' => false,
            'shared_with_pilot_at' => null,
            'shared_with_pilot_by' => null,
        ];

        if (Schema::hasColumn('events', 'pilot_trip_email_sent_at')) {
            $payload['pilot_trip_email_sent_at'] = null;
        }

        $event->update($payload);

        Notification::make()
            ->title('Cofnięto udostępnienie')
            ->body('Impreza nie jest już widoczna w panelu pilota.')
            ->success()
            ->send();

        $this->dispatch('$refresh');
    }

    public function toggleCurrencyExchangeAction(): Action
    {
        return Action::make('toggleCurrencyExchange')
            ->label('Portal: wymiana')
            ->icon('heroicon-o-arrows-right-left')
            ->color(fn (): string => $this->event()->showsPilotCurrencyExchange() ? 'success' : 'gray')
            ->outlined(fn (): bool => ! $this->event()->showsPilotCurrencyExchange())
            ->tooltip('Widoczność formularza wymiany waluty w portalu pilota i poniżej na tej stronie.')
            ->visible(fn (): bool => Schema::hasColumn('events', 'pilot_portal_show_currency_exchange'))
            ->action(function (): void {
                $event = $this->event();
                $event->update([
                    'pilot_portal_show_currency_exchange' => ! $event->showsPilotCurrencyExchange(),
                ]);

                Notification::make()
                    ->title('Zapisano widoczność wymiany w portalu')
                    ->success()
                    ->send();

                $this->dispatchPortalVisibilityUpdated();
            });
    }

    public function toggleBusCollectionsAction(): Action
    {
        return Action::make('toggleBusCollections')
            ->label('Portal: zbiórka')
            ->icon('heroicon-o-banknotes')
            ->color(fn (): string => $this->event()->showsPilotBusCollections() ? 'success' : 'gray')
            ->outlined(fn (): bool => ! $this->event()->showsPilotBusCollections())
            ->tooltip('Widoczność formularza zbiórki w autokarze w portalu pilota i poniżej na tej stronie.')
            ->visible(fn (): bool => Schema::hasColumn('events', 'pilot_portal_show_bus_collections'))
            ->action(function (): void {
                $event = $this->event();
                $event->update([
                    'pilot_portal_show_bus_collections' => ! $event->showsPilotBusCollections(),
                ]);

                Notification::make()
                    ->title('Zapisano widoczność zbiórki w portalu')
                    ->success()
                    ->send();

                $this->dispatchPortalVisibilityUpdated();
            });
    }

    public function assignPilotHintVisible(): bool
    {
        return Schema::hasColumn('events', 'shared_with_pilot')
            && blank($this->event()->assigned_to);
    }

    public function canPreviewPortal(): bool
    {
        return (bool) Auth::user()?->hasRole(['admin', 'super_admin', 'biuro']);
    }

    public function previewAsPilotVisible(): bool
    {
        return $this->canPreviewPortal() && filled($this->event()->assigned_to);
    }

    public function previewUrl(): string
    {
        $url = PilotEventResource::getUrl(
            'view',
            ['record' => $this->eventId],
            panel: 'pilot',
        ).'?preview=1';

        $pilotId = $this->event()->assigned_to;
        if (filled($pilotId)) {
            $url .= '&pilot='.(int) $pilotId;
        }

        return $url;
    }

    public function previewAsPilotLabel(): string
    {
        $name = $this->event()->assignedUser?->name;

        return filled($name)
            ? 'Podgląd jako '.$name
            : 'Podgląd jako ten pilot';
    }

    public function migrationHintVisible(): bool
    {
        return ! Schema::hasColumn('events', 'pilot_portal_show_currency_exchange')
            || ! Schema::hasColumn('events', 'pilot_portal_show_bus_collections');
    }

    protected function dispatchPortalVisibilityUpdated(): void
    {
        $this->dispatch('pilot-portal-visibility-updated', eventId: $this->eventId);
        $this->dispatch('$refresh');
    }

    protected function event(): Event
    {
        return Event::query()
            ->with(['assignedUser', 'sharedWithPilotByUser'])
            ->findOrFail($this->eventId);
    }
}
