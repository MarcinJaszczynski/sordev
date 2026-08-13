<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Contract;
use App\Models\ContractPaymentSchedule;
use App\Models\EventAgreement;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

/**
 * Publiczny / demo checkout: płatność bieżącej raty PLN + wybór miejsca FX.
 */
final class ContractInstallmentCheckoutService
{
    public const FX_LOCATION_PILOT = 'pilot';

    public const FX_LOCATION_OFFICE = 'office';

    /**
     * @return array{
     *     contract_id: int,
     *     total_due_pln: float,
     *     total_paid_pln: float,
     *     total_remaining_pln: float,
     *     has_installments: bool,
     *     next_pln: ?array{
     *         id: int,
     *         label: ?string,
     *         amount: float,
     *         paid_amount: float,
     *         remaining: float,
     *         due_date: ?string,
     *         sort_order: int
     *     },
     *     next_step: 'pay_pln'|'pay_fx_info'|'done',
     *     fx_rows: list<array{
     *         id: int,
     *         label: ?string,
     *         amount_foreign: float,
     *         currency_code: ?string,
     *         paid_by: string,
     *         due_date: ?string,
     *         is_paid: bool
     *     }>,
     *     schedule_lines: list<array<string, mixed>>,
     *     aligned: bool
     * }
     */
    public function snapshot(Contract|EventAgreement $agreement): array
    {
        if ($agreement instanceof Contract) {
            $aligned = $this->alignSchedulesWithContractTotal($agreement);
        } else {
            $aligned = false;
        }

        // Zawsze świeże z DB — przy create() sync ładuje pustą relację i loadMissing jej nie odświeża.
        $schedules = $this->freshSchedules($agreement);

        $totalDue = round((float) ($agreement->amount_due ?? $agreement->total_price ?? 0), 2);
        $totalPaid = round((float) ($agreement->amount_paid ?? 0), 2);
        $fromSchedulesPaid = $this->sumPaidPln($schedules);
        if ($fromSchedulesPaid > $totalPaid + 0.009) {
            $totalPaid = $fromSchedulesPaid;
        }
        $totalRemaining = round(max(0, $totalDue - $totalPaid), 2);

        // Harmonogram PLN ma pierwszeństwo — nawet gdy payment_scheme w pamięci jest nieaktualny.
        $hasInstallments = $schedules->contains(
            fn ($row): bool => round((float) ($row->amount ?? 0), 2) > 0.009
        );

        $nextPln = null;
        if ($hasInstallments) {
            $nextPln = $this->resolveNextPlnInstallment($schedules);
        } elseif ($totalRemaining > 0.009) {
            $nextPln = [
                'id' => 0,
                'label' => 'Płatność umowy',
                'amount' => $totalDue,
                'paid_amount' => $totalPaid,
                'remaining' => $totalRemaining,
                'due_date' => null,
                'sort_order' => 0,
            ];
        }

        $fxRows = $this->fxRows($schedules);
        $nextStep = 'done';
        if ($nextPln !== null && (float) $nextPln['remaining'] > 0.009) {
            $nextStep = 'pay_pln';
        } elseif ($this->hasUnpaidFx($fxRows)) {
            $nextStep = 'pay_fx_info';
        }

        return [
            'contract_id' => (int) $agreement->id,
            'total_due_pln' => $totalDue,
            'total_paid_pln' => $totalPaid,
            'total_remaining_pln' => $totalRemaining,
            'has_installments' => $hasInstallments,
            'next_pln' => $nextPln,
            'next_step' => $nextStep,
            'fx_rows' => $fxRows,
            'schedule_lines' => $this->scheduleLines($schedules),
            'aligned' => $aligned,
        ];
    }

