<?php

namespace App\Filament\Resources\KategoriaSzablonuResource\Pages;

use App\Filament\Resources\KategoriaSzablonuResource;
use Filament\Resources\Pages\CreateRecord;

class CreateKategoriaSzablonu extends CreateRecord
{
    protected static string $resource = KategoriaSzablonuResource::class;

    public function getTitle(): string
    {
        return 'Dodaj kategorię szablonu';
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Kategoria szablonu została utworzona';
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
