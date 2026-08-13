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
    public const LIMIT_SETTLEMENT_COSTS = 400;

    public const LIMIT_VENDOR_INVOICES = 200;

    public const LIMIT_CONTRACT_SCHEDULES = 200;

    public const LIMIT_AGREEMENT_SCHEDULES = 200;

    /** @var array<string, bool> */
    protected array $truncated = [];

    /** @return array<string, bool> */
    public function lastTruncation(): array
    {
        return $this->truncated;
    }

    public function wasTruncated(): bool
    {
        return in_array(true, $this->truncated, true);
    }

    /** @return Collection<int, array<string, mixed>> */
    public function collect(?Carbon $from = null, ?Carbon $to = null): Collection
    {
        $from ??= now()->subMonths(1)->startOfDay();
        $to ??= now()->addMonths(6)->endOfDay();
        $this->truncated = [];

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
        $rows = EventSettlementCost::query()
            ->pendingPaymentInbox()
            ->whereBetween('advance_due_date', [$from, $to])
            ->with(['settlement.event', 'plannedCurrency', 'contractor'])
            ->limit(self::LIMIT_SETTLEMENT_COSTS + 1)
            ->get();

        $this->truncated['settlement_costs'] = $rows->count() > self::LIMIT_SETTLEMENT_COSTS;
        $rows = $rows->take(self::LIMIT_SETTLEMENT_COSTS);

        $programPointIds = $rows
            ->filter(fn (EventSettlementCost $cost): bool => in_array($cost->source_type, ['program_point', 'program_point_payment'], true) && $cost->source_id)
            ->pluck('source_id')
            ->unique()
            ->filter()
            ->values();

        $programPoints = $programPointIds->isNotEmpty() && Schema::hasTable('event_program_points')
            ? \App\Models\EventProgramPoint::query()->whereIn('id', $programPointIds)->get()->keyBy('id')
            : collect();

        return collect(
            $rows
                ->map(function (EventSettlementCost $cost) use ($programPoints): ?array {
                    $isProgramPayment = $cost->source_type === 'program_point_payment';
                    $plan = $cost->resolvePlanCostForPayment();
                    $planned = (float) ($plan->planned_amount_pln ?? $plan->planned_amount ?? $cost->planned_amount_pln ?? $cost->planned_amount ?? 0);
                    // Na wierszu zobowiązania liczymy tylko zaksięgowany actual — flaga bez kwoty ≠ wpłata.
                    $paid = SettlementPaymentHealthService::isBookedPaymentStatus($cost->payment_status)
                        ? (float) ($cost->actual_amount_pln ?? 0)
                        : 0.0;

                    if ($plan->is($cost) === false || EventSettlementCost::isPaymentSourceType($cost->source_type)) {
                        $health = app(SettlementPaymentHealthService::class);
                        $all = $cost->settlement?->relationLoaded('costs')
                            ? $cost->settlement->costs
                            : ($cost->settlement?->costs()->get() ?? collect([$cost]));
                        $paid = $health->paidPlnForPlanCost($plan, $all);
                        $planned = $health->indicativePlannedPlnForCost($plan);
                    }

                    $remaining = SettlementPaymentHealthService::remainingPln($paid, $planned);

                    if ($planned > SettlementPaymentHealthService::TOLERANCE
                        && SettlementPaymentHealthService::isFullyPaid($paid, $planned)) {
                        return null;
                    }

                    $amount = $remaining > SettlementPaymentHealthService::TOLERANCE ? $remaining : $planned;
                    $event = $cost->settlement?->event;
                    $pointId = $plan->source_type === 'program_point' ? $plan->source_id : ($cost->source_id ?: null);
                    $point = $pointId ? $programPoints->get((int) $pointId) : null;
                    $contractorName = $cost->contractor?->name ?? $plan->contractor?->name;

                    $contextParts = array_values(array_filter([
                        $contractorName ? 'Kontrahent: '.$contractorName : null,
                        $point?->name ? 'Punkt: '.$point->name : null,
                        $cost->paid_by === 'pilot' ? 'Płatnik: Pilot' : 'Płatnik: Biuro',
                    ]));

                    return [
                        'id' => 'cost-'.$cost->id,
                        'type' => $isProgramPayment ? 'program_payment' : 'settlement',
                        'type_label' => $isProgramPayment ? 'Zaliczka punktu' : 'Rozliczenie',
                        'event_id' => $cost->settlement?->event_id,
                        'event_code' => $event?->code,
                        'event_name' => $event?->name,
                        'event_label' => trim(($event?->code ? $event->code.' · ' : '').(string) ($event?->name ?? '')) ?: null,
                        'title' => $cost->name ?: $plan->name,
                        'context' => implode(' · ', $contextParts),
                        'contractor_name' => $contractorName,
                        'program_point_name' => $point?->name,
                        'amount' => $amount,
                        'amount_label' => MoneyFormatter::format($amount),
                        'due_date' => $cost->advance_due_date?->toDateString(),
                        'status' => EventSettlementCost::$paymentStatuses[$cost->payment_status] ?? $cost->payment_status,
                        'payer' => EventSettlementCost::$paidByOptions[$cost->paid_by] ?? $cost->paid_by,
                        'paid_by' => $cost->paid_by ?? 'office',
                        'url' => $cost->settlement_id
                            ? \App\Support\AdminPanelUrls::eventFinanceForSettlement($cost->settlement_id)
                            : null,
                        'settlement_id' => $cost->settlement_id,
                        'cost_id' => $cost->id,
                        'plan_cost_id' => $plan->id,
                        'contractor_id' => $cost->contractor_id ?? $plan->contractor_id,
                        'color' => $isProgramPayment ? '#0d9488' : '#ea580c',
                        'can_complete' => true,
                        'complete_mode' => 'cost_payment_form',
                        'remaining_pln' => $remaining,
                        'suggested_amount_pln' => $amount,
                        'suggested_paid_by' => $cost->paid_by ?? $plan->paid_by ?? 'office',
                        'suggested_due_date' => $cost->advance_due_date?->toDateString(),
                    ];
                })
                ->filter()
                ->values()
                ->all()
        );
    }

    /** @return Collection<int, array<string, mixed>> */
    protected function vendorInvoices(Carbon $from, Carbon $to): Collection
    {
        if (! Schema::hasTable('vendor_invoices')) {
            return collect();
        }

        $rows = VendorInvoice::query()
            ->whereIn('payment_status', ['due', 'partial'])
            ->whereNotNull('due_date')
            ->whereBetween('due_date', [$from, $to])
            ->with(['event', 'contractor'])
            ->limit(self::LIMIT_VENDOR_INVOICES + 1)
            ->get();

        $this->truncated['vendor_invoices'] = $rows->count() > self::LIMIT_VENDOR_INVOICES;
        $rows = $rows->take(self::LIMIT_VENDOR_INVOICES);

        return collect(
            $rows
                ->map(function (VendorInvoice $invoice): array {
                    $gross = (float) ($invoice->gross_amount ?? 0);
                    $paid = (float) ($invoice->paid_amount ?? 0);
                    $remaining = max(0, $gross - $paid);
                    $event = $invoice->event;
                    $seller = $invoice->seller_name ?: $invoice->contractor?->name;

                    return [
                        'id' => 'vendor-'.$invoice->id,
                        'type' => 'vendor_invoice',
                        'type_label' => 'Faktura KSeF',
                        'event_id' => $invoice->event_id,
                        'event_code' => $event?->code,
                        'event_name' => $event?->name,
                        'event_label' => trim(($event?->code ? $event->code.' · ' : '').(string) ($event?->name ?? '')) ?: null,
                        'title' => $invoice->invoice_number ?: ($invoice->ksef_number ?: 'Faktura #'.$invoice->id),
                        'context' => implode(' · ', array_filter([
                            $seller ? 'Sprzedawca: '.$seller : null,
                            $invoice->event_id ? null : 'Bez przypisanej imprezy',
                        ])),
                        'contractor_name' => $seller,
                        'amount' => $remaining > 0 ? $remaining : $gross,
                        'amount_label' => MoneyFormatter::format($remaining > 0 ? $remaining : $gross, $invoice->currency ?: 'PLN'),
                        'due_date' => $invoice->due_date?->toDateString(),
                        'status' => VendorInvoice::$paymentStatuses[$invoice->payment_status] ?? $invoice->payment_status,
                        'payer' => 'Biuro',
                        'paid_by' => 'office',
                        'url' => \App\Support\AdminPanelUrls::vendorInvoiceEdit($invoice),
                        'contractor_id' => $invoice->contractor_id,
                        'color' => '#b45309',
                        'can_complete' => true,
                        'complete_mode' => 'quick',
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

        $rows = ContractPaymentSchedule::query()
            ->whereNotNull('due_date')
            ->whereBetween('due_date', [$from, $to])
            ->with(['contract.event', 'contract.paymentSchedules'])
            ->limit(self::LIMIT_CONTRACT_SCHEDULES + 1)
            ->get();

        $this->truncated['contract_schedules'] = $rows->count() > self::LIMIT_CONTRACT_SCHEDULES;
        $rows = $rows->take(self::LIMIT_CONTRACT_SCHEDULES);

        return collect(
            $rows
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
                    'event_name' => $row->contract?->event?->name,
                    'event_label' => trim(($row->contract?->event?->code ? $row->contract->event->code.' · ' : '').(string) ($row->contract?->event?->name ?? '')) ?: null,
                    'title' => $row->label ?: ('Rata #'.($row->sort_order ?? '?')),
                    'context' => implode(' · ', array_filter([
                        $row->contract?->participant_name ? 'Uczestnik: '.$row->contract->participant_name : null,
                        $row->contract?->customer_name ? 'Zamawiający: '.$row->contract->customer_name : null,
                        $row->contract?->contract_number ? 'Umowa: '.$row->contract->contract_number : null,
                    ])),
                    'amount' => (float) $row->amount,
                    'amount_label' => MoneyFormatter::format($row->amount),
                    'due_date' => $row->due_date?->toDateString(),
                    'status' => $this->scheduleStatusLabel($row->contract),
                    'payer' => 'Biuro',
                    'paid_by' => 'office',
                    'url' => $row->contract_id
                        ? \App\Support\AdminPanelUrls::contractEdit((int) $row->contract_id)
                        : null,
                    'color' => '#7c3aed',
                    'can_complete' => true,
                    'complete_mode' => 'quick',
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

        $rows = EventAgreementPaymentSchedule::query()
            ->whereNotNull('due_date')
            ->whereBetween('due_date', [$from, $to])
            ->with(['eventAgreement.event', 'eventAgreement.paymentSchedules'])
            ->limit(self::LIMIT_AGREEMENT_SCHEDULES + 1)
            ->get();

        $this->truncated['agreement_schedules'] = $rows->count() > self::LIMIT_AGREEMENT_SCHEDULES;
        $rows = $rows->take(self::LIMIT_AGREEMENT_SCHEDULES);

        return collect(
            $rows
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
                    'event_name' => $row->eventAgreement?->event?->name,
                    'event_label' => trim(($row->eventAgreement?->event?->code ? $row->eventAgreement->event->code.' · ' : '').(string) ($row->eventAgreement?->event?->name ?? '')) ?: null,
                    'title' => $row->label ?: ('Rata #'.($row->sort_order ?? '?')),
                    'context' => implode(' · ', array_filter([
                        $row->eventAgreement?->participant_name ? 'Uczestnik: '.$row->eventAgreement->participant_name : null,
                        $row->eventAgreement?->customer_name ? 'Zamawiający: '.$row->eventAgreement->customer_name : null,
                    ])),
                    'amount' => (float) $row->amount,
                    'amount_label' => MoneyFormatter::format($row->amount),
                    'due_date' => $row->due_date?->toDateString(),
                    'status' => $this->scheduleStatusLabel($row->eventAgreement),
                    'payer' => 'Biuro',
                    'paid_by' => 'office',
                    'url' => $row->eventAgreement?->event_id
                        ? \App\Support\AdminPanelUrls::eventEdit((int) $row->eventAgreement->event_id)
                        : null,
                    'color' => '#2563eb',
                    'can_complete' => true,
                    'complete_mode' => 'quick',
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