    /**
     * Dopasuj sumę rat PLN do aktualnego amount_due (gdy kwota umowy się zmieniła).
     * Już wpłacone kwoty zostają; zmieniają się tylko nieopłacone reszty rat.
     */
    public function alignSchedulesWithContractTotal(Contract $contract): bool
    {
        if (! Schema::hasTable('contract_payment_schedules')) {
            return false;
        }

        $schedules = $this->freshSchedules($contract);
        $pln = $schedules
            ->filter(fn (ContractPaymentSchedule $row): bool => round((float) $row->amount, 2) > 0.009)
            ->sortBy('sort_order')
            ->values();

        // Bez rat PLN nie ma czego wyrównywać (lump_sum / tylko FX).
        if ($pln->isEmpty()) {
            return false;
        }

        // Align tylko gdy umowa jest na raty albo już ma >1 transzę PLN.
        $scheme = (string) ($contract->payment_scheme ?? '');
        if ($scheme !== Contract::PAYMENT_SCHEME_INSTALLMENTS && $pln->count() < 2) {
            return false;
        }

        $target = round((float) ($contract->amount_due ?? $contract->total_price ?? 0), 2);
        if ($target <= 0) {
            return false;
        }

        $currentSum = round((float) $pln->sum(fn (ContractPaymentSchedule $row): float => (float) $row->amount), 2);
        if (abs($currentSum - $target) <= 0.05) {
            return false;
        }

        $paidLocked = round((float) $pln->sum(fn (ContractPaymentSchedule $row): float => (float) ($row->paid_amount ?? 0)), 2);
        $remainingTarget = round(max(0, $target - $paidLocked), 2);

        $open = $pln->filter(function (ContractPaymentSchedule $row): bool {
            $amount = round((float) $row->amount, 2);
            $paid = round((float) ($row->paid_amount ?? 0), 2);

            return ($amount - $paid) > 0.009;
        })->values();

        // Brak otwartych rat — dopisz różnicę do ostatniej albo zostaw (nie tworzymy nowych wierszy).
        if ($open->isEmpty()) {
            if ($remainingTarget <= 0.009) {
                return false;
            }
            /** @var ContractPaymentSchedule $last */
            $last = $pln->last();
            $paid = round((float) ($last->paid_amount ?? 0), 2);
            $last->forceFill([
                'amount' => round($paid + $remainingTarget, 2),
            ])->save();

            return true;
        }

        $openRemainingSum = round((float) $open->sum(function (ContractPaymentSchedule $row): float {
            return max(0, (float) $row->amount - (float) ($row->paid_amount ?? 0));
        }), 2);

        if ($openRemainingSum <= 0.009 && $remainingTarget <= 0.009) {
            return false;
        }

        DB::transaction(function () use ($open, $openRemainingSum, $remainingTarget): void {
            $assignedRemaining = 0.0;
            $lastIndex = $open->count() - 1;

            foreach ($open as $index => $row) {
                $paid = round((float) ($row->paid_amount ?? 0), 2);
                $oldRemaining = round(max(0, (float) $row->amount - $paid), 2);

                if ($index === $lastIndex) {
                    $newRemaining = round(max(0, $remainingTarget - $assignedRemaining), 2);
                } elseif ($openRemainingSum > 0.009) {
                    $newRemaining = round($remainingTarget * ($oldRemaining / $openRemainingSum), 2);
                    $assignedRemaining = round($assignedRemaining + $newRemaining, 2);
                } else {
                    $newRemaining = 0.0;
                }

                $row->forceFill([
                    'amount' => round($paid + $newRemaining, 2),
                    'paid_amount' => $paid,
                ])->save();
            }
        });

        // Domknięte raty (paid >= amount) bez zmian kwoty — ich paid już w paidLocked.
        return true;
    }

    /**
     * @param  array<int|string, string>  $fxLocations schedule_id => office|pilot
     * @return array{snapshot: array<string, mixed>, charged: float, schedule_id: ?int, fully_paid: bool}
     */
    public function applyDemoPlnPayment(
        Contract $contract,
        string $paymentMethod,
        array $fxLocations = [],
    ): array {
        $this->persistFxLocations($contract, $fxLocations);
        $snapshot = $this->snapshot($contract);
        $next = $snapshot['next_pln'];

        if ($next === null || (float) $next['remaining'] <= 0.009) {
            throw new InvalidArgumentException('Brak kwoty PLN do zapłaty w tym kroku.');
        }

        $charge = round((float) $next['remaining'], 2);
        $scheduleId = (int) ($next['id'] ?? 0);

        DB::transaction(function () use ($contract, $paymentMethod, $charge, $scheduleId): void {
            if ($scheduleId > 0) {
                /** @var ContractPaymentSchedule|null $schedule */
                $schedule = $contract->paymentSchedules()->lockForUpdate()->find($scheduleId);
                if (! $schedule) {
                    throw new InvalidArgumentException('Nie znaleziono raty do opłacenia.');
                }

                $due = round((float) $schedule->amount, 2);
                $paid = round((float) ($schedule->paid_amount ?? 0) + $charge, 2);
                $schedule->forceFill([
                    'paid_amount' => min($paid, $due > 0 ? $due : $paid),
                    'paid_at' => $schedule->paid_at ?? now(),
                ])->save();
            }

            $contract->refresh();
            $newPaid = round((float) ($contract->amount_paid ?? 0) + $charge, 2);
            $totalDue = round((float) ($contract->amount_due ?? 0), 2);
            $fullyPaidPln = $newPaid + 0.009 >= $totalDue;

            $contract->forceFill([
                'payment_method' => $paymentMethod,
                'amount_paid' => min($newPaid, $totalDue > 0 ? $totalDue : $newPaid),
                'paid_at' => $fullyPaidPln ? now() : ($contract->paid_at ?? now()),
                'payment_status' => $fullyPaidPln ? 'paid' : 'partial',
                'status' => $contract->status === 'signed' || $contract->status === 'completed'
                    ? 'completed'
                    : $contract->status,
            ])->save();
        });

        $fresh = $contract->fresh(['paymentSchedules']);
        $after = $this->snapshot($fresh);

        return [
            'snapshot' => $after,
            'charged' => $charge,
            'schedule_id' => $scheduleId > 0 ? $scheduleId : null,
            'fully_paid' => $after['next_step'] === 'done'
                || ($after['total_remaining_pln'] <= 0.009 && ! $this->hasUnpaidFx($after['fx_rows'])),
        ];
    }

