<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\EventSettlement;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SettlementTotalsRecalculated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public EventSettlement $settlement,
    ) {}
}
