<?php

namespace App\Services\Contracts;

use App\Models\Contract;

class ContractPaymentProfileResolver
{
    public const PROFILE_GROUP_ORDERING = 'group_ordering';

    public const PROFILE_INDIVIDUAL = 'individual';

    public const PROFILE_GROUP_INDIVIDUAL = 'group_individual';

    public const PROFILE_CUSTOM = 'custom';

    public function resolve(Contract $contract): string
    {
        if ($contract->contract_type === Contract::TYPE_CUSTOM) {
            return self::PROFILE_CUSTOM;
        }

        if ($contract->contract_type === Contract::TYPE_INDIVIDUAL) {
            return self::PROFILE_INDIVIDUAL;
        }

        if ($contract->contract_type === Contract::TYPE_GROUP
            && $contract->payment_scheme === Contract::PAYMENT_SCHEME_INDIVIDUAL) {
            return self::PROFILE_GROUP_INDIVIDUAL;
        }

        return self::PROFILE_GROUP_ORDERING;
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            self::PROFILE_GROUP_ORDERING => 'Grupowa (zamawiający)',
            self::PROFILE_INDIVIDUAL => 'Indywidualna',
            self::PROFILE_GROUP_INDIVIDUAL => 'Grupowa (wpłaty osobno)',
            self::PROFILE_CUSTOM => 'Umowa własna',
        ];
    }

    public function label(Contract $contract): string
    {
        return self::labels()[$this->resolve($contract)] ?? '—';
    }
}
