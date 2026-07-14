<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventSettlement;
use App\Models\EventSettlementParticipantPayment;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class ClientGroupPaymentsService
{
    public function __construct(
        protected ClientAccessService $access,
        protected ParticipantPaymentBalanceService $balances,
    ) {}

    public function rowsFor(User $user, Event $event): Collection
    {
        if (! $this->access->isGuardian($user, $event)) {
            return collect();
        }

        if (! Schema::hasTable('event_settlement_participant_payments')) {
            return collect();
        }

        $settlement = $this->activeSettlement($event);

        if (! $settlement) {
            return collect();
        }

        return EventSettlementParticipantPayment::query()
            ->with(['contracts.paymentSchedules'])
            ->where('settlement_id', $settlement->id)
            ->orderBy('participant_name')
            ->get()
            ->map(function (EventSettlementParticipantPayment $payment): array {
                $balance = $this->balances->balanceRow($payment);

                return [
                    'id' => $payment->id,
                    'participant_name' => $payment->participant_name,
                    'booking_reference' => $payment->booking_reference,
                    'due_amount_pln' => $balance['due_pln'],
                    'paid_amount_pln' => $balance['paid_pln'],
                    'remaining_pln' => $balance['remaining_pln'],
                    'discount_amount_pln' => (float) $payment->discount_amount_pln,
                    'balance' => $balance['balance'],
                    'coverage_status' => $balance['coverage_status'],
                    'coverage_label' => $balance['coverage_label'],
                    'next_due_date' => $balance['next_due_date'],
                    'next_due_amount' => $balance['next_due_amount'],
                    'installment_label' => $balance['installment_label'],
                    'payment_status' => $payment->payment_status,
                    'payment_status_label' => EventSettlementParticipantPayment::$paymentStatuses[$payment->payment_status] ?? $payment->payment_status,
                    'payment_date' => $payment->payment_date,
                    'payment_method' => $payment->payment_method,
                    'document_number' => $payment->document_number,
                ];
            });
    }

    public function summaryFor(User $user, Event $event): array
    {
        $rows = $this->rowsFor($user, $event);
        $aggregate = $this->balances->eventAggregate($event);

        return [
            'count' => $rows->count(),
            'paid_count' => $aggregate['paid_count'] ?? 0,
            'total_due' => $rows->sum('due_amount_pln'),
            'total_paid' => $rows->sum('paid_amount_pln'),
            'total_remaining' => $rows->sum('remaining_pln'),
            'total_balance' => $rows->sum('balance'),
            'coverage_status' => $aggregate['coverage_status'] ?? SettlementPaymentHealthService::STATUS_OK,
        ];
    }

    protected function activeSettlement(Event $event): ?EventSettlement
    {
        if (! Schema::hasTable('event_settlements')) {
            return null;
        }

        return $event->settlements()
            ->orderByDesc('id')
            ->first();
    }
}
