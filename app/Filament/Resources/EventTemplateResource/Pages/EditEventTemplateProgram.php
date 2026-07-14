<?php

namespace App\Filament\Resources\EventTemplateResource\Pages;

use App\Filament\Concerns\AuthorizesEventTemplatePages;
use App\Filament\Resources\EventTemplateResource;
use App\Filament\Resources\EventTemplateResource\Concerns\HasEventTemplateWorkflowContext;
use App\Models\EventTemplate;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;

class EditEventTemplateProgram extends Page
{
    use AuthorizesEventTemplatePages;
    use HasEventTemplateWorkflowContext;
    use InteractsWithRecord;

    protected static string $resource = EventTemplateResource::class;

    protected static string $view = 'filament.resources.event-template-resource.pages.edit-event-template-program';

    protected static ?string $navigationLabel = 'Program';

    protected static ?string $title = 'Program szablonu';

    protected static ?string $navigationIcon = 'heroicon-o-bars-3';

    protected static function requiresFullTemplateEdit(): bool
    {
        return false;
    }

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    public function getEventTemplateProperty(): EventTemplate
    {
        return $this->record;
    }
}
