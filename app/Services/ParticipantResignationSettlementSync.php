<?php

namespace App\Services;

use App\Models\EventParticipantResignation;
use App\Models\EventSettlement;
use App\Models\EventSettlementParticipantPayment;

class ParticipantResignationSettlementSync
{
    public function sync(EventParticipantResignation $resignation): EventSettlementParticipantPayment
    {
        $resignation->loadMissing(['event', 'lines', 'contract', 'eventAgreement']);

        $event = $resignation->event;
        if (! $event) {
            throw new \RuntimeException('Brak powiązanej imprezy.');
        }

        $resignation->recalculateTotalsFromLines();

        $settlement = EventSettlement::findOrCreateActiveForEvent($event);
        $payment = $this->resolveParticipantPayment($resignation, $settlement);

        $paid = (float) $resignation->amount_paid_pln;
        $retention = (float) $resignation->retention_amount_pln;
        $refund = (float) $resignation->refund_amount_pln;

        if ($resignation->resignation_type === 'insurance') {
            $due = 0;
            $discount = 0;
        } else {
            $due = round(max(0, $retention), 2);
            $discount = round(max(0, (float) $resignation->amount_due_pln - $due), 2);
        }

        $payment->fill([
            'participant_name' => $resignation->participant_name,
            'booking_reference' => $this->resolveBookingReference($resignation),
            'due_amount_pln' => $due,
            'paid_amount_pln' => $paid,
            'discount_amount_pln' => $discount,
            'attended' => false,
            'payment_status' => $this->resolvePaymentStatus($paid, $due, $refund),
            'notes' => $this->buildPaymentNotes($resignation, $refund),
        ]);

        $payment->settlement()->associate($settlement);
        $payment->save();

        $resignation->forceFill([
            'settlement_id' => $settlement->id,
            'participant_payment_id' => $payment->id,
            'status' => 'settled',
            'synced_at' => now(),
            'refund_amount_pln' => $refund,
            'retention_amount_pln' => $retention,
        ])->saveQuietly();

        $settlement->recalculateTotals();

        return $payment->fresh();
    }

    private function resolveParticipantPayment(
        EventParticipantResignation $resignation,
        EventSettlement $settlement,
    ): EventSettlementParticipantPayment {
        if ($resignation->participant_payment_id) {
            $existing = EventSettlementParticipantPayment::find($resignation->participant_payment_id);
            if ($existing) {
                $existing->settlement()->associate($settlement);

                return $existing;
            }
        }

        if ($resignation->contract?->participant_payment_id) {
            $fromContract = EventSettlementParticipantPayment::find($resignation->contract->participant_payment_id);
            if ($fromContract) {
                $fromContract->settlement()->associate($settlement);

                return $fromContract;
            }
        }

        return new EventSettlementParticipantPayment;
    }

    private function resolveBookingReference(EventParticipantResignation $resignation): ?string
    {
        if ($resignation->contract?->contract_number) {
            return $resignation->contract->contract_number;
        }

        if ($resignation->eventAgreement?->agreement_number) {
            return $resignation->eventAgreement->agreement_number;
        }

        return $resignation->participantPayment?->booking_reference;
    }

    private function resolvePaymentStatus(float $paid, float $due, float $refund): string
    {
        if ($refund > 0 && $paid > $due) {
            return $paid <= 0 ? 'cancelled' : 'partial';
        }

        if ($paid <= 0) {
            return 'cancelled';
        }

        if ($paid < $due) {
            return 'partial';
        }

        if ($paid > $due) {
            return 'overpaid';
        }

        return 'paid';
    }

    private function buildPaymentNotes(EventParticipantResignation $resignation, float $refund): string
    {
        $type = EventParticipantResignation::$types[$resignation->resignation_type] ?? $resignation->resignation_type;
        $lines = [];

        $lines[] = "Rezygnacja ({$type}) z dnia ".($resignation->resigned_at?->format('d.m.Y') ?? '—').'.';

        if ($resignation->resignation_type === 'insurance' && filled($resignation->insurance_policy_number)) {
            $lines[] = 'Polisa: '.$resignation->insurance_policy_number.'.';
        }

        if ($refund > 0) {
            $lines[] = 'Do zwrotu uczestnikowi: '.number_format($refund, 2, ',', ' ').' PLN.';
        }

        if ((float) $resignation->retention_amount_pln > 0) {
            $lines[] = 'Potrącenie (zatrzymane): '.number_format((float) $resignation->retention_amount_pln, 2, ',', ' ').' PLN.';
        }

        if ($resignation->lines->isNotEmpty()) {
            $serviceLines = $resignation->lines
                ->map(fn ($line) => sprintf(
                    '%s — zwrot %s PLN, potrącenie %s PLN',
                    $line->description,
                    number_format((float) $line->refunded_amount_pln, 2, ',', ' '),
                    number_format((float) $line->retained_amount_pln, 2, ',', ' '),
                ))
                ->implode('; ');

            $lines[] = 'Świadczenia: '.$serviceLines.'.';
        }

        if (filled($resignation->reason)) {
            $lines[] = 'Powód: '.$resignation->reason;
        }

        if (filled($resignation->notes)) {
            $lines[] = $resignation->notes;
        }

        return implode(' ', $lines);
    }
}
