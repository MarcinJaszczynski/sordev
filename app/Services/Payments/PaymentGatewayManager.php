<?php

declare(strict_types=1);

namespace App\Services\Payments;

use InvalidArgumentException;

final class PaymentGatewayManager
{
    public function driver(?string $name = null): PaymentGateway
    {
        $name ??= (string) config('payments.driver', 'fake');

        return match ($name) {
            'fake' => app(FakePaymentGateway::class),
            'tpay' => app(TpayPaymentGateway::class),
            'payu' => app(PayuPaymentGateway::class),
            default => throw new InvalidArgumentException("Nieznany driver płatności: {$name}"),
        };
    }
}
