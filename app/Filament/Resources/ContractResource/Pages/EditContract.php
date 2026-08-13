<?php

namespace App\Filament\Resources\ContractResource\Pages;

use App\Filament\Resources\ContractResource;
use App\Filament\Resources\ContractResource\Concerns\ManagesContractEditHeaderActions;
use Filament\Resources\Pages\EditRecord;

class EditContract extends EditRecord
{
    use ManagesContractEditHeaderActions;

    protected static string $resource = ContractResource::class;

    protected function getHeaderActions(): array
    {
        return $this->contractOperationalHeaderActions();
    }
}
