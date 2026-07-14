<?php

namespace App\Filament\Resources\ContractResource\Pages;

use App\Filament\Resources\ContractResource;
use Filament\Resources\Pages\CreateRecord;

class CreateContract extends CreateRecord
{
    protected static string $resource = ContractResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (blank($data['event_id'] ?? null) && request()->has('event_id')) {
            $data['event_id'] = request()->integer('event_id');
        }

        return $data;
    }
}
