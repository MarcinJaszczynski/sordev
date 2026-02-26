<?php

namespace App\Filament\Resources\EventResource\Pages;

use App\Filament\Resources\EventResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditEvent extends EditRecord
{
    protected static string $resource = EventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('calculation')
                ->label('Kosztorys')
                ->icon('heroicon-o-calculator')
                ->url(fn () => static::getResource()::getUrl('calculation', ['record' => $this->record->id]))
                ->color('success'),
            Actions\DeleteAction::make()
                ->visible(fn () => $this->record->status === 'draft'),
        ];
    }
}
