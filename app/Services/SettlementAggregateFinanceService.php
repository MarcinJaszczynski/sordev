<?php

namespace App\Services;

use App\Actions\Finance\UpdateSettlementCostPlanAction;
use App\Data\UpdateSettlementCostPlanData;
use App\Models\Currency;
use App\Models\Event;
use App\Models\EventSettlement;
use App\Models\EventSettlementCost;
use App\Support\CurrencyAmountDisplay;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

final class SettlementAggregateFinanceService
{
    public function paymentSourceType(string $baseSourceType): string
    {
        return $baseSourceType.'_payment';
    }

    public function ensureBaseCost(Event $event, string $baseSourceType): ?EventSettlementCost
    {
        EventSettlement::findOrCreateActiveForEvent($event);
        $event->refreshActiveSettlementCosts();

        return $event->activeSettlement?->costs()
            ->where('source_type', $baseSourceType)
            ->whereNull('source_id')
            ->first();
    }

    public function resolveReferenceTotalPln(Event $event, string $baseSourceType): float
    {
        $cost = $this->ensureBaseCost($event, $baseSourceType);

        if ($cost && $cost->planned_amount_pln !== null) {
            return (float) $cost->planned_amount_pln;
        }

        if ($baseSourceType === 'transport') {
            return (float) (new EventTransportCostCalculator($event))->effectiveTransportCost();
        }

        return 0.0;
    }

