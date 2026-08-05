<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\EventSettlement;

readonly class RecalculateSettlementTotalsData
{
    public function __construct(
        public EventSettlement $settlement,
        public bool $fullRefresh = false,
    ) {}
}
