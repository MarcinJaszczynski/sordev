<?php

namespace App\Filament\Resources\MarkupResource\Pages;

use App\Filament\Resources\MarkupResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;

class CreateMarkup extends CreateRecord
{
    protected static string $resource = MarkupResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (!empty($data['is_default']) && \App\Models\Markup::where('is_default', true)->exists()) {
            Notification::make()
                ->title('Uwaga: Istnieje już domyślny narzut!')
                ->body('Zaznaczenie tego pola spowoduje zastąpienie obecnego domyślnego narzutu.')
                ->warning()
                ->send();
        }
        return $data;
    }
}
