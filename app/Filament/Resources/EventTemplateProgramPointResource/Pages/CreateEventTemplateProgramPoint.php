<?php

namespace App\Filament\Resources\EventTemplateProgramPointResource\Pages;

use App\Filament\Resources\EventTemplateProgramPointResource;
use Filament\Resources\Pages\CreateRecord;

class CreateEventTemplateProgramPoint extends CreateRecord
{
    protected static string $resource = EventTemplateProgramPointResource::class;

    public function getTitle(): string
    {
        return 'Dodaj nowy punkt programu';
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Punkt programu został utworzony';
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
