<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Event;
use App\Models\EventProgramPoint;
use App\Models\EventTemplateProgramPoint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EventProgramEditor extends Component
{
    public Event $event;
    public $showModal = false;
    public $modalData = [];
    public $editMode = false;
    public $editingPointId = null;
    public $expandedItems = [];

    public function mount(Event $event)
    {
        Log::info('EventProgramEditor::mount() wywołane', [
            'event' => $event ? $event->id : 'null',
            'eventName' => $event ? $event->name : 'null'
        ]);

        $this->event = $event;
        $this->resetModalData();
    }

    protected function resetModalData()
    {
        $this->modalData = [
            'event_template_program_point_id' => null,
            'day' => 1,
            'order' => 0,
            'unit_price' => 0,
            'quantity' => 1,
            'notes' => '',
            'include_in_program' => true,
            'include_in_calculation' => true,
            'active' => true,
        ];
        $this->editMode = false;
        $this->editingPointId = null;
    }

    public function render()
    {
        $programData = $this->getProgramData();
        $availablePoints = EventTemplateProgramPoint::orderBy('name')->get();

        return view('livewire.event-program-editor', [
            'programData' => $programData,
            'availablePoints' => $availablePoints,
        ]);
    }

    public function getProgramData()
    {
        $programPoints = $this->event->programPoints()
            ->with('templatePoint')
            ->orderBy('day')
            ->orderBy('order')
            ->get();

        // Grupuj według dni
        return $programPoints->groupBy('day')->map(function ($points, $day) {
            return [
                'day' => $day,
                'points' => $points->map(function ($point) {
                    return [
                        'id' => $point->id,
                        'name' => $point->templatePoint->name ?? 'Brak nazwy',
                        'description' => $point->templatePoint->description ?? '',
                        'order' => $point->order,
                        'unit_price' => $point->unit_price,
                        'quantity' => $point->quantity,
                        'total_price' => $point->total_price,
                        'notes' => $point->notes,
                        'include_in_program' => $point->include_in_program,
                        'include_in_calculation' => $point->include_in_calculation,
                        'active' => $point->active,
                    ];
                }),
            ];
        });
    }

    public function openAddModal($day = 1)
    {
        $this->resetModalData();
        $this->modalData['day'] = $day;

        // Ustaw kolejność na końcu dnia
        $maxOrder = $this->event->programPoints()
            ->where('day', $day)
            ->max('order');
        $this->modalData['order'] = ($maxOrder ?? 0) + 1;

        $this->showModal = true;
    }

    public function openEditModal($pointId)
    {
        $point = EventProgramPoint::findOrFail($pointId);

        $this->modalData = [
            'event_template_program_point_id' => $point->event_template_program_point_id,
            'day' => $point->day,
            'order' => $point->order,
            'unit_price' => $point->unit_price,
            'quantity' => $point->quantity,
            'notes' => $point->notes,
            'include_in_program' => $point->include_in_program,
            'include_in_calculation' => $point->include_in_calculation,
            'active' => $point->active,
        ];

        $this->editMode = true;
        $this->editingPointId = $pointId;
        $this->showModal = true;
    }

    public function savePoint()
    {
        $this->validate([
            'modalData.event_template_program_point_id' => 'required|exists:event_template_program_points,id',
            'modalData.day' => 'required|integer|min:1',
            'modalData.order' => 'required|integer|min:1',
            'modalData.unit_price' => 'required|numeric|min:0',
            'modalData.quantity' => 'required|integer|min:1',
        ]);

        try {
            if ($this->editMode && $this->editingPointId) {
                // Edycja istniejącego punktu
                $point = EventProgramPoint::findOrFail($this->editingPointId);
                $point->update([
                    'event_template_program_point_id' => $this->modalData['event_template_program_point_id'],
                    'day' => $this->modalData['day'],
                    'order' => $this->modalData['order'],
                    'unit_price' => $this->modalData['unit_price'],
                    'quantity' => $this->modalData['quantity'],
                    'notes' => $this->modalData['notes'] ?? '',
                    'include_in_program' => $this->modalData['include_in_program'] ?? false,
                    'include_in_calculation' => $this->modalData['include_in_calculation'] ?? false,
                    'active' => $this->modalData['active'] ?? false,
                ]);
            } else {
                // Dodawanie nowego punktu
                EventProgramPoint::create([
                    'event_id' => $this->event->id,
                    'event_template_program_point_id' => $this->modalData['event_template_program_point_id'],
                    'day' => $this->modalData['day'],
                    'order' => $this->modalData['order'],
                    'unit_price' => $this->modalData['unit_price'],
                    'quantity' => $this->modalData['quantity'],
                    'notes' => $this->modalData['notes'] ?? '',
                    'include_in_program' => $this->modalData['include_in_program'] ?? false,
                    'include_in_calculation' => $this->modalData['include_in_calculation'] ?? false,
                    'active' => $this->modalData['active'] ?? false,
                ]);
            }

            $this->closeModal();
            session()->flash('message', 'Punkt programu został zapisany');
        } catch (\Exception $e) {
            session()->flash('error', 'Błąd podczas zapisywania: ' . $e->getMessage());
            Log::error('Błąd podczas zapisywania punktu programu: ' . $e->getMessage());
        }
    }

    public function deletePoint($pointId)
    {
        try {
            $point = EventProgramPoint::findOrFail($pointId);
            $point->delete();
            session()->flash('message', 'Punkt programu został usunięty');
        } catch (\Exception $e) {
            session()->flash('error', 'Błąd podczas usuwania: ' . $e->getMessage());
            Log::error('Błąd podczas usuwania punktu programu: ' . $e->getMessage());
        }
    }

    public function duplicatePoint($pointId)
    {
        try {
            $point = EventProgramPoint::findOrFail($pointId);
            $duplicate = $point->duplicate();
            session()->flash('message', 'Punkt programu został zduplikowany');
        } catch (\Exception $e) {
            session()->flash('error', 'Błąd podczas duplikowania: ' . $e->getMessage());
            Log::error('Błąd podczas duplikowania punktu programu: ' . $e->getMessage());
        }
    }

    public function moveToDay($pointId, $newDay)
    {
        try {
            $point = EventProgramPoint::findOrFail($pointId);
            $point->moveToDay($newDay);
            session()->flash('message', 'Punkt programu został przeniesiony');
        } catch (\Exception $e) {
            session()->flash('error', 'Błąd podczas przenoszenia: ' . $e->getMessage());
            Log::error('Błąd podczas przenoszenia punktu programu: ' . $e->getMessage());
        }
    }

    public function updateOrder($orderedItems)
    {
        try {
            DB::transaction(function () use ($orderedItems) {
                foreach ($orderedItems as $item) {
                    EventProgramPoint::where('id', $item['value'])
                        ->update([
                            'day' => $item['group'],
                            'order' => $item['order']
                        ]);
                }
            });
            session()->flash('message', 'Kolejność została zaktualizowana');
        } catch (\Exception $e) {
            session()->flash('error', 'Błąd podczas aktualizacji kolejności: ' . $e->getMessage());
            Log::error('Błąd podczas aktualizacji kolejności: ' . $e->getMessage());
        }
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->resetModalData();
    }

    public function toggleExpanded($itemId)
    {
        if (in_array($itemId, $this->expandedItems)) {
            $this->expandedItems = array_filter($this->expandedItems, fn($id) => $id !== $itemId);
        } else {
            $this->expandedItems[] = $itemId;
        }
    }
}
