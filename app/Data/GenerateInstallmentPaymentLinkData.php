<?php

declare(strict_types=1);

namespace App\Data;

use Illuminate\Database\Eloquent\Model;

/**
 * Harmonogram raty: ContractPaymentSchedule | EventAgreementPaymentSchedule.
 */
readonly class GenerateInstallmentPaymentLinkData
{
    public function __construct(
        public Model $schedule,
        public int $ttlDays = 30,
    ) {}
}
