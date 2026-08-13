<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Models\OnlinePaymentSession;
use RuntimeException;

/**
 * Stub Tpay — wymaga TPAY_CLIENT_ID + TPAY_SECRET.
 * Do pełnej integracji OpenAPI Tpay Open Banking / Transaction API.
 */
final class TpayPaymentGateway implements PaymentGateway
{
    public function driver(): string
    {
        return 'tpay';
    }

    public function createCheckout(OnlinePaymentSession $session): array
    {
        if (! filled(config('payments.tpay.client_id')) || ! filled(config('payments.tpay.secret'))) {
            throw new RuntimeException('Tpay nie jest skonfigurowany (TPAY_CLIENT_ID / TPAY_SECRET). Użyj PAYMENTS_DRIVER=fake na środowisku deweloperskim.');
        }

        // Produkcyjna integracja: OAuth + POST /transactions → transactionPaymentUrl
        throw new RuntimeException('Integracja Tpay API nie jest jeszcze włączona. Ustaw PAYMENTS_DRIVER=fake albo dokończ TpayPaymentGateway.');
    }

    public function verifyCallback(array $payload): bool
    {
        return false;
    }
}
