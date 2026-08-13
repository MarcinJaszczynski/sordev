<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\EventAgreement;
use App\Models\EventSettlement;
use App\Models\EventSettlementParticipantPayment;
use App\Models\EventSettlementParticipantPaymentEntry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class ParticipantPaymentLedgerService
{
    public function addEntry(
        EventSettlementParticipantPayment $payment,
        float $amount,
        Carbon|string|null $paidAt = null,
        string $source = EventSettlementParticipantPaymentEntry::SOURCE_MANUAL,
        ?string $paymentMethod = null,
        ?int $bankPaymentImportLineId = null,
        ?string $notes = null,
        ?int $createdBy = null,
        ?string $payerName = null,
        ?string $bankTransferDescription = null,
        string $paymentKind = EventSettlementParticipantPaymentEntry::KIND_REGULAR,
        ?float $amountForeign = null,
        ?float $rate = null,
        ?int $currencyId = null,
    ): EventSettlementParticipantPaymentEntry {
        if (! Schema::hasTable('event_settlement_participant_payment_entries')) {
            throw new \RuntimeException('Tabela historii wpłat nie jest dostępna.');
        }

        $amount = round($amount, 2);
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Kwota wpłaty musi być większa od zera.');
        }

        $paidAt = $paidAt instanceof Carbon
            ? $paidAt
            : Carbon::parse($paidAt ?? now());

        return DB::transaction(function () use (
            $payment,
            $amount,
            $paidAt,
            $source,
            $paymentMethod,
            $bankPaymentImportLineId,
            $notes,
            $createdBy,
            $payerName,
            $bankTransferDescription,
            $paymentKind,
            $amountForeign,
            $rate,
            $currencyId,
        ): EventSettlementParticipantPaymentEntry {
            $payload = [
                'participant_payment_id' => $payment->id,
                'paid_at' => $paidAt,
                'amount_pln' => $amount,
                'payer_name' => $payerName,
                'bank_transfer_description' => $bankTransferDescription,
                'payment_kind' => $paymentKind ?: EventSettlementParticipantPaymentEntry::KIND_REGULAR,
                'source' => $source,
                'payment_method' => $paymentMethod,
                'bank_payment_import_line_id' => $bankPaymentImportLineId,
                'notes' => $notes,
                'created_by' => $createdBy ?? Auth::id(),
            ];

            if (Schema::hasColumn('event_settlement_participant_payment_entries', 'currency_id')) {
                $payload['amount'] = $amountForeign !== null ? round($amountForeign, 2) : null;
                $payload['currency_id'] = $currencyId;
                $payload['rate'] = $rate !== null ? round($rate, 6) : null;
            }

            $entry = EventSettlementParticipantPaymentEntry::query()->create($payload);

            $this->syncPaidAggregate($payment->fresh(['entries']));

            return $entry;
        });
    }

    public function removeEntry(EventSettlementParticipantPaymentEntry $entry): void
    {
        if (! Schema::hasTable('event_settlement_participant_payment_entries')) {
            throw new \RuntimeException('Tabela historii wpłat nie jest dostępna.');
        }

        DB::transaction(function () use ($entry): void {
            $payment = $entry->participantPayment()->firstOrFail();
            $entry->delete();
            $this->syncPaidAggregate($payment->fresh(['entries']));
        });
    }

    public function syncPaidAggregate(EventSettlementParticipantPayment $payment): void
    {
        if (! Schema::hasTable('event_settlement_participant_payment_entries')) {
            return;
        }

        $payment->loadMissing('entries');

        $paidTotal = round((float) $payment->entries->sum('amount_pln'), 2);
        $latestPaidAt = $payment->entries
            ->sortByDesc(fn (EventSettlementParticipantPaymentEntry $entry) => $entry->paid_at?->timestamp ?? 0)
            ->first()
            ?->paid_at;

        $latestMethod = $payment->entries
            ->sortByDesc(fn (EventSettlementParticipantPaymentEntry $entry) => $entry->paid_at?->timestamp ?? 0)
            ->first()
            ?->payment_method;

        $payment->forceFill([
            'paid_amount_pln' => $paidTotal,
            'payment_date' => $paidTotal > 0 ? $latestPaidAt : null,
            'payment_method' => $latestMethod ?: $payment->payment_method,
        ])->save();

        $fresh = $payment->fresh();
        $this->syncLinkedContracts($fresh, $paidTotal);
        $this->syncLinkedAgreements($fresh, $paidTotal);

        $payment->loadMissing('settlement');
        $payment->settlement?->recalculateTotals();
    }

    public function resolvedPaidAmount(EventSettlementParticipantPayment $payment): float
    {
        if (Schema::hasTable('event_settlement_participant_payment_entries')) {
            $payment->loadMissing('entries');

            if ($payment->entries->isNotEmpty()) {
                return round((float) $payment->entries->sum('amount_pln'), 2);
            }
        }

        return round((float) $payment->paid_amount_pln, 2);
    }

    public function reconcileSettlement(EventSettlement $settlement): void
    {
        if (! Schema::hasTable('event_settlement_participant_payment_entries')) {
            return;
        }

        $settlement->loadMissing('participantPayments.entries');

        foreach ($settlement->participantPayments as $payment) {
            if ($payment->entries->isEmpty()) {
                continue;
            }

            $this->syncPaidAggregate($payment);
        }
    }

    private function syncLinkedContracts(EventSettlementParticipantPayment $payment, float $paidTotal): void
    {
        if (! Schema::hasTable('contracts')) {
            return;
        }

        $payment->loadMissing('contracts');

        foreach ($payment->contracts as $contract) {
            if (! $contract instanceof Contract) {
                continue;
            }

            $dueTotal = (float) ($contract->total_price ?: $payment->due_amount_pln);

            $contract->forceFill([
                'amount_paid' => $paidTotal,
                'payment_status' => $this->resolveLinkedPaymentStatus($paidTotal, $dueTotal),
                'paid_at' => $paidTotal > 0 ? ($contract->paid_at ?? now()) : $contract->paid_at,
            ])->saveQuietly();
        }
    }

    private function syncLinkedAgreements(EventSettlementParticipantPayment $payment, float $paidTotal): void
    {
        if (! Schema::hasTable('event_agreements')) {
            return;
        }

        $payment->loadMissing('agreements');

        foreach ($payment->agreements as $agreement) {
            if (! $agreement instanceof EventAgreement) {
                continue;
            }

            $dueTotal = (float) ($agreement->amount_due ?: $payment->due_amount_pln);

            $agreement->forceFill([
                'amount_paid' => $paidTotal,
                'payment_status' => $this->resolveLinkedPaymentStatus($paidTotal, $dueTotal),
                'paid_at' => $paidTotal > 0 ? ($agreement->paid_at ?? now()) : $agreement->paid_at,
            ])->saveQuietly();
        }
    }

    private function resolveLinkedPaymentStatus(float $paidTotal, float $dueTotal): string
    {
        if ($paidTotal <= 0) {
            return 'pending';
        }

        if ($paidTotal >= $dueTotal - SettlementPaymentHealthService::TOLERANCE) {
            return 'paid';
        }

        return 'partial';
    }
}
