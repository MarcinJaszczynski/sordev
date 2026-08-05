<?php

declare(strict_types=1);

namespace App\Actions\Finance;

use App\Data\RecordParticipantPaymentData;
use App\Data\RecalculateSettlementTotalsData;
use App\Models\EventSettlementParticipantPaymentEntry;
use App\Services\ParticipantPaymentLedgerService;
use Illuminate\Support\Facades\DB;

final class RecordParticipantPaymentAction
{
    public function __construct(
        private readonly ParticipantPaymentLedgerService $ledger,
        private readonly RecalculateSettlementTotalsAction $recalculateSettlement,
    ) {}

    public function __invoke(RecordParticipantPaymentData $data): EventSettlementParticipantPaymentEntry
    {
        return DB::transaction(function () use ($data): EventSettlementParticipantPaymentEntry {
            $entry = $this->ledger->addEntry(
                payment: $data->payment,
                amount: round($data->amount, 2),
                paidAt: $data->paidAt,
                source: $data->source ?? EventSettlementParticipantPaymentEntry::SOURCE_MANUAL,
                paymentMethod: $data->paymentMethod,
                notes: $data->notes,
                payerName: $data->payerName,
            );

            $settlement = $data->payment->settlement;
            if ($settlement) {
                ($this->recalculateSettlement)(new RecalculateSettlementTotalsData(
                    settlement: $settlement,
                    fullRefresh: false,
                ));
            }

            return $entry;
        });
    }
}
