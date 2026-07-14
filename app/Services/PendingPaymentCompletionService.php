<?php

namespace App\Services;

use App\Models\ContractPaymentSchedule;
use App\Models\EventAgreementPaymentSchedule;
use App\Models\EventSettlementCost;
use App\Models\VendorInvoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class PendingPaymentCompletionService
{
    public function complete(string $rowId): void
    {
        DB::transaction(function () use ($rowId): void {
            if (str_starts_with($rowId, 'cost-')) {
                $this->completeSettlementCost((int) substr($rowId, 5));

                return;
            }

            if (str_starts_with($rowId, 'vendor-')) {
                $this->completeVendorInvoice((int) substr($rowId, 7));

                return;
            }

            if (str_starts_with($rowId, 'contract-')) {
                $this->completeContractSchedule((int) substr($rowId, 9));

                return;
            }

            if (str_starts_with($rowId, 'agreement-')) {
                $this->completeAgreementSchedule((int) substr($rowId, 10));

                return;
            }

            throw new InvalidArgumentException('Nieznany identyfikator pozycji: '.$rowId);
        });
    }

    protected function completeSettlementCost(int $id): void
    {
        $cost = EventSettlementCost::query()->findOrFail($id);

        if (in_array($cost->payment_status, ['paid', 'cancelled'], true)) {
            return;
        }

        $planned = (float) ($cost->planned_amount ?? 0);
        $plannedPln = (float) ($cost->planned_amount_pln ?? $planned);

        $cost->update([
            'payment_status' => 'paid',
            'paid_at' => now(),
            'actual_amount' => $cost->actual_amount ?? $planned,
            'actual_amount_pln' => $cost->actual_amount_pln ?? $plannedPln,
        ]);
    }

    protected function completeVendorInvoice(int $id): void
    {
        if (! Schema::hasTable('vendor_invoices')) {
            throw new InvalidArgumentException('Tabela faktur dostawców nie jest dostępna.');
        }

        $invoice = VendorInvoice::query()->findOrFail($id);

        if ($invoice->payment_status === 'paid') {
            return;
        }

        $invoice->update([
            'payment_status' => 'paid',
            'paid_amount' => $invoice->gross_amount,
            'payment_date' => now(),
        ]);
    }

    protected function completeContractSchedule(int $scheduleId): void
    {
        $schedule = ContractPaymentSchedule::query()
            ->with(['contract.paymentSchedules'])
            ->findOrFail($scheduleId);

        $contract = $schedule->contract;
        if (! $contract) {
            throw new InvalidArgumentException('Brak kontraktu dla harmonogramu.');
        }

        $targetPaid = $this->cumulativeAmountThroughSchedule($schedule, $contract->paymentSchedules);
        $newPaid = max((float) $contract->amount_paid, $targetPaid);

        $schedule->update([
            'paid_amount' => round((float) $schedule->amount, 2),
            'paid_at' => now(),
        ]);

        $contract->update([
            'amount_paid' => round($newPaid, 2),
            'paid_at' => now(),
            'payment_status' => $newPaid + 0.009 >= (float) $contract->total_price ? 'paid' : 'pending',
        ]);

        app(ContractPaymentSyncService::class)->sync($contract->fresh());
    }

    protected function completeAgreementSchedule(int $scheduleId): void
    {
        $schedule = EventAgreementPaymentSchedule::query()
            ->with(['eventAgreement.paymentSchedules'])
            ->findOrFail($scheduleId);

        $agreement = $schedule->eventAgreement;
        if (! $agreement) {
            throw new InvalidArgumentException('Brak umowy dla harmonogramu.');
        }

        $targetPaid = $this->cumulativeAmountThroughSchedule($schedule, $agreement->paymentSchedules);
        $newPaid = max((float) $agreement->amount_paid, $targetPaid);

        $agreement->update([
            'amount_paid' => round($newPaid, 2),
            'paid_at' => now(),
            'payment_status' => $newPaid + 0.009 >= (float) $agreement->amount_due ? 'paid' : 'pending',
        ]);

        app(AgreementPaymentSyncService::class)->sync($agreement->fresh());
    }

    /**
     * @param  \Illuminate\Support\Collection<int, ContractPaymentSchedule|EventAgreementPaymentSchedule>  $schedules
     */
    protected function cumulativeAmountThroughSchedule(
        ContractPaymentSchedule|EventAgreementPaymentSchedule $target,
        $schedules,
    ): float {
        $cumulative = 0.0;

        foreach ($schedules->sortBy('sort_order') as $row) {
            $cumulative += (float) $row->amount;

            if ((int) $row->id === (int) $target->id) {
                return round($cumulative, 2);
            }
        }

        return round((float) $target->amount, 2);
    }
}
