<?php

namespace App\Services\Contracts\PaymentStrategies;

use App\Models\Contract;
use App\Models\EventParticipant;
use App\Models\EventSettlement;
use App\Models\EventSettlementParticipantPayment;
use App\Services\ContractGroupPricingService;
use App\Services\Contracts\ContractNumberAllocator;

class GroupIndividualPaymentsStrategy extends AbstractContractPaymentStrategy
{
    public function sync(Contract $contract, ?EventSettlement $targetSettlement = null): void
    {
        $contract->loadMissing(['event.agreements', 'event.activeParticipants']);

        if (! $contract->event) {
            return;
        }

        $settlement = $targetSettlement ?? EventSettlement::findOrCreateActiveForEvent($contract->event);
        $unitPrice = app(ContractGroupPricingService::class)->resolvedUnitPrice($contract);
        $targetCount = max(1, (int) ($contract->participant_count ?? 1));
        $linkedPaymentIds = [];
        $totalPaid = 0.0;

        foreach ($this->resolvePayingSlots($contract, $unitPrice, $targetCount) as $slot) {
            $participantPayment = null;

            if ($slot['participant_payment_id']) {
                $participantPayment = EventSettlementParticipantPayment::query()->find($slot['participant_payment_id']);
            }

            if (! $participantPayment) {
                $participantPayment = $this->resolveParticipantPayment($contract, $slot['booking_reference']);
            }

            $participantPayment = $this->upsertParticipantPayment(
                $contract,
                $settlement,
                $participantPayment,
                $slot['booking_reference'],
                $slot['participant_name'],
                $slot['due_amount'],
                $slot['paid_amount'],
                [
                    'notes' => $this->groupPaymentNote($contract->id, $slot['source']),
                ],
            );

            $linkedPaymentIds[] = (int) $participantPayment->id;
            $totalPaid += (float) $participantPayment->paid_amount_pln;

            if ($slot['event_participant_id']) {
                EventParticipant::query()
                    ->whereKey($slot['event_participant_id'])
                    ->update(['participant_payment_id' => $participantPayment->id]);
            }
        }

        $this->syncGroupContractAggregate($contract, $totalPaid, $linkedPaymentIds);
        app(\App\Services\ContractParticipantPaymentLinkService::class)
            ->syncForContract($contract->fresh(), $linkedPaymentIds);
        $settlement->recalculateTotals();
    }

    public function remove(Contract $contract): void
    {
        $payments = EventSettlementParticipantPayment::query()
            ->where('notes', 'like', '%umowy grupowej #'.$contract->id.'%')
            ->get();

        foreach ($payments as $participantPayment) {
            if ($participantPayment->hasLinkedAgreementsBesides($contract->id)) {
                continue;
            }

            $settlement = $participantPayment->settlement;
            $participantPayment->delete();
            $settlement?->recalculateTotals();
        }
    }

    public function progress(Contract $contract): array
    {
        $contract->loadMissing('event');
        $targetCount = max(1, (int) ($contract->participant_count ?? 1));
        $unitPrice = app(ContractGroupPricingService::class)->resolvedUnitPrice($contract);
        $amountDue = round($unitPrice * $targetCount, 2);

        $payments = $this->linkedPayments($contract);
        $paidCount = $payments->filter(fn (EventSettlementParticipantPayment $payment) => in_array(
            $payment->payment_status,
            ['paid', 'overpaid'],
            true
        ))->count();
        $amountPaid = (float) $payments->sum('paid_amount_pln');

        return [
            'paid_count' => $paidCount,
            'target_count' => $targetCount,
            'amount_due' => $amountDue,
            'amount_paid' => $amountPaid,
            'amount_remaining' => max(0, $amountDue - $amountPaid),
            'progress_label' => sprintf('%d/%d zapłaciło', $paidCount, $targetCount),
        ];
    }

