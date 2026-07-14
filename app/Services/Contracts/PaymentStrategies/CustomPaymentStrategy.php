<?php

namespace App\Services\Contracts\PaymentStrategies;

use App\Models\Contract;
use App\Models\EventSettlement;

class CustomPaymentStrategy extends AbstractContractPaymentStrategy
{
    public function sync(Contract $contract, ?EventSettlement $targetSettlement = null): void
    {
        $mode = (string) data_get($contract->meta, 'payment_mode', Contract::CUSTOM_PAYMENT_TOTAL_LUMP);

        match ($mode) {
            Contract::CUSTOM_PAYMENT_PER_PARTICIPANT => app(GroupIndividualPaymentsStrategy::class)->sync($contract, $targetSettlement),
            Contract::CUSTOM_PAYMENT_MANUAL => $this->syncManual($contract, $targetSettlement),
            default => app(GroupOrderingPartyStrategy::class)->sync($contract, $targetSettlement),
        };
    }

    public function remove(Contract $contract): void
    {
        $mode = (string) data_get($contract->meta, 'payment_mode', Contract::CUSTOM_PAYMENT_TOTAL_LUMP);

        match ($mode) {
            Contract::CUSTOM_PAYMENT_PER_PARTICIPANT => app(GroupIndividualPaymentsStrategy::class)->remove($contract),
            Contract::CUSTOM_PAYMENT_MANUAL => app(GroupOrderingPartyStrategy::class)->remove($contract),
            default => app(GroupOrderingPartyStrategy::class)->remove($contract),
        };
    }

    public function progress(Contract $contract): array
    {
        $mode = (string) data_get($contract->meta, 'payment_mode', Contract::CUSTOM_PAYMENT_TOTAL_LUMP);

        return match ($mode) {
            Contract::CUSTOM_PAYMENT_PER_PARTICIPANT => app(GroupIndividualPaymentsStrategy::class)->progress($contract),
            default => app(GroupOrderingPartyStrategy::class)->progress($contract),
        };
    }

    protected function syncManual(Contract $contract, ?EventSettlement $targetSettlement = null): void
    {
        if (! $contract->participant_payment_id && (float) $contract->amount_paid <= 0) {
            return;
        }

        app(GroupOrderingPartyStrategy::class)->sync($contract, $targetSettlement);
    }
}
