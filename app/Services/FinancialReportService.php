<?php

namespace App\Services;

use App\Models\EventSettlementCost;
use App\Models\EventSettlementParticipantPayment;
use App\Models\VendorInvoice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class FinancialReportService
{
    /**
     * @param  array{
     *     type?: ?string,
     *     date_from?: ?string,
     *     date_to?: ?string,
     *     event_id?: ?int,
     *     contractor_id?: ?int,
     *     search?: ?string
     * }  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function rows(array $filters = []): Collection
    {
        return collect()
            ->merge($this->participantPaymentRows($filters))
            ->merge($this->vendorInvoiceRows($filters))
            ->merge($this->settlementCostRows($filters))
            ->sortBy([
                fn (array $row) => $row['operation_date'] ?? '9999-12-31',
                fn (array $row) => $row['label'] ?? '',
            ])
            ->values();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function summarize(Collection $rows): array
    {
        return [
            'count' => $rows->count(),
            'amount_in' => round($rows->where('direction', 'in')->sum('amount_pln'), 2),
            'amount_out' => round($rows->where('direction', 'out')->sum('amount_pln'), 2),
            'balance' => round($rows->sum(fn (array $row) => $row['direction'] === 'in' ? $row['amount_pln'] : -$row['amount_pln']), 2),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    protected function participantPaymentRows(array $filters): Collection
    {
        if (($filters['type'] ?? null) && $filters['type'] !== 'participant_payment') {
            return collect();
        }

        if (! Schema::hasTable('event_settlement_participant_payments')) {
            return collect();
        }

        $query = EventSettlementParticipantPayment::query()
            ->with(['settlement.event'])
            ->where('paid_amount_pln', '>', 0);

        if (! empty($filters['event_id'])) {
            $query->whereHas('settlement', fn (Builder $q) => $q->where('event_id', $filters['event_id']));
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('payment_date', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('payment_date', '<=', $filters['date_to']);
        }

        if (! empty($filters['search'])) {
            $term = '%'.$filters['search'].'%';
            $query->where(function (Builder $inner) use ($term): void {
                $inner->where('participant_name', 'like', $term)
                    ->orWhere('booking_reference', 'like', $term)
                    ->orWhere('document_number', 'like', $term);
            });
        }

        return $query->get()->map(fn (EventSettlementParticipantPayment $payment): array => [
            'id' => 'participant-'.$payment->id,
            'operation_type' => 'participant_payment',
            'operation_label' => 'Wpłata uczestnika',
            'label' => $payment->participant_name,
            'counterparty' => $payment->participant_name,
            'event_code' => $payment->settlement?->event?->code,
            'event_name' => $payment->settlement?->event?->name,
            'amount_pln' => (float) $payment->paid_amount_pln,
            'direction' => 'in',
            'status' => EventSettlementParticipantPayment::$paymentStatuses[$payment->payment_status] ?? $payment->payment_status,
            'operation_date' => $payment->payment_date?->format('Y-m-d'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    protected function vendorInvoiceRows(array $filters): Collection
    {
        if (($filters['type'] ?? null) && $filters['type'] !== 'vendor_invoice') {
            return collect();
        }

        if (! Schema::hasTable('vendor_invoices')) {
            return collect();
        }

        $query = VendorInvoice::query()->with(['event', 'contractor']);

        if (! empty($filters['event_id'])) {
            $query->where('event_id', $filters['event_id']);
        }

        if (! empty($filters['contractor_id'])) {
            $query->where('contractor_id', $filters['contractor_id']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('payment_date', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('payment_date', '<=', $filters['date_to']);
        }

        if (! empty($filters['search'])) {
            $term = '%'.$filters['search'].'%';
            $query->where(function (Builder $inner) use ($term): void {
                $inner->where('invoice_number', 'like', $term)
                    ->orWhere('ksef_number', 'like', $term)
                    ->orWhere('seller_name', 'like', $term)
                    ->orWhere('seller_nip', 'like', $term);
            });
        }

        return $query->get()->map(fn (VendorInvoice $invoice): array => [
            'id' => 'invoice-'.$invoice->id,
            'operation_type' => 'vendor_invoice',
            'operation_label' => 'Faktura kosztowa',
            'label' => $invoice->invoice_number ?: ('KSeF '.$invoice->ksef_number),
            'counterparty' => $invoice->seller_name,
            'event_code' => $invoice->event?->code,
            'event_name' => $invoice->event?->name,
            'amount_pln' => (float) ($invoice->payment_status === 'paid' ? $invoice->paid_amount : $invoice->gross_amount),
            'direction' => 'out',
            'status' => VendorInvoice::$paymentStatuses[$invoice->payment_status] ?? $invoice->payment_status,
            'operation_date' => ($invoice->payment_date ?? $invoice->due_date)?->format('Y-m-d'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    protected function settlementCostRows(array $filters): Collection
    {
        if (($filters['type'] ?? null) && $filters['type'] !== 'settlement_cost') {
            return collect();
        }

        if (! Schema::hasTable('event_settlement_costs')) {
            return collect();
        }

        $query = EventSettlementCost::query()
            ->with(['settlement.event', 'contractor'])
            ->where('actual_amount_pln', '>', 0);

        if (! empty($filters['event_id'])) {
            $query->whereHas('settlement', fn (Builder $q) => $q->where('event_id', $filters['event_id']));
        }

        if (! empty($filters['contractor_id'])) {
            $query->where('contractor_id', $filters['contractor_id']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('paid_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('paid_at', '<=', $filters['date_to']);
        }

        if (! empty($filters['search'])) {
            $term = '%'.$filters['search'].'%';
            $query->where(function (Builder $inner) use ($term): void {
                $inner->where('name', 'like', $term)
                    ->orWhere('document_number', 'like', $term)
                    ->orWhereHas('contractor', fn (Builder $contractor) => $contractor->where('name', 'like', $term));
            });
        }

        return $query->get()->map(fn (EventSettlementCost $cost): array => [
            'id' => 'cost-'.$cost->id,
            'operation_type' => 'settlement_cost',
            'operation_label' => EventSettlementCost::$sourceTypeLabels[$cost->source_type] ?? 'Koszt rozliczenia',
            'label' => $cost->name,
            'counterparty' => $cost->contractor?->name,
            'event_code' => $cost->settlement?->event?->code,
            'event_name' => $cost->settlement?->event?->name,
            'amount_pln' => (float) $cost->actual_amount_pln,
            'direction' => 'out',
            'status' => EventSettlementCost::$paymentStatuses[$cost->payment_status] ?? $cost->payment_status,
            'operation_date' => $cost->paid_at?->format('Y-m-d'),
        ]);
    }
}
