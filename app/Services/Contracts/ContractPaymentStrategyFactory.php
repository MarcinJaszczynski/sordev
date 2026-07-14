<?php

namespace App\Services\Contracts;

use App\Models\Contract;
use App\Services\Contracts\PaymentStrategies\ContractPaymentStrategy;
use App\Services\Contracts\PaymentStrategies\CustomPaymentStrategy;
use App\Services\Contracts\PaymentStrategies\GroupIndividualPaymentsStrategy;
use App\Services\Contracts\PaymentStrategies\GroupOrderingPartyStrategy;
use App\Services\Contracts\PaymentStrategies\IndividualParticipantStrategy;

class ContractPaymentStrategyFactory
{
    public function forContract(Contract $contract): ContractPaymentStrategy
    {
        return match (app(ContractPaymentProfileResolver::class)->resolve($contract)) {
            ContractPaymentProfileResolver::PROFILE_INDIVIDUAL => app(IndividualParticipantStrategy::class),
            ContractPaymentProfileResolver::PROFILE_GROUP_INDIVIDUAL => app(GroupIndividualPaymentsStrategy::class),
            ContractPaymentProfileResolver::PROFILE_CUSTOM => app(CustomPaymentStrategy::class),
            default => app(GroupOrderingPartyStrategy::class),
        };
    }
}
