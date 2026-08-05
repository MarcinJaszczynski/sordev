<?php

declare(strict_types=1);

namespace App\Actions\Finance;

use App\Data\RecalculateSettlementTotalsData;
use App\Events\SettlementTotalsRecalculated;
use App\Models\EventSettlement;
use Illuminate\Support\Facades\DB;

final class RecalculateSettlementTotalsAction
{
    public function __invoke(RecalculateSettlementTotalsData $data): EventSettlement
    {
        return DB::transaction(function () use ($data): EventSettlement {
            $settlement = $data->settlement->fresh() ?? $data->settlement;

            if ($data->fullRefresh) {
                $settlement->refreshDerivedData();
            } else {
                $settlement->recalculateTotals();
                $settlement->refresh();
            }

            SettlementTotalsRecalculated::dispatch($settlement);

            return $settlement;
        });
    }
}
