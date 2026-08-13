<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Models\OnlinePaymentSession;

interface PaymentGateway
{
    public function driver(): string;

    /**
     * @return array{checkout_url: string, external_id: ?string, meta?: array<string, mixed>}
     */
    public function createCheckout(OnlinePaymentSession $session): array;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function verifyCallback(array $payload): bool;
}