    /**
     * @param  array<int|string, string>  $fxLocations
     */
    public function persistFxLocations(Contract $contract, array $fxLocations): void
    {
        if ($fxLocations === [] || ! Schema::hasColumn('contract_payment_schedules', 'paid_by')) {
            return;
        }

        foreach ($fxLocations as $scheduleId => $location) {
            $location = in_array($location, [self::FX_LOCATION_OFFICE, self::FX_LOCATION_PILOT], true)
                ? $location
                : self::FX_LOCATION_PILOT;

            $contract->paymentSchedules()
                ->whereKey((int) $scheduleId)
                ->where('amount', '<=', 0.009)
                ->where('amount_foreign', '>', 0)
                ->update(['paid_by' => $location]);
        }
    }

    /**
     * @return Collection<int, ContractPaymentSchedule>
     */
    private function freshSchedules(Contract|EventAgreement $agreement): Collection
    {
        $agreement->unsetRelation('paymentSchedules');

        return $agreement->paymentSchedules()->orderBy('sort_order')->get();
    }

    /**
     * @param  Collection<int, ContractPaymentSchedule>  $schedules
     * @return ?array{id: int, label: ?string, amount: float, paid_amount: float, remaining: float, due_date: ?string, sort_order: int}
     */
    private function resolveNextPlnInstallment(Collection $schedules): ?array
    {
        foreach ($schedules as $row) {
            $amount = round((float) ($row->amount ?? 0), 2);
            if ($amount <= 0.009) {
                continue;
            }
            $paid = round((float) ($row->paid_amount ?? 0), 2);
            $remaining = round(max(0, $amount - $paid), 2);
            if ($remaining <= 0.009) {
                continue;
            }

            return [
                'id' => (int) $row->id,
                'label' => $row->label,
                'amount' => $amount,
                'paid_amount' => $paid,
                'remaining' => $remaining,
                'due_date' => optional($row->due_date)?->format('d.m.Y'),
                'sort_order' => (int) ($row->sort_order ?? 0),
            ];
        }

        return null;
    }

    /**
     * @param  Collection<int, mixed>  $schedules
     * @return list<array<string, mixed>>
     */
    private function fxRows(Collection $schedules): array
    {
        $rows = [];
        foreach ($schedules as $row) {
            $fx = round((float) ($row->amount_foreign ?? 0), 2);
            $pln = round((float) ($row->amount ?? 0), 2);
            if ($fx <= 0.009 || $pln > 0.009) {
                continue;
            }
            $paidBy = (string) ($row->paid_by ?: self::FX_LOCATION_PILOT);
            $rows[] = [
                'id' => (int) $row->id,
                'label' => $row->label,
                'amount_foreign' => $fx,
                'currency_code' => $row->currency_code ? strtoupper((string) $row->currency_code) : null,
                'paid_by' => $paidBy,
                'due_date' => optional($row->due_date)?->format('d.m.Y'),
                'is_paid' => false,
            ];
        }

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $fxRows
     */
    private function hasUnpaidFx(array $fxRows): bool
    {
        return collect($fxRows)->contains(fn (array $row): bool => ! ($row['is_paid'] ?? false));
    }

    /**
     * @param  Collection<int, mixed>  $schedules
     */
    private function sumPaidPln(Collection $schedules): float
    {
        return round((float) $schedules->sum(function ($row): float {
            if (round((float) ($row->amount ?? 0), 2) <= 0.009) {
                return 0.0;
            }

            return (float) ($row->paid_amount ?? 0);
        }), 2);
    }

    /**
     * @param  Collection<int, mixed>  $schedules
     * @return list<array<string, mixed>>
     */
    private function scheduleLines(Collection $schedules): array
    {
        return $schedules->map(function ($row): array {
            $amount = round((float) ($row->amount ?? 0), 2);
            $paid = round((float) ($row->paid_amount ?? 0), 2);
            $fx = round((float) ($row->amount_foreign ?? 0), 2);

            return [
                'id' => (int) $row->id,
                'label' => $row->label ?: 'Transza',
                'amount' => $amount,
                'paid_amount' => $paid,
                'remaining' => round(max(0, $amount - $paid), 2),
                'amount_foreign' => $fx > 0 ? $fx : null,
                'currency_code' => $row->currency_code,
                'paid_by' => $row->paid_by,
                'due_date' => optional($row->due_date)?->format('d.m.Y'),
                'is_pln' => $amount > 0.009,
                'is_fx' => $fx > 0.009 && $amount <= 0.009,
                'is_paid' => $amount > 0.009 ? ($paid + 0.009 >= $amount) : false,
            ];
        })->values()->all();
    }
}
