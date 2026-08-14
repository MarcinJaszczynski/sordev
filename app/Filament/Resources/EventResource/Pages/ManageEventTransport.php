<?php

namespace App\Filament\Resources\EventResource\Pages;

use App\Actions\Events\RecalculateEventTotalsAction;
use App\Actions\Events\SendDriverPickupInfoAction;
use App\Data\RecalculateEventTotalsData;
use App\Filament\Resources\EventResource;
use App\Filament\Resources\EventResource\Concerns\HasEventOperationsSubNavigation;
use App\Filament\Resources\EventResource\Concerns\HasEventWorkflowContext;
use App\Filament\Forms\EventProgramDayRouteFields;
use App\Models\Contractor;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\HtmlString;

class ManageEventTransport extends EditRecord
{
    use HasEventOperationsSubNavigation;
    use HasEventWorkflowContext;

    protected static string $resource = EventResource::class;

    protected static string $view = 'filament.resources.event-resource.pages.manage-event-transport';

    protected static ?string $navigationLabel = 'Transport';

    protected static ?string $title = 'Transport';

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Placeholder::make('transport_empty_state')
                ->hiddenLabel()
                ->visible(function (Forms\Get $get): bool {
                    return blank($get('bus_id'))
                        && blank($get('transport_contractor_id'))
                        && blank($get('transport_company_name'))
                        && ! (bool) $get('use_manual_transport_cost');
                })
                ->content(new HtmlString(
                    '<div class="rounded-lg border border-dashed border-gray-300 bg-gray-50 px-4 py-6 text-center dark:border-gray-600 dark:bg-gray-800/50">'
                    .'<p class="text-sm font-medium text-gray-900 dark:text-gray-100">Brak przypisanego autokaru</p>'
                    .'<p class="mt-1 text-sm text-gray-500">Wybierz firmę transportową i autokar w sekcji „Przewoźnik i kierowca” poniżej — albo włącz ryczałt.</p>'
                    .'</div>'
                ))
                ->columnSpanFull(),

