<?php

namespace App\Http\Livewire;

use Livewire\Component;
use App\Models\EventTemplate;
use App\Models\EventTemplateProgramPoint;
use Illuminate\Support\Facades\DB;

class EventProgramTree extends Component
{
    public EventTemplate $eventTemplate;
    public $pointsByDay = [];

    protected $listeners = ['updateOrder', 'togglePivotProperty'];

    public function togglePivotProperty($pivotId, $property)
    {
        $allowed = ['include_in_program', 'include_in_calculation', 'active'];
        if (!in_array($property, $allowed)) return;
        $pivot = DB::table('event_template_event_template_program_point')->where('id', $pivotId)->first();
        if ($pivot) {
            $newValue = !$pivot->$property;
            DB::table('event_template_event_template_program_point')
                ->where('id', $pivotId)
                ->update([$property => $newValue]);
            $this->loadPoints();
        }
    }

    // Zaznacz/odznacz ubezpieczenie dla danego dnia
    public function toggleDayInsurance($day, $insuranceId)
    {
        $templateId = $this->eventTemplate->id;
        $exists = \App\Models\EventTemplateDayInsurance::where('event_template_id', $templateId)
            ->where('day', $day)
            ->where('insurance_id', $insuranceId)
            ->first();
        if ($exists) {
            $exists->delete();
        } else {
            \App\Models\EventTemplateDayInsurance::create([
                'event_template_id' => $templateId,
                'day' => $day,
                'insurance_id' => $insuranceId,
            ]);
        }
        // Odśwież dane
        $this->eventTemplate->load('dayInsurances');
    }

    public function mount(EventTemplate $eventTemplate)
    {
        $this->eventTemplate = $eventTemplate;
        $this->loadPoints();
    }

    protected function loadPoints()
    {
        // Pobierz wpisy pivot
        $rows = DB::table('event_template_event_template_program_point')
            ->where('event_template_id', $this->eventTemplate->id)
            ->orderBy('day')
            ->orderBy('order')
            ->get();

        // Zbuduj płaską listę elementów z właściwościami pivot
        $items = [];
        foreach ($rows as $row) {
            $point = EventTemplateProgramPoint::find($row->event_template_program_point_id);
            $items[] = [
                'pivot_id'   => $row->id,
                'parent_id'  => $row->parent_id,
                'day'        => $row->day,
                'order'      => $row->order,
                'name'       => $point->name,
                'description'=> $point->description,
                'include_in_program' => $row->include_in_program,
                'include_in_calculation' => $row->include_in_calculation,
                'active' => $row->active,
                'children'   => [],
            ];
        }

        // Grupuj i zagnieżdżaj według dni
        $grouped = [];
        foreach ($items as $item) {
            $day = $item['day'];
            if (!isset($grouped[$day])) {
                $grouped[$day] = [];
            }
            $grouped[$day][$item['pivot_id']] = $item;
        }

        foreach ($grouped as $day => &$map) {
            $tree = [];
            // przypisz dzieci
            foreach ($map as $id => &$node) {
                if ($node['parent_id'] && isset($map[$node['parent_id']])) {
                    $map[$node['parent_id']]['children'][] = &$node;
                } else {
                    $tree[] = &$node;
                }
            }
            // sortuj końcową listę po order
            usort($tree, fn($a, $b) => $a['order'] <=> $b['order']);
            $grouped[$day] = $tree;
        }

        $this->pointsByDay = $grouped;
    }

    public function updateOrder($pivotId, $parentPivotId, $day, $order)
    {
        DB::table('event_template_event_template_program_point')
            ->where('id', $pivotId)
            ->update([
                'parent_id' => $parentPivotId,
                'day'       => $day,
                'order'     => $order,
            ]);

        $this->loadPoints();
    }

    public function render()
    {
        return view('livewire.event-program-tree');
    }

    /**
     * Oblicz sumę kosztów ubezpieczeń dla wszystkich dni
     * Uwzględnia: insurance_enabled, insurance_per_day, insurance_per_person, qty, qty_gratis
     */
    public function calculateTotalInsurance($qty = 1, $qty_gratis = 0)
    {
        $totalInsurance = 0.0;

        // Zgrupuj ubezpieczenia po dniach i dla każdego dnia policz sumę price_per_person,
        // potem pomnóż przez (qty + qty_gratis) — zgodnie ze specyfikacją.
        $daysGrouped = [];
        foreach ($this->eventTemplate->dayInsurances as $dayInsurance) {
            $insurance = $dayInsurance->insurance;
            if (! $insurance || ! $insurance->insurance_enabled) continue;

            $day = $dayInsurance->day;
            if (! isset($daysGrouped[$day])) $daysGrouped[$day] = 0.0;

            // Dodajemy zawsze price_per_person — to pozwala uwzględnić wszystkie pozycje
            // przypisane do danego dnia.
            $daysGrouped[$day] += (float) $insurance->price_per_person;
        }

        $count = max(0, $qty) + max(0, $qty_gratis);
        foreach ($daysGrouped as $daySum) {
            $totalInsurance += $daySum * $count;
        }

        return $totalInsurance;
    }
}
