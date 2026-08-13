<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use App\Support\ParticipantPaymentBalancePresenter;

/**
 * Shared helper for Client / Admin payment views: Należne vs Wpłacone vs Różnica.
 */
trait ShowsParticipantPaymentBalance
{
    /**
     * @param  array<string, mixed>|null  $row
     * @return array<string, float|string>
     */
    protected function participantPaymentBalance(?array $row): array
    {
        return ParticipantPaymentBalancePresenter::fromRow($row);
    }
}
