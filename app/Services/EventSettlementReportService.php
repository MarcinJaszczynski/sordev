<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventSettlement;
use App\Models\EventSettlementParticipantPayment;
use App\Support\MoneyFormatter;

final class EventSettlementReportService
{
    public function __construct(
        private SettlementPayerBreakdownService $payerBreakdown,
        private SettlementPaymentHealthService $health,
        private ParticipantPaymentBalanceService $participantBalances,
        private PilotSettlementService $pilotSettlement,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(Event $event, ?EventSettlement $settlement = null): array
    {
        $settlement ??= EventSettlement::findOrCreateActiveForEvent($event);
        $settlement->loadMissing(['costs', 'participantPayments', 'pilotCashPreparations']);

        $dashboard = $this->payerBreakdown->forSettlement($settlement);
        $participantAggregate = $this->participantBalances->eventAggregate($event);
        $participantRows = $settlement->participantPayments
            ->map(function (EventSettlementParticipantPayment $payment): array {
                $balance = $this->participantBalances->balanceRow($payment);

                return array_merge([
                    'participant_name' => $payment->participant_name,
                    'booking_reference' => $payment->booking_reference,
                ], $balance);
            })
            ->values()
            ->all();

        $cashReconciliation = [];
        try {
            $cash = $this->pilotSettlement->getCashReconciliation($settlement);
            $cashReconciliation = $cash instanceof \Illuminate\Support\Collection ? $cash->values()->all() : (array) $cash;
        } catch (\Throwable) {
            $cashReconciliation = [];
        }

        return [
            'event' => [
                'id' => $event->id,
                'code' => $event->code,
                'name' => $event->name,
                'participant_count' => (int) ($event->participant_count ?? 0),
            ],
            'settlement' => [
                'id' => $settlement->id,
                'status' => $settlement->status,
                'planned_cost_pln' => (float) ($settlement->planned_cost_pln ?? 0),
                'actual_cost_pln' => (float) ($settlement->actual_cost_pln ?? 0),
                'participant_due_pln' => (float) ($settlement->participant_due_pln ?? 0),
                'participant_paid_pln' => (float) ($settlement->participant_paid_pln ?? 0),
                'participant_balance' => (float) ($settlement->participant_balance ?? 0),
                'net_result_pln' => (float) ($settlement->net_result_pln ?? 0),
            ],
            'dashboard' => $dashboard,
            'participant_aggregate' => $participantAggregate,
            'participant_rows' => $participantRows,
            'cash_reconciliation' => $cashReconciliation,
            'summary_labels' => [
                'planned_cost' => MoneyFormatter::format((float) ($settlement->planned_cost_pln ?? 0), 'PLN'),
                'actual_cost' => MoneyFormatter::format((float) ($settlement->actual_cost_pln ?? 0), 'PLN'),
                'participant_due' => MoneyFormatter::format((float) ($settlement->participant_due_pln ?? 0), 'PLN'),
                'participant_paid' => MoneyFormatter::format((float) ($settlement->participant_paid_pln ?? 0), 'PLN'),
                'net_result' => MoneyFormatter::format((float) ($settlement->net_result_pln ?? 0), 'PLN'),
            ],
        ];
    }
}
