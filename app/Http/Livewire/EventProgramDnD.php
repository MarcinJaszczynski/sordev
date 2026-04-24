<?php

namespace App\Http\Livewire;

use App\Models\Event;
use App\Models\EventProgramPoint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class EventProgramDnD extends Component
{
    public $eventId;

    public $points = [];

    protected $listeners = ['saveOrderFromDnDPayload' => 'saveOrder'];

    public function mount($eventId)
    {
        $this->eventId = $eventId;
        $this->loadPoints();
    }

    public function loadPoints()
    {
        $event = Event::find($this->eventId);
        if (! $event) {
            $this->points = [];

            return;
        }

        $this->points = $event->programPoints()->orderBy('day')->orderBy('order')->get()->map(function ($p) {
            return [
                'id' => $p->id,
                'name' => $p->name,
                'day' => $p->day,
                'order' => $p->order,
                'parent_id' => $p->parent_id,
            ];
        })->toArray();
    }

    public function saveOrder($payload)
    {
        // Accept payload either as direct array or wrapped inside event detail
        if (is_string($payload)) {
            $decoded = json_decode($payload, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $payload = $decoded;
            }
        }

        try {
            DB::transaction(function () use ($payload) {
                foreach ($payload as $item) {
                    $id = $item['id'] ?? null;
                    if (! $id) {
                        continue;
                    }
                    $newDay = $item['day'] ?? null;
                    $newOrder = $item['order'] ?? null;
                    $newParent = array_key_exists('parent_id', $item) ? ($item['parent_id'] !== '' ? (int) $item['parent_id'] : null) : null;

                    EventProgramPoint::where('id', $id)->update([
                        'day' => $newDay,
                        'order' => $newOrder,
                        'parent_id' => $newParent,
                    ]);
                }
            });

            $this->loadPoints();
            $this->dispatch('toast', type: 'success', message: 'Kolejność zapisana');
        } catch (\Throwable $e) {
            Log::error('Błąd zapisu kolejności DnD: '.$e->getMessage());
            $this->dispatch('toast', type: 'error', message: 'Błąd zapisu kolejności');
        }
    }

    public function moveUp($id)
    {
        $point = EventProgramPoint::find($id);
        if (! $point || $point->event_id != $this->eventId) {
            return;
        }

        $sibling = EventProgramPoint::where('event_id', $this->eventId)
            ->where('day', $point->day)
            ->where('order', '<', $point->order)
            ->orderBy('order', 'desc')
            ->first();

        if ($sibling) {
            $oldOrder = $point->order;
            $point->order = $sibling->order;
            $sibling->order = $oldOrder;
            $point->save();
            $sibling->save();
            $this->loadPoints();
            $this->dispatch('toast', type: 'success', message: 'Przeniesiono w górę');
        }
    }

    public function moveDown($id)
    {
        $point = EventProgramPoint::find($id);
        if (! $point || $point->event_id != $this->eventId) {
            return;
        }

        $sibling = EventProgramPoint::where('event_id', $this->eventId)
            ->where('day', $point->day)
            ->where('order', '>', $point->order)
            ->orderBy('order', 'asc')
            ->first();

        if ($sibling) {
            $oldOrder = $point->order;
            $point->order = $sibling->order;
            $sibling->order = $oldOrder;
            $point->save();
            $sibling->save();
            $this->loadPoints();
            $this->dispatch('toast', type: 'success', message: 'Przeniesiono w dół');
        }
    }

    public function setDay($id, $day)
    {
        $point = EventProgramPoint::find($id);
        if (! $point || $point->event_id != $this->eventId) {
            return;
        }
        $day = intval($day);
        $point->day = $day;
        // set order to end of that day
        $max = EventProgramPoint::where('event_id', $this->eventId)->where('day', $day)->max('order');
        $point->order = is_null($max) ? 0 : $max + 1;
        $point->save();
        $this->loadPoints();
    }

    public function render()
    {
        return view('livewire.event-program-dnd');
    }
}
