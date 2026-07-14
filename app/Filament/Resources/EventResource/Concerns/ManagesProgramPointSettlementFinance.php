<?php

namespace App\Filament\Resources\EventResource\Concerns;

use App\Filament\Forms\ProgramPointSettlementFinanceFields;
use App\Models\Currency;
use App\Models\Event;
use App\Models\EventProgramPoint;
use App\Models\EventSettlement;
use App\Models\EventSettlementCost;
use App\Services\ProgramPointPricingCalculator;
use App\Services\ProgramPointSettlementDocumentSync;
use App\Services\SettlementFinanceFormSupport;
use App\Support\CurrencyAmountDisplay;
use Filament\Actions\StaticAction;
use Filament\Forms;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

trait ManagesProgramPointSettlementFinance
{
    private const PROGRAM_POINT_PAYMENT_SOURCE_TYPE = 'program_point_payment';

    protected function settlementOwnerEvent(): Event
    {
        $owner = $this->getOwnerRecord();

        return $owner instanceof Event ? $owner : throw new \LogicException('Settlement finance requires an Event owner record.');
    }

    protected function applySettlementFinanceModalSubmitSync(Tables\Actions\Action $action): Tables\Actions\Action
    {
        return $action->modalSubmitAction(
            fn (StaticAction $action): StaticAction => $action->extraAttributes([
                'x-on:mousedown' => 'if (document.activeElement?.blur) document.activeElement.blur()',
            ]),
        );
    }

    protected function updateProgramPointPaidBy(EventProgramPoint $record, ?string $paidBy): void
    {
        $paidBy = in_array($paidBy, ['office', 'pilot'], true) ? $paidBy : 'office';

        $settlement = EventSettlement::findOrCreateActiveForEvent($this->settlementOwnerEvent());
        $cost = $settlement->upsertCostFromProgramPoint(
            $record->loadMissing('templatePoint', 'currency', 'event', 'reservations'),
        );
        $cost->update(['paid_by' => $paidBy]);

        $settlement->costs()
            ->where('source_type', self::PROGRAM_POINT_PAYMENT_SOURCE_TYPE)
            ->where('source_id', $record->id)
            ->update(['paid_by' => $paidBy]);

        $settlement->recalculateTotals();
    }

