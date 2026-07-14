<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\ContractPaymentSchedule;
use App\Models\EventAgreement;
use App\Models\EventAgreementPaymentSchedule;
use App\Models\EventSettlementCost;
use App\Models\VendorInvoice;
use App\Support\MoneyFormatter;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class PendingPaymentAggregator
{
    /** @return Collection<int, array<string, mixed>> */
    public function collect(?Carbon $from = null, ?Carbon $to = null): Collection
    {
        $from ??= now()->subMonths(1)->startOfDay();
        $to ??= now()->addMonths(6)->endOfDay();

        return $this->settlementCosts($from, $to)
            ->merge($this->vendorInvoices($from, $to))
            ->merge($this->contractSchedules($from, $to))
            ->merge($this->agreementSchedules($from, $to))
            ->filter(fn (array $row): bool => filled($row['due_date'] ?? null))
            ->sortBy([
                ['due_date', 'asc'],
                ['title', 'asc'],
            ])
            ->values();
    }

    /** @return Collection<int, array<string, mixed>> */
    protected function settlementCosts(Carbon $from, Carbon $to): Collection
    {
        return collect(
            EventSettlementCost::query()
                ->pendingPaymentInbox()
                ->whereBetween('advance_due_date', [$from, $to])
                ->with(['settlement.event', 'plannedCurrency'])
                ->limit(400)
                ->get()
                ->map(function (EventSettlementCost $cost): array {
                    $isProgramPayment = $cost->source_type === 'program_point_payment';

                    return [
                        'id' => 'cost-'.$cost->id,
                        'type' => $isProgramPayment ? 'program_payment' : 'settlement',
                        'type_label' => $isProgramPayment ? 'Zaliczka punktu' : 'Rozliczenie',
                        'event_id' => $cost->settlement?->event_id,
                        'event_code' => $cost->settlement?->event?->code,
                        'title' => $cost->name,
                        'amount' => (float) ($cost->planned_amount_pln ?? $cost->planned_amount ?? 0),
                        'amount_label' => MoneyFormatter::format($cost->planned_amount_pln ?? $cost->planned_amount),
                        'due_date' => $cost->advance_due_date?->toDateString(),
                        'status' => EventSettlementCost::$paymentStatuses[$cost->payment_status] ?? $cost->payment_status,
                        'payer' => EventSettlementCost::$paidByOptions[$cost->paid_by] ?? $cost->paid_by,
                        'paid_by' => $cost->paid_by ?? 'office',
                        'url' => $cost->settlement_id
                            ? \App\Filament\Resources\EventSettlementResource::getUrl('edit', ['record' => $cost->settlement_id])
                            : null,
                        'settlement_id' => $cost->settlement_id,
                        'contractor_id' => $cost->contractor_id,
                        'color' => $isProgramPayment ? '#0d9488' : '#ea580c',
                        'can_complete' => true,
                    ];
                })
                ->all()
        );
    }

    /** @return Collection<int, array<string, mixed>> */
    protected function vendorInvoices(Carbon $from, Carbon $to): Collection
    {
        if (! Schema::hasTable('vendor_invoices')) {
            return collect();
        }

        return collect(
            VendorInvoice::query()
                ->whereIn('payment_status', ['due', 'partial'])
                ->whereNotNull('due_date')
                ->whereBetween('due_date', [$from, $to])
                ->with(['event'])
                ->limit(200)
                ->get()
                ->map(function (VendorInvoice $invoice): array {
                    $gross = (float) ($invoice->gross_amount ?? 0);
                    $paid = (float) ($invoice->paid_amount ?? 0);
                    $remaining = max(0, $gross - $paid);

                    return [
                        'id' => 'vendor-'.$invoice->id,
                        'type' => 'vendor_invoice',
                        'type_label' => 'Faktura KSeF',
                        'event_id' => $invoice->event_id,
                        'event_code' => $invoice->event?->code,
                        'title' => $invoice->invoice_number ?: ($invoice->ksef_number ?: 'Faktura #'.$invoice->id),
                        'amount' => $remaining > 0 ? $remaining : $gross,
                        'amount_label' => MoneyFormatter::format($remaining > 0 ? $remaining : $gross, $invoice->currency ?: 'PLN'),
                        'due_date' => $invoice->due_date?->toDateString(),
                        'status' => VendorInvoice::$paymentStatuses[$invoice->payment_status] ?? $invoice->payment_status,
                        'payer' => 'Biuro',
                        'paid_by' => 'office',
                        'url' => \App\Filament\Resources\VendorInvoiceResource::getUrl('edit', ['record' => $invoice->id]),
                        'contractor_id' => $invoice->contractor_id,
                        'color' => '#b45309',
                        'can_complete' => true,
                    ];
                })
                ->all()
        );
    }

    /** @return Collection<int, array<string, mixed>> */
    protected function contractSchedules(Carbon $from, Carbon $to): Collection
    {
        if (! Schema::hasTable('contract_payment_schedules')) {
            return collect();
        }

        return collect(
            ContractPaymentSchedule::query()
                ->whereNotNull('due_date')
                ->whereBetween('due_date', [$from, $to])
                ->with(['contract.event', 'contract.paymentSchedules'])
                ->limit(200)
                ->get()
                ->filter(fn (ContractPaymentSchedule $row): bool => $this->isScheduleOutstanding(
                    $row,
                    $row->contract,
                    $row->contract?->paymentSchedules ?? collect(),
                ))
                ->map(fn (ContractPaymentSchedule $row): array => [
                    'id' => 'contract-'.$row->id,
                    'type' => 'contract',
                    'type_label' => 'Kontrakt TFG',
                    'event_id' => $row->contract?->event_id,
                    'event_code' => $row->contract?->event?->code,
                    'title' => $row->label ?: ('Rata #'.($row->sort_order ?? '?')),
                    'amount' => (float) $row->amount,
                    'amount_label' => MoneyFormatter::format($row->amount),
                    'due_date' => $row->due_date?->toDateString(),
                    'status' => $this->scheduleStatusLabel($row->contract),
                    'payer' => 'Biuro',
                    'paid_by' => 'office',
                    'url' => $row->contract_id
                        ? \App\Filament\Resources\ContractResource::getUrl('edit', ['record' => $row->contract_id])
                        : null,
                    'color' => '#7c3aed',
                    'can_complete' => true,
                ])
                ->all()
        );
    }

    /** @return Collection<int, array<string, mixed>> */
    protected function agreementSchedules(Carbon $from, Carbon $to): Collection
    {
        if (! Schema::hasTable('event_agreement_payment_schedules')) {
            return collect();
        }

        return collect(
            EventAgreementPaymentSchedule::query()
                ->whereNotNull('due_date')
                ->whereBetween('due_date', [$from, $to])
                ->with(['eventAgreement.event', 'eventAgreement.paymentSchedules'])
                ->limit(200)
                ->get()
                ->filter(fn (EventAgreementPaymentSchedule $row): bool => $this->isScheduleOutstanding(
                    $row,
                    $row->eventAgreement,
                    $row->eventAgreement?->paymentSchedules ?? collect(),
                ))
                ->map(fn (EventAgreementPaymentSchedule $row): array => [
                    'id' => 'agreement-'.$row->id,
                    'type' => 'agreement',
                    'type_label' => 'Umowa',
                    'event_id' => $row->eventAgreement?->event_id,
                    'event_code' => $row->eventAgreement?->event?->code,
                    'title' => $row->label ?: ('Rata #'.($row->sort_order ?? '?')),
                    'amount' => (float) $row->amount,
                    'amount_label' => MoneyFormatter::format($row->amount),
                    'due_date' => $row->due_date?->toDateString(),
                    'status' => $this->scheduleStatusLabel($row->eventAgreement),
                    'payer' => 'Biuro',
                    'paid_by' => 'office',
                    'url' => $row->eventAgreement?->event_id
                        ? \App\Filament\Resources\EventResource::getUrl('edit', ['record' => $row->eventAgreement->event_id])
                        : null,
                    'color' => '#2563eb',
                    'can_complete' => true,
                ])
                ->all()
        );
    }

    /**
     * @param  EloquentCollection<int, ContractPaymentSchedule|EventAgreementPaymentSchedule>|Collection<int, ContractPaymentSchedule|EventAgreementPaymentSchedule>  $schedules
     */
    protected function isScheduleOutstanding(
        ContractPaymentSchedule|EventAgreementPaymentSchedule $schedule,
        Contract|EventAgreement|null $parent,
        EloquentCollection|Collection $schedules,
    ): bool {
        if (! $parent) {
            return false;
        }

        if (in_array($parent->payment_status, ['paid', 'cancelled', 'failed'], true)) {
            return false;
        }

        if (in_array($parent->status, ['cancelled', 'template'], true)) {
            return false;
        }

        $amountPaid = (float) ($parent->amount_paid ?? 0);
        $sorted = $schedules->sortBy('sort_order')->values();
        $cumulativeDue = 0.0;

        foreach ($sorted as $row) {
            $cumulativeDue += (float) $row->amount;

            if ((int) $row->id === (int) $schedule->id) {
                return $amountPaid + 0.009 < $cumulativeDue;
            }
        }

        return $amountPaid + 0.009 < (float) $schedule->amount;
    }

    protected function scheduleStatusLabel(Contract|EventAgreement|null $parent): string
    {
        if (! $parent) {
            return 'Harmonogram';
        }

        $paid = (float) ($parent->amount_paid ?? 0);

        if ($paid <= 0) {
            return 'Do zapłaty';
        }

        if ($parent->payment_status === 'paid') {
            return 'Opłacona';
        }

        return 'Częściowo opłacona';
    }
}
