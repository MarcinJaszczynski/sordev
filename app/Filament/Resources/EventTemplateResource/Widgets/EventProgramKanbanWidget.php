<?php

namespace App\Filament\Resources\EventTemplateResource\Widgets;

use Filament\Widgets\Widget;
use App\Models\EventTemplate;
use Illuminate\Contracts\View\View;

class EventProgramKanbanWidget extends Widget
{
    public ?EventTemplate $eventTemplate = null;
    protected static string $view = 'filament.resources.event-template-resource.widgets.event-program-kanban-widget';
    protected static ?string $maxWidth = 'full';

    public function mount(EventTemplate $eventTemplate)
    {
        $this->eventTemplate = $eventTemplate;
    }

    public function getViewData(): array
    {
        $days = $this->eventTemplate->duration_days ?? 1;
        $columns = [];
        for ($i = 1; $i <= $days; $i++) {
            $columns[$i] = [
                'day' => $i,
                'title' => "Dzień $i",
                'points' => $this->eventTemplate->programPoints()
                    ->wherePivot('day', $i)
                    ->orderBy('event_template_event_template_program_point.order')
                    ->get(),
            ];
        }
        return [
            'columns' => $columns,
            'eventTemplate' => $this->eventTemplate,
        ];
    }
}
