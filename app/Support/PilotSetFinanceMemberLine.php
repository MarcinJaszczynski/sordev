<?php

namespace App\Support;

use App\Models\Currency;

final class PilotSetFinanceMemberLine
{
    public const STATUS_PILOT_DUE = 'pilot_due';

    public const STATUS_PILOT_PAID = 'pilot_paid';

    public const STATUS_OFFICE_PAID = 'office_paid';

    public const STATUS_OFFICE_DUE = 'office_due';

    public function __construct(
        public int $pointId,
        public string $name,
        public string $status,
        public string $displayLabel,
        public bool $inProgram,
        public bool $countsTowardPilotTotal = false,
        public ?string $amountLabel = null,
        public ?string $dueDateLabel = null,
        public float $plannedAmount = 0.0,
        public float $remainingAmount = 0.0,
        public ?Currency $currency = null,
        public bool $convertToPln = true,
    ) {}

    public function toArray(): array
    {
        return [
            'point_id' => $this->pointId,
            'name' => $this->name,
            'status' => $this->status,
            'display_label' => $this->displayLabel,
            'in_program' => $this->inProgram,
            'counts_toward_pilot_total' => $this->countsTowardPilotTotal,
            'amount_label' => $this->amountLabel,
            'due_date_label' => $this->dueDateLabel,
        ];
    }
}
