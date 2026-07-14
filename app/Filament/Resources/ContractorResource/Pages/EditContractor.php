<?php

namespace App\Filament\Resources\ContractorResource\Pages;

use App\Filament\Resources\ContractorResource;
use App\Support\ContractorContactDetails;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditContractor extends EditRecord
{
    protected static string $resource = ContractorResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (! ContractorResource::formTypesIncludePilot($data['types'] ?? null)) {
            $data['birth_date'] = null;
            $data['pesel'] = null;
        }

        return $data;
    }

    public function getSubheading(): ?string
    {
        $summary = ContractorContactDetails::inlineSummary($this->getRecord());

        return $summary !== '' ? $summary : null;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