            EventProgramDayRouteFields::section(),
            EventResource::carrierAndDriverSection(),
        ]);
    }

    public function getContentTabLabel(): ?string
    {
        return null;
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Legacy: imprezy ze szablonem bez zapisanego początku programu.
        if (
            Schema::hasColumn('events', 'program_start_place_id')
            && blank($data['program_start_place_id'] ?? null)
            && $this->record->event_template_id
        ) {
            $data['program_start_place_id'] = $this->record->eventTemplate?->start_place_id;
        }

        if (Schema::hasColumn('events', 'driver_pickup_info_sent_at')) {
            $data['driver_pickup_info_sent'] = $this->record->isDriverPickupInfoSent();
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (Schema::hasColumn('events', 'transport_company_name')) {
            $contractorId = filled($data['transport_contractor_id'] ?? null)
                ? (int) $data['transport_contractor_id']
                : null;

            $data['transport_company_name'] = $contractorId
                ? Contractor::query()->whereKey($contractorId)->value('name')
                : null;
        }

        if (Schema::hasColumn('events', 'driver_contractor_id')) {
            $driverId = filled($data['driver_contractor_id'] ?? null)
                ? (int) $data['driver_contractor_id']
                : null;

            if ($driverId) {
                $driver = Contractor::query()->find($driverId);
                if (Schema::hasColumn('events', 'driver_name')) {
                    $data['driver_name'] = $driver?->name;
                }
                if (Schema::hasColumn('events', 'driver_phone')) {
                    $data['driver_phone'] = $driver?->phone;
                }
            }
        }

        if (Schema::hasColumn('events', 'driver_pickup_info_sent_at') && array_key_exists('driver_pickup_info_sent', $data)) {
            $sent = (bool) ($data['driver_pickup_info_sent'] ?? false);

            if ($sent && ! $this->record->driver_pickup_info_sent_at) {
                $data['driver_pickup_info_sent_at'] = now();
                $data['driver_pickup_info_sent_by'] = auth()->id();
            } elseif (! $sent) {
                $data['driver_pickup_info_sent_at'] = null;
                $data['driver_pickup_info_sent_by'] = null;
            }
        }

        unset($data['driver_pickup_info_sent'], $data['driver_contractor_search_all'], $data['transport_contractor_search_all']);

        return $data;
    }

    protected function afterSave(): void
    {
        try {
            $event = $this->record->fresh([
                'bus',
                'programPoints',
                'qtyVariants',
                'eventTemplate.markup',
                'eventTemplate.taxes',
                'markup',
            ]);

            if ($event) {
                app(RecalculateEventTotalsAction::class)(new RecalculateEventTotalsData(
                    event: $event,
                    participantCount: max(1, (int) ($event->participant_count ?? 1)),
                    startPlaceId: $event->start_place_id ? (int) $event->start_place_id : null,
                    persist: true,
                ));
                $this->record->refresh();
            }
        } catch (\Throwable $e) {
            // ignore recalculation failures after transport save
        }

        try {
            $this->record->refreshActiveSettlementCosts();
        } catch (\Throwable $e) {
            // ignore settlement refresh failures silently
        }

        $this->dispatch('event-price-table-refresh');
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Zapisano dane transportu';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('send_driver_info')
                ->label('Wyślij do kierowcy')
                ->icon('heroicon-o-paper-airplane')
                ->color('primary')
                ->visible(fn (): bool => Schema::hasColumn('events', 'driver_contractor_id')
                    || Schema::hasColumn('events', 'driver_pickup_info_sent_at'))
                ->modalHeading('Wyślij informację do kierowcy')
                ->modalDescription('Wiadomość pójdzie e-mailem i SMS-em (wg zaznaczenia). Możesz dopisać własne uwagi.')
                ->modalSubmitActionLabel('Wyślij')
                ->fillForm(fn (): array => [
                    'message_html' => SendDriverPickupInfoAction::defaultMessageHtml($this->record->fresh([
                        'driverContractor',
                        'startPlace',
                        'bus',
                        'transportContractor',
                    ]) ?? $this->record),
                    'send_email' => true,
                    'send_sms' => true,
                ])
                ->form([
                    Forms\Components\Toggle::make('send_email')
                        ->label('E-mail')
                        ->default(true),
                    Forms\Components\Toggle::make('send_sms')
                        ->label('SMS')
                        ->default(true),
                    \FilamentTiptapEditor\TiptapEditor::make('message_html')
                        ->label('Treść wiadomości')
                        ->required()
                        ->columnSpanFull(),
                ])
                ->action(function (array $data): void {
                    try {
                        $result = app(SendDriverPickupInfoAction::class)(
                            event: $this->record->fresh(['driverContractor']) ?? $this->record,
                            messageHtml: (string) ($data['message_html'] ?? ''),
                            sendEmail: (bool) ($data['send_email'] ?? false),
                            sendSms: (bool) ($data['send_sms'] ?? false),
                        );
                    } catch (\InvalidArgumentException $e) {
                        Notification::make()
                            ->title('Nie wysłano')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();

                        return;
                    }

                    $this->record->refresh();
                    $this->fillForm();

                    $channels = array_filter([
                        $result['email_sent'] ? 'e-mail' : null,
                        $result['sms_sent'] ? 'SMS' : null,
                    ]);

                    Notification::make()
                        ->title('Wysłano informację do kierowcy')
                        ->body('Kanały: '.implode(', ', $channels))
                        ->success()
                        ->send();
                }),

            Actions\Action::make('pdf_driver')
                ->label('Pakiet kierowcy')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->tooltip('Załączniki dodasz w zakładce „Dokumenty”: zaznacz pakiety PDF i status „Zaakceptowany”.')
                ->url(fn () => route('admin.events.pdf', ['event' => $this->record->id, 'audience' => 'driver']))
                ->openUrlInNewTab(),
        ];
    }
}
