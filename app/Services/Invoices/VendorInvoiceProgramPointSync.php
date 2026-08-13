<?php

namespace App\Services\Invoices;

use App\Models\Currency;
use App\Models\EventProgramPoint;
use App\Models\EventSettlement;
use App\Models\EventSettlementCost;
use App\Models\VendorInvoice;
use Illuminate\Support\Facades\DB;

class VendorInvoiceProgramPointSync
{
    private const PAYMENT_SOURCE_TYPE = 'program_point_payment';

    public function sync(VendorInvoice $invoice): ?EventSettlementCost
    {
        if (! $invoice->event_id || ! $invoice->event_program_point_id) {
            return null;
        }

        $point = EventProgramPoint::query()
            ->where('event_id', $invoice->event_id)
            ->find($invoice->event_program_point_id);

        if (! $point) {
            return null;
        }

        return DB::transaction(function () use ($invoice, $point): EventSettlementCost {
            $settlement = EventSettlement::findOrCreateActiveForEvent($point->event);
            $cost = $settlement->upsertCostFromProgramPoint($point->loadMissing('templatePoint', 'currency'));

            $currency = $this->resolveCurrency($invoice);
            $currencyId = $currency?->id ?? $cost->planned_currency_id ?? $point->currency_id;
            $rate = (float) ($currency?->exchange_rate ?? $cost->planned_rate ?? 1);
            $gross = (float) ($invoice->gross_amount ?? 0);

            if ($gross <= 0) {
                return $cost;
            }

            $actualPln = round($gross * max(0, $rate), 2);

            $payment = $settlement->costs()
                ->where('source_type', self::PAYMENT_SOURCE_TYPE)
                ->where('source_id', $point->id)
                ->where('document_number', $invoice->invoice_number ?? $invoice->ksef_number)
                ->first();

            $invoiceIsPaid = $invoice->payment_status === 'paid';
            $actualAmount = $invoiceIsPaid ? $gross : null;
            $actualAmountPln = $invoiceIsPaid ? $actualPln : null;

            $paymentPayload = [
                'source_type' => self::PAYMENT_SOURCE_TYPE,
                'source_id' => $point->id,
                'name' => ($cost->name ?: ($point->name ?? 'Punkt #'.$point->id)).' • KSeF',
                'planned_amount' => 0,
                'planned_currency_id' => $cost->planned_currency_id,
                'planned_rate' => $cost->planned_rate,
                'planned_amount_pln' => 0,
                // Actual tylko gdy faktura faktycznie opłacona — inaczej nie zawyża „Opłacone”.
                'actual_amount' => $actualAmount,
                'actual_currency_id' => $invoiceIsPaid ? $currencyId : null,
                'actual_rate' => $invoiceIsPaid ? $rate : null,
                'actual_amount_pln' => $actualAmountPln,
                'paid_by' => 'office',
                'advance_type' => 'full',
                'payment_method' => $invoice->payment_method,
                'document_number' => $invoice->invoice_number ?? $invoice->ksef_number,
                'paid_at' => $invoiceIsPaid ? ($invoice->payment_date ?? $invoice->issue_date) : null,
                'payment_status' => $invoiceIsPaid ? 'paid' : 'planned',
                'contractor_id' => $invoice->contractor_id ?? $cost->contractor_id,
                'notes' => 'Synchronizacja z faktury KSeF #'.$invoice->id
                    .($invoiceIsPaid ? '' : ' (faktura jeszcze nieopłacona)'),
            ];

            if ($payment) {
                $payment->update($paymentPayload);
            } else {
                $payment = $settlement->costs()->create($paymentPayload);
            }

            $health = app(\App\Services\SettlementPaymentHealthService::class);
            $allCosts = $settlement->costs()->get();
            $paidPln = $health->paidPlnForPlanCost($cost, $allCosts);
            $plannedPln = $health->plannedPlnForCost($cost);
            $statusFromPayments = $health->planPaymentStatusFromAmounts($paidPln, $plannedPln);

            $cost->update([
                'actual_amount' => null,
                'actual_currency_id' => null,
                'actual_rate' => null,
                'actual_amount_pln' => null,
                'payment_status' => $statusFromPayments,
            ]);

            $point->paid_price = $paidPln;
            $point->saveQuietly();

            $invoice->update([
                'event_settlement_cost_id' => $cost->id,
            ]);

            $settlement->recalculateTotals();

            return $cost->fresh();
        });
    }

    private function resolveCurrency(VendorInvoice $invoice): ?Currency
    {
        $code = strtoupper(trim((string) ($invoice->currency ?? 'PLN')));

        return Currency::query()
            ->where(function ($query) use ($code): void {
                $query->where('code', $code)->orWhere('symbol', $code);
            })
            ->orderBy('id')
            ->first();
    }
}
