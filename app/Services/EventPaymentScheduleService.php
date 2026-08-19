<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\ContractPaymentSchedule;
use App\Models\Event;
use App\Models\EventAgreement;
use App\Models\EventAgreementPaymentSchedule;
use App\Models\EventProgramPoint;
use App\Models\EventSettlementCost;
use App\Models\VendorInvoice;
use App\Support\CalendarDay;
use App\Support\CurrencyAmountDisplay;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class EventPaymentScheduleService
{
    /** @var array<int, Collection<int, array<string, mixed>>> */
    private array $cacheByEventId = [];

    /** @var array<int, Collection<int, array<string, mixed>>> */
    private array $cacheByPointId = [];

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function collectForProgramPoint(EventProgramPoint $point, ?Event $event = null): Collection
    {
        if (isset($this->cacheByPointId[$point->id])) {
            return $this->cacheByPointId[$point->id];
        }

        $event ??= $point->event;
        $rows = $this->buildScheduleForProgramPoint($point, $event);
        $this->cacheByPointId[$point->id] = $rows;

        return $rows;
    }

    /**
     * @param  Collection<int, EventProgramPoint>|EloquentCollection<int, EventProgramPoint>  $points
     */
    public function warmCacheForProgramPoints(Collection|EloquentCollection $points, Event $event): void
    {
        $missing = $points->filter(fn (EventProgramPoint $point): bool => ! isset($this->cacheByPointId[$point->id]));

        if ($missing->isEmpty()) {
            return;
        }

        $pointIds = $missing->pluck('id');
        $settlement = $event->relationLoaded('activeSettlement')
            ? $event->activeSettlement
            : $event->activeSettlement()->first();

        $costsByPointId = collect();

        if ($settlement) {
            $costsByPointId = $settlement->costs()
                ->whereIn('source_id', $pointIds)
                ->whereIn('source_type', ['program_point', 'program_point_payment'])
                ->with('plannedCurrency')
                ->get()
                ->groupBy('source_id');
        }

        $invoicesByPointId = collect();

        if (Schema::hasTable('vendor_invoices')) {
            $invoices = VendorInvoice::queryForProgramPoints($pointIds)
                ->with(['programPoints:id'])
                ->get();

            foreach ($pointIds as $pointId) {
                $invoicesByPointId->put(
                    $pointId,
                    $invoices
                        ->filter(fn (VendorInvoice $invoice): bool => $invoice->isLinkedToProgramPoint((int) $pointId))
                        ->values()
                );
            }
        }

        foreach ($missing as $point) {
            $this->cacheByPointId[$point->id] = $this->buildScheduleForProgramPoint(
                $point,
                $event,
                $costsByPointId->get($point->id, collect()),
                $invoicesByPointId->get($point->id, collect()),
            );
        }
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function buildScheduleForProgramPoint(
        EventProgramPoint $point,
        ?Event $event,
        ?Collection $costs = null,
        ?Collection $invoices = null,
    ): Collection {
        $event ??= $point->event;

        if (! $event) {
            return collect();
        }

        $rows = collect();

        foreach ($this->resolveProgramPointCosts($point, $costs) as $cost) {
            $row = $this->mapSettlementCostRow($cost, $event);

            if ($row !== null) {
                $rows->push($row);
            }
        }

        foreach ($this->resolveProgramPointInvoices($point, $invoices) as $invoice) {
            $row = $this->mapProgramPointVendorInvoiceRow($invoice, $point, $event);

            if ($row !== null) {
                $rows->push($row);
            }
        }

        return $rows
            ->sortBy([
                ['due_date', 'asc'],
                ['kind_label', 'asc'],
            ])
            ->values();
    }

    /**
     * @return Collection<int, EventSettlementCost>
     */
    private function resolveProgramPointCosts(EventProgramPoint $point, ?Collection $costs = null): Collection
    {
        if ($costs !== null) {
            return $costs instanceof EloquentCollection ? $costs : collect($costs->all());
        }

        $event = $point->event;
        $settlement = $event?->activeSettlement;

        if (! $settlement) {
            return collect();
        }

        return $settlement->costs()
            ->where('source_id', $point->id)
            ->whereIn('source_type', ['program_point', 'program_point_payment'])
            ->with('plannedCurrency')
            ->get();
    }

    /**
     * @return Collection<int, VendorInvoice>
     */
    private function resolveProgramPointInvoices(EventProgramPoint $point, ?Collection $invoices = null): Collection
    {
        if (! Schema::hasTable('vendor_invoices')) {
            return collect();
        }

        if ($invoices !== null) {
            return $invoices instanceof EloquentCollection ? $invoices : collect($invoices->all());
        }

        if ($point->relationLoaded('vendorInvoices')) {
            return $point->vendorInvoices;
        }

        return VendorInvoice::queryForProgramPoints([$point->id])->get();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function mapProgramPointVendorInvoiceRow(VendorInvoice $invoice, EventProgramPoint $point, ?Event $event): ?array
    {
        if (! $invoice->due_date || ! in_array($invoice->payment_status, ['due', 'partial'], true)) {
            return null;
        }

        $gross = (float) ($invoice->gross_amount ?? 0);
        $paid = (float) ($invoice->paid_amount ?? 0);
        $remaining = max(0, $gross - $paid);
        $amount = $remaining > 0 ? $remaining : $gross;
        $hasPdf = filled($invoice->pdf_path);

        return [
            'id' => 'vendor-'.$invoice->id,
            'kind' => 'vendor_invoice',
            'kind_label' => 'Faktura KSeF',
            'due_date' => $invoice->due_date->toDateString(),
            'title' => $invoice->invoice_number ?: ($invoice->ksef_number ?: 'Faktura #'.$invoice->id),
            'amount_label' => \App\Support\MoneyFormatter::format($amount, $invoice->currency ?: 'PLN'),
            'phrase' => 'Faktura — termin',
            'is_overdue' => CalendarDay::isBeforeToday($invoice->due_date),
            'remaining_label' => null,
            'status' => 'due',
            'url' => \App\Support\AdminPanelUrls::vendorInvoiceEdit($invoice),
            'source_type' => 'vendor_invoice',
            'source_id' => $invoice->id,
            'event_id' => $event?->id ?? $point->event_id,
            'program_point_id' => $point->id,
            'invoice_number' => $invoice->invoice_number,
            'payment_status' => $invoice->payment_status,
            'has_pdf' => $hasPdf,
            'pdf_url' => $hasPdf ? $invoice->pdf_url : null,
            'color' => '#b45309',
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function collectForEvent(Event $event, ?Carbon $from = null, ?Carbon $to = null): Collection
    {
        if (isset($this->cacheByEventId[$event->id])) {
            return $this->filterByDateRange($this->cacheByEventId[$event->id], $from, $to);
        }

        $rows = $this->buildScheduleForEvent($event);
        $this->cacheByEventId[$event->id] = $rows;

        return $this->filterByDateRange($rows, $from, $to);
    }

    /**
     * @param  Collection<int, Event>|EloquentCollection<int, Event>  $events
     */
    public function warmCacheForEvents(Collection|EloquentCollection $events): void
    {
        $missing = $events->filter(fn (Event $event): bool => ! isset($this->cacheByEventId[$event->id]));

        if ($missing->isEmpty()) {
            return;
        }

        foreach ($missing as $event) {
            $this->cacheByEventId[$event->id] = $this->buildScheduleForEvent($event);
        }
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function buildScheduleForEvent(Event $event): Collection
    {
        $rows = collect();

        foreach ($this->resolveSettlementCosts($event) as $cost) {
            $row = $this->mapSettlementCostRow($cost, $event);

            if ($row !== null) {
                $rows->push($row);
            }
        }

        foreach ($this->resolveContractSchedules($event) as $schedule) {
            $row = $this->mapContractScheduleRow($schedule, $event);

            if ($row !== null) {
                $rows->push($row);
            }
        }

        foreach ($this->resolveAgreementSchedules($event) as $schedule) {
            $row = $this->mapAgreementScheduleRow($schedule, $event);

            if ($row !== null) {
                $rows->push($row);
            }
        }

        foreach ($this->resolveVendorInvoices($event) as $invoice) {
            $row = $this->mapVendorInvoiceRow($invoice, $event);

            if ($row !== null) {
                $rows->push($row);
            }
        }

        return $rows
            ->sortBy([
                ['due_date', 'asc'],
                ['kind_label', 'asc'],
            ])
            ->values();
    }

    /**
     * @return Collection<int, EventSettlementCost>
     */
    private function resolveSettlementCosts(Event $event): Collection
    {
        if ($event->relationLoaded('settlements')) {
            return $event->settlements
                ->flatMap(fn ($settlement) => $settlement->relationLoaded('costs')
                    ? $settlement->costs
                    : collect())
                ->values();
        }

        return EventSettlementCost::query()
            ->whereHas('settlement', fn ($query) => $query->where('event_id', $event->id))
            ->with(['plannedCurrency', 'settlement.event'])
            ->get();
    }

    /**
     * @return Collection<int, ContractPaymentSchedule>
     */
    private function resolveContractSchedules(Event $event): Collection
    {
        if (! Schema::hasTable('contract_payment_schedules')) {
            return collect();
        }

        if ($event->relationLoaded('agreements')) {
            return $event->agreements
                ->filter(fn ($agreement): bool => $agreement instanceof Contract)
                ->flatMap(fn (Contract $contract) => $contract->relationLoaded('paymentSchedules')
                    ? $contract->paymentSchedules
                    : collect())
                ->values();
        }

        return ContractPaymentSchedule::query()
            ->whereHas('contract', fn ($query) => $query->where('event_id', $event->id))
            ->with(['contract.paymentSchedules', 'contract.event'])
            ->get();
    }

    /**
     * @return Collection<int, EventAgreementPaymentSchedule>
     */
    private function resolveAgreementSchedules(Event $event): Collection
    {
        if (! Schema::hasTable('event_agreement_payment_schedules')) {
            return collect();
        }

        return EventAgreementPaymentSchedule::query()
            ->whereHas('eventAgreement', fn ($query) => $query->where('event_id', $event->id))
            ->with(['eventAgreement.paymentSchedules', 'eventAgreement.event'])
            ->get();
    }

    /**
     * @return Collection<int, VendorInvoice>
     */
    private function resolveVendorInvoices(Event $event): Collection
    {
        if (! Schema::hasTable('vendor_invoices')) {
            return collect();
        }

        if ($event->relationLoaded('vendorInvoices')) {
            return $event->vendorInvoices;
        }

        return VendorInvoice::query()
            ->where('event_id', $event->id)
            ->get();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function mapSettlementCostRow(EventSettlementCost $cost, Event $event): ?array
    {
        if (in_array($cost->payment_status, ['paid', 'cancelled'], true)) {
            return null;
        }

        $kind = $this->resolveSettlementKind($cost);

        if ($kind === null) {
            return null;
        }

        if ($this->isAdvanceMarkedPaid($cost)) {
            return $this->mapPaidAdvanceRow($cost, $event, $kind === 'plan' ? 'advance' : $kind);
        }

        if (! $this->isSettlementCostOutstanding($cost, $kind)) {
            return null;
        }

        $dueDate = $cost->advance_due_date;

        if (! $dueDate) {
            return null;
        }

        $displayKind = $kind === 'plan' && $this->looksLikeDeposit($cost) ? 'advance' : $kind;

        return [
            'id' => 'cost-'.$cost->id,
            'kind' => $displayKind,
            'kind_label' => $this->kindLabel($displayKind),
            'phrase' => $this->unpaidPhrase($displayKind),
            'due_date' => $dueDate->toDateString(),
            'title' => $cost->name ?: 'Koszt rozliczenia',
            'amount_label' => $this->formatSettlementCostAmount($cost, $displayKind),
            'remaining_label' => null,
            'status' => 'due',
            'is_overdue' => CalendarDay::isBeforeToday($dueDate),
            'url' => $cost->settlement_id
                ? \App\Support\AdminPanelUrls::eventFinanceForSettlement($cost->settlement_id)
                : \App\Support\AdminPanelUrls::eventFinance($event),
            'source_type' => 'settlement_cost',
            'source_id' => $cost->id,
            'event_id' => $event->id,
            'color' => $displayKind === 'advance' ? '#0d9488' : '#ea580c',
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function mapPaidAdvanceRow(EventSettlementCost $cost, Event $event, string $kind): ?array
    {
        $paidAt = $cost->paid_at ?? $cost->advance_due_date;

        if (! $paidAt) {
            return null;
        }

        $amountKind = $kind === 'plan' ? 'advance' : $kind;
        $remaining = $this->remainingAfterAdvance($cost);
        $remainingLabel = $remaining > 0.009
            ? CurrencyAmountDisplay::format(
                $remaining,
                $cost->plannedCurrency,
                (bool) ($cost->planned_convert_to_pln ?? true),
            )
            : null;

        return [
            'id' => 'cost-paid-'.$cost->id,
            'kind' => 'advance_paid',
            'kind_label' => 'Zaliczka zapłacona',
            'phrase' => 'Zaliczka zapłacona',
            'due_date' => $paidAt->toDateString(),
            'title' => $cost->name ?: 'Koszt rozliczenia',
            'amount_label' => $this->formatSettlementCostAmount($cost, $amountKind),
            'remaining_label' => $remainingLabel,
            'status' => 'paid',
            'is_overdue' => false,
            'url' => $cost->settlement_id
                ? \App\Support\AdminPanelUrls::eventFinanceForSettlement($cost->settlement_id)
                : \App\Support\AdminPanelUrls::eventFinance($event),
            'source_type' => 'settlement_cost',
            'source_id' => $cost->id,
            'event_id' => $event->id,
            'color' => '#166534',
        ];
    }

    private function remainingAfterAdvance(EventSettlementCost $cost): float
    {
        $planned = round((float) ($cost->planned_amount ?? 0), 2);
        $paid = round((float) ($cost->advance_amount ?? 0), 2);

        if ($paid <= 0.009) {
            $paid = round((float) ($cost->actual_amount ?? 0), 2);
        }

        return max(0.0, round($planned - $paid, 2));
    }

    private function unpaidPhrase(string $kind): string
    {
        return match ($kind) {
            'advance' => 'Zaliczka do zapłaty',
            'plan' => 'Termin płatności',
            'payment' => 'Wpłata do',
            default => 'Płatność do',
        };
    }

    private function looksLikeDeposit(EventSettlementCost $cost): bool
    {
        return in_array($cost->advance_type, ['advance', 'deposit'], true)
            || (float) ($cost->advance_amount ?? 0) > 0.009
            || in_array($cost->payment_status, ['advance_required', 'reserved', 'advance_paid'], true);
    }

    private function isAdvanceMarkedPaid(EventSettlementCost $cost): bool
    {
        if (in_array($cost->payment_status, ['advance_paid'], true)) {
            return true;
        }

        return filled($cost->paid_at) && $this->looksLikeDeposit($cost);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function mapContractScheduleRow(ContractPaymentSchedule $schedule, Event $event): ?array
    {
        if (! $schedule->due_date) {
            return null;
        }

        $contract = $schedule->contract;

        if (! $contract || ! $this->isScheduleOutstanding($schedule, $contract, $contract->paymentSchedules ?? collect())) {
            return null;
        }

        return [
            'id' => 'contract-'.$schedule->id,
            'kind' => 'contract_schedule',
            'kind_label' => 'Rata kontraktu',
            'due_date' => $schedule->due_date->toDateString(),
            'title' => $schedule->label ?: ('Rata #'.($schedule->sort_order ?? '?')),
            'amount_label' => \App\Support\MoneyFormatter::format((float) $schedule->amount, $contract->currency ?: 'PLN'),
            'phrase' => 'Rata kontraktu do',
            'is_overdue' => CalendarDay::isBeforeToday($schedule->due_date),
            'remaining_label' => null,
            'status' => 'due',
            'url' => $contract->id
                ? \App\Support\AdminPanelUrls::contractEdit($contract)
                : null,
            'source_type' => 'contract_schedule',
            'source_id' => $schedule->id,
            'event_id' => $event->id,
            'color' => '#7c3aed',
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function mapAgreementScheduleRow(EventAgreementPaymentSchedule $schedule, Event $event): ?array
    {
        if (! $schedule->due_date) {
            return null;
        }

        $agreement = $schedule->eventAgreement;

        if (! $agreement || ! $this->isScheduleOutstanding($schedule, $agreement, $agreement->paymentSchedules ?? collect())) {
            return null;
        }

        return [
            'id' => 'agreement-'.$schedule->id,
            'kind' => 'agreement_schedule',
            'kind_label' => 'Rata umowy',
            'due_date' => $schedule->due_date->toDateString(),
            'title' => $schedule->label ?: ('Rata #'.($schedule->sort_order ?? '?')),
            'amount_label' => \App\Support\MoneyFormatter::format((float) $schedule->amount, 'PLN'),
            'phrase' => 'Rata umowy do',
            'is_overdue' => CalendarDay::isBeforeToday($schedule->due_date),
            'remaining_label' => null,
            'status' => 'due',
            'url' => \App\Support\AdminPanelUrls::eventEdit($event),
            'source_type' => 'agreement_schedule',
            'source_id' => $schedule->id,
            'event_id' => $event->id,
            'color' => '#2563eb',
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function mapVendorInvoiceRow(VendorInvoice $invoice, Event $event): ?array
    {
        if (! $invoice->due_date || ! in_array($invoice->payment_status, ['due', 'partial'], true)) {
            return null;
        }

        $gross = (float) ($invoice->gross_amount ?? 0);
        $paid = (float) ($invoice->paid_amount ?? 0);
        $remaining = max(0, $gross - $paid);
        $amount = $remaining > 0 ? $remaining : $gross;

        return [
            'id' => 'vendor-'.$invoice->id,
            'kind' => 'vendor_invoice',
            'kind_label' => 'Faktura KSeF',
            'due_date' => $invoice->due_date->toDateString(),
            'title' => $invoice->invoice_number ?: ($invoice->ksef_number ?: 'Faktura #'.$invoice->id),
            'amount_label' => \App\Support\MoneyFormatter::format($amount, $invoice->currency ?: 'PLN'),
            'phrase' => 'Faktura — termin',
            'is_overdue' => CalendarDay::isBeforeToday($invoice->due_date),
            'remaining_label' => null,
            'status' => 'due',
            'url' => \App\Support\AdminPanelUrls::vendorInvoiceEdit($invoice),
            'source_type' => 'vendor_invoice',
            'source_id' => $invoice->id,
            'event_id' => $event->id,
            'color' => '#b45309',
        ];
    }

    private function resolveSettlementKind(EventSettlementCost $cost): ?string
    {
        if ($cost->source_type === 'program_point') {
            return $this->looksLikeDeposit($cost) ? 'advance' : 'plan';
        }

        if ($cost->source_type === 'program_point_payment') {
            if (in_array($cost->advance_type, ['advance', 'deposit'], true) || (float) ($cost->advance_amount ?? 0) > 0) {
                return 'advance';
            }

            return 'payment';
        }

        if (in_array($cost->advance_type, ['advance', 'deposit'], true)) {
            return 'advance';
        }

        return 'payment';
    }

    private function isSettlementCostOutstanding(EventSettlementCost $cost, string $kind): bool
    {
        if (in_array($cost->payment_status, ['paid', 'cancelled'], true)) {
            return false;
        }

        if ($kind === 'advance') {
            if (filled($cost->paid_at) || in_array($cost->payment_status, ['advance_paid', 'paid'], true)) {
                return false;
            }
        }

        if ($kind === 'payment') {
            if (filled($cost->paid_at) || filled($cost->actual_amount)) {
                return false;
            }
        }

        return true;
    }

    private function formatSettlementCostAmount(EventSettlementCost $cost, string $kind): string
    {
        $amount = match ($kind) {
            'advance', 'advance_paid' => (float) ($cost->advance_amount ?? 0) > 0.009
                ? (float) $cost->advance_amount
                : (float) ($cost->planned_amount ?? 0),
            default => (float) ($cost->planned_amount ?? $cost->advance_amount ?? 0),
        };

        if ($amount <= 0.009) {
            return 'kwota nieustalona';
        }

        return CurrencyAmountDisplay::format(
            $amount,
            $cost->plannedCurrency,
            (bool) ($cost->planned_convert_to_pln ?? true),
        );
    }

    private function kindLabel(string $kind): string
    {
        return match ($kind) {
            'plan' => 'Termin płatności',
            'advance' => 'Zaliczka',
            'advance_paid' => 'Zaliczka zapłacona',
            'payment' => 'Wpłata',
            'contract_schedule' => 'Rata kontraktu',
            'agreement_schedule' => 'Rata umowy',
            'vendor_invoice' => 'Faktura KSeF',
            default => 'Płatność',
        };
    }

    /**
     * @param  EloquentCollection<int, ContractPaymentSchedule|EventAgreementPaymentSchedule>|Collection<int, ContractPaymentSchedule|EventAgreementPaymentSchedule>  $schedules
     */
    private function isScheduleOutstanding(
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

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    private function filterByDateRange(Collection $rows, ?Carbon $from, ?Carbon $to): Collection
    {
        $from ??= now()->subMonths(1)->startOfDay();
        $to ??= now()->addMonths(12)->endOfDay();

        return $rows
            ->filter(function (array $row) use ($from, $to): bool {
                $due = Carbon::parse($row['due_date']);

                return $due->between($from, $to);
            })
            ->values();
    }
}
