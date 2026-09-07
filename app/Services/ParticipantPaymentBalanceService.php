<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\Event;
use App\Models\EventSettlement;
use App\Models\EventSettlementParticipantPayment;
use App\Services\Contracts\ContractPaymentProfileResolver;
use App\Support\CurrencyAmountDisplay;
use Illuminate\Support\Carbon;
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
        $payment->loadMissing(['contracts.paymentSchedules']);

        $fromContract = $this->balanceFromLinkedContract($payment);
        if ($fromContract !== null) {
            return $fromContract;
        }

        $duePln = round((float) $payment->due_amount_pln, 2);
        $paidPln = round((float) $payment->paid_amount_pln, 2);
        $remainingPln = round(max(0, $duePln - $paidPln), 2);
        $installment = $this->resolveInstallmentContext($payment, $duePln, $paidPln);
        $coverageStatus = $this->resolveCoverageStatus($paidPln, $duePln, $installment['next_due_date']);

        return [
            'due_pln' => $duePln,
            'paid_pln' => $paidPln,
            'remaining_pln' => $remainingPln,
            'balance' => round($paidPln - $duePln, 2),
            'coverage_status' => $coverageStatus,
            'coverage_label' => SettlementPaymentHealthService::$statusLabels[$coverageStatus] ?? $coverageStatus,
            'display_status_label' => $this->displayStatusLabel($coverageStatus, $paidPln),
            'next_due_date' => $installment['next_due_date'],
            'next_due_amount' => $installment['next_due_amount'],
            'next_schedule_id' => $installment['next_schedule_id'],
            'installment_label' => $installment['installment_label'],
            'profile' => $installment['profile'],
            'profile_label' => $installment['profile_label'],
            'source' => 'ledger',
        ];
    }

    /**
     * Kanoniczne saldo z umowy (te same liczby co w portalu „Płatności”).
     *
     * @return array<string, mixed>
     */
    public function forContract(Contract $contract): array
    {
        $contract->loadMissing('paymentSchedules');
        $checkout = app(ContractInstallmentCheckoutService::class)->snapshot($contract);

        $plnSchedules = $contract->paymentSchedules
            ->filter(fn ($row): bool => round((float) ($row->amount ?? 0), 2) > SettlementPaymentHealthService::TOLERANCE);
        $dueFromSchedules = round((float) $plnSchedules->sum(fn ($row): float => (float) $row->amount), 2);
        $paidFromSchedules = round((float) $plnSchedules->sum(fn ($row): float => (float) ($row->paid_amount ?? 0)), 2);

        // Raty PLN mają pierwszeństwo nad amount_due=0 / ledgerem.
        $duePln = $dueFromSchedules > SettlementPaymentHealthService::TOLERANCE
            ? $dueFromSchedules
            : round((float) ($checkout['total_due_pln'] ?? $contract->amount_due ?? $contract->total_price ?? 0), 2);

        $paidPln = max(
            round((float) ($checkout['total_paid_pln'] ?? $contract->amount_paid ?? 0), 2),
            $paidFromSchedules,
        );
        $remainingPln = round(max(0, $duePln - $paidPln), 2);

        $next = $checkout['next_pln'] ?? null;
        $nextDue = null;
        if (is_array($next) && filled($next['due_date'] ?? null)) {
            try {
                $nextDue = Carbon::createFromFormat('d.m.Y', (string) $next['due_date'])->startOfDay();
            } catch (\Throwable) {
                $nextDue = null;
            }
        }

        $coverageStatus = $this->resolveCoverageStatus($paidPln, $duePln, $nextDue);
        $profile = $this->profileResolver->resolve($contract);
        $profileLabel = $this->profileResolver->label($contract);

        return [
            'due_pln' => $duePln,
            'paid_pln' => $paidPln,
            'remaining_pln' => $remainingPln,
            'balance' => round($paidPln - $duePln, 2),
            'coverage_status' => $coverageStatus,
            'coverage_label' => SettlementPaymentHealthService::$statusLabels[$coverageStatus] ?? $coverageStatus,
            'display_status_label' => $this->displayStatusLabel($coverageStatus, $paidPln),
            'next_due_date' => $nextDue,
            'next_due_amount' => is_array($next) ? (float) ($next['remaining'] ?? 0) : null,
            'next_schedule_id' => is_array($next) && (int) ($next['id'] ?? 0) > 0 ? (int) $next['id'] : null,
            'installment_label' => is_array($next) ? ($next['label'] ?? null) : null,
            'profile' => $profile,
            'profile_label' => $profileLabel,
            'source' => 'contract',
        ];
    }

    /**
     * Saldo uczestnika listy (umowa → ledger).
     *
     * @return array<string, mixed>
     */
    public function forParticipant(\App\Models\EventParticipant $participant): array
    {
        $participant->loadMissing(['participantPayment.contracts.paymentSchedules', 'contract.paymentSchedules']);

        if ($participant->participantPayment) {
            return $this->balanceRow($participant->participantPayment);
        }

        $contract = $participant->contract;
        if ($contract) {
            return $this->forContract($contract);
        }

        return [
            'due_pln' => 0.0,
            'paid_pln' => 0.0,
            'remaining_pln' => 0.0,
            'balance' => 0.0,
            'coverage_status' => SettlementPaymentHealthService::STATUS_NA,
            'coverage_label' => SettlementPaymentHealthService::$statusLabels[SettlementPaymentHealthService::STATUS_NA] ?? 'Brak',
            'display_status_label' => 'Brak wpłat',
            'next_due_date' => null,
            'next_due_amount' => null,
            'next_schedule_id' => null,
            'installment_label' => null,
            'profile' => null,
            'profile_label' => null,
            'source' => 'none',
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function balanceFromLinkedContract(EventSettlementParticipantPayment $payment): ?array
    {
        $contract = $payment->contracts->first();
        if (! $contract && Schema::hasTable('contracts')) {
            $contract = Contract::query()
                ->where('participant_payment_id', $payment->id)
                ->with('paymentSchedules')
                ->first();
        }

        if (! $contract) {
            return null;
        }

        $contract->loadMissing('paymentSchedules');
        $pln = $contract->paymentSchedules
            ->filter(fn ($row): bool => round((float) ($row->amount ?? 0), 2) > SettlementPaymentHealthService::TOLERANCE);

        // Bez rat PLN zostaw ledger (np. umowa tylko FX / bez harmonogramu).
        if ($pln->isEmpty() && round((float) ($contract->amount_due ?? 0), 2) <= SettlementPaymentHealthService::TOLERANCE) {
            return null;
        }

        $balance = $this->forContract($contract);

        // Soft-sync księgi, żeby listy / raporty nie rozjeżdżały się z umową.
        $ledgerDue = round((float) $payment->due_amount_pln, 2);
        $ledgerPaid = round((float) $payment->paid_amount_pln, 2);
        if (abs($ledgerDue - $balance['due_pln']) > 0.05
            || abs($ledgerPaid - $balance['paid_pln']) > 0.05
        ) {
            $newStatus = $balance['remaining_pln'] <= SettlementPaymentHealthService::TOLERANCE && $balance['due_pln'] > 0
                ? 'paid'
                : ($balance['paid_pln'] > SettlementPaymentHealthService::TOLERANCE ? 'partial' : 'pending');

            $payment->forceFill([
                'due_amount_pln' => $balance['due_pln'],
                'paid_amount_pln' => $balance['paid_pln'],
                'payment_status' => $newStatus,
            ])->saveQuietly();
        }

        return $balance;
    }

    public function displayStatusLabel(string $coverageStatus, float $paidPln = 0.0): string
    {
        return match ($coverageStatus) {
            SettlementPaymentHealthService::STATUS_OK => EventSettlementParticipantPayment::$paymentStatuses['paid'] ?? 'Opłacone',
            SettlementPaymentHealthService::STATUS_NA => 'Brak należności',
            SettlementPaymentHealthService::STATUS_OVERDUE => 'Po terminie',
            SettlementPaymentHealthService::STATUS_DUE => 'W terminie',
            SettlementPaymentHealthService::STATUS_SHORTFALL => $paidPln > SettlementPaymentHealthService::TOLERANCE
                ? (EventSettlementParticipantPayment::$paymentStatuses['partial'] ?? 'Częściowo')
                : (EventSettlementParticipantPayment::$paymentStatuses['pending'] ?? 'Oczekuje'),
            default => SettlementPaymentHealthService::$statusLabels[$coverageStatus] ?? $coverageStatus,
        };
    }

    /**
     * @return array{
     *     count: int,
     *     paid_count: int,
     *     waive_count: int,
     *     overdue_count: int,
     *     due_count: int,
     *     shortfall_count: int,
     *     total_due: float,
     *     total_paid: float,
     *     total_remaining: float,
     *     coverage_status: string,
     *     coverage_label: string,
     *     profile: ?string,
     *     status_counts: array<string, int>,
     *     attention: list<array{name: string, remaining_pln: float, status: string, status_label: string, next_due_date: ?string, next_due_amount: ?float}>,
     *     expected_count: int,
     *     gratis_count: int,
     *     registered_count: int,
     *     registered_paying_count: int,
     *     paid_full_count: int,
     *     paid_any_count: int,
     *     unregistered_count: int,
     *     price_per_person: float,
     *     expected_due_pln: float,
     *     expected_foreign: list<array{currency: string, amount: float, price_per_person: float, label: string}>,
     *     capacity_remaining_pln: float,
     *     ledger_due_pln: float,
     *     ledger_remaining_pln: float,
     *     installment_gaps: list<array<string, mixed>>
     * }
     */
    public function eventAggregate(Event $event): array
    {
        $settlement = $event->relationLoaded('activeSettlement')
            ? $event->activeSettlement
            : ($event->activeSettlement ?? $event->settlements()->latest('id')->first());

        $payments = $settlement
            ? ($settlement->relationLoaded('participantPayments')
                ? $settlement->participantPayments
                : $settlement->participantPayments()->get())
            : collect();

        $rows = $payments->map(fn (EventSettlementParticipantPayment $payment): array => [
            'payment' => $payment,
            'balance' => $this->balanceRow($payment),
        ]);

        $statusCounts = [
            SettlementPaymentHealthService::STATUS_OK => 0,
            SettlementPaymentHealthService::STATUS_DUE => 0,
            SettlementPaymentHealthService::STATUS_OVERDUE => 0,
            SettlementPaymentHealthService::STATUS_SHORTFALL => 0,
            SettlementPaymentHealthService::STATUS_NA => 0,
        ];

        $attention = [];
        $paidFullCount = 0;
        $paidAnyCount = 0;
        $waiveCount = 0;
        $registeredPayingCount = 0;
        $worstStatus = SettlementPaymentHealthService::STATUS_OK;

        foreach ($rows as $row) {
            $balance = $row['balance'];
            $status = (string) ($balance['coverage_status'] ?? SettlementPaymentHealthService::STATUS_SHORTFALL);
            $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;
            $worstStatus = $this->worseStatus($worstStatus, $status);

            $duePln = (float) ($balance['due_pln'] ?? 0);
            $paidPln = (float) ($balance['paid_pln'] ?? 0);

            if ($status === SettlementPaymentHealthService::STATUS_OK) {
                $paidFullCount++;
            }
            if ($status === SettlementPaymentHealthService::STATUS_NA) {
                $waiveCount++;
            } elseif ($duePln > SettlementPaymentHealthService::TOLERANCE) {
                $registeredPayingCount++;
            }
            if ($paidPln > SettlementPaymentHealthService::TOLERANCE) {
                $paidAnyCount++;
            }

            if (in_array($status, [
                SettlementPaymentHealthService::STATUS_OVERDUE,
                SettlementPaymentHealthService::STATUS_SHORTFALL,
                SettlementPaymentHealthService::STATUS_DUE,
            ], true)) {
                /** @var EventSettlementParticipantPayment $payment */
                $payment = $row['payment'];
                $attention[] = [
                    'name' => (string) ($payment->participant_name ?: '—'),
                    'remaining_pln' => (float) ($balance['remaining_pln'] ?? 0),
                    'status' => $status,
                    'status_label' => (string) ($balance['display_status_label'] ?? $balance['coverage_label'] ?? $status),
                    'next_due_date' => $balance['next_due_date'] instanceof \Carbon\CarbonInterface
                        ? $balance['next_due_date']->format('Y-m-d')
                        : null,
                    'next_due_amount' => $balance['next_due_amount'] !== null
                        ? (float) $balance['next_due_amount']
                        : null,
                ];
            }
        }

        usort($attention, function (array $a, array $b): int {
            $priority = [
                SettlementPaymentHealthService::STATUS_OVERDUE => 0,
                SettlementPaymentHealthService::STATUS_SHORTFALL => 1,
                SettlementPaymentHealthService::STATUS_DUE => 2,
            ];

            return ($priority[$a['status']] ?? 9) <=> ($priority[$b['status']] ?? 9)
                ?: ($b['remaining_pln'] <=> $a['remaining_pln']);
        });

        $groupContract = $this->resolveGroupContract($event);
        $balances = $rows->pluck('balance');
        $ledgerDue = round((float) $balances->sum('due_pln'), 2);
        $ledgerPaid = round((float) $balances->sum('paid_pln'), 2);
        $ledgerRemaining = round((float) $balances->sum('remaining_pln'), 2);

        $expectedCount = max(0, (int) ($event->participant_count ?? 0));
        $gratisCount = max(0, (int) $event->resolveGratisCountForParticipantCount($expectedCount ?: 1));
        if ($expectedCount <= 0) {
            $expectedCount = max($registeredPayingCount, 1);
        }

        // Cena bazowa: umowa/ręczna, potem kalkulacja.
        // Należność PLN: suma ledgeru (z rabatami / due=0) + osoby poza listą × cena.
        $unitPrices = $this->resolveCapacityUnitPrices($event, $expectedCount, $groupContract);
        $pricePerPerson = $unitPrices['PLN'];
        $registeredCount = $payments->count();
        $unregisteredCount = max(0, $expectedCount - $registeredCount);
        $expectedDuePln = round($ledgerDue + ($unregisteredCount * $pricePerPerson), 2);
        // FX: pełna pojemność listy (N), bez obniżania przez due=0 na liście.
        $capacitySeats = $registeredCount + $unregisteredCount;
        $paidForeignByCode = $this->sumPaidForeignByCurrency($payments);
        $expectedForeign = [];
        foreach ($unitPrices['foreign'] as $code => $ppp) {
            $ppp = round((float) $ppp, 2);
            if ($ppp <= 0) {
                continue;
            }
            $expectedAmount = round($capacitySeats * $ppp, 2);
            $paidAmount = round((float) ($paidForeignByCode[$code] ?? 0), 2);
            $expectedForeign[] = [
                'currency' => $code,
                'price_per_person' => $ppp,
                'amount' => $expectedAmount,
                'paid' => $paidAmount,
                'remaining' => round(max(0, $expectedAmount - $paidAmount), 2),
                'label' => "{$ppp} {$code}",
            ];
            unset($paidForeignByCode[$code]);
        }
        // Wpłaty w walucie spoza cennika — też pokaż.
        foreach ($paidForeignByCode as $code => $paidAmount) {
            $paidAmount = round((float) $paidAmount, 2);
            if ($paidAmount <= SettlementPaymentHealthService::TOLERANCE) {
                continue;
            }
            $expectedForeign[] = [
                'currency' => (string) $code,
                'price_per_person' => 0.0,
                'amount' => 0.0,
                'paid' => $paidAmount,
                'remaining' => 0.0,
                'label' => (string) $code,
            ];
        }

        $capacityRemaining = round(max(0, $expectedDuePln - $ledgerPaid), 2);
        $installmentGaps = $this->buildInstallmentGaps($event, $expectedCount, $pricePerPerson, $ledgerPaid);
        $plnGaps = array_values(array_filter($installmentGaps, fn (array $g): bool => empty($g['is_foreign'])));
        $foreignGaps = array_values(array_filter($installmentGaps, fn (array $g): bool => ! empty($g['is_foreign'])));

        foreach ($installmentGaps as $gap) {
            if (($gap['remaining'] ?? 0) <= SettlementPaymentHealthService::TOLERANCE) {
                continue;
            }
            $worstStatus = $this->worseStatus($worstStatus, (string) ($gap['status'] ?? SettlementPaymentHealthService::STATUS_SHORTFALL));
        }

        if ($capacityRemaining > SettlementPaymentHealthService::TOLERANCE
            && $worstStatus === SettlementPaymentHealthService::STATUS_OK) {
            $worstStatus = SettlementPaymentHealthService::STATUS_SHORTFALL;
        }

        return [
            'count' => $payments->count(),
            'paid_count' => $paidFullCount,
            'waive_count' => $waiveCount,
            'overdue_count' => $statusCounts[SettlementPaymentHealthService::STATUS_OVERDUE] ?? 0,
            'due_count' => $statusCounts[SettlementPaymentHealthService::STATUS_DUE] ?? 0,
            'shortfall_count' => $statusCounts[SettlementPaymentHealthService::STATUS_SHORTFALL] ?? 0,
            // UI primary = pojemność imprezy (tylko PLN — waluty osobno)
            'total_due' => $expectedDuePln,
            'total_paid' => $ledgerPaid,
            'total_remaining' => $capacityRemaining,
            'coverage_status' => $worstStatus,
            'coverage_label' => SettlementPaymentHealthService::$statusLabels[$worstStatus] ?? $worstStatus,
            'profile' => $groupContract ? $this->profileResolver->resolve($groupContract) : null,
            'status_counts' => $statusCounts,
            'attention' => $attention,
            'expected_count' => $expectedCount,
            'gratis_count' => $gratisCount,
            'registered_count' => $payments->count(),
            'registered_paying_count' => $registeredPayingCount,
            'paid_full_count' => $paidFullCount,
            'paid_any_count' => $paidAnyCount,
            'unregistered_count' => $unregisteredCount,
            'price_per_person' => $pricePerPerson,
            'expected_due_pln' => $expectedDuePln,
            'expected_foreign' => $expectedForeign,
            'capacity_remaining_pln' => $capacityRemaining,
            'ledger_due_pln' => $ledgerDue,
            'ledger_remaining_pln' => $ledgerRemaining,
            'installment_gaps' => $installmentGaps,
            'installment_gaps_pln' => $plnGaps,
            'installment_gaps_foreign' => $foreignGaps,
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, EventSettlementParticipantPayment>  $payments
     * @return array<string, float>
     */
    private function sumPaidForeignByCurrency($payments): array
    {
        if (! Schema::hasTable('event_settlement_participant_payment_entries')
            || ! Schema::hasColumn('event_settlement_participant_payment_entries', 'currency_id')) {
            return [];
        }

        $totals = [];
        foreach ($payments as $payment) {
            $payment->loadMissing('entries.currency');
            foreach ($payment->entries as $entry) {
                if (! CurrencyAmountDisplay::isForeignCurrency($entry->currency_id)) {
                    continue;
                }
                $amount = (float) ($entry->amount ?? 0);
                if ($amount <= SettlementPaymentHealthService::TOLERANCE) {
                    continue;
                }
                $code = strtoupper((string) ($entry->currency?->code ?: $entry->currency?->symbol ?: 'FX'));
                $totals[$code] = round(($totals[$code] ?? 0) + $amount, 2);
            }
        }

        return $totals;
    }

    /**
     * Luki per transza względem N × kwota/os. (FIFO z sumy wpłat ledgeru).
     *
     * @return list<array{
     *     label: string,
     *     due_date: ?string,
     *     expected: float,
     *     paid_toward: float,
     *     remaining: float,
     *     is_foreign: bool,
     *     currency: ?string,
     *     status: string,
     *     status_label: string
     * }>
     */
    private function buildInstallmentGaps(Event $event, int $expectedCount, float $pricePerPerson, float $ledgerPaid): array
    {
        if ($expectedCount <= 0 || ! Schema::hasTable('event_payment_installment_templates')) {
            return [];
        }

        $perPerson = app(EventPaymentInstallmentTemplateService::class)
            ->materializeForBase($event, $pricePerPerson);

        if ($perPerson === []) {
            return [];
        }

        $remainingPaid = $ledgerPaid;
        $gaps = [];

        foreach ($perPerson as $row) {
            $foreign = (float) ($row['amount_foreign'] ?? 0);
            $isForeign = $foreign > SettlementPaymentHealthService::TOLERANCE
                && (float) ($row['amount'] ?? 0) <= SettlementPaymentHealthService::TOLERANCE;

            if ($isForeign) {
                $expected = round($foreign * $expectedCount, 2);
                $gaps[] = [
                    'label' => (string) ($row['label'] ?: 'Waluta'),
                    'due_date' => filled($row['due_date'] ?? null) ? (string) $row['due_date'] : null,
                    'expected' => $expected,
                    'paid_toward' => 0.0,
                    'remaining' => $expected,
                    'is_foreign' => true,
                    'currency' => $row['currency_code'] ?? null,
                    'status' => SettlementPaymentHealthService::STATUS_SHORTFALL,
                    'status_label' => 'Waluta (poza ledgerem PLN)',
                ];

                continue;
            }

            $expected = round(((float) ($row['amount'] ?? 0)) * $expectedCount, 2);
            if ($expected <= SettlementPaymentHealthService::TOLERANCE) {
                continue;
            }

            $paidToward = round(min($remainingPaid, $expected), 2);
            $remainingPaid = round(max(0, $remainingPaid - $paidToward), 2);
            $remaining = round(max(0, $expected - $paidToward), 2);

            $dueRaw = $row['due_date'] ?? null;
            $dueDate = filled($dueRaw) ? Carbon::parse((string) $dueRaw) : null;
            $status = $remaining <= SettlementPaymentHealthService::TOLERANCE
                ? SettlementPaymentHealthService::STATUS_OK
                : $this->resolveCoverageStatus(0.0, $remaining, $dueDate);

            $gaps[] = [
                'label' => (string) ($row['label'] ?: 'Transza'),
                'due_date' => $dueDate?->toDateString(),
                'expected' => $expected,
                'paid_toward' => $paidToward,
                'remaining' => $remaining,
                'is_foreign' => false,
                'currency' => 'PLN',
                'status' => $status,
                'status_label' => SettlementPaymentHealthService::$statusLabels[$status] ?? $status,
            ];
        }

        return $gaps;
    }

    /**
     * @return array{
     *     next_due_date: ?Carbon,
     *     next_due_amount: ?float,
     *     next_schedule_id: ?int,
     *     installment_label: ?string,
     *     profile: ?string,
     *     profile_label: ?string
     * }
     */
    private function resolveInstallmentContext(
        EventSettlementParticipantPayment $payment,
        ?float $duePln = null,
        ?float $paidPln = null,
    ): array {
        $duePln ??= (float) $payment->due_amount_pln;
        $paidPln ??= (float) $payment->paid_amount_pln;

        $contract = $payment->contracts()->first() ?? null;
        $profile = $contract ? $this->profileResolver->resolve($contract) : null;
        $profileLabel = $contract ? $this->profileResolver->label($contract) : null;

        if ($contract && Schema::hasTable('contract_payment_schedules')) {
            $schedules = $contract->paymentSchedules()
                ->orderBy('sort_order')
                ->orderBy('due_date')
                ->get();

            if ($schedules->isNotEmpty()) {
                // Gdy raty mają paid_amount — idź po pozostałości raty, nie po FIFO z ledgera.
                $hasSchedulePaid = $schedules->contains(
                    fn ($s): bool => round((float) ($s->paid_amount ?? 0), 2) > SettlementPaymentHealthService::TOLERANCE
                        || $s->paid_at !== null
                );

                if ($hasSchedulePaid) {
                    foreach ($schedules->sortBy('sort_order') as $schedule) {
                        $amount = round((float) ($schedule->amount ?? 0), 2);
                        if ($amount <= SettlementPaymentHealthService::TOLERANCE) {
                            continue;
                        }
                        $paid = round((float) ($schedule->paid_amount ?? 0), 2);
                        $remaining = round(max(0, $amount - $paid), 2);
                        if ($remaining <= SettlementPaymentHealthService::TOLERANCE) {
                            continue;
                        }

                        $dueRaw = $schedule->due_date ?? null;
                        $dueDate = $dueRaw instanceof Carbon
                            ? $dueRaw
                            : ($dueRaw ? Carbon::parse($dueRaw) : null);

                        return [
                            'next_due_date' => $dueDate,
                            'next_due_amount' => $remaining,
                            'next_schedule_id' => (int) $schedule->id,
                            'installment_label' => filled($schedule->label) ? (string) $schedule->label : 'Transza',
                            'profile' => $profile,
                            'profile_label' => $profileLabel,
                        ];
                    }

                    return [
                        'next_due_date' => null,
                        'next_due_amount' => null,
                        'next_schedule_id' => null,
                        'installment_label' => 'Wszystkie raty opłacone',
                        'profile' => $profile,
                        'profile_label' => $profileLabel,
                    ];
                }

                return $this->nextFromAbsoluteSchedules(
                    $schedules->map(fn ($s): array => [
                        'id' => (int) $s->id,
                        'label' => $s->label,
                        'amount' => (float) $s->amount,
                        'due_date' => $s->due_date,
                        'sort_order' => (int) $s->sort_order,
                    ])->all(),
                    $paidPln,
                    $duePln,
                    $profile,
                    $profileLabel,
                );
            }
        }

        // Uczestnik bez umowy / bez rat: terminy z szablonu imprezy.
        $event = null;
        if ($payment->relationLoaded('settlement') && $payment->settlement) {
            $event = $payment->settlement->relationLoaded('event')
                ? $payment->settlement->event
                : $payment->settlement->event()->first();
        } elseif ($payment->settlement_id) {
            $event = EventSettlement::query()->with('event')->find($payment->settlement_id)?->event;
        }

        if ($event instanceof Event && Schema::hasTable('event_payment_installment_templates')) {
            $virtual = app(EventPaymentInstallmentTemplateService::class)
                ->virtualSchedulesForAmount($event, $duePln);

            if ($virtual !== []) {
                return $this->nextFromAbsoluteSchedules(
                    collect($virtual)->values()->map(fn (array $row, int $i): array => [
                        'id' => null,
                        'label' => $row['label'],
                        'amount' => (float) $row['amount'],
                        'due_date' => $row['due_date'],
                        'sort_order' => $i,
                    ])->all(),
                    $paidPln,
                    $duePln,
                    $profile,
                    $profileLabel,
                );
            }
        }

        return [
            'next_due_date' => null,
            'next_due_amount' => null,
            'next_schedule_id' => null,
            'installment_label' => null,
            'profile' => $profile,
            'profile_label' => $profileLabel,
        ];
    }

    /**
     * @param  list<array{id: ?int, label: mixed, amount: float, due_date: mixed, sort_order: int}>  $schedules
     * @return array{
     *     next_due_date: ?Carbon,
     *     next_due_amount: ?float,
     *     next_schedule_id: ?int,
     *     installment_label: ?string,
     *     profile: ?string,
     *     profile_label: ?string
     * }
     */
    private function nextFromAbsoluteSchedules(
        array $schedules,
        float $paidPln,
        float $duePln,
        ?string $profile,
        ?string $profileLabel,
    ): array {
        if ($schedules === []) {
            return [
                'next_due_date' => null,
                'next_due_amount' => null,
                'next_schedule_id' => null,
                'installment_label' => null,
                'profile' => $profile,
                'profile_label' => $profileLabel,
            ];
        }

        $cumulative = 0.0;
        $next = null;

        foreach ($schedules as $schedule) {
            // Raty walutowe (amount 0) nie wpływają na pokrycie PLN.
            $amount = (float) ($schedule['amount'] ?? 0);
            if ($amount <= SettlementPaymentHealthService::TOLERANCE) {
                continue;
            }

            $cumulative += $amount;

            if ($paidPln + SettlementPaymentHealthService::TOLERANCE < $cumulative) {
                $next = $schedule;
                break;
            }
        }

        if ($next === null) {
            $fullyCovered = SettlementPaymentHealthService::isFullyPaid($paidPln, $duePln)
                || $duePln <= SettlementPaymentHealthService::TOLERANCE;

            return [
                'next_due_date' => null,
                'next_due_amount' => null,
                'next_schedule_id' => null,
                'installment_label' => $fullyCovered
                    ? 'Wszystkie raty opłacone'
                    : 'Harmonogram pokryty (pozostaje saldo)',
                'profile' => $profile,
                'profile_label' => $profileLabel,
            ];
        }

        $remainingForInstallment = max(0, round($cumulative - $paidPln, 2));
        $dueRaw = $next['due_date'] ?? null;
        $dueDate = $dueRaw instanceof Carbon
            ? $dueRaw
            : ($dueRaw ? Carbon::parse($dueRaw) : null);

        return [
            'next_due_date' => $dueDate,
            'next_due_amount' => $remainingForInstallment > 0 ? $remainingForInstallment : (float) $next['amount'],
            'next_schedule_id' => isset($next['id']) ? (int) $next['id'] : null,
            'installment_label' => filled($next['label'] ?? null)
                ? (string) $next['label']
                : ('Rata #'.(((int) ($next['sort_order'] ?? 0)) + 1)),
            'profile' => $profile,
            'profile_label' => $profileLabel,
        ];
    }

    private function resolveCoverageStatus(float $paidPln, float $duePln, ?Carbon $nextDue): string
    {
        if ($duePln <= SettlementPaymentHealthService::TOLERANCE) {
            return SettlementPaymentHealthService::STATUS_NA;
        }

        if (SettlementPaymentHealthService::isFullyPaid($paidPln, $duePln)) {
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
            SettlementPaymentHealthService::STATUS_NA => 0,
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
     * Cena/os. do pojemności imprezy: umowa/ręczna (effective), inaczej kalkulacja.
     *
     * @return array{PLN: float, foreign: array<string, float>}
     */
    private function resolveCapacityUnitPrices(Event $event, int $expectedCount, ?Contract $groupContract): array
    {
        $pln = 0.0;
        $foreign = [];

        try {
            $comparison = app(EventClientPriceComparisonService::class)->forEvent($event);
            foreach ($comparison['currencies'] ?? [] as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $code = strtoupper(trim((string) ($row['currency'] ?? '')));
                $unit = $row['effective'] ?? $row['calculation'] ?? null;
                if ($code === '' || $unit === null) {
                    continue;
                }
                $unit = round((float) $unit, 2);
                if ($unit <= SettlementPaymentHealthService::TOLERANCE) {
                    continue;
                }
                if ($code === 'PLN') {
                    $pln = $unit;
                } else {
                    $foreign[$code] = $unit;
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }

        if ($pln <= SettlementPaymentHealthService::TOLERANCE) {
            try {
                $priceSummary = app(EventPriceSummaryService::class)->forEvent($event, includeNearest: false);
                $pln = round((float) ($priceSummary['price_per_person_rounded'] ?? $priceSummary['price_per_person'] ?? 0), 2);
                if ($foreign === []) {
                    foreach ($priceSummary['foreign_prices'] ?? [] as $fx) {
                        if (! is_array($fx)) {
                            continue;
                        }
                        $code = strtoupper(trim((string) ($fx['currency'] ?? '')));
                        $ppp = round((float) ($fx['price_per_person'] ?? 0), 2);
                        if ($code === '' || $code === 'PLN' || $ppp <= SettlementPaymentHealthService::TOLERANCE) {
                            continue;
                        }
                        $foreign[$code] = $ppp;
                    }
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }

        if ($pln <= SettlementPaymentHealthService::TOLERANCE) {
            $pln = round((float) $event->resolvedPricePerPerson($expectedCount), 2);
        }
        if ($pln <= SettlementPaymentHealthService::TOLERANCE && $groupContract) {
            $pln = round(app(ContractGroupPricingService::class)->resolvedUnitPrice($groupContract, $event), 2);
        }

        return [
            'PLN' => $pln,
            'foreign' => $foreign,
        ];
    }

    /**
     * @return array{
     *     count: int,
     *     paid_count: int,
     *     waive_count: int,
     *     overdue_count: int,
     *     due_count: int,
     *     shortfall_count: int,
     *     total_due: float,
     *     total_paid: float,
     *     total_remaining: float,
     *     coverage_status: string,
     *     coverage_label: string,
     *     profile: null,
     *     status_counts: array<string, int>,
     *     attention: list<array<string, mixed>>
     * }
     */
    private function emptyAggregate(): array
    {
        return [
            'count' => 0,
            'paid_count' => 0,
            'waive_count' => 0,
            'overdue_count' => 0,
            'due_count' => 0,
            'shortfall_count' => 0,
            'total_due' => 0.0,
            'total_paid' => 0.0,
            'total_remaining' => 0.0,
            'coverage_status' => SettlementPaymentHealthService::STATUS_OK,
            'coverage_label' => SettlementPaymentHealthService::$statusLabels[SettlementPaymentHealthService::STATUS_OK] ?? 'Zapłacone',
            'profile' => null,
            'status_counts' => [
                SettlementPaymentHealthService::STATUS_OK => 0,
                SettlementPaymentHealthService::STATUS_DUE => 0,
                SettlementPaymentHealthService::STATUS_OVERDUE => 0,
                SettlementPaymentHealthService::STATUS_SHORTFALL => 0,
                SettlementPaymentHealthService::STATUS_NA => 0,
            ],
            'attention' => [],
            'expected_count' => 0,
            'gratis_count' => 0,
            'registered_count' => 0,
            'registered_paying_count' => 0,
            'paid_full_count' => 0,
            'paid_any_count' => 0,
            'unregistered_count' => 0,
            'price_per_person' => 0.0,
            'expected_due_pln' => 0.0,
            'expected_foreign' => [],
            'capacity_remaining_pln' => 0.0,
            'ledger_due_pln' => 0.0,
            'ledger_remaining_pln' => 0.0,
            'installment_gaps' => [],
            'installment_gaps_pln' => [],
            'installment_gaps_foreign' => [],
        ];
    }
}
