<?php

namespace App\Http\Livewire;

use App\Models\EventTemplate;
use App\Models\EventTemplateProgramPoint;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class EventProgramManager extends Component
{
    public int $eventTemplateId;

    public array $pointsByDay = [];

    protected $listeners = ['pointDropped' => 'updateOrder'];

    public function mount(EventTemplate $eventTemplate)
    {
        $this->eventTemplateId = $eventTemplate->id;
        $this->loadPoints();
    }

    public function loadPoints(): void
    {
        $rows = DB::table('event_template_event_template_program_point')
            ->where('event_template_id', $this->eventTemplateId)
            ->orderBy('day')
            ->orderBy('order')
            ->get();

        $items = [];
        foreach ($rows as $row) {
            $pointModel = EventTemplateProgramPoint::find($row->event_template_program_point_id);
            $items[$row->id] = [
                'id' => $row->id,
                'parent_id' => $row->parent_id,
                'day' => $row->day,
                'order' => $row->order,
                'name' => $pointModel?->name ?? '—',
                'children' => [],
            ];
        }

        $grouped = [];
        foreach ($items as $id => &$item) {
            if ($item['parent_id'] && isset($items[$item['parent_id']])) {
                $items[$item['parent_id']]['children'][] = &$item;
            } else {
                $grouped[$item['day']][] = &$item;
            }
        }

        foreach ($grouped as $day => &$dayItems) {
            usort($dayItems, fn ($a, $b) => $a['order'] <=> $b['order']);
        }

        $this->pointsByDay = $grouped;
    }

    public function updateOrder(array $payload): void
    {
        DB::table('event_template_event_template_program_point')
            ->where('id', $payload['id'])
            ->update([
                'parent_id' => $payload['parent'],
                'day' => $payload['day'],
                'order' => $payload['order'],
            ]);

        $this->loadPoints();
    }

    public function render()
    {
        return view('livewire.event-program-manager');
    }
}
