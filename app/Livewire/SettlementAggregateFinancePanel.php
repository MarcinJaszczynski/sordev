<?php

namespace App\Livewire;

use App\Filament\Forms\ProgramPointSettlementFinanceFields;
use App\Models\Event;
use App\Services\SettlementAggregateFinanceService;
use App\Services\SettlementFinanceFormSupport;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Illuminate\Support\HtmlString;
use Livewire\Component;

class SettlementAggregateFinancePanel extends Component implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    public int $eventId;

    /** @var 'transport'|'accommodation' */
    public string $aggregateType;

    public string $heading;

    public function mount(int $eventId, string $aggregateType, ?string $heading = null): void
    {
        $this->eventId = $eventId;
        $this->aggregateType = $aggregateType;
        $this->heading = $heading ?? match ($aggregateType) {
            'transport' => 'Finanse transportu',
            'accommodation' => 'Finanse noclegu',
            default => 'Finanse',
        };
    }

    public function render()
    {
        return view('livewire.settlement-aggregate-finance-panel');
    }

    /**
     * @return array{planned: string, paid: string, remaining: string, status: string}
     */
    public function summary(): array
    {
        return app(SettlementAggregateFinanceService::class)
            ->summary($this->event(), $this->aggregateType);
    }

    public function planAction(): Action
    {
        $service = app(SettlementAggregateFinanceService::class);
        $event = $this->event();

        return Action::make('plan')
            ->label('Plan')
            ->icon('heroicon-o-banknotes')
            ->color('warning')
            ->button()
            ->modalHeading($this->heading.' — plan')
            ->modalWidth('3xl')
            ->fillForm(fn (): array => $service->buildFormData($event, $this->aggregateType))
            ->form([
                Forms\Components\Section::make('Kwoty referencyjne')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Placeholder::make('reference_total')
                            ->label('Kalkulacja / kosztorys')
                            ->content(fn (Forms\Get $get): string => SettlementFinanceFormSupport::formatAmountLabel(
                                (float) ($get('event_point_total') ?? 0),
                                $get('settlement_planned_currency_id'),
                                true,
                            )),
                    ]),
                Forms\Components\Section::make('Plan rozliczenia')
                    ->columns(2)
                    ->schema(ProgramPointSettlementFinanceFields::planFields()),
            ])
            ->action(function (array $data) use ($service, $event): void {
                $summary = $service->persist($event, $this->aggregateType, $data, false);
                $this->notifySaved('Plan finansowy zapisany', $summary);
            })
            ->disabled(! $service->ensureBaseCost($event, $this->aggregateType));
    }

    public function advanceAction(): Action
    {
        $service = app(SettlementAggregateFinanceService::class);
        $event = $this->event();

        return Action::make('advance')
            ->label('Zaliczka')
            ->icon('heroicon-o-credit-card')
            ->color('gray')
            ->button()
            ->modalHeading($this->heading.' — zaliczka')
            ->modalWidth('3xl')
            ->fillForm(fn (): array => $service->buildFormData($event, $this->aggregateType))
            ->form([
                Forms\Components\Section::make('Kwoty referencyjne')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Placeholder::make('event_point_total_ref')
                            ->label('Kosztorys')
                            ->content(fn (Forms\Get $get): string => SettlementFinanceFormSupport::formatAmountLabel(
                                (float) ($get('event_point_total') ?? 0),
                                $get('settlement_planned_currency_id'),
                                (bool) ($get('settlement_planned_convert_to_pln') ?? true),
                            )),
                        Forms\Components\Placeholder::make('planned_amount_ref')
                            ->label('Planowana kwota')
                            ->content(fn (Forms\Get $get): string => SettlementFinanceFormSupport::formatAmountLabel(
                                (float) ($get('settlement_planned_amount') ?? 0),
                                $get('settlement_planned_currency_id'),
                                (bool) ($get('settlement_planned_convert_to_pln') ?? true),
                            )),
                    ]),
                Forms\Components\Section::make('Zaliczka i status')
                    ->columns(3)
                    ->schema(ProgramPointSettlementFinanceFields::advanceFields()),
                ...ProgramPointSettlementFinanceFields::advanceDocumentSection(),
            ])
            ->action(function (array $data) use ($service, $event): void {
                $summary = $service->persist($event, $this->aggregateType, $data, false);
                $this->notifySaved('Zaliczka zapisana', $summary);
            })
            ->disabled(! $service->ensureBaseCost($event, $this->aggregateType));
    }

    public function paymentsAction(): Action
    {
        $service = app(SettlementAggregateFinanceService::class);
        $event = $this->event();

        return Action::make('payments')
            ->label('Wpłaty')
            ->icon('heroicon-o-receipt-percent')
            ->color('success')
            ->button()
            ->modalHeading($this->heading.' — wpłaty')
            ->modalWidth('4xl')
            ->fillForm(fn (): array => $service->buildFormData($event, $this->aggregateType))
            ->form([
                Forms\Components\Section::make('Podsumowanie')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Placeholder::make('event_point_total_ref')
                            ->label('Kosztorys')
                            ->content(fn (Forms\Get $get): string => SettlementFinanceFormSupport::formatAmountLabel(
                                (float) ($get('event_point_total') ?? 0),
                                $get('settlement_planned_currency_id'),
                                (bool) ($get('settlement_planned_convert_to_pln') ?? true),
                            )),
                        Forms\Components\Placeholder::make('planned_amount_ref')
                            ->label('Planowana kwota')
                            ->content(fn (Forms\Get $get): string => SettlementFinanceFormSupport::formatAmountLabel(
                                (float) ($get('settlement_planned_amount') ?? 0),
                                $get('settlement_planned_currency_id'),
                                (bool) ($get('settlement_planned_convert_to_pln') ?? true),
                            )),
                        Forms\Components\Placeholder::make('payments_summary')
                            ->label('')
                            ->content(fn (Forms\Get $get): HtmlString => SettlementFinanceFormSupport::paymentsSummaryHtml($get))
                            ->columnSpanFull(),
                    ]),
                Forms\Components\Section::make('Wpłaty i dopłaty')
                    ->schema([
                        Forms\Components\Repeater::make('payment_entries')
                            ->label('')
                            ->addActionLabel('Dodaj wpis')
                            ->defaultItems(1)
                            ->live(debounce: 800)
                            ->reorderable()
                            ->schema(ProgramPointSettlementFinanceFields::paymentEntryFields())
                            ->columns(3)
                            ->columnSpanFull(),
                    ]),
            ])
            ->action(function (array $data) use ($service, $event): void {
                $summary = $service->persist($event, $this->aggregateType, $data, true);
                $this->notifySaved('Wpłaty zapisane', $summary);
            })
            ->disabled(! $service->ensureBaseCost($event, $this->aggregateType));
    }

    protected function event(): Event
    {
        return Event::query()->findOrFail($this->eventId);
    }

    /**
     * @param  array{
     *     planned_label: string,
     *     paid_label: string,
     *     advance_label: string,
     *     remaining_label: string
     * }  $summary
     */
    protected function notifySaved(string $title, array $summary): void
    {
        Notification::make()
            ->success()
            ->title($title)
            ->body(SettlementFinanceFormSupport::notificationBody($summary))
            ->send();

        $this->dispatch('event-price-table-refresh');
    }
}
