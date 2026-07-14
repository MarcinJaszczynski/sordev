<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\EventAgreement;

class ContractPaymentScheduleService
{
    /**
     * @param  array<int, array<string, mixed>>  $schedules
     */
    public function syncForContract(Contract $contract, array $schedules, ?string $paymentScheme = null): void
    {
        $paymentScheme ??= (string) ($contract->payment_scheme ?? Contract::PAYMENT_SCHEME_LUMP_SUM);

        if ($paymentScheme !== Contract::PAYMENT_SCHEME_INSTALLMENTS) {
            $contract->paymentSchedules()->delete();

            return;
        }

        $normalized = app(ContractGroupPricingService::class)->normalizedSchedules($schedules);

        $contract->paymentSchedules()->delete();

        foreach ($normalized as $row) {
            $contract->paymentSchedules()->create([
                'sort_order' => $row['sort_order'],
                'label' => $row['label'],
                'amount' => $row['amount'],
                'due_date' => $row['due_date'],
                'notes' => $row['notes'],
            ]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $schedules
     */
    public function syncForEventAgreement(EventAgreement $agreement, array $schedules, ?string $paymentScheme = null): void
    {
        $paymentScheme ??= (string) ($agreement->payment_scheme ?? EventAgreement::PAYMENT_SCHEME_LUMP_SUM);

        if ($paymentScheme !== EventAgreement::PAYMENT_SCHEME_INSTALLMENTS) {
            $agreement->paymentSchedules()->delete();

            return;
        }

        $normalized = app(ContractGroupPricingService::class)->normalizedSchedules($schedules);

        $agreement->paymentSchedules()->delete();

        foreach ($normalized as $row) {
            $agreement->paymentSchedules()->create([
                'sort_order' => $row['sort_order'],
                'label' => $row['label'],
                'amount' => $row['amount'],
                'due_date' => $row['due_date'],
                'notes' => $row['notes'],
            ]);
        }
    }
}
