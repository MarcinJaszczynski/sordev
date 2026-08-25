<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\EventSettlementCost;
use Carbon\CarbonInterface;

readonly class UpdateSettlementCostPaymentData
{
    public function __construct(
        public EventSettlementCost $payment,
        public float $amountPln,
        public string $paymentMethod = 'transfer',
        public string $paidBy = 'office',
        public string $advanceType = 'advance',
        public ?CarbonInterface $paidAt = null,
        public ?CarbonInterface $dueDate = null,
        public ?string $documentNumber = null,
        public ?string $notes = null,
        public ?float $amount = null,
        public ?float $rate = null,
        public int|string|null $currencyId = null,
        public ?int $reservationId = null,
        public bool $convertToPln = true,
    ) {}
}
