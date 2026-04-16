<?php

namespace App\Filament\Resources\EventSettlementResource\Pages;

use App\Filament\Resources\EventSettlementResource;
use Filament\Resources\Pages\CreateRecord;

class CreateEventSettlement extends CreateRecord
{
    protected static string $resource = EventSettlementResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();

        foreach (['planned_cost_pln', 'actual_cost_pln', 'participant_due_pln', 'participant_paid_pln'] as $field) {
            $data[$field] = (float) ($data[$field] ?? 0);
        }

        $data['status'] = $data['status'] ?? 'draft';

        return $data;
    }
}
