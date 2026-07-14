<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\ContractPaymentSchedule;
use App\Models\Event;
use App\Models\EventSettlementParticipantPayment;
use App\Services\Contracts\ContractPaymentProfileResolver;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

final class ParticipantPaymentBalanceService
{
    public function __construct(
        private ContractPaymentProfileResolver $profileResolver,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function balanceRow(EventSettlementParticipantPayment $payment): array
    {
        $duePln = round((float) $payment->due_amount_pln, 2);
        $paidPln = round((float) $payment->paid_amount_pln, 2);
        $remainingPln = round(max(0, $duePln - $paidPln), 2);
        $installment = $this->resolveInstallmentContext($payment);
        $coverageStatus = $this->resolveCoverageStatus($paidPln, $duePln, $installment['next_due_date']);

        return [
            'due_pln' => $duePln,
            'paid_pln' => $paidPln,
            'remaining_pln' => $remainingPln,
            'balance' => round($paidPln - $duePln, 2),
            'coverage_status' => $coverageStatus,
            'coverage_label' => SettlementPaymentHealthService::$statusLabels[$coverageStatus] ?? $coverageStatus,
            'next_due_date' => $installment['next_due_date'],
            'next_due_amount' => $installment['next_due_amount'],
            'installment_label' => $installment['installment_label'],
            'profile' => $installment['profile'],
            'profile_label' => $installment['profile_label'],
        ];
    }

    /**
     * @return array{
     *     count: int,
     *     paid_count: int,
     *     total_due: float,
     *     total_paid: float,
     *     total_remaining: float,
     *     coverage_status: string,
     *     profile: ?string
     * }
     */
    public function eventAggregate(Event $event): array
    {
        $settlement = $event->activeSettlement ?? $event->settlements()->latest('id')->first();

        if (! $settlement) {
            return $this->emptyAggregate();
        }

        $payments = $settlement->participantPayments()->get();
        $rows = $payments->map(fn (EventSettlementParticipantPayment $payment): array => $this->balanceRow($payment));

        $paidCount = $rows->filter(fn (array $row): bool => ($row['coverage_status'] ?? '') === SettlementPaymentHealthService::STATUS_OK)->count();

        $worstStatus = SettlementPaymentHealthService::STATUS_OK;
        foreach ($rows as $row) {
            $worstStatus = $this->worseStatus($worstStatus, (string) ($row['coverage_status'] ?? SettlementPaymentHealthService::STATUS_OK));
        }

        $groupContract = $this->resolveGroupContract($event);

        return [
            'count' => $payments->count(),
            'paid_count' => $paidCount,
            'total_due' => round((float) $rows->sum('due_pln'), 2),
            'total_paid' => round((float) $rows->sum('paid_pln'), 2),
            'total_remaining' => round((float) $rows->sum('remaining_pln'), 2),
            'coverage_status' => $worstStatus,
            'profile' => $groupContract ? $this->profileResolver->resolve($groupContract) : null,
        ];
    }

    /**
     * @return array{next_due_date: ?Carbon, next_due_amount: ?float, installment_label: ?string, profile: ?string, profile_label: ?string}
     */
    private function resolveInstallmentContext(EventSettlementParticipantPayment $payment): array
    {
        $contract = $payment->contracts()->first() ?? null;
        $profile = $contract ? $this->profileResolver->resolve($contract) : null;
        $profileLabel = $contract ? $this->profileResolver->label($contract) : null;

        if (! $contract || ! Schema::hasTable('contract_payment_schedules')) {
            return [
                'next_due_date' => null,
                'next_due_amount' => null,
                'installment_label' => null,
                'profile' => $profile,
                'profile_label' => $profileLabel,
            ];
        }

        $schedules = $contract->paymentSchedules()
            ->orderBy('sort_order')
            ->orderBy('due_date')
            ->get();

        if ($schedules->isEmpty()) {
            return [
                'next_due_date' => null,
                'next_due_amount' => null,
                'installment_label' => null,
                'profile' => $profile,
                'profile_label' => $profileLabel,
            ];
        }

        $paidPln = (float) $payment->paid_amount_pln;
        $cumulative = 0.0;
        $next = null;

        foreach ($schedules as $schedule) {
            $cumulative += (float) $schedule->amount;

            if ($paidPln + SettlementPaymentHealthService::TOLERANCE < $cumulative) {
                $next = $schedule;
                break;
            }
        }

        if (! $next instanceof ContractPaymentSchedule) {
            return [
                'next_due_date' => null,
                'next_due_amount' => null,
                'installment_label' => 'Wszystkie raty opłacone',
                'profile' => $profile,
                'profile_label' => $profileLabel,
            ];
        }

        $remainingForInstallment = max(0, round($cumulative - $paidPln, 2));

        return [
            'next_due_date' => $next->due_date ? Carbon::parse($next->due_date) : null,
            'next_due_amount' => $remainingForInstallment > 0 ? $remainingForInstallment : (float) $next->amount,
            'installment_label' => $next->label ?: ('Rata #'.((int) $next->sort_order + 1)),
            'profile' => $profile,
            'profile_label' => $profileLabel,
        ];
    }

    private function resolveCoverageStatus(float $paidPln, float $duePln, ?Carbon $nextDue): string
    {
        if ($duePln <= SettlementPaymentHealthService::TOLERANCE) {
            return SettlementPaymentHealthService::STATUS_OK;
        }

        if ($paidPln >= $duePln - SettlementPaymentHealthService::TOLERANCE) {
            return SettlementPaymentHealthService::STATUS_OK;
        }

        if ($nextDue === null) {
            return SettlementPaymentHealthService::STATUS_SHORTFALL;
        }

        if ($nextDue->endOfDay()->isFuture() || $nextDue->isToday()) {
            return SettlementPaymentHealthService::STATUS_DUE;
        }

        return SettlementPaymentHealthService::STATUS_OVERDUE;
    }

    private function worseStatus(string $current, string $candidate): string
    {
        $priority = [
            SettlementPaymentHealthService::STATUS_OVERDUE => 4,
            SettlementPaymentHealthService::STATUS_SHORTFALL => 3,
            SettlementPaymentHealthService::STATUS_DUE => 2,
            SettlementPaymentHealthService::STATUS_OK => 1,
        ];

        return ($priority[$candidate] ?? 0) > ($priority[$current] ?? 0) ? $candidate : $current;
    }

    private function resolveGroupContract(Event $event): ?Contract
    {
        if (! Schema::hasTable('contracts')) {
            return null;
        }

        return Contract::query()
            ->where('event_id', $event->id)
            ->where('contract_type', Contract::TYPE_GROUP)
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return array{count: int, paid_count: int, total_due: float, total_paid: float, total_remaining: float, coverage_status: string, profile: null}
     */
    private function emptyAggregate(): array
    {
        return [
            'count' => 0,
            'paid_count' => 0,
            'total_due' => 0.0,
            'total_paid' => 0.0,
            'total_remaining' => 0.0,
            'coverage_status' => SettlementPaymentHealthService::STATUS_OK,
            'profile' => null,
        ];
    }
}
