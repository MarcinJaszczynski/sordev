<?php

declare(strict_types=1);

namespace App\Filament\Resources\MailTemplateResource\Pages;

use App\Filament\Resources\MailTemplateResource;
use App\Services\MailTemplateService;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMailTemplates extends ListRecords
{
    protected static string $resource = MailTemplateResource::class;

    public function mount(): void
    {
        parent::mount();
        app(MailTemplateService::class)->ensureDefaults();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
