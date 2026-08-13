<?php

namespace App\Services;

use App\Models\EventAgreement;
use App\Models\EventSettlement;
use App\Models\EventSettlementParticipantPayment;

class AgreementPaymentSyncService
{
    public function sync(EventAgreement $agreement, ?EventSettlement $targetSettlement = null): void
    {
        if (! $this->shouldSync($agreement)) {
            $this->remove($agreement);

            return;
        }

        $agreement->loadMissing('event');

        $bookingReference = $agreement->agreement_number ?: ('UMOWA-'.$agreement->id);
        $participantName = $agreement->signer_name
            ?: $agreement->participant_name
            ?: $agreement->customer_name
            ?: 'Klient';

        $paymentMethod = match ($agreement->payment_method) {
            'demo_card' => 'card',
            'demo_transfer' => 'transfer',
            'demo_blik' => 'other',
            default => 'other',
        };

        if (! $agreement->event) {
            return;
        }

        $participantPayment = $this->resolveParticipantPayment($agreement, $bookingReference);
        $settlement = $targetSettlement
            ?? $participantPayment?->settlement
            ?? EventSettlement::findOrCreateActiveForEvent($agreement->event);

        if (! $participantPayment) {
            $participantPayment = new EventSettlementParticipantPayment;
        }

        $participantPayment->settlement()->associate($settlement);
        $ledgerPaid = $participantPayment->exists
            ? (float) app(ParticipantPaymentLedgerService::class)->resolvedPaidAmount($participantPayment)
            : 0.0;
        $agreementPaid = (float) ($agreement->amount_paid ?? 0);
        // Demo/flow płatności ustawia amount_paid na umowie — ledger może jeszcze nie mieć wpisu.
        $effectivePaidAmount = round(max($ledgerPaid, $agreementPaid), 2);
        $participantPayment->fill([
            'participant_name' => $participantName,
            'booking_reference' => $bookingReference,
            'due_amount_pln' => (float) $agreement->amount_due,
            'paid_amount_pln' => $effectivePaidAmount,
            'payment_date' => $agreement->paid_at,
            'payment_method' => $paymentMethod,
            'document_number' => $agreement->agreement_number,
            'attended' => $participantPayment->exists ? $participantPayment->attended : true,
            'notes' => 'Synchronizacja z umowy #'.$agreement->id,
        ]);

        if ($agreement->payment_status === 'paid' || $effectivePaidAmount >= (float) $agreement->amount_due) {
            $participantPayment->payment_status = 'paid';
        } elseif ($effectivePaidAmount > 0) {
            $participantPayment->payment_status = 'partial';
        }
        $participantPayment->save();

        if ((int) $agreement->participant_payment_id !== (int) $participantPayment->id) {
            $agreement->forceFill([
                'participant_payment_id' => $participantPayment->id,
            ])->saveQuietly();
        }

        $settlement->recalculateTotals();
    }

    public function remove(EventAgreement $agreement): void
    {
        $participantPayment = $this->resolveParticipantPayment(
            $agreement,
            $agreement->agreement_number ?: ('UMOWA-'.$agreement->id)
        );

        if (! $participantPayment) {
            return;
        }

        $hasOtherLinkedAgreements = $participantPayment->agreements()
            ->whereKeyNot($agreement->id)
            ->exists();

        if ($hasOtherLinkedAgreements) {
            return;
        }

        if (! str_contains((string) $participantPayment->notes, 'Synchronizacja z umowy #')) {
            return;
        }

        $settlement = $participantPayment->settlement;
        $participantPayment->delete();
        $settlement?->recalculateTotals();
    }

    private function shouldSync(EventAgreement $agreement): bool
    {
        if (! $agreement->event_id) {
            return false;
        }

        if (($agreement->meta['is_individual_template'] ?? false) === true
            && empty($agreement->meta['parent_template_id'] ?? null)) {
            return false;
        }

        if (in_array($agreement->status, ['template', 'cancelled'], true)) {
            return false;
        }

        if ($agreement->payment_status === 'failed') {
            return false;
        }

        return in_array($agreement->status, ['sent', 'signed', 'completed'], true)
            || (float) ($agreement->amount_paid ?? 0) > 0
            || $agreement->participant_payment_id !== null;
    }

    private function resolveParticipantPayment(EventAgreement $agreement, string $bookingReference): ?EventSettlementParticipantPayment
    {
        if ($agreement->participant_payment_id) {
            return EventSettlementParticipantPayment::query()
                ->with('settlement')
                ->find($agreement->participant_payment_id);
        }

        if (! $agreement->event) {
            return null;
        }

        $settlementIds = $agreement->event->settlements()->pluck('id');

        if ($settlementIds->isEmpty()) {
            return null;
        }

        return EventSettlementParticipantPayment::query()
            ->with('settlement')
            ->whereIn('settlement_id', $settlementIds)
            ->where(function ($query) use ($bookingReference) {
                $query->where('booking_reference', $bookingReference)
                    ->orWhere('document_number', $bookingReference);
            })
            ->latest('id')
            ->first();
    }
}
