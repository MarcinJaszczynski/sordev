<?php

namespace App\Filament\Resources\EventResource\Pages;

use App\Filament\Resources\EventResource;
use App\Filament\Resources\EventResource\Concerns\HasEventWorkflowContext;
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
    use HasEventWorkflowContext;

    protected static string $resource = EventResource::class;

    protected static ?string $navigationLabel = 'Transport';

    protected static ?string $title = 'Transport i kierowca';

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    public function form(Form $form): Form
    {
        return $form->schema([
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
            (new \App\Services\EventPriceCalculator)->calculateForEvent($this->record->fresh(['bus']));

            $fresh = $this->record->fresh(['bus']);
            if ($fresh) {
                $calc = \App\Services\EventCostCalculator::for($fresh)->calculate(
                    max(1, (int) ($fresh->participant_count ?? 1))
                );

                if (isset($calc['base_pln'])) {
                    $fresh->updateQuietly([
                        'total_cost' => round((float) $calc['base_pln'], 2),
                    ]);
                }
            }
        } catch (\Throwable $e) {
            report($e);
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
