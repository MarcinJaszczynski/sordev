<?php

namespace App\Services\Contracts\PaymentStrategies;

use App\Models\Contract;
use App\Models\EventSettlement;

interface ContractPaymentStrategy
{
    public function sync(Contract $contract, ?EventSettlement $targetSettlement = null): void;

    public function remove(Contract $contract): void;

    /**
     * @return array{
     *     paid_count: int,
     *     target_count: int,
     *     amount_due: float,
     *     amount_paid: float,
     *     amount_remaining: float,
     *     progress_label: string
     * }
     */
    public function progress(Contract $contract): array;
}
