<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\EventSettlementParticipantPayment;
use Illuminate\Support\Carbon;

readonly class RecordParticipantPaymentData
{
    public function __construct(
        public EventSettlementParticipantPayment $payment,
        public float $amount,
        public Carbon|string|null $paidAt = null,
        public ?string $paymentMethod = null,
        public ?string $notes = null,
        public ?string $payerName = null,
        public ?string $source = null,
    ) {}
}
