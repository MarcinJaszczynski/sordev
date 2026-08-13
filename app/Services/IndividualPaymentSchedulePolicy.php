<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Contract;
use App\Models\Event;
use Illuminate\Support\Carbon;

/**
 * Reguła dla umów indywidualnych: nie przyjmuj pełnej kwoty jednorazowo
 * wcześniej niż 30 dni przed startem imprezy.
 */
final class IndividualPaymentSchedulePolicy
{
    public const FULL_PAYMENT_MIN_DAYS_BEFORE_START = 30;

    public function daysUntilStart(Event $event, ?Carbon $asOf = null): ?int
    {
        if (! $event->start_date) {
            return null;
        }

        $asOf ??= now()->startOfDay();
        $start = Carbon::parse($event->start_date)->startOfDay();

        return (int) $asOf->diffInDays($start, false);
    }

    public function requiresInstallments(Event $event, ?Carbon $asOf = null): bool
    {
        $days = $this->daysUntilStart($event, $asOf);

        return $days !== null && $days > self::FULL_PAYMENT_MIN_DAYS_BEFORE_START;
    }

    /**
     * @param  list<array<string, mixed>>  $schedules
     * @return array{
     *     payment_scheme: string,
     *     payment_schedules: list<array<string, mixed>>,
     *     requires_installments: bool,
     *     policy_message: ?string
     * }
     */
    public function enforceForIndividual(
        Event $event,
        float $amountDuePln,
        string $paymentScheme,
        array $schedules,
        ?Carbon $asOf = null,
        bool $includeForeign = true,
    ): array {
        $amountDuePln = round(max(0, $amountDuePln), 2);
        $needsInstallments = $this->requiresInstallments($event, $asOf);

        if (! $includeForeign) {
            $schedules = array_values(array_filter(
                $schedules,
                static fn (array $row): bool => ! (
                    round((float) ($row['amount'] ?? 0), 2) <= 0.009
                    && round((float) ($row['amount_foreign'] ?? 0), 2) > 0.009
                ),
            ));
        }

        $hasMeaningfulSchedules = $this->hasPlnInstallmentSplit($schedules, $amountDuePln);

        // Pełna kwota PLN za wcześnie → zamień część PLN na zaliczkę+dopłatę, zachowaj raty FX (gdy dozwolone).
        if ($needsInstallments && $schedules !== [] && $this->hasFullAmountDueTooEarly($event, $schedules, $amountDuePln, $asOf)) {
            $foreignOnly = $includeForeign
                ? array_values(array_filter(
                    $schedules,
                    static fn (array $row): bool => round((float) ($row['amount'] ?? 0), 2) <= 0.009
                        && round((float) ($row['amount_foreign'] ?? 0), 2) > 0.009,
                ))
                : [];
            $schedules = array_merge(
                $this->suggestedCompliantSchedules($event, $amountDuePln),
                $foreignOnly,
            );
            $paymentScheme = Contract::PAYMENT_SCHEME_INSTALLMENTS;
            $hasMeaningfulSchedules = true;
        }

        if ($needsInstallments && $paymentScheme === Contract::PAYMENT_SCHEME_LUMP_SUM && ! $hasMeaningfulSchedules) {
            $schedules = $this->suggestedCompliantSchedules($event, $amountDuePln);
            $paymentScheme = Contract::PAYMENT_SCHEME_INSTALLMENTS;
        }

        if ($needsInstallments && $paymentScheme === Contract::PAYMENT_SCHEME_LUMP_SUM && $hasMeaningfulSchedules) {
            $paymentScheme = Contract::PAYMENT_SCHEME_INSTALLMENTS;
        }

        if ($needsInstallments && $paymentScheme === Contract::PAYMENT_SCHEME_INSTALLMENTS && ! $hasMeaningfulSchedules) {
            $schedules = $this->suggestedCompliantSchedules($event, $amountDuePln);
        }

        return [
            'payment_scheme' => $paymentScheme,
            'payment_schedules' => $schedules,
            'requires_installments' => $needsInstallments,
            'policy_message' => $needsInstallments
                ? 'Do startu imprezy jest więcej niż '
                    .self::FULL_PAYMENT_MIN_DAYS_BEFORE_START
                    .' dni — pełna kwota jednorazowo jest niedozwolona. Ustawiono / wymagane raty (zaliczka + dopłata).'
                : null,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function suggestedCompliantSchedules(Event $event, float $amountDuePln): array
    {
        $amountDuePln = round(max(0, $amountDuePln), 2);
        $deposit = round($amountDuePln * 0.30, 2);
        $remainder = round($amountDuePln - $deposit, 2);

        $start = $event->start_date ? Carbon::parse($event->start_date)->startOfDay() : null;
        $balanceDue = $start
            ? $start->copy()->subDays(self::FULL_PAYMENT_MIN_DAYS_BEFORE_START)->toDateString()
            : null;

        $rows = [];
        if ($deposit > 0) {
            $today = now()->toDateString();
            $rows[] = [
                'label' => 'Zaliczka (30%)',
                'amount' => $deposit,
                'amount_foreign' => null,
                'currency_code' => null,
                'paid_by' => 'office',
                'due_from' => $today,
                'due_to' => $today,
                'due_date' => $today,
                'notes' => 'Przy zawarciu umowy',
            ];
        }
        if ($remainder > 0) {
            $rows[] = [
                'label' => 'Dopłata (70%)',
                'amount' => $remainder,
                'amount_foreign' => null,
                'currency_code' => null,
                'paid_by' => 'office',
                'due_from' => $balanceDue,
                'due_to' => $balanceDue,
                'due_date' => $balanceDue,
                'notes' => 'Nie wcześniej niż D−'.self::FULL_PAYMENT_MIN_DAYS_BEFORE_START,
            ];
        }

        return $rows;
    }

    public function policyHintHtml(Event $event): string
    {
        if (! $this->requiresInstallments($event)) {
            return '<p class="text-sm text-gray-600 dark:text-gray-300">'
                .'Do startu ≤ '.self::FULL_PAYMENT_MIN_DAYS_BEFORE_START
                .' dni — możesz przyjąć pełną kwotę jednorazowo (plus ewentualna waluta w autokarze).</p>';
        }

        $days = $this->daysUntilStart($event);

        return '<p class="text-sm text-amber-700 dark:text-amber-300">'
            .'<strong>Reguła 30 dni:</strong> do startu pozostało '
            .e((string) $days)
            .' dni. Przy umowie indywidualnej nie przyjmuj pełnej kwoty jednorazowo — '
            .'zaliczka teraz, dopłata najwcześniej na D−'
            .self::FULL_PAYMENT_MIN_DAYS_BEFORE_START
            .'.</p>';
    }

    /**
     * @param  list<array<string, mixed>>  $schedules
     */
    private function hasPlnInstallmentSplit(array $schedules, float $amountDuePln): bool
    {
        $plnRows = collect($schedules)
            ->filter(fn (array $row): bool => round((float) ($row['amount'] ?? 0), 2) > 0.009)
            ->values();

        if ($plnRows->count() < 2) {
            return false;
        }

        $sum = round((float) $plnRows->sum(fn (array $row): float => (float) ($row['amount'] ?? 0)), 2);

        return $amountDuePln <= 0.009 || abs($sum - $amountDuePln) <= 0.05;
    }

    /**
     * @param  list<array<string, mixed>>  $schedules
     */
    private function hasFullAmountDueTooEarly(
        Event $event,
        array $schedules,
        float $amountDuePln,
        ?Carbon $asOf = null,
    ): bool {
        if ($amountDuePln <= 0.009 || ! $event->start_date) {
            return false;
        }

        $asOf ??= now()->startOfDay();
        $cutoff = Carbon::parse($event->start_date)->startOfDay()
            ->subDays(self::FULL_PAYMENT_MIN_DAYS_BEFORE_START);

        foreach ($schedules as $row) {
            $amount = round((float) ($row['amount'] ?? 0), 2);
            if ($amount + 0.01 < $amountDuePln) {
                continue;
            }

            $due = filled($row['due_date'] ?? null)
                ? Carbon::parse((string) $row['due_date'])->startOfDay()
                : $asOf;

            if ($due->lt($cutoff)) {
                return true;
            }
        }

        return false;
    }
}
