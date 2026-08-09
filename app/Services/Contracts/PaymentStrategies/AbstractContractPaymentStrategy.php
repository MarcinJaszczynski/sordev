<?php

namespace App\Services\Contracts\PaymentStrategies;

use App\Models\Contract;
use App\Models\EventSettlement;
use App\Models\EventSettlementParticipantPayment;
use App\Services\Contracts\ContractNumberAllocator;

abstract class AbstractContractPaymentStrategy implements ContractPaymentStrategy
{
    protected function operationalReference(Contract $contract): string
    {
        $number = app(ContractNumberAllocator::class)->ensureOperationalNumber($contract);

        return $number ?: ($contract->contract_number ?: ('UMOWA-'.$contract->id));
    }

    protected function resolveParticipantPayment(Contract $contract, string $bookingReference): ?EventSettlementParticipantPayment
    {
        if ($contract->participant_payment_id) {
            return EventSettlementParticipantPayment::query()
                ->with('settlement')
                ->find($contract->participant_payment_id);
        }

        if (! $contract->event) {
            return null;
        }

        $settlementIds = $contract->event->settlements()->pluck('id');

        if ($settlementIds->isEmpty()) {
            return null;
        }

        return EventSettlementParticipantPayment::query()
            ->with('settlement')
            ->whereIn('settlement_id', $settlementIds)
            ->where(function ($query) use ($bookingReference, $contract) {
                $query->where('booking_reference', $bookingReference)
                    ->orWhere('document_number', $bookingReference);

                if (filled($contract->contract_number)) {
                    $query->orWhere('booking_reference', $contract->contract_number)
                        ->orWhere('document_number', $contract->contract_number);
                }
            })
            ->latest('id')
            ->first();
    }

    protected function resolveSettlement(Contract $contract, ?EventSettlementParticipantPayment $participantPayment, ?EventSettlement $targetSettlement): ?EventSettlement
    {
        if ($targetSettlement) {
            return $targetSettlement;
        }

        if ($participantPayment?->settlement) {
            return $participantPayment->settlement;
        }

        if (! $contract->event) {
            return null;
        }

        return EventSettlement::findOrCreateActiveForEvent($contract->event);
    }

    protected function mapPaymentMethod(Contract $contract): string
    {
        return match ($contract->client_payment_method) {
            'demo_card' => 'card',
            'demo_transfer' => 'transfer',
            'demo_blik' => 'other',
            default => 'other',
        };
    }

    protected function participantName(Contract $contract): string
    {
        return $contract->signer_name
            ?: $contract->participant_name
            ?: $contract->customer_name
            ?: 'Klient';
    }

    protected function upsertParticipantPayment(
        Contract $contract,
        EventSettlement $settlement,
        ?EventSettlementParticipantPayment $participantPayment,
        string $bookingReference,
        string $participantName,
        float $dueAmount,
        float $paidAmount,
        array $extra = [],
    ): EventSettlementParticipantPayment {
        if (! $participantPayment) {
            $participantPayment = new EventSettlementParticipantPayment;
        }

        $participantPayment->settlement()->associate($settlement);
        $ledgerPaidAmount = $participantPayment->exists
            ? app(\App\Services\ParticipantPaymentLedgerService::class)->resolvedPaidAmount($participantPayment)
            : 0.0;
        $effectivePaidAmount = round(max($ledgerPaidAmount, $paidAmount), 2);

        $participantPayment->fill(array_merge([
            'participant_name' => $participantName,
            'booking_reference' => $bookingReference,
            'due_amount_pln' => round($dueAmount, 2),
            'paid_amount_pln' => $effectivePaidAmount,
            'payment_date' => $contract->paid_at,
            'payment_method' => $this->mapPaymentMethod($contract),
            'document_number' => $bookingReference,
            'attended' => $participantPayment->exists ? $participantPayment->attended : true,
            'notes' => 'Synchronizacja z umowy #'.$contract->id,
        ], $extra));

        $participantPayment->save();

        return $participantPayment;
    }

    protected function linkContractToPayment(Contract $contract, EventSettlementParticipantPayment $participantPayment): void
    {
        if ((int) $contract->participant_payment_id !== (int) $participantPayment->id) {
            $contract->forceFill([
                'participant_payment_id' => $participantPayment->id,
            ])->saveQuietly();
        }
    }

    protected function defaultProgress(Contract $contract): array
    {
        $amountDue = (float) $contract->total_price;
        $amountPaid = (float) $contract->amount_paid;
        $targetCount = max(1, (int) ($contract->participant_count ?? 1));
        $paidCount = $contract->payment_status === 'paid' ? $targetCount : ($amountPaid > 0 ? 1 : 0);

        return [
            'paid_count' => $paidCount,
            'target_count' => $targetCount,
            'amount_due' => $amountDue,
            'amount_paid' => $amountPaid,
            'amount_remaining' => max(0, $amountDue - $amountPaid),
            'progress_label' => sprintf('%d/%d', $paidCount, $targetCount),
        ];
    }
}
