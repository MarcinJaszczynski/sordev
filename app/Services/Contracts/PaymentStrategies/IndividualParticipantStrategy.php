<?php

namespace App\Services\Contracts\PaymentStrategies;

use App\Models\Contract;
use App\Models\EventSettlement;

class IndividualParticipantStrategy extends AbstractContractPaymentStrategy
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
            [
                'notes' => 'Synchronizacja z umowy indywidualnej #'.$contract->id,
            ],
        );

        $this->linkContractToPayment($contract, $participantPayment);
        $settlement->recalculateTotals();
    }

    public function remove(Contract $contract): void
    {
        app(GroupOrderingPartyStrategy::class)->remove($contract);
    }

    public function progress(Contract $contract): array
    {
        $progress = $this->defaultProgress($contract);
        $progress['target_count'] = 1;

        return $progress;
    }
}
