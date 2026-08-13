<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Event;
use App\Models\EventSettlementCost;
use Carbon\CarbonInterface;

readonly class UpsertFinanceManualCostData
{
    public function __construct(
        public Event $event,
        public string $name,
        public float $amount,
        public ?int $currencyId = null,
        public bool $convertToPln = true,
        public string $paidBy = 'office',
        public ?string $notes = null,
        public ?CarbonInterface $dueDate = null,
        public ?EventSettlementCost $planCost = null,
        public ?int $contractorId = null,
    ) {}
}