    /**
     * @return array<int, array{
     *     participant_name: string,
     *     booking_reference: string,
     *     due_amount: float,
     *     paid_amount: float,
     *     participant_payment_id: ?int,
     *     event_participant_id: ?int,
     *     source: string
     * }>
     */
    protected function resolvePayingSlots(Contract $contract, float $unitPrice, int $targetCount): array
    {
        $event = $contract->event;
        $slots = [];
        $usedPaymentIds = [];
        $nextParticipantSequence = $this->nextParticipantSequence($contract);

        foreach ($this->linkedPayments($contract) as $existingPayment) {
            if (count($slots) >= $targetCount) {
                break;
            }

            $slots[] = [
                'participant_name' => (string) $existingPayment->participant_name,
                'booking_reference' => (string) $existingPayment->booking_reference,
                'due_amount' => (float) $existingPayment->due_amount_pln,
                'paid_amount' => (float) $existingPayment->paid_amount_pln,
                'participant_payment_id' => (int) $existingPayment->id,
                'event_participant_id' => null,
                'source' => 'existing_payment:'.$existingPayment->id,
            ];
            $usedPaymentIds[] = (int) $existingPayment->id;
        }

        $individualContracts = $event->agreements
            ->filter(function ($agreement) {
                $type = $agreement->contract_type ?? $agreement->agreement_type ?? null;

                return $type === Contract::TYPE_INDIVIDUAL
                    && ! in_array($agreement->status, ['template', 'cancelled'], true);
            });

        foreach ($individualContracts as $individualContract) {
            if (count($slots) >= $targetCount) {
                break;
            }

            $paymentId = $individualContract->participant_payment_id;
            if ($paymentId && in_array((int) $paymentId, $usedPaymentIds, true)) {
                continue;
            }

            $bookingReference = app(ContractNumberAllocator::class)->ensureOperationalNumber($individualContract)
                ?: $this->operationalReference($individualContract);

            $slots[] = [
                'participant_name' => $individualContract->participant_name ?: $individualContract->signer_name ?: 'Uczestnik',
                'booking_reference' => $bookingReference,
                'due_amount' => (float) ($individualContract->total_price ?: $unitPrice),
                'paid_amount' => (float) $individualContract->amount_paid,
                'participant_payment_id' => $paymentId ? (int) $paymentId : null,
                'event_participant_id' => null,
                'source' => 'individual_contract:'.$individualContract->id,
            ];

            if ($paymentId) {
                $usedPaymentIds[] = (int) $paymentId;
            }
        }

        $participants = $event->activeParticipants ?? collect();

        foreach ($participants as $participant) {
            if (count($slots) >= $targetCount) {
                break;
            }

            if ($participant->participant_payment_id && in_array((int) $participant->participant_payment_id, $usedPaymentIds, true)) {
                continue;
            }

            $bookingReference = filled($participant->booking_reference)
                ? (string) $participant->booking_reference
                : $this->allocateParticipantReference($contract, $nextParticipantSequence);

            $paid = 0.0;
            if ($participant->participant_payment_id) {
                $existing = EventSettlementParticipantPayment::query()->find($participant->participant_payment_id);
                $paid = (float) ($existing?->paid_amount_pln ?? 0);
            }

            $slots[] = [
                'participant_name' => $participant->fullName() ?: 'Uczestnik',
                'booking_reference' => $bookingReference,
                'due_amount' => $unitPrice,
                'paid_amount' => $paid,
                'participant_payment_id' => $participant->participant_payment_id ? (int) $participant->participant_payment_id : null,
                'event_participant_id' => (int) $participant->id,
                'source' => 'event_participant:'.$participant->id,
            ];

            if ($participant->participant_payment_id) {
                $usedPaymentIds[] = (int) $participant->participant_payment_id;
            }
        }

        while (count($slots) < $targetCount) {
            $slots[] = [
                'participant_name' => 'Uczestnik '.(count($slots) + 1),
                'booking_reference' => $this->allocateParticipantReference($contract, $nextParticipantSequence),
                'due_amount' => $unitPrice,
                'paid_amount' => 0.0,
                'participant_payment_id' => null,
                'event_participant_id' => null,
                'source' => 'placeholder:'.(count($slots) + 1),
            ];
        }

        return $slots;
    }

    protected function allocateParticipantReference(Contract $contract, int &$nextSequence): string
    {
        $contract->loadMissing('event');
        $event = $contract->event;

        if (! $event || blank($event->code)) {
            $reference = sprintf('UM-%d-U%03d', $contract->id, $nextSequence);
            $nextSequence++;

            return $reference;
        }

        $base = strtoupper(trim((string) $event->code));
        $reference = $base.'U'.str_pad((string) $nextSequence, 3, '0', STR_PAD_LEFT);
        $nextSequence++;

        return $reference;
    }

    protected function nextParticipantSequence(Contract $contract): int
    {
        $contract->loadMissing('event');
        $event = $contract->event;

        if (! $event || blank($event->code)) {
            return 1;
        }

        $base = strtoupper(trim((string) $event->code));

        $maxSequence = Contract::query()
            ->where('event_id', $event->id)
            ->whereNotNull('operational_number')
            ->pluck('operational_number')
            ->merge(
                EventSettlementParticipantPayment::query()
                    ->whereHas('settlement', fn ($query) => $query->where('event_id', $event->id))
                    ->pluck('booking_reference')
            )
            ->map(fn (?string $number) => $this->extractSequence($number, $base))
            ->max();

        return max(1, (int) $maxSequence + 1);
    }

    protected function extractSequence(?string $number, string $base): int
    {
        if (blank($number)) {
            return 0;
        }

        $pattern = '/^'.preg_quote($base, '/').'U(\d+)$/i';

        if (preg_match($pattern, strtoupper(trim($number)), $matches)) {
            return (int) $matches[1];
        }

        return 0;
    }

    /**
     * @param  array<int, int>  $linkedPaymentIds
     */
    protected function syncGroupContractAggregate(Contract $contract, float $totalPaid, array $linkedPaymentIds): void
    {
        $unitPrice = app(ContractGroupPricingService::class)->resolvedUnitPrice($contract);
        $amountDue = round($unitPrice * max(1, (int) ($contract->participant_count ?? 1)), 2);
        $paymentStatus = $totalPaid + 0.009 >= $amountDue && $amountDue > 0
            ? 'paid'
            : ($totalPaid > 0 ? 'pending' : (string) $contract->payment_status);

        $contract->forceFill([
            'amount_paid' => round($totalPaid, 2),
            'payment_status' => $paymentStatus,
        ])->saveQuietly();
    }

    protected function groupPaymentNote(int $contractId, string $source): string
    {
        return sprintf('Synchronizacja z umowy grupowej #%d (%s)', $contractId, $source);
    }

    /**
     * @return \Illuminate\Support\Collection<int, EventSettlementParticipantPayment>
     */
    protected function linkedPayments(Contract $contract): \Illuminate\Support\Collection
    {
        $ids = app(\App\Services\ContractParticipantPaymentLinkService::class)
            ->linkedPaymentIds($contract);

        if ($ids !== []) {
            return EventSettlementParticipantPayment::query()->whereIn('id', $ids)->get();
        }

        return EventSettlementParticipantPayment::query()
            ->where('notes', 'like', '%umowy grupowej #'.$contract->id.'%')
            ->get();
    }
}
