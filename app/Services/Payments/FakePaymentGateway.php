<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Models\OnlinePaymentSession;

/**
 * Lokalny / stagingowy symulator bramki (bez zewnętrznego API).
 */
final class FakePaymentGateway implements PaymentGateway
{
    public function driver(): string
    {
        return 'fake';
    }

    public function createCheckout(OnlinePaymentSession $session): array
    {
        return [
            'checkout_url' => route('payments.online.fake-checkout', ['uuid' => $session->uuid]),
            'external_id' => 'fake-'.$session->uuid,
            'meta' => ['mode' => 'fake'],
        ];
    }

    public function verifyCallback(array $payload): bool
    {
        return filled($payload['uuid'] ?? null);
    }
}
