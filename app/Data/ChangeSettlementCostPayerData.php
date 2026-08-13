<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\EventSettlementCost;

readonly class ChangeSettlementCostPayerData
{
    public function __construct(
        public EventSettlementCost $planCost,
        public string $paidBy,
    ) {}
}
