<?php

namespace App\Services\Contracts;

use App\Models\Contract;

class ContractPaymentProgressService
{
    public function __construct(
        private readonly ContractPaymentProfileResolver $profileResolver,
        private readonly ContractPaymentStrategyFactory $strategyFactory,
    ) {}

    /**
     * @return array{
     *     profile: string,
     *     profile_label: string,
     *     paid_count: int,
     *     target_count: int,
     *     amount_due: float,
     *     amount_paid: float,
     *     amount_remaining: float,
     *     progress_label: string,
     *     summary_label: string
     * }
     */
    public function forContract(Contract $contract): array
    {
        $profile = $this->profileResolver->resolve($contract);
        $progress = $this->strategyFactory->forContract($contract)->progress($contract);

        $summaryLabel = sprintf(
            '%s · %s · %s / %s PLN',
            $this->profileResolver->label($contract),
            $progress['progress_label'],
            number_format($progress['amount_paid'], 2, ',', ' '),
            number_format($progress['amount_due'], 2, ',', ' '),
        );

        return array_merge($progress, [
            'profile' => $profile,
            'profile_label' => $this->profileResolver->label($contract),
            'summary_label' => $summaryLabel,
        ]);
    }
}
