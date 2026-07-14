<?php

namespace App\Filament\Resources\EventResource\RelationManagers\Concerns;

use App\Models\Contract;
use App\Models\EventAgreement;
use App\Services\ContractGroupPricingService;
use App\Services\ContractPaymentScheduleService;

trait ManagesContractPaymentSchedules
{
    protected function groupPricingService(): ContractGroupPricingService
    {
        return app(ContractGroupPricingService::class);
    }

    protected function paymentScheduleService(): ContractPaymentScheduleService
    {
        return app(ContractPaymentScheduleService::class);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mergeGroupPricingIntoFormData(array $data): array
    {
        return $this->groupPricingService()->applyGroupPricingToFormData(
            $data,
            method_exists($this, 'getOwnerRecord') ? $this->getOwnerRecord() : null,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function syncPaymentSchedulesForContract(Contract $contract, array $data): void
    {
        $this->paymentScheduleService()->syncForContract(
            $contract,
            $this->groupPricingService()->extractSchedulesFromFormData($data),
            $data['payment_scheme'] ?? null,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function syncPaymentSchedulesForEventAgreement(EventAgreement $agreement, array $data): void
    {
        $this->paymentScheduleService()->syncForEventAgreement(
            $agreement,
            $this->groupPricingService()->extractSchedulesFromFormData($data),
            $data['payment_scheme'] ?? null,
        );
    }
}
