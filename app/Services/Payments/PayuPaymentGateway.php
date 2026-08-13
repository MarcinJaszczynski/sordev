<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Models\OnlinePaymentSession;
use RuntimeException;

/** Stub PayU — analogicznie do Tpay. */
final class PayuPaymentGateway implements PaymentGateway
{
    public function driver(): string
    {
        return 'payu';
    }

    public function createCheckout(OnlinePaymentSession $session): array
    {
        if (! filled(config('payments.payu.pos_id'))) {
            throw new RuntimeException('PayU nie jest skonfigurowany. Użyj PAYMENTS_DRIVER=fake.');
        }

        throw new RuntimeException('Integracja PayU API nie jest jeszcze włączona. Ustaw PAYMENTS_DRIVER=fake albo dokończ PayuPaymentGateway.');
    }

    public function verifyCallback(array $payload): bool
    {
        return false;
    }
}
