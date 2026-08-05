<?php

declare(strict_types=1);

namespace App\Actions\Finance;

use App\Data\GenerateInstallmentPaymentLinkData;
use App\Models\ContractPaymentSchedule;
use App\Models\EventAgreementPaymentSchedule;
use Illuminate\Support\Facades\URL;
use InvalidArgumentException;

final class GenerateInstallmentPaymentLinkAction
{
    /**
     * @return array{url: string, type: string, schedule_id: int, expires_at: string}
     */
    public function __invoke(GenerateInstallmentPaymentLinkData $data): array
    {
        $schedule = $data->schedule;
        $ttlDays = max(1, $data->ttlDays);

        if ($schedule instanceof ContractPaymentSchedule) {
            $type = 'contract';
        } elseif ($schedule instanceof EventAgreementPaymentSchedule) {
            $type = 'agreement';
        } else {
            throw new InvalidArgumentException('Nieobsługiwany typ harmonogramu płatności.');
        }

        $expiresAt = now()->addDays($ttlDays);

        $url = URL::temporarySignedRoute(
            'payments.installment.show',
            $expiresAt,
            [
                'type' => $type,
                'schedule' => $schedule->getKey(),
            ],
        );

        return [
            'url' => $url,
            'type' => $type,
            'schedule_id' => (int) $schedule->getKey(),
            'expires_at' => $expiresAt->toIso8601String(),
        ];
    }
}
