<?php

declare(strict_types=1);

namespace App\Actions\Finance;

use App\Data\CompletePendingPaymentData;
use App\Data\RecalculateSettlementTotalsData;
use App\Models\EventSettlementCost;
use App\Services\PendingPaymentCompletionService;
use Illuminate\Support\Facades\DB;

final class CompletePendingPaymentAction
{
    public function __construct(
        private readonly PendingPaymentCompletionService $completion,
        private readonly RecalculateSettlementTotalsAction $recalculateSettlement,
    ) {}

    public function __invoke(CompletePendingPaymentData $data): void
    {
        DB::transaction(function () use ($data): void {
            $this->completion->complete($data->rowId);

            if (str_starts_with($data->rowId, 'cost-')) {
                $cost = EventSettlementCost::query()->find((int) substr($data->rowId, 5));
                $settlement = $cost?->settlement;
                if ($settlement) {
                    ($this->recalculateSettlement)(new RecalculateSettlementTotalsData(
                        settlement: $settlement,
                        fullRefresh: false,
                    ));
                }
            }
        });
    }
}
