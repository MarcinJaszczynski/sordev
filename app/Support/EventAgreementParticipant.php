<?php

namespace App\Support;

use App\Models\Contract;
use App\Models\EventAgreement;

final class EventAgreementParticipant
{
    public static function isIndividual(Contract|EventAgreement $agreement): bool
    {
        $type = $agreement instanceof Contract
            ? $agreement->contract_type
            : $agreement->agreement_type;

        return $type === Contract::TYPE_INDIVIDUAL;
    }

    public static function participantName(Contract|EventAgreement $agreement): string
    {
        $name = trim((string) ($agreement->participant_name ?? ''));

        if ($name !== '') {
            return $name;
        }

        if (! self::isIndividual($agreement)) {
            return '';
        }

        return trim((string) ($agreement->signer_name ?: $agreement->customer_name ?? ''));
    }

    public static function referenceNumber(Contract|EventAgreement $agreement): ?string
    {
        if ($agreement instanceof Contract) {
            return $agreement->contract_number;
        }

        return $agreement->agreement_number;
    }
}
