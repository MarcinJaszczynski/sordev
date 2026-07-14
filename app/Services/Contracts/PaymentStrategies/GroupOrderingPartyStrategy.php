<?php

namespace App\Services\Contracts\PaymentStrategies;

use App\Models\Contract;
use App\Models\EventSettlement;

class GroupOrderingPartyStrategy extends AbstractContractPaymentStrategy
{
    public function sync(Contract $contract, ?EventSettlement $targetSettlement = null): void
    {
        $contract->loadMissing('event');

        if (! $contract->event) {
            return;
        }

        $bookingReference = $this->operationalReference($contract);
        $participantPayment = $this->resolveParticipantPayment($contract, $bookingReference);
        $settlement = $this->resolveSettlement($contract, $participantPayment, $targetSettlement);

        if (! $settlement) {
            return;
        }

        $participantPayment = $this->upsertParticipantPayment(
            $contract,
            $settlement,
            $participantPayment,
            $bookingReference,
            $this->participantName($contract),
            (float) $contract->total_price,
            (float) $contract->amount_paid,
        );

        $this->linkContractToPayment($contract, $participantPayment);
        $settlement->recalculateTotals();
    }

    public function remove(Contract $contract): void
    {
        $bookingReference = $this->operationalReference($contract);
        $participantPayment = $this->resolveParticipantPayment($contract, $bookingReference);

        if (! $participantPayment) {
            return;
        }

        if ($participantPayment->hasLinkedAgreementsBesides($contract->id)) {
            return;
        }

        if (! str_contains((string) $participantPayment->notes, 'Synchronizacja z umowy #')) {
            return;
        }

        $settlement = $participantPayment->settlement;
        $participantPayment->delete();
        $settlement?->recalculateTotals();
    }

    public function progress(Contract $contract): array
    {
        return $this->defaultProgress($contract);
    }
}
