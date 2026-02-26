<?php

namespace App\Filament\Resources\EventTemplateResource\Widgets;

use Filament\Widgets\Widget;
use App\Models\EventTemplate;
use App\Models\EventTemplateProgramPoint;

class EventProgramKanban extends Widget
{
    protected static string $view = 'filament.resources.event-template-resource.widgets.event-program-kanban';
    public ?EventTemplate $record = null;
    public array $columns = [];
    public array $allProgramPoints = [];

    public function mount(): void
    {
        $this->record = $this->getRecord();
        $this->allProgramPoints = EventTemplateProgramPoint::orderBy('name')->get()->toArray();
        $this->columns = $this->getColumns();
    }

    protected function getRecord(): ?EventTemplate
    {
        // Filament automatycznie przekazuje $record do widgetu na stronie zasobu
        return $this->record ?? null;
    }

    protected function getColumns(): array
    {
        $columns = [];
        if (!$this->record) return $columns;
        $days = $this->record->duration_days ?? 1;
        for ($i = 1; $i <= $days; $i++) {
            $columns[$i] = [
                'day' => $i,
                'title' => "Dzień $i",
                'points' => [],
            ];
        }
        $programPoints = $this->record->programPoints()->withPivot(['id', 'day', 'order', 'notes', 'include_in_program', 'include_in_calculation', 'active'])->get();
        foreach ($programPoints as $point) {
            $day = $point->pivot->day ?? 1;
            if (isset($columns[$day])) {
                $columns[$day]['points'][] = $point;
            }
        }
        return $columns;
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return view(static::$view, [
            'columns' => $this->columns,
            'allProgramPoints' => $this->allProgramPoints,
        ]);
    }
}