    /**
     * @return array<string, mixed>
     */
    public function buildFormData(Event $event, string $baseSourceType): array
    {
        $paymentSourceType = $this->paymentSourceType($baseSourceType);
        $documentSync = app(ProgramPointSettlementDocumentSync::class);
        $referenceTotalPln = $this->resolveReferenceTotalPln($event, $baseSourceType);

        $existingCost = $this->ensureBaseCost($event, $baseSourceType);

        $plannedCurrencyId = $existingCost?->planned_currency_id
            ?? Currency::query()->where('code', 'PLN')->orWhere('symbol', 'PLN')->value('id');
        $plannedRate = (float) ($existingCost?->planned_rate ?? 1);
        $convertToPln = (bool) ($existingCost?->planned_convert_to_pln ?? true);
        $plannedAmount = (float) ($existingCost?->planned_amount ?? $referenceTotalPln);
        $plannedAmountPln = $existingCost?->planned_amount_pln;

        if ($plannedAmountPln === null && $convertToPln) {
            $plannedAmountPln = CurrencyAmountDisplay::isForeignCurrency($plannedCurrencyId)
                ? round($plannedAmount * $plannedRate, 2)
                : round($plannedAmount, 2);
        }

        $settlement = $event->activeSettlement;
        $paymentRowsCollection = $settlement?->costs()
            ->where('source_type', $paymentSourceType)
            ->whereNull('source_id')
            ->orderBy('paid_at')
            ->orderBy('id')
            ->get() ?? collect();

        $advanceRow = $paymentRowsCollection
            ->first(fn (EventSettlementCost $payment): bool => in_array((string) $payment->advance_type, ['advance', 'deposit'], true) || (float) ($payment->advance_amount ?? 0) > 0);

        $advanceDocumentData = $advanceRow
            ? $documentSync->loadDocumentDataForCost($advanceRow)
            : [
                'document_id' => null,
                'document_type' => null,
                'document_number' => null,
                'document_files' => [],
            ];

        $paymentRows = $paymentRowsCollection
            ->reject(fn (EventSettlementCost $payment): bool => $advanceRow && $payment->id === $advanceRow->id)
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
            'event_point_total' => $referenceTotalPln,
            'settlement_planned_amount' => $plannedAmount,
            'settlement_planned_currency_id' => $plannedCurrencyId,
            'settlement_planned_convert_to_pln' => $convertToPln,
            'settlement_planned_rate' => $plannedRate,
            'settlement_planned_amount_pln' => $plannedAmountPln,
            'settlement_paid_by' => $existingCost?->paid_by ?? 'office',
            'settlement_payment_due_date' => $existingCost?->advance_due_date,
            'settlement_payment_method' => $existingCost?->payment_method,
            'settlement_notes' => $existingCost?->notes,
            'settlement_advance_type' => $advanceRow?->advance_type ?? $existingCost?->advance_type ?? 'advance',
            'settlement_advance_paid_by' => $advanceRow?->paid_by ?? $existingCost?->paid_by ?? 'office',
            'settlement_advance_amount' => (float) ($advanceRow?->advance_amount ?? $existingCost?->advance_amount ?? 0),
            'settlement_advance_due_date' => $advanceRow?->advance_due_date,
            'settlement_advance_paid_amount' => filled($advanceRow?->actual_amount) ? (float) $advanceRow->actual_amount : null,
            'settlement_advance_paid_currency_id' => $advanceRow?->actual_currency_id ?? $plannedCurrencyId,
            'settlement_advance_paid_rate' => (float) ($advanceRow?->actual_rate ?? $plannedRate),
            'settlement_advance_paid_amount_pln' => filled($advanceRow?->actual_amount_pln) ? (float) $advanceRow->actual_amount_pln : null,
            'settlement_advance_paid_at' => $advanceRow?->paid_at,
            'settlement_advance_payment_method' => $advanceRow?->payment_method ?? $existingCost?->payment_method,
            'settlement_advance_notes' => $advanceRow?->notes,
            'advance_document_id' => $advanceDocumentData['document_id'],
            'advance_document_type' => $advanceDocumentData['document_type'],
            'advance_document_number' => $advanceDocumentData['document_number'],
            'advance_document_files' => $advanceDocumentData['document_files'],
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
    public function persist(Event $event, string $baseSourceType, array $data, bool $syncPaymentEntries): array
    {
        $paymentSourceType = $this->paymentSourceType($baseSourceType);
        $settlement = EventSettlement::findOrCreateActiveForEvent($event);
        $event->refreshActiveSettlementCosts();
        $cost = $this->ensureBaseCost($event, $baseSourceType);

        if (! $cost) {
            return [
                'planned_pln' => null,
                'paid_pln' => 0,
                'advance_pln' => 0,
                'remaining_pln' => null,
                'planned_label' => '—',
                'paid_label' => '—',
                'advance_label' => '—',
                'remaining_label' => '—',
            ];
        }

        $documentSync = app(ProgramPointSettlementDocumentSync::class);

        $plannedRate = (float) ($data['settlement_planned_rate'] ?? $cost->planned_rate ?? 1);
        $plannedAmount = (float) ($data['settlement_planned_amount'] ?? $cost->planned_amount ?? 0);
        $plannedConvertToPln = (bool) ($data['settlement_planned_convert_to_pln'] ?? $cost->planned_convert_to_pln ?? true);
        $plannedCurrencyId = $data['settlement_planned_currency_id'] ?? $cost->planned_currency_id;

        $plannedAmountPln = array_key_exists('settlement_planned_amount_pln', $data)
            ? ($data['settlement_planned_amount_pln'] === null || $data['settlement_planned_amount_pln'] === '' ? null : (float) $data['settlement_planned_amount_pln'])
            : (
                ! CurrencyAmountDisplay::isForeignCurrency($plannedCurrencyId)
                    ? round($plannedAmount, 2)
                    : ($plannedConvertToPln ? round($plannedAmount * $plannedRate, 2) : null)
            );

        if ($plannedAmountPln === null && $plannedConvertToPln && ! CurrencyAmountDisplay::isForeignCurrency($plannedCurrencyId)) {
            $plannedAmountPln = round($plannedAmount, 2);
        }

        $dueDateRaw = array_key_exists('settlement_payment_due_date', $data)
            ? $data['settlement_payment_due_date']
            : $cost->advance_due_date;

        $cost = app(UpdateSettlementCostPlanAction::class)(new UpdateSettlementCostPlanData(
            planCost: $cost,
            plannedAmountPln: (float) ($plannedAmountPln ?? 0),
            paidBy: (string) ($data['settlement_paid_by'] ?? $cost->paid_by ?? 'office'),
            notes: $data['settlement_notes'] ?? $cost->notes,
            dueDate: filled($dueDateRaw) ? Carbon::parse($dueDateRaw) : null,
            plannedAmount: $plannedAmount,
            plannedCurrencyId: $plannedCurrencyId,
            plannedConvertToPln: $plannedConvertToPln,
            plannedRate: $plannedRate,
            paymentMethod: $data['settlement_payment_method'] ?? $cost->payment_method,
            advanceType: $data['settlement_advance_type'] ?? $cost->advance_type,
        ));

        $cost = $cost->fresh() ?? $cost;

        // Keep advance_amount on the plan row when provided by aggregate form.
        if (array_key_exists('settlement_advance_amount', $data)) {
            $cost->update([
                'advance_amount' => $data['settlement_advance_amount'] === null || $data['settlement_advance_amount'] === ''
                    ? null
                    : (float) $data['settlement_advance_amount'],
            ]);
        }

        $existingAdvanceRow = $settlement->costs()
            ->where('source_type', $paymentSourceType)
            ->whereNull('source_id')
            ->where(function (Builder $query): void {
                $query->whereIn('advance_type', ['advance', 'deposit'])
                    ->orWhere('advance_amount', '>', 0);
            })
            ->orderBy('id')
            ->first();

        $advanceAmount = (float) ($data['settlement_advance_amount'] ?? 0);
        $advancePaidAmount = filled($data['settlement_advance_paid_amount'] ?? null)
            ? (float) $data['settlement_advance_paid_amount']
            : 0.0;
        $advancePaidCurrencyId = $data['settlement_advance_paid_currency_id'] ?? $plannedCurrencyId;
        $advancePaidRate = (float) ($data['settlement_advance_paid_rate'] ?? $plannedRate);
        $advancePaidAmountPln = filled($data['settlement_advance_paid_amount_pln'] ?? null)
            ? (float) $data['settlement_advance_paid_amount_pln']
            : ($advancePaidAmount > 0
                ? (CurrencyAmountDisplay::isForeignCurrency($advancePaidCurrencyId)
                    ? round($advancePaidAmount * $advancePaidRate, 2)
                    : round($advancePaidAmount, 2))
                : null);
        $advancePaidAt = $data['settlement_advance_paid_at'] ?? null;
        $advanceDocumentNumber = $data['advance_document_number'] ?? null;

        if ($advanceAmount > 0 || $advancePaidAmount > 0 || filled($data['settlement_advance_due_date'] ?? null) || filled($advancePaidAt) || filled($data['settlement_advance_notes'] ?? null) || filled($advanceDocumentNumber)) {
            $advanceStatus = 'advance_required';
            if ($advancePaidAt || $advancePaidAmount > 0) {
                $advanceStatus = $advanceAmount > 0 && $advancePaidAmount < $advanceAmount
                    ? 'partially_paid'
                    : 'advance_paid';
            }

            $advancePayload = [
                'source_type' => $paymentSourceType,
                'source_id' => null,
                'name' => ($cost->name ?: 'Koszt').' • zaliczka',
                'planned_amount' => 0,
                'planned_currency_id' => $plannedCurrencyId,
                'planned_convert_to_pln' => $plannedConvertToPln,
                'planned_rate' => $plannedRate,
                'planned_amount_pln' => 0,
                'actual_amount' => $advancePaidAmount > 0 ? $advancePaidAmount : null,
                'actual_currency_id' => $advancePaidCurrencyId,
                'actual_rate' => $advancePaidRate,
                'actual_amount_pln' => $advancePaidAmountPln,
                'paid_by' => $data['settlement_advance_paid_by'] ?? $cost->paid_by ?? 'office',
                'advance_type' => $data['settlement_advance_type'] ?? 'advance',
                'payment_method' => (($data['settlement_advance_paid_by'] ?? $cost->paid_by ?? 'office') === 'pilot')
                    ? 'cash'
                    : ($data['settlement_advance_payment_method'] ?? null),
                'document_number' => $advanceDocumentNumber,
                'paid_at' => $advancePaidAt,
                'payment_status' => $advanceStatus,
                'advance_due_date' => $data['settlement_advance_due_date'] ?? null,
                'advance_amount' => $advanceAmount > 0 ? $advanceAmount : null,
                'notes' => $data['settlement_advance_notes'] ?? null,
                'contractor_id' => $cost->contractor_id,
                'order' => (int) ($cost->order ?? 0) + 1,
            ];

            if ($existingAdvanceRow) {
                $existingAdvanceRow->update($advancePayload);
                $advanceCost = $existingAdvanceRow->fresh();
            } else {
                $advanceCost = $settlement->costs()->create($advancePayload);
            }

            $documentSync->syncForCost($settlement, $advanceCost, $data, 'advance_');
        } elseif ($existingAdvanceRow) {
            $existingAdvanceRow->delete();
        }

        if ($syncPaymentEntries) {
            $entries = collect($data['payment_entries'] ?? [])
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
                ->where('source_type', $paymentSourceType)
                ->whereNull('source_id')
                ->where(function (Builder $query): void {
                    $query->whereNotIn('advance_type', ['advance', 'deposit'])
                        ->orWhereNull('advance_type');
                })
                ->delete();

            $baseName = $cost->name ?: 'Koszt';

            foreach ($entries as $index => $entry) {
                $actualAmount = filled($entry['actual_amount'] ?? null) ? (float) $entry['actual_amount'] : null;
                $actualCurrencyId = $entry['actual_currency_id'] ?? $plannedCurrencyId;
                $actualRate = (float) ($entry['actual_rate'] ?? $plannedRate ?? 1);
                $actualAmountPln = filled($entry['actual_amount_pln'] ?? null)
                    ? (float) $entry['actual_amount_pln']
                    : ($actualAmount !== null
                        ? (CurrencyAmountDisplay::isForeignCurrency($actualCurrencyId)
                            ? round($actualAmount * max(0, $actualRate), 2)
                            : round($actualAmount, 2))
                        : null);

                $paymentCost = $settlement->costs()->create([
                    'source_type' => $paymentSourceType,
                    'source_id' => null,
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
                    'advance_type' => EventSettlementCost::normalizeUserAdvanceType($entry['advance_type'] ?? null),
                    'payment_method' => (($entry['paid_by'] ?? 'office') === 'pilot')
                        ? 'cash'
                        : ($entry['payment_method'] ?? null),
                    'document_number' => $entry['document_number'] ?? null,
                    'paid_at' => $entry['paid_at'] ?? null,
                    'payment_status' => (filled($entry['paid_at'] ?? null) || $actualAmount !== null) ? 'paid' : 'planned',
                    'advance_due_date' => $entry['due_date'] ?? null,
                    'advance_amount' => null,
                    'notes' => $entry['notes'] ?? null,
                    'contractor_id' => $cost->contractor_id,
                    'order' => (int) ($cost->order ?? 0) + $index + 1,
                ]);

                $documentSync->syncForCost($settlement, $paymentCost, $entry);
            }
        }

        $paymentRows = $settlement->costs()
            ->where('source_type', $paymentSourceType)
            ->whereNull('source_id')
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

        $advanceAmountStored = (float) ($data['settlement_advance_amount'] ?? $cost->advance_amount ?? 0);

        $cost->fill([
            'actual_amount' => null,
            'actual_currency_id' => null,
            'actual_rate' => null,
            'actual_amount_pln' => null,
            'payment_status' => $statusFromPayments,
            'advance_amount' => $advanceAmountStored > 0 ? $advanceAmountStored : null,
            'advance_due_date' => array_key_exists('settlement_payment_due_date', $data)
                ? $data['settlement_payment_due_date']
                : $cost->advance_due_date,
        ]);
        $cost->save();

        $remainingPln = $plannedPlnValue !== null ? max(0, $plannedPlnValue - $paidPln) : null;
        $remainingForeign = max(0, $plannedAmount - $paidForeign);

        $settlement->recalculateTotals();
        app(\App\Services\PilotSettlementService::class)->refreshCashFromCosts($settlement);
        $cost->loadMissing('plannedCurrency');

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

    /**
     * @return array{planned: string, paid: string, remaining: string, status: string}
     */
    public function summary(Event $event, string $baseSourceType): array
    {
        $cost = $this->ensureBaseCost($event, $baseSourceType);
        $paymentSourceType = $this->paymentSourceType($baseSourceType);

        if (! $cost) {
            return [
                'planned' => '—',
                'paid' => '—',
                'remaining' => '—',
                'status' => 'Brak kosztu',
            ];
        }

        $paymentRows = $event->activeSettlement?->costs()
            ->where('source_type', $paymentSourceType)
            ->whereNull('source_id')
            ->get() ?? collect();

        $paidPln = (float) $paymentRows
            ->filter(fn (EventSettlementCost $row): bool => SettlementPaymentHealthService::isBookedPaymentStatus($row->payment_status))
            ->sum(fn (EventSettlementCost $row) => (float) ($row->actual_amount_pln ?? 0));

        $plannedPln = $cost->planned_amount_pln !== null ? (float) $cost->planned_amount_pln : (float) ($cost->planned_amount ?? 0);
        $remaining = SettlementPaymentHealthService::remainingPln($paidPln, $plannedPln);

        $status = match (true) {
            $plannedPln <= SettlementPaymentHealthService::TOLERANCE => 'Brak planu',
            SettlementPaymentHealthService::isFullyPaid($paidPln, $plannedPln) => 'Opłacone',
            SettlementPaymentHealthService::isPartiallyPaid($paidPln, $plannedPln)
                && in_array($cost->payment_status, ['advance_paid', 'advance_required'], true) => 'Zaliczka',
            SettlementPaymentHealthService::isPartiallyPaid($paidPln, $plannedPln) => 'Częściowo',
            $cost->payment_status === 'advance_required' => 'Wymaga zaliczki',
            default => 'Do zapłaty',
        };

        return [
            'planned' => number_format($plannedPln, 2, ',', ' ').' PLN',
            'paid' => number_format($paidPln, 2, ',', ' ').' PLN',
            'remaining' => number_format($remaining, 2, ',', ' ').' PLN',
            'status' => $status,
        ];
    }
}