    /**
     * @return array<int, Tables\Actions\Action>
     */
    protected function programPointFinanceTableActions(bool $includePricingBreakdown = true): array
    {
        return [
            $this->applySettlementFinanceModalSubmitSync(
                Tables\Actions\Action::make('settle_point_plan')
                ->label('Plan')
                ->icon('heroicon-o-banknotes')
                ->color('warning')
                ->button()
                ->extraAttributes(['class' => 'epp-finance-action'])
                ->modalHeading(fn (EventProgramPoint $record): string => 'Finanse (plan): '.($record->name ?: $record->templatePoint?->name ?? ('Punkt #'.$record->id)))
                ->modalDescription('Kalkulacja i plan rozliczenia.')
                ->modalWidth('3xl')
                ->fillForm(fn (EventProgramPoint $record): array => $this->buildSettlePointFormData($record))
                ->form(function (EventProgramPoint $record) use ($includePricingBreakdown): array {
                    $sections = [];

                    if ($includePricingBreakdown) {
                        $event = $this->settlementOwnerEvent();
                        $participantCount = max(1, (int) ($event->participant_count ?? 1));
                        $breakdown = ProgramPointPricingCalculator::breakdownForEventPoint($record, $participantCount);

                        $sections[] = Forms\Components\Section::make('Wycena punktu')
                            ->description('Algorytm jak w bibliotece szablonu.')
                            ->columns(2)
                            ->schema([
                                Forms\Components\Placeholder::make('pricing_breakdown_display')
                                    ->label('Wyliczenie')
                                    ->content(ProgramPointPricingCalculator::describeBreakdown($breakdown))
                                    ->extraAttributes(['class' => 'epp-pricing-preview whitespace-pre-line'])
                                    ->columnSpanFull(),
                                Forms\Components\Placeholder::make('calculation_total_display')
                                    ->label('Kalkulacja (szablon)')
                                    ->content($record->formatAmount((float) ($record->calculated_price ?? 0))),
                                Forms\Components\Placeholder::make('event_point_total_display')
                                    ->label('Suma imprezy')
                                    ->content($record->formatAmount($record->resolveEffectiveTotalPrice($participantCount))),
                            ]);
                    } else {
                        $event = $this->settlementOwnerEvent();
                        $participantCount = max(1, (int) ($event->participant_count ?? 1));

                        $sections[] = Forms\Components\Section::make('Kwoty referencyjne')
                            ->columns(2)
                            ->schema([
                                Forms\Components\Placeholder::make('event_point_total_display')
                                    ->label('Kosztorys')
                                    ->content($record->formatAmount($record->resolveEffectiveTotalPrice($participantCount))),
                            ]);
                    }

                    $sections[] = Forms\Components\Section::make('Plan rozliczenia')
                        ->columns(2)
                        ->schema(ProgramPointSettlementFinanceFields::planFields());

                    return $sections;
                })
                ->action(function (array $data, EventProgramPoint $record): void {
                    $summary = $this->persistSettlePointFinance($record, $data, false);

                    \Filament\Notifications\Notification::make()
                        ->success()
                        ->title('Plan finansowy zapisany')
                        ->body(SettlementFinanceFormSupport::notificationBody($summary))
                        ->send();
                })
                ->visible(fn (EventProgramPoint $record): bool => (bool) $record->active)
            ),

            $this->applySettlementFinanceModalSubmitSync(
                Tables\Actions\Action::make('settle_point_advance')
                ->label('Zaliczka')
                ->icon('heroicon-o-credit-card')
                ->color('gray')
                ->button()
                ->extraAttributes(['class' => 'epp-finance-action'])
                ->modalHeading(fn (EventProgramPoint $record): string => 'Finanse (zaliczka): '.($record->name ?: $record->templatePoint?->name ?? ('Punkt #'.$record->id)))
                ->modalWidth('3xl')
                ->fillForm(fn (EventProgramPoint $record): array => $this->buildSettlePointFormData($record))
                ->form([
                    Forms\Components\Section::make('Kwoty referencyjne')
                        ->columns(2)
                        ->schema([
                            Forms\Components\Placeholder::make('event_point_total_ref')
                                ->label('Kosztorys punktu')
                                ->content(fn (Forms\Get $get): string => $this->formatFormAmountLabel(
                                    (float) ($get('event_point_total') ?? 0),
                                    $get('settlement_planned_currency_id'),
                                    (bool) ($get('settlement_planned_convert_to_pln') ?? true),
                                )),
                            Forms\Components\Placeholder::make('planned_amount_ref')
                                ->label('Planowana kwota')
                                ->content(fn (Forms\Get $get): string => $this->formatFormAmountLabel(
                                    (float) ($get('settlement_planned_amount') ?? 0),
                                    $get('settlement_planned_currency_id'),
                                    (bool) ($get('settlement_planned_convert_to_pln') ?? true),
                                )),
                        ]),
                    Forms\Components\Section::make('Zaliczki')
                        ->schema([
                            Forms\Components\Repeater::make('advance_entries')
                                ->label('')
                                ->addActionLabel('Dodaj zaliczkę')
                                ->defaultItems(0)
                                ->reorderable()
                                ->schema(ProgramPointSettlementFinanceFields::advanceEntryFields())
                                ->columns(3)
                                ->columnSpanFull(),
                        ]),
                ])
                ->action(function (array $data, EventProgramPoint $record): void {
                    $summary = $this->persistSettlePointFinance($record, $data, syncPaymentEntries: false, syncAdvanceEntries: true);

                    \Filament\Notifications\Notification::make()
                        ->success()
                        ->title('Zaliczka zapisana')
                        ->body(SettlementFinanceFormSupport::notificationBody($summary))
                        ->send();
                })
                ->visible(fn (EventProgramPoint $record): bool => (bool) $record->active)
            ),

            $this->applySettlementFinanceModalSubmitSync(
                Tables\Actions\Action::make('settle_point_payments')
                ->label('Wpłaty')
                ->icon('heroicon-o-receipt-percent')
                ->color('success')
                ->button()
                ->extraAttributes(['class' => 'epp-finance-action'])
                ->modalHeading(fn (EventProgramPoint $record): string => 'Finanse (wpłaty): '.($record->name ?: $record->templatePoint?->name ?? ('Punkt #'.$record->id)))
                ->modalWidth('4xl')
                ->fillForm(fn (EventProgramPoint $record): array => $this->buildSettlePointFormData($record))
                ->form([
                    Forms\Components\Section::make('Podsumowanie')
                        ->columns(2)
                        ->schema([
                            Forms\Components\Placeholder::make('event_point_total_ref')
                                ->label('Kosztorys punktu')
                                ->content(fn (Forms\Get $get): string => $this->formatFormAmountLabel(
                                    (float) ($get('event_point_total') ?? 0),
                                    $get('settlement_planned_currency_id'),
                                    (bool) ($get('settlement_planned_convert_to_pln') ?? true),
                                )),
                            Forms\Components\Placeholder::make('planned_amount_ref')
                                ->label('Planowana kwota')
                                ->content(fn (Forms\Get $get): string => $this->formatFormAmountLabel(
                                    (float) ($get('settlement_planned_amount') ?? 0),
                                    $get('settlement_planned_currency_id'),
                                    (bool) ($get('settlement_planned_convert_to_pln') ?? true),
                                )),
                            Forms\Components\Placeholder::make('payments_summary')
                                ->label('')
                                ->content(fn (Forms\Get $get): HtmlString => $this->buildFinancePaymentsSummary($get))
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
                ->action(function (array $data, EventProgramPoint $record): void {
                    $summary = $this->persistSettlePointFinance($record, $data, syncPaymentEntries: true);

                    \Filament\Notifications\Notification::make()
                        ->success()
                        ->title('Wpłaty zapisane')
                        ->body(SettlementFinanceFormSupport::notificationBody($summary))
                        ->send();
                })
                ->visible(fn (EventProgramPoint $record): bool => (bool) $record->active)
            ),
        ];
    }

    /**
     * @return array{unit_price: float, quantity: int, total: float}
     */
    protected function resolveCalculationPricingForPoint(EventProgramPoint $record, int $participantCount): array
    {
        $quantity = $record->resolveCalculatedQuantity($participantCount);
        $total = (float) ($record->calculated_price ?? 0);

        if ($total <= 0 && $record->templatePoint) {
            $unit = (float) ($record->templatePoint->unit_price ?? 0);
            $total = round($unit * $quantity, 2);
            $unitPrice = $unit;
        } else {
            $unitPrice = $quantity > 0 ? round($total / $quantity, 2) : (float) ($record->unit_price ?? 0);
        }

        if ($total <= 0) {
            $total = $record->resolveEffectiveTotalPrice($participantCount);
            $unitPrice = (float) ($record->unit_price ?? 0);
        }

        return [
            'unit_price' => $unitPrice,
            'quantity' => $quantity,
            'total' => round($total, 2),
        ];
    }

    protected function buildFinancePaymentsSummary(Forms\Get $get): HtmlString
    {
        return SettlementFinanceFormSupport::paymentsSummaryHtml($get);
    }

    protected function formatFormAmountLabel(float $amount, mixed $currencyId, bool $convertToPln): string
    {
        return SettlementFinanceFormSupport::formatAmountLabel($amount, $currencyId, $convertToPln);
    }

    /**
     * @param  array{
     *     planned_label: string,
     *     paid_label: string,
     *     advance_label: string,
     *     remaining_label: string
     * }  $summary
     */
    protected function formatFinanceSummaryNotification(array $summary): string
    {
        return SettlementFinanceFormSupport::notificationBody($summary);
    }

    protected function isAdvancePaymentRow(EventSettlementCost $payment): bool
    {
        return in_array((string) $payment->advance_type, ['advance', 'deposit'], true)
            || (float) ($payment->advance_amount ?? 0) > 0;
    }

    protected function buildSettlePointFormData(EventProgramPoint $record): array
    {
        $event = $this->settlementOwnerEvent();
        $participantCount = max(1, (int) ($event->participant_count ?? 1));
        $calculation = $this->resolveCalculationPricingForPoint($record, $participantCount);
        $plannedTotal = $record->resolveEffectiveTotalPrice($participantCount);
        $documentSync = app(ProgramPointSettlementDocumentSync::class);

        $existingCost = $event->settlements()
            ->whereIn('status', ['draft', 'active', 'pilot_settled'])
            ->latest('id')
            ->first()?->costs()
            ->where('source_type', 'program_point')
            ->where('source_id', $record->id)
            ->first();

        $convertToPln = (bool) ($existingCost?->planned_convert_to_pln ?? $record->convert_to_pln ?? true);
        $plannedCurrencyId = $existingCost?->planned_currency_id ?? $record->currency_id;
        $plannedRate = (float) ($existingCost?->planned_rate ?? $record->currency?->exchange_rate ?? 1);
        $plannedAmount = (float) ($existingCost?->planned_amount ?? $plannedTotal);
        $plannedAmountPln = $existingCost?->planned_amount_pln;

        if ($plannedAmountPln === null && $convertToPln) {
            $plannedAmountPln = ProgramPointSettlementFinanceFields::isForeignCurrency($plannedCurrencyId)
                ? round($plannedAmount * $plannedRate, 2)
                : round($plannedAmount, 2);
        }

        $paymentRowsCollection = $event->activeSettlement?->costs()
            ->where('source_type', self::PROGRAM_POINT_PAYMENT_SOURCE_TYPE)
            ->where('source_id', $record->id)
            ->orderBy('paid_at')
            ->orderBy('id')
            ->get() ?? collect();

        $advanceRowsCollection = $paymentRowsCollection
            ->filter(fn (EventSettlementCost $payment): bool => $this->isAdvancePaymentRow($payment));

        $advanceEntries = $advanceRowsCollection
            ->map(function (EventSettlementCost $payment) use ($documentSync) {
                $documentData = $documentSync->loadDocumentDataForCost($payment);

                return [
                    'id' => $payment->id,
                    'due_date' => $payment->advance_due_date,
                    'advance_amount' => (float) ($payment->advance_amount ?? 0) > 0
                        ? (float) $payment->advance_amount
                        : null,
                    'currency_id' => $payment->planned_currency_id,
                    'paid_at' => $payment->paid_at,
                    'notes' => $payment->notes,
                    'document_id' => $documentData['document_id'],
                    'document_type' => $documentData['document_type'],
                    'document_number' => $documentData['document_number'],
                    'document_files' => $documentData['document_files'],
                ];
            })
            ->values()
            ->all();

        $paymentRows = $paymentRowsCollection
            ->reject(fn (EventSettlementCost $payment): bool => $this->isAdvancePaymentRow($payment))
            ->map(function (EventSettlementCost $payment) use ($documentSync) {
                $documentData = $documentSync->loadDocumentDataForCost($payment);

                return [
                    'paid_by' => $payment->paid_by ?: 'office',
                    'due_date' => $payment->advance_due_date,
                    'actual_amount' => filled($payment->actual_amount) ? (float) $payment->actual_amount : null,
                    'actual_currency_id' => $payment->actual_currency_id,
                    'actual_rate' => (float) ($payment->actual_rate ?? 1),
                    'actual_amount_pln' => filled($payment->actual_amount_pln) ? (float) $payment->actual_amount_pln : null,
                    'payment_method' => $payment->payment_method,
                    'document_id' => $documentData['document_id'],
                    'document_type' => $documentData['document_type'],
                    'document_number' => $documentData['document_number'],
                    'document_files' => $documentData['document_files'],
                    'paid_at' => $payment->paid_at,
                    'notes' => $payment->notes,
                ];
            })
            ->values()
            ->all();

        if ($paymentRows === []) {
            $paymentRows = [[
                'paid_by' => $existingCost?->paid_by ?? 'office',
                'due_date' => null,
                'actual_amount' => null,
                'actual_currency_id' => $plannedCurrencyId,
                'actual_rate' => $plannedRate,
                'actual_amount_pln' => null,
                'payment_method' => null,
                'document_id' => null,
                'document_type' => null,
                'document_number' => null,
                'document_files' => [],
                'paid_at' => null,
                'notes' => null,
            ]];
        }

        return [
            'calculation_unit_price' => $calculation['unit_price'],
            'calculation_quantity' => $calculation['quantity'],
            'calculation_total' => $calculation['total'],
            'event_point_total' => $plannedTotal,
            'event_point_unit_price' => (float) ($record->unit_price ?? 0),
            'event_point_quantity' => (int) ($record->quantity ?? 1),
            'settlement_planned_amount' => $plannedAmount,
            'settlement_planned_currency_id' => $plannedCurrencyId,
            'settlement_planned_convert_to_pln' => $convertToPln,
            'settlement_planned_rate' => $plannedRate,
            'settlement_planned_amount_pln' => $plannedAmountPln,
            'settlement_paid_by' => $existingCost?->paid_by ?? 'office',
            'settlement_payment_due_date' => $existingCost?->advance_due_date,
            'settlement_payment_method' => $existingCost?->payment_method,
            'settlement_notes' => $existingCost?->notes,
            'point_paid_price' => (float) ($record->paid_price ?? 0),
            'advance_entries' => $advanceEntries,
            'payment_entries' => $paymentRows,
        ];
    }

    /**
     * @return array{
     *     planned_pln: ?float,
     *     paid_pln: float,
     *     advance_pln: float,
     *     remaining_pln: ?float,
     *     planned_label: string,
     *     paid_label: string,
     *     advance_label: string,
     *     remaining_label: string
     * }
     */
    protected function persistSettlePointFinance(
        EventProgramPoint $record,
        array $data,
        bool $syncPaymentEntries = false,
        bool $syncAdvanceEntries = false,
    ): array {
        $event = $this->settlementOwnerEvent();
        $settlement = EventSettlement::findOrCreateActiveForEvent($event);
        $cost = $settlement->upsertCostFromProgramPoint($record->loadMissing('templatePoint', 'currency'));
        $settlementContractorId = filled($record->contractor_id)
            ? (int) $record->contractor_id
            : ($cost->contractor_id ? (int) $cost->contractor_id : null);
        $documentSync = app(ProgramPointSettlementDocumentSync::class);

        $plannedRate = (float) ($data['settlement_planned_rate'] ?? $cost->planned_rate ?? 1);
        $plannedAmount = (float) ($data['settlement_planned_amount'] ?? $cost->planned_amount ?? 0);
        $plannedConvertToPln = (bool) ($data['settlement_planned_convert_to_pln'] ?? $cost->planned_convert_to_pln ?? $record->convert_to_pln ?? true);
        $plannedCurrencyId = $data['settlement_planned_currency_id'] ?? $cost->planned_currency_id;

        $plannedAmountPln = array_key_exists('settlement_planned_amount_pln', $data)
            ? ($data['settlement_planned_amount_pln'] === null || $data['settlement_planned_amount_pln'] === '' ? null : (float) $data['settlement_planned_amount_pln'])
            : (
                ! ProgramPointSettlementFinanceFields::isForeignCurrency($plannedCurrencyId)
                    ? round($plannedAmount, 2)
                    : ($plannedConvertToPln ? round($plannedAmount * $plannedRate, 2) : null)
            );

        if ($plannedAmountPln === null && $plannedConvertToPln && ! ProgramPointSettlementFinanceFields::isForeignCurrency($plannedCurrencyId)) {
            $plannedAmountPln = round($plannedAmount, 2);
        }

        $costUpdate = [
            'planned_amount' => $plannedAmount,
            'planned_currency_id' => $plannedCurrencyId,
            'planned_convert_to_pln' => $plannedConvertToPln,
            'planned_rate' => $plannedRate,
            'planned_amount_pln' => $plannedAmountPln,
            'contractor_id' => $settlementContractorId,
            'paid_by' => $data['settlement_paid_by'] ?? $cost->paid_by,
            'payment_status' => $cost->payment_status,
            'payment_method' => $data['settlement_payment_method'] ?? $cost->payment_method,
            'notes' => $data['settlement_notes'] ?? $cost->notes,
            'advance_due_date' => array_key_exists('settlement_payment_due_date', $data)
                ? $data['settlement_payment_due_date']
                : $cost->advance_due_date,
        ];

        if (array_key_exists('settlement_advance_type', $data)) {
            $costUpdate['advance_type'] = $data['settlement_advance_type'];
        }

        $cost->update($costUpdate);

        $totalAdvanceAmount = (float) ($cost->advance_amount ?? 0);
        $nearestAdvanceDueDate = $cost->advance_due_date;

        if ($syncAdvanceEntries) {
            $advanceEntries = collect($data['advance_entries'] ?? [])
                ->filter(function (array $entry): bool {
                    return (float) ($entry['advance_amount'] ?? 0) > 0
                        || filled($entry['due_date'] ?? null)
                        || filled($entry['paid_at'] ?? null)
                        || filled($entry['notes'] ?? null)
                        || filled($entry['document_number'] ?? null)
                        || filled($entry['document_type'] ?? null)
                        || (is_array($entry['document_files'] ?? null) && $entry['document_files'] !== []);
                })
                ->values();

            $existingAdvanceIds = $settlement->costs()
                ->where('source_type', self::PROGRAM_POINT_PAYMENT_SOURCE_TYPE)
                ->where('source_id', $record->id)
                ->where(function (Builder $query): void {
                    $query->whereIn('advance_type', ['advance', 'deposit'])
                        ->orWhere('advance_amount', '>', 0);
                })
                ->pluck('id')
                ->map(fn ($id): int => (int) $id);

            $submittedIds = $advanceEntries
                ->pluck('id')
                ->filter()
                ->map(fn ($id): int => (int) $id);

            $idsToDelete = $existingAdvanceIds->diff($submittedIds);

            if ($idsToDelete->isNotEmpty()) {
                $settlement->costs()->whereIn('id', $idsToDelete->all())->delete();
            }

            $baseName = $cost->name ?: ($record->name ?? ('Punkt #'.$record->id));
            $advancePaidBy = $data['settlement_paid_by'] ?? $cost->paid_by ?? 'office';

            foreach ($advanceEntries as $index => $entry) {
                $advanceAmount = (float) ($entry['advance_amount'] ?? 0);
                $currencyId = $entry['currency_id'] ?? $plannedCurrencyId;
                $currencyRate = (float) (Currency::find($currencyId)?->exchange_rate ?? $plannedRate);
                $paidAt = $entry['paid_at'] ?? null;
                $isPaid = filled($paidAt);
                $actualAmount = $isPaid && $advanceAmount > 0 ? $advanceAmount : null;
                $actualCurrencyId = $actualAmount !== null ? $currencyId : null;
                $actualRate = $actualAmount !== null ? $currencyRate : null;
                $actualAmountPln = null;

                if ($actualAmount !== null) {
                    $actualAmountPln = ProgramPointSettlementFinanceFields::isForeignCurrency($currencyId)
                        ? round($actualAmount * $currencyRate, 2)
                        : round($actualAmount, 2);
                }

                $advancePayload = [
                    'source_type' => self::PROGRAM_POINT_PAYMENT_SOURCE_TYPE,
                    'source_id' => $record->id,
                    'name' => $baseName.' • zaliczka #'.($index + 1),
                    'planned_amount' => 0,
                    'planned_currency_id' => $currencyId,
                    'planned_convert_to_pln' => $plannedConvertToPln,
                    'planned_rate' => $currencyRate,
                    'planned_amount_pln' => 0,
                    'actual_amount' => $actualAmount,
                    'actual_currency_id' => $actualCurrencyId,
                    'actual_rate' => $actualRate,
                    'actual_amount_pln' => $actualAmountPln,
                    'paid_by' => $advancePaidBy,
                    'advance_type' => 'advance',
                    'payment_method' => null,
                    'document_number' => $entry['document_number'] ?? null,
                    'paid_at' => $paidAt,
                    'payment_status' => $isPaid ? 'advance_paid' : 'advance_required',
                    'advance_due_date' => $entry['due_date'] ?? null,
                    'advance_amount' => $advanceAmount > 0 ? $advanceAmount : null,
                    'notes' => $entry['notes'] ?? null,
                    'contractor_id' => $settlementContractorId,
                    'order' => (int) ($cost->order ?? 0) + $index + 1,
                ];

                $existingId = filled($entry['id'] ?? null) ? (int) $entry['id'] : null;
                $existingAdvance = $existingId
                    ? $settlement->costs()
                        ->where('source_type', self::PROGRAM_POINT_PAYMENT_SOURCE_TYPE)
                        ->where('source_id', $record->id)
                        ->where('id', $existingId)
                        ->first()
                    : null;

                if ($existingAdvance) {
                    $existingAdvance->update($advancePayload);
                    $advanceCost = $existingAdvance->fresh();
                } else {
                    $advanceCost = $settlement->costs()->create($advancePayload);
                }

                $documentSync->syncForCost($settlement, $advanceCost, $entry);
            }

            $syncedAdvanceRows = $settlement->costs()
                ->where('source_type', self::PROGRAM_POINT_PAYMENT_SOURCE_TYPE)
                ->where('source_id', $record->id)
                ->where(function (Builder $query): void {
                    $query->whereIn('advance_type', ['advance', 'deposit'])
                        ->orWhere('advance_amount', '>', 0);
                })
                ->get();

            $totalAdvanceAmount = (float) $syncedAdvanceRows->sum(fn (EventSettlementCost $row) => (float) ($row->advance_amount ?? 0));
            $nearestAdvanceDueDate = $syncedAdvanceRows
                ->filter(fn (EventSettlementCost $row): bool => ! filled($row->paid_at) && filled($row->advance_due_date))
                ->sortBy('advance_due_date')
                ->first()?->advance_due_date;
        }

        $entries = collect($data['payment_entries'] ?? []);

        if ($syncPaymentEntries) {
            $entries = $entries
                ->filter(function (array $entry): bool {
                    return filled($entry['actual_amount'] ?? null)
                        || filled($entry['due_date'] ?? null)
                        || filled($entry['paid_at'] ?? null)
                        || filled($entry['document_number'] ?? null)
                        || filled($entry['document_type'] ?? null)
                        || (is_array($entry['document_files'] ?? null) && $entry['document_files'] !== []);
                })
                ->values();

            $settlement->costs()
                ->where('source_type', self::PROGRAM_POINT_PAYMENT_SOURCE_TYPE)
                ->where('source_id', $record->id)
                ->where(function (Builder $query): void {
                    $query->whereNotIn('advance_type', ['advance', 'deposit'])
                        ->orWhereNull('advance_type');
                })
                ->delete();

            $baseName = $cost->name ?: ($record->name ?? ('Punkt #'.$record->id));

            foreach ($entries as $index => $entry) {
                $actualAmount = filled($entry['actual_amount'] ?? null) ? (float) $entry['actual_amount'] : null;
                $actualCurrencyId = $entry['actual_currency_id'] ?? $plannedCurrencyId;
                $actualRate = (float) ($entry['actual_rate'] ?? $plannedRate ?? 1);
                $actualAmountPln = filled($entry['actual_amount_pln'] ?? null)
                    ? (float) $entry['actual_amount_pln']
                    : ($actualAmount !== null
                        ? (ProgramPointSettlementFinanceFields::isForeignCurrency($actualCurrencyId)
                            ? round($actualAmount * max(0, $actualRate), 2)
                            : round($actualAmount, 2))
                        : null);

                $paymentCost = $settlement->costs()->create([
                    'source_type' => self::PROGRAM_POINT_PAYMENT_SOURCE_TYPE,
                    'source_id' => $record->id,
                    'name' => $baseName.' • wpłata #'.($index + 1),
                    'planned_amount' => 0,
                    'planned_currency_id' => $plannedCurrencyId,
                    'planned_convert_to_pln' => $plannedConvertToPln,
                    'planned_rate' => $plannedRate,
                    'planned_amount_pln' => 0,
                    'actual_amount' => $actualAmount,
                    'actual_currency_id' => $actualCurrencyId,
                    'actual_rate' => $actualRate,
                    'actual_amount_pln' => $actualAmountPln,
                    'paid_by' => $entry['paid_by'] ?? 'office',
                    'advance_type' => $cost->advance_type ?? 'full',
                    'payment_method' => $entry['payment_method'] ?? null,
                    'document_number' => $entry['document_number'] ?? null,
                    'paid_at' => $entry['paid_at'] ?? null,
                    'payment_status' => (filled($entry['paid_at'] ?? null) || $actualAmount !== null) ? 'paid' : 'planned',
                    'advance_due_date' => $entry['due_date'] ?? null,
                    'advance_amount' => null,
                    'notes' => $entry['notes'] ?? null,
                    'contractor_id' => $settlementContractorId,
                    'order' => (int) ($cost->order ?? 0) + $index + 1,
                ]);

                $documentSync->syncForCost($settlement, $paymentCost, $entry);
            }
        }

        $paymentRows = $settlement->costs()
            ->where('source_type', self::PROGRAM_POINT_PAYMENT_SOURCE_TYPE)
            ->where('source_id', $record->id)
            ->get();

        $paidPln = (float) $paymentRows
            ->where('payment_status', '!=', 'cancelled')
            ->sum(fn (EventSettlementCost $row) => (float) ($row->actual_amount_pln ?? 0));

        $paidForeign = (float) $paymentRows
            ->where('payment_status', '!=', 'cancelled')
            ->sum(fn (EventSettlementCost $row) => (float) ($row->actual_amount ?? 0));

        $plannedPlnValue = $cost->planned_amount_pln !== null ? (float) $cost->planned_amount_pln : null;
        $statusFromPayments = 'planned';

        if ($plannedPlnValue !== null && $plannedPlnValue > 0 && $paidPln >= $plannedPlnValue) {
            $statusFromPayments = 'paid';
        } elseif ($plannedPlnValue === null && $plannedAmount > 0 && $paidForeign >= $plannedAmount) {
            $statusFromPayments = 'paid';
        } elseif ($paidPln > 0 || $paidForeign > 0) {
            $statusFromPayments = 'partially_paid';
        }

        $costFill = [
            'actual_amount' => null,
            'actual_currency_id' => null,
            'actual_rate' => null,
            'actual_amount_pln' => null,
            'payment_status' => $statusFromPayments,
        ];

        if ($syncAdvanceEntries) {
            $costFill['advance_amount'] = $totalAdvanceAmount > 0 ? $totalAdvanceAmount : null;
            $costFill['advance_due_date'] = $nearestAdvanceDueDate;
        }

        $cost->fill($costFill);
        $cost->save();

        $advanceAmountStored = $syncAdvanceEntries
            ? $totalAdvanceAmount
            : (float) ($cost->advance_amount ?? 0);

        $record->planned_price = $plannedAmount;
        $record->paid_price = $paidPln;
        $record->saveQuietly();

        $remainingPln = $plannedPlnValue !== null ? max(0, $plannedPlnValue - $paidPln) : null;
        $remainingForeign = max(0, $plannedAmount - $paidForeign);

        $settlement->recalculateTotals();
        $cost->loadMissing('plannedCurrency');

        $this->afterSettlePointFinanceSaved();

        return [
            'planned_pln' => $plannedPlnValue,
            'paid_pln' => $paidPln,
            'advance_pln' => $advanceAmountStored,
            'remaining_pln' => $remainingPln,
            'planned_label' => CurrencyAmountDisplay::format($plannedAmount, $cost->plannedCurrency, $plannedConvertToPln),
            'paid_label' => CurrencyAmountDisplay::format($paidForeign > 0 ? $paidForeign : $paidPln, $cost->plannedCurrency, $plannedConvertToPln),
            'advance_label' => CurrencyAmountDisplay::format($advanceAmountStored, $cost->plannedCurrency, $plannedConvertToPln),
            'remaining_label' => CurrencyAmountDisplay::format($remainingForeign, $cost->plannedCurrency, $plannedConvertToPln),
        ];
    }

    protected function afterSettlePointFinanceSaved(): void
    {
        if (method_exists($this, 'invalidateSettlementCostCache')) {
            $this->invalidateSettlementCostCache();
        }

        if (method_exists($this, 'resetTable')) {
            $this->resetTable();
        }

        $this->dispatch('event-program-points-refresh');
        $this->dispatch('event-price-table-refresh');
    }
}
