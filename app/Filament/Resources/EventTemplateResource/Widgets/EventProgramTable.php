<?php

namespace App\Filament\Resources\EventTemplateResource\Widgets;

use Filament\Widgets\Widget;
use App\Models\EventTemplate;
use Illuminate\Support\Collection;

class EventProgramTable extends Widget
{
    protected static string $view = 'filament.resources.event-template-resource.widgets.event-program-table';
    
    public ?EventTemplate $record = null;

    protected int | string | array $columnSpan = 'full';

    public function getProgramDaysProperty(): Collection
    {
        if (!$this->record) {
            return collect();
        }
        
        // Pobierz punkty programu posortowane według dni i kolejności
        $programPoints = $this->record->programPoints()
            ->withPivot(['day', 'order', 'notes', 'include_in_program', 'include_in_calculation', 'active'])
            ->orderBy('event_template_event_template_program_point.day')
            ->orderBy('event_template_event_template_program_point.order')
            ->get();

        // Pogrupuj punkty programu według dni
        $days = collect();
        for ($i = 1; $i <= $this->record->duration_days; $i++) {
            $daysPoints = $programPoints->filter(function ($point) use ($i) {
                return $point->pivot->day == $i && $point->pivot->include_in_program;
            });
            
            $days->put($i, $daysPoints);
        }

        return $days;
    }

    public function getDayInsurancesProperty()
    {
        if (!$this->record) return collect();
        return $this->record->dayInsurances->keyBy('day');
    }

    /**
     * Zwraca bazową cenę za osobę (bez ubezpieczenia) dla domyślnego wariantu ilości i waluty
     */
    public function getBasePricePerPersonProperty()
    {
        if (!$this->record) return null;
        // Pobierz pierwszy wariant ceny (np. najniższy wariant ilości i waluty)
        $price = \App\Models\EventTemplatePricePerPerson::where('event_template_id', $this->record->id)
            ->orderBy('event_template_qty_id')
            ->orderBy('currency_id')
            ->first();
        return $price;
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return view(static::$view, [
            'programDays' => $this->programDays,
            'dayInsurances' => $this->dayInsurances,
            'basePricePerPerson' => $this->basePricePerPerson,
        ]);
    }
}
