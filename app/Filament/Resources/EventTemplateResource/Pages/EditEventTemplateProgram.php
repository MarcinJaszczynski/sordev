<?php

namespace App\Filament\Resources\EventTemplateResource\Pages;

use App\Filament\Resources\EventTemplateResource;
use Filament\Resources\Pages\Page;
use App\Models\EventTemplate;

class EditEventTemplateProgram extends Page
{
    protected static string $resource = EventTemplateResource::class;
    protected static string $view = 'filament.resources.event-template-resource.pages.edit-event-template-program';

    public $record;
    public EventTemplate $eventTemplate;

    public function mount($record): void
    {
        $this->record = $record;
        $this->eventTemplate = EventTemplate::findOrFail($record);
    }
}
