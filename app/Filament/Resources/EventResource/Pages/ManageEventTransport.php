<?php

namespace App\Filament\Resources\EventResource\Pages;

use App\Actions\Events\RecalculateEventTotalsAction;
use App\Data\RecalculateEventTotalsData;
use App\Filament\Resources\EventResource;
use App\Filament\Resources\EventResource\Concerns\HasEventOperationsSubNavigation;
use App\Filament\Resources\EventResource\Concerns\HasEventWorkflowContext;
use App\Filament\Forms\EventProgramDayRouteFields;
use App\Models\Contractor;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Schema;

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
                ->visible(fn (): bool => blank($this->record->bus_id)
                    && blank($this->record->transport_contractor_id)
                    && blank($this->record->transport_company_name))
                ->content(new \Illuminate\Support\HtmlString(
                    '<div class="rounded-lg border border-dashed border-gray-300 bg-gray-50 px-4 py-6 text-center dark:border-gray-600 dark:bg-gray-800/50">'
                    .'<p class="text-sm font-medium text-gray-900 dark:text-gray-100">Brak przypisanego autokaru</p>'
                    .'<p class="mt-1 text-sm text-gray-500">Wybierz firmę transportową i autokar w sekcji „Przewoźnik i kierowca” poniżej.</p>'
                    .'</div>'
                ))
                ->columnSpanFull(),

            Forms\Components\Section::make('Koszty transportu')
                ->description('Planowana, kalkulacja i zapłacona kwota z rozliczenia imprezy.')
                ->schema([
                    Forms\Components\Placeholder::make('transport_finance_panel')
                        ->hiddenLabel()
                        ->content(fn (): \Illuminate\Support\HtmlString => new \Illuminate\Support\HtmlString(
                            Blade::render(
                                '@livewire(\'settlement-aggregate-finance-panel\', [\'eventId\' => '.$this->record->getKey().', \'aggregateType\' => \'transport\', \'heading\' => \'Finanse transportu\'], key(\'transport-finance-'.$this->record->getKey().'\'))'
                            )
                        ))
                        ->columnSpanFull(),

                    Forms\Components\Placeholder::make('transport_cost_summary')
                        ->label('')
                        ->content(function () {
                            $calculator = new \App\Services\EventTransportCostCalculator($this->record);
                            $variant = [
                                'qty' => max(1, (int) ($this->record->participant_count ?? 1)),
                                'gratis' => 0,
                                'staff' => 1,
                                'driver' => 1,
                            ];

                            return view('partials.event-transport-summary', [
                                'record' => $this->record,
                                'transportCost' => $calculator->effectiveTransportCost($variant),
                                'eventTransportKm' => $calculator->resolveTransportKm(),
                            ]);
                        }),
                ]),
            EventProgramDayRouteFields::section(),
            EventResource::carrierAndDriverSection(),
        ]);
    }

    public function getContentTabLabel(): ?string
    {
        return 'Transport';
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
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

        if (Schema::hasColumn('events', 'driver_pickup_info_sent_at')) {
            $sent = (bool) ($data['driver_pickup_info_sent'] ?? false);

            if ($sent && ! $this->record->driver_pickup_info_sent_at) {
                $data['driver_pickup_info_sent_at'] = now();
                $data['driver_pickup_info_sent_by'] = Auth::id();
            } elseif (! $sent) {
                $data['driver_pickup_info_sent_at'] = null;
                $data['driver_pickup_info_sent_by'] = null;
            }

            unset($data['driver_pickup_info_sent']);
        }

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
