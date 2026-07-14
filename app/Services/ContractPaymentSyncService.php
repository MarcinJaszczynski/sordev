<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\EventSettlement;
use App\Services\Contracts\ContractPaymentStrategyFactory;

class ContractPaymentSyncService
{
    public function __construct(
        private readonly ContractPaymentStrategyFactory $strategyFactory,
    ) {}

    public function sync(Contract $contract, ?EventSettlement $targetSettlement = null): void
    {
        if (! $this->shouldSync($contract)) {
            $this->remove($contract);

            return;
        }

        $this->strategyFactory->forContract($contract->fresh())->sync($contract->fresh(), $targetSettlement);
    }

    public function remove(Contract $contract): void
    {
        $this->strategyFactory->forContract($contract)->remove($contract);
    }

    public function shouldSync(Contract $contract): bool
    {
        if (! $contract->event_id) {
            return false;
        }

        if (($contract->meta['is_individual_template'] ?? false) === true
            && empty($contract->meta['parent_template_id'] ?? null)) {
            return false;
        }

        if (in_array($contract->status, ['template', 'cancelled'], true)) {
            return false;
        }

        if ($contract->payment_status === 'failed') {
            return false;
        }

        if ($contract->usesIndividualParticipantPayments()) {
            return in_array($contract->status, ['sent', 'signed', 'completed'], true)
                || (float) ($contract->amount_paid ?? 0) > 0
                || ! empty($contract->meta['linked_participant_payment_ids'] ?? []);
        }

        if ($contract->contract_type === Contract::TYPE_CUSTOM
            && data_get($contract->meta, 'payment_mode') === Contract::CUSTOM_PAYMENT_MANUAL
            && ! $contract->participant_payment_id
            && (float) ($contract->amount_paid ?? 0) <= 0) {
            return false;
        }

        return in_array($contract->status, ['sent', 'signed', 'completed'], true)
            || (float) ($contract->amount_paid ?? 0) > 0
            || $contract->participant_payment_id !== null;
    }
}
