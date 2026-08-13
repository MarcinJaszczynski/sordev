<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\EventSettlementCost;
use Carbon\CarbonInterface;

readonly class UpdateSettlementCostPlanData
{
    public function __construct(
        public EventSettlementCost $planCost,
        public float $plannedAmountPln,
        public string $paidBy = 'office',
        public ?string $notes = null,
        public ?CarbonInterface $dueDate = null,
        public ?float $plannedAmount = null,
        public int|string|null $plannedCurrencyId = null,
        public ?bool $plannedConvertToPln = null,
        public ?float $plannedRate = null,
        public bool $touchContractor = false,
        public ?int $contractorId = null,
        public ?string $paymentMethod = null,
        public ?string $advanceType = null,
    ) {}
}
