<?php

namespace App\Filament\Resources\EventResource\RelationManagers\Concerns;

use App\Models\Contract;
use App\Models\EventAgreement;
use App\Services\ContractOrderingPartyService;

trait ManagesContractOrderingParties
{
    protected function orderingPartyService(): ContractOrderingPartyService
    {
        return app(ContractOrderingPartyService::class);
    }

    protected function mergeOrderingPartiesIntoFormData(array $data): array
    {
        return $this->orderingPartyService()->applyPrimaryCustomerToFormData($data);
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<int, array<string, mixed>>, 2: ?string}
     */
    protected function extractOrderingPartyPayload(array $data): array
    {
        return $this->orderingPartyService()->extractOrderingPartyPayload($data);
    }

    protected function syncOrderingPartiesForContract(Contract $contract, array $parties, ?string $notes = null): void
    {
        $this->orderingPartyService()->syncForContract($contract, $parties, $notes);
    }

    protected function syncOrderingPartiesForEventAgreement(EventAgreement $agreement, array $parties, ?string $notes = null): void
    {
        $this->orderingPartyService()->syncForEventAgreement($agreement, $parties, $notes);
    }
}
