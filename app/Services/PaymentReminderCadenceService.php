<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ContractPaymentSchedule;
use App\Models\EventSettlementParticipantPayment;
use App\Models\PaymentReminderLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * Wysyłka przypomnień wg cadence (dni po terminie).
 */
final class PaymentReminderCadenceService
{
    public function __construct(
        private readonly ParticipantPaymentReminderService $reminders,
    ) {}

    /**
     * @return array{scanned: int, sent: int, skipped: int, failed: int}
     */
    public function run(?Carbon $now = null): array
    {
        $now ??= now();
        $cadence = config('payments.reminder_cadence_days', [3, 7, 14]);
        if ($cadence === []) {
            $cadence = [3, 7, 14];
        }

        $stats = ['scanned' => 0, 'sent' => 0, 'skipped' => 0, 'failed' => 0];

        if (! Schema::hasTable('event_settlement_participant_payments')) {
            return $stats;
        }

        $payments = EventSettlementParticipantPayment::query()
            ->with(['settlement.event', 'eventParticipant', 'contracts.paymentSchedules'])
            ->whereHas('settlement.event')
            ->where(function ($query): void {
                $query->whereNull('payment_status')
                    ->orWhereNotIn('payment_status', ['paid', 'cancelled']);
            })
            ->limit(500)
            ->get();

        foreach ($payments as $payment) {
            $stats['scanned']++;
            $result = $this->maybeRemind($payment, $cadence, $now);
            $stats[$result]++;
        }

        return $stats;
    }

    /**
     * @param  list<int>  $cadence
     * @return 'sent'|'skipped'|'failed'
     */
    private function maybeRemind(EventSettlementParticipantPayment $payment, array $cadence, Carbon $now): string
    {
        $balance = app(ParticipantPaymentBalanceService::class)->balanceRow($payment);
        $remaining = (float) ($balance['remaining_pln'] ?? 0);
        if ($remaining <= 0) {
            return 'skipped';
        }

        $dueAt = $this->resolveDueDate($payment);
        if (! $dueAt || $dueAt->copy()->startOfDay()->isFuture()) {
            return 'skipped';
        }

        $daysOverdue = (int) $dueAt->startOfDay()->diffInDays($now->copy()->startOfDay());
        $matchedCadence = null;
        foreach ($cadence as $day) {
            $day = (int) $day;
            if ($day > 0 && $daysOverdue >= $day) {
                $matchedCadence = $day;
            }
        }

        if ($matchedCadence === null) {
            return 'skipped';
        }

        if (Schema::hasTable('payment_reminder_logs')) {
            $already = PaymentReminderLog::query()
                ->where('event_settlement_participant_payment_id', $payment->id)
                ->where('cadence_day', $matchedCadence)
                ->where('status', 'sent')
                ->exists();

            if ($already) {
                return 'skipped';
            }
        }

        $send = $this->reminders->sendReminder($payment);

        if (Schema::hasTable('payment_reminder_logs')) {
            PaymentReminderLog::query()->create([
                'event_settlement_participant_payment_id' => $payment->id,
                'channel' => 'email',
                'cadence_day' => $matchedCadence,
                'status' => $send['sent'] ? 'sent' : 'failed',
                'message' => $send['message'],
                'recipient' => null,
            ]);
        }

        return $send['sent'] ? 'sent' : 'failed';
    }

    private function resolveDueDate(EventSettlementParticipantPayment $payment): ?Carbon
    {
        $earliest = null;

        foreach ($payment->contracts as $contract) {
            foreach ($contract->paymentSchedules as $schedule) {
                if (! $schedule instanceof ContractPaymentSchedule) {
                    continue;
                }

                $dueRaw = $schedule->due_to ?? $schedule->due_date;
                if (! $dueRaw) {
                    continue;
                }

                if ($this->scheduleLooksPaid($schedule)) {
                    continue;
                }

                $due = Carbon::parse($dueRaw)->startOfDay();
                if ($earliest === null || $due->lt($earliest)) {
                    $earliest = $due;
                }
            }
        }

        if ($earliest) {
            return $earliest;
        }

        $event = $payment->settlement?->event;
        if ($event?->start_date) {
            return $event->start_date->copy()->subDays(14)->startOfDay();
        }

        return null;
    }

    private function scheduleLooksPaid(ContractPaymentSchedule $schedule): bool
    {
        $amount = (float) ($schedule->amount ?? 0);
        $paid = (float) ($schedule->paid_amount ?? 0);

        if ($schedule->paid_at && $amount <= 0) {
            return true;
        }

        return $amount > 0 && $paid + 0.009 >= $amount;
    }
}
