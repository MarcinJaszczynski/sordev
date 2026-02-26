<?php

namespace App\Filament\Resources\EventTemplateProgramPointResource\Widgets;

use Filament\Widgets\Widget;
use App\Models\EventTemplateProgramPoint;
use Illuminate\Support\Facades\Log; // Dodaj import Log

class EventTemplatesForProgramPoint extends Widget
{
    protected static string $view = 'filament.resources.event-template-program-point-resource.widgets.event-templates-for-program-point';

    public ?EventTemplateProgramPoint $record = null;

    // Usunięto własny konstruktor __construct, bo nie jest obsługiwany przez Filament/Livewire

    public function getEventTemplatesProperty()
    {
        if (!$this->record) {
            Log::info('EventTemplatesForProgramPoint: Record is null');
            return collect();
        }
        // Upewnij się, że relacja jest załadowana, aby uniknąć N+1, chociaż Filament często sobie z tym radzi.
        // $this->record->loadMissing('eventTemplates'); 
        $templates = $this->record->eventTemplates;
        Log::info('EventTemplatesForProgramPoint: Templates data for record ' . $this->record->id, $templates->map(function($template) {
            return ['id' => $template->id, 'name' => $template->name];
        })->toArray());
        return $templates;
    }
}
