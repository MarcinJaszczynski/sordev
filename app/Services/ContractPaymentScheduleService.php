<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\ContractPaymentSchedule;
use App\Models\EventAgreement;
use App\Models\EventAgreementPaymentSchedule;
use Illuminate\Support\Facades\Schema;

class ContractPaymentScheduleService
{
    /**
     * @param  array<int, array<string, mixed>>  $schedules
     */
    public function syncForContract(Contract $contract, array $schedules, ?string $paymentScheme = null): void
    {
        $paymentScheme ??= (string) ($contract->payment_scheme ?? Contract::PAYMENT_SCHEME_LUMP_SUM);

        if ($paymentScheme !== Contract::PAYMENT_SCHEME_INSTALLMENTS) {
            $contract->paymentSchedules()
                ->where(function ($q): void {
                    $q->whereNull('paid_at')
                        ->where(function ($inner): void {
                            $inner->whereNull('paid_amount')
                                ->orWhere('paid_amount', '<=', 0);
                        });
                })
                ->delete();

            return;
        }

        $normalized = app(ContractGroupPricingService::class)->normalizedSchedules($schedules);
        $this->upsertContractSchedules($contract, $normalized);
    }

    /**
     * @param  array<int, array<string, mixed>>  $schedules
     */
    public function syncForEventAgreement(EventAgreement $agreement, array $schedules, ?string $paymentScheme = null): void
    {
        $paymentScheme ??= (string) ($agreement->payment_scheme ?? EventAgreement::PAYMENT_SCHEME_LUMP_SUM);

        if ($paymentScheme !== EventAgreement::PAYMENT_SCHEME_INSTALLMENTS) {
            $agreement->paymentSchedules()
                ->where(function ($q): void {
                    $q->whereNull('paid_at')
                        ->where(function ($inner): void {
                            $inner->whereNull('paid_amount')
                                ->orWhere('paid_amount', '<=', 0);
                        });
                })
                ->delete();

            return;
        }

        $normalized = app(ContractGroupPricingService::class)->normalizedSchedules($schedules);
        $this->upsertEventAgreementSchedules($agreement, $normalized);
    }

    /**
     * Upsert po sort_order — zachowuje paid_amount / paid_at przy edycji.
     *
     * @param  list<array<string, mixed>>  $normalized
     */
    private function upsertContractSchedules(Contract $contract, array $normalized): void
    {
        $existing = $contract->paymentSchedules()->get()->keyBy(fn (ContractPaymentSchedule $row) => (int) $row->sort_order);
        $keepIds = [];

        foreach ($normalized as $row) {
            $sort = (int) $row['sort_order'];
            $payload = $this->schedulePayload($row);

            /** @var ContractPaymentSchedule|null $model */
            $model = $existing->get($sort);
            if ($model) {
                $model->fill($payload)->save();
            } else {
                $model = $contract->paymentSchedules()->create($payload);
            }

            $keepIds[] = (int) $model->id;
        }

        if ($keepIds === []) {
            // Nie kasuj rat już opłaconych — tylko nieopłacone przy przejściu na lump sum obsługuje sync wyżej.
            $contract->paymentSchedules()
                ->where(function ($q): void {
                    $q->whereNull('paid_at')
                        ->where(function ($inner): void {
                            $inner->whereNull('paid_amount')
                                ->orWhere('paid_amount', '<=', 0);
                        });
                })
                ->delete();
        } else {
            $contract->paymentSchedules()
                ->whereNotIn('id', $keepIds)
                ->where(function ($q): void {
                    $q->whereNull('paid_at')
                        ->where(function ($inner): void {
                            $inner->whereNull('paid_amount')
                                ->orWhere('paid_amount', '<=', 0);
                        });
                })
                ->delete();
        }
    }

    /**
     * @param  list<array<string, mixed>>  $normalized
     */
    private function upsertEventAgreementSchedules(EventAgreement $agreement, array $normalized): void
    {
        $existing = $agreement->paymentSchedules()->get()->keyBy(fn (EventAgreementPaymentSchedule $row) => (int) $row->sort_order);
        $keepIds = [];

        foreach ($normalized as $row) {
            $sort = (int) $row['sort_order'];
            $payload = $this->schedulePayload($row);

            /** @var EventAgreementPaymentSchedule|null $model */
            $model = $existing->get($sort);
            if ($model) {
                $model->fill($payload)->save();
            } else {
                $model = $agreement->paymentSchedules()->create($payload);
            }

            $keepIds[] = (int) $model->id;
        }

        if ($keepIds === []) {
            $agreement->paymentSchedules()
                ->where(function ($q): void {
                    $q->whereNull('paid_at')
                        ->where(function ($inner): void {
                            $inner->whereNull('paid_amount')
                                ->orWhere('paid_amount', '<=', 0);
                        });
                })
                ->delete();
        } else {
            $agreement->paymentSchedules()
                ->whereNotIn('id', $keepIds)
                ->where(function ($q): void {
                    $q->whereNull('paid_at')
                        ->where(function ($inner): void {
                            $inner->whereNull('paid_amount')
                                ->orWhere('paid_amount', '<=', 0);
                        });
                })
                ->delete();
        }
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function schedulePayload(array $row): array
    {
        $payload = [
            'sort_order' => (int) $row['sort_order'],
            'label' => $row['label'],
            'amount' => $row['amount'],
            'due_date' => $row['due_date'],
            'notes' => $row['notes'],
        ];

        if (Schema::hasColumn((new ContractPaymentSchedule)->getTable(), 'amount_foreign')) {
            $payload['amount_foreign'] = $row['amount_foreign'] ?? null;
            $payload['currency_code'] = $row['currency_code'] ?? null;
            $payload['paid_by'] = $row['paid_by'] ?? null;
        }

        if (Schema::hasColumn((new ContractPaymentSchedule)->getTable(), 'due_from')) {
            $payload['due_from'] = $row['due_from'] ?? null;
            $payload['due_to'] = $row['due_to'] ?? ($row['due_date'] ?? null);
            if (filled($payload['due_to']) && blank($payload['due_date'])) {
                $payload['due_date'] = $payload['due_to'];
            }
        }

        return $payload;
    }
}
