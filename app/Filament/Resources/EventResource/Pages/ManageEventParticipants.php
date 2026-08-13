<?php

namespace App\Filament\Resources\EventResource\Pages;

use App\Filament\Actions\HelpArticleAction;
use App\Filament\Forms\ClientInvoiceRequestFormFields;
use App\Filament\Pages\ClientInvoiceRequestsInboxPage;
use App\Filament\Resources\EventResource;
use App\Support\ClientInvoiceRequestAdminHelper;
use Filament\Actions;
use Filament\Navigation\NavigationItem;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Schema;

class ManageEventParticipants extends ManageEventParticipantsSection
{
    protected static string $view = 'filament.resources.event-resource.pages.manage-event-participants';

    protected static ?string $navigationLabel = 'Uczestnicy';

    /** H1 = aktywna sekcja nested (primary: Uczestnicy). */
    protected static ?string $title = 'Lista';

    protected static ?string $navigationIcon = 'heroicon-o-users';

    public static function shouldRegisterNavigation(array $parameters = []): bool
    {
        return Schema::hasTable('event_participants');
    }

    /**
     * @param  array<string, mixed>  $urlParameters
     * @return array<NavigationItem>
     */
    public static function getNavigationItems(array $urlParameters = []): array
    {
        return [
            NavigationItem::make(static::getNavigationLabel())
                ->group(static::getNavigationGroup())
                ->parentItem(static::getNavigationParentItem())
                ->icon(static::getNavigationIcon())
                ->activeIcon(static::getActiveNavigationIcon())
                ->isActiveWhen(fn (): bool => collect(static::participantsRouteNames())
                    ->contains(fn (string $routeName): bool => request()->routeIs($routeName)))
                ->sort(static::getNavigationSort())
                ->badge(static::getNavigationBadge(), color: static::getNavigationBadgeColor())
                ->url(EventResource::getUrl('participants', $urlParameters)),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            HelpArticleAction::make('uczestnicy'),
            Actions\Action::make('create_invoice_request')
                ->label('Wniosek o fakturę')
                ->icon('heroicon-o-receipt-percent')
                ->color('primary')
                ->visible(fn (): bool => Schema::hasTable('client_invoice_requests'))
                ->modalHeading(fn (): string => 'Wniosek o fakturę — '.$this->currentEvent()->name)
                ->modalDescription('Formularz dla biura — tak jak klient wysyła z portalu. Po zapisie wniosek pojawi się w skrzynce wniosków o fakturę.')
                ->modalIcon('heroicon-o-receipt-percent')
                ->modalWidth('3xl')
                ->modalSubmitActionLabel('Zapisz wniosek')
                ->fillForm(fn (): array => ClientInvoiceRequestAdminHelper::prefillFromEvent($this->currentEvent()))
                ->form(fn (): array => ClientInvoiceRequestFormFields::adminModalSchema(
                    lockedEventId: (int) $this->currentEvent()->id,
                    event: $this->currentEvent(),
                ))
                ->action(function (array $data): void {
                    ClientInvoiceRequestAdminHelper::createFromAdminForm($data);

                    Notification::make()
                        ->title('Utworzono wniosek o fakturę')
                        ->body('Wniosek jest widoczny w skrzynce „Wnioski o fakturę”.')
                        ->actions([
                            Notification\Actions\Action::make('open_inbox')
                                ->label('Otwórz skrzynkę')
                                ->url(ClientInvoiceRequestsInboxPage::getUrl()),
                        ])
                        ->success()
                        ->send();
                }),
            Actions\Action::make('ops_lists')
                ->label('Listy operacyjne')
                ->icon('heroicon-o-clipboard-document-list')
                ->color('gray')
                ->tooltip('Eksport list: autokarowa, ubezpieczeniowa i pełny spis uczestników.')
                ->modalHeading('Listy z rejestru uczestników')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Zamknij')
                ->modalContent(fn (): \Illuminate\Support\HtmlString => new \Illuminate\Support\HtmlString(
                    '<div class="space-y-2 text-sm">'
                    .'<p>Eksport CSV z aktywnej listy uczestników (jedno źródło danych).</p>'
                    .'<ul class="list-disc space-y-1 pl-5">'
                    .'<li><a class="text-primary-600 underline" href="'.e(route('admin.events.participants.operational-lists', ['event' => $this->record, 'type' => 'bus'])).'">Lista autokarowa</a></li>'
                    .'<li><a class="text-primary-600 underline" href="'.e(route('admin.events.participants.operational-lists', ['event' => $this->record, 'type' => 'insurance'])).'">Lista ubezpieczeniowa</a></li>'
                    .'<li><a class="text-primary-600 underline" href="'.e(route('admin.events.participants.operational-lists', ['event' => $this->record, 'type' => 'roster'])).'">Pełny spis uczestników</a></li>'
                    .'</ul></div>'
                )),

            Actions\Action::make('insurance')
                ->label('Ubezpieczenie')
                ->icon('heroicon-o-shield-check')
                ->color('primary')
                ->tooltip('Polisa, gotowość i koszty NNW/KL — Operacje → Ubezpieczenia.')
                ->url(fn (): string => EventResource::getUrl('day-insurances', ['record' => $this->record])),
        ];
    }
}
