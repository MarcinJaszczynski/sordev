<?php

namespace App\Livewire;

use App\Models\EventTemplate;
use App\Models\EventTemplateProgramPoint;
use App\Support\Tasks\TaskNavigation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Livewire\Component;

class EventProgramTreeEditor extends Component
{
    public EventTemplate $eventTemplate;

    public $programByDays = [];

    public $showModal = false;

    public $editPoint = null;

    public $modalData = [
        'id' => null,
        'program_point_id' => '',
        'day' => 1,
        'notes' => '',
        'start_time' => null,
        'end_time' => null,
        'include_in_program' => true,
        'include_in_calculation' => true,
        'active' => true,
    ];

    public $searchProgramPoint = '';

    public bool $searchDropdownOpen = false;

    public ?string $selectedProgramPointName = null;

    public ?string $selectedProgramPointDescription = null;

    // Prevent re-rendering after drag & drop updates
    public $skipRender = false;

    protected function rules()
    {
        return [
            'modalData.program_point_id' => 'required|exists:event_template_program_points,id',
            'modalData.day' => 'required|integer|min:1|max:'.($this->eventTemplate->duration_days + 1),
            'modalData.notes' => 'nullable|string',
            'modalData.start_time' => 'nullable|date_format:H:i',
            'modalData.end_time' => 'nullable|date_format:H:i',
            'modalData.include_in_program' => 'boolean',
            'modalData.include_in_calculation' => 'boolean',
            'modalData.active' => 'boolean',
        ];
    }

    protected $messages = [
        'modalData.program_point_id.required' => 'Pole punkt programu jest wymagane.',
        'modalData.program_point_id.exists' => 'Wybrany punkt programu jest nieprawidłowy.',
        'modalData.day.required' => 'Pole dzień jest wymagane.',
        'modalData.day.integer' => 'Dzień musi być liczbą.',
        'modalData.day.min' => 'Dzień nie może być mniejszy niż 1.',
        'modalData.day.max' => 'Dzień nie może być większy niż liczba dni szablonu plus fakultatywne.',
    ];

    public function mount(EventTemplate $eventTemplate)
    {
        $this->eventTemplate = $eventTemplate;
        $this->loadProgramByDays();
    }

    public function loadProgramByDays()
    {
        $programPoints = $this->eventTemplate->programPoints()
            ->with(['currency', 'tags', 'children.currency', 'children.tags'])
            ->orderBy('day', 'asc')
            ->orderBy('order', 'asc')
            ->get();

        $grouped = $programPoints->groupBy('pivot.day');
        $days = [];

        // Dodaj dni standardowe
        for ($i = 1; $i <= $this->eventTemplate->duration_days; $i++) {
            $points = $grouped[$i] ?? collect();
            $days[] = [
                'day' => $i,
                'points' => $points->map(function ($point) {
                    $children = $point->children->map(function ($child) {
                        $props = $this->getChildProperties($child->id);

                        return [
                            'id' => $child->id,
                            'name' => $child->name,
                            'description' => $child->description,
                            'office_notes' => $child->office_notes,
                            'duration_hours' => $child->duration_hours,
                            'duration_minutes' => $child->duration_minutes,
                            'featured_image' => $child->featured_image,
                            'gallery_images' => $child->gallery_images,
                            'unit_price' => $child->unit_price,
                            'group_size' => $child->group_size,
                            'currency' => $child->currency ? $child->currency->toArray() : null,
                            'tags' => $child->tags ? $child->tags->toArray() : [],
                            'include_in_program' => $props['include_in_program'],
                            'include_in_calculation' => $props['include_in_calculation'],
                            'active' => $props['active'],
                            'show_title_style' => $props['show_title_style'] ?? true,
                            'show_description' => $props['show_description'] ?? true,
                        ];
                    })->toArray();

                    return [
                        'pivot_id' => $point->pivot->id,
                        'id' => $point->id,
                        'name' => $point->name,
                        'description' => $point->description,
                        'office_notes' => $point->office_notes,
                        'duration_hours' => $point->duration_hours,
                        'duration_minutes' => $point->duration_minutes,
                        'featured_image' => $point->featured_image,
                        'gallery_images' => $point->gallery_images,
                        'unit_price' => $point->unit_price,
                        'group_size' => $point->group_size,
                        'currency' => $point->currency ? $point->currency->toArray() : null,
                        'tags' => $point->tags ? $point->tags->toArray() : [],
                        'day' => $point->pivot->day,
                        'order' => $point->pivot->order,
                        'pivot_notes' => $point->pivot->notes,
                        'start_time' => $point->pivot->start_time,
                        'end_time' => $point->pivot->end_time,
                        'program_point_id' => $point->id,
                        'include_in_program' => $point->pivot->include_in_program,
                        'include_in_calculation' => $point->pivot->include_in_calculation,
                        'active' => $point->pivot->active,
                        'show_title_style' => $point->pivot->show_title_style ?? true,
                        'show_description' => $point->pivot->show_description ?? true,
                        'children' => $children,
                    ];
                })->sortBy('order')->values()->toArray(),
            ];
        }

        // Dodaj dzień fakultatywny (duration_days + 1)
        $facultativeDay = $this->eventTemplate->duration_days + 1;
        $facultativePoints = $grouped[$facultativeDay] ?? collect();
        $days[] = [
            'day' => $facultativeDay,
            'points' => $facultativePoints->map(function ($point) {
                $children = $point->children->map(function ($child) {
                    $props = $this->getChildProperties($child->id);

                    return [
                        'id' => $child->id,
                        'name' => $child->name,
                        'description' => $child->description,
                        'office_notes' => $child->office_notes,
                        'duration_hours' => $child->duration_hours,
                        'duration_minutes' => $child->duration_minutes,
                        'featured_image' => $child->featured_image,
                        'gallery_images' => $child->gallery_images,
                        'unit_price' => $child->unit_price,
                        'group_size' => $child->group_size,
                        'currency' => $child->currency ? $child->currency->toArray() : null,
                        'tags' => $child->tags ? $child->tags->toArray() : [],
                        'include_in_program' => $props['include_in_program'],
                        'include_in_calculation' => $props['include_in_calculation'],
                        'active' => $props['active'],
                        'show_title_style' => $props['show_title_style'] ?? true,
                        'show_description' => $props['show_description'] ?? true,
                    ];
                })->toArray();

                return [
                    'pivot_id' => $point->pivot->id,
                    'id' => $point->id,
                    'name' => $point->name,
                    'description' => $point->description,
                    'office_notes' => $point->office_notes,
                    'duration_hours' => $point->duration_hours,
                    'duration_minutes' => $point->duration_minutes,
                    'featured_image' => $point->featured_image,
                    'gallery_images' => $point->gallery_images,
                    'unit_price' => $point->unit_price,
                    'group_size' => $point->group_size,
                    'currency' => $point->currency ? $point->currency->toArray() : null,
                    'tags' => $point->tags ? $point->tags->toArray() : [],
                    'day' => $point->pivot->day,
                    'order' => $point->pivot->order,
                    'pivot_notes' => $point->pivot->notes,
                    'start_time' => $point->pivot->start_time,
                    'end_time' => $point->pivot->end_time,
                    'program_point_id' => $point->id,
                    'include_in_program' => $point->pivot->include_in_program,
                    'include_in_calculation' => $point->pivot->include_in_calculation,
                    'active' => $point->pivot->active,
                    'show_title_style' => $point->pivot->show_title_style ?? true,
                    'show_description' => $point->pivot->show_description ?? true,
                    'children' => $children,
                ];
            })->sortBy('order')->values()->toArray(),
        ];

        $this->programByDays = $days;
    }

    public function render()
    {
        $searchTerm = trim((string) $this->searchProgramPoint);
        $minSearchLength = 2;

        $availablePoints = EventTemplateProgramPoint::with('tags')
            ->when($searchTerm !== '' && strlen($searchTerm) >= $minSearchLength, function ($query) use ($searchTerm) {
                $fragments = collect(preg_split('/[,\s]+/', $searchTerm))
                    ->map(fn ($f) => trim($f))
                    ->filter(fn ($f) => strlen($f) >= 2);

                // Pojedynczy fragment / fraza: szukaj w OR po polach.
                // Wiele fragmentów (przecinki/spacje): każdy musi pasować (AND) — np. "kraków warsztat".
                if ($fragments->count() <= 1) {
                    $frag = $fragments->first() ?: $searchTerm;
                    $query->where(function ($q) use ($frag) {
                        $q->whereRaw('UPPER(name) LIKE UPPER(?)', ["%{$frag}%"])
                            ->orWhereRaw('UPPER(description) LIKE UPPER(?)', ["%{$frag}%"])
                            ->orWhereRaw('UPPER(office_notes) LIKE UPPER(?)', ["%{$frag}%"])
                            ->orWhereHas('tags', function ($tagQuery) use ($frag) {
                                $tagQuery->whereRaw('UPPER(name) LIKE UPPER(?)', ["%{$frag}%"]);
                            });
                    });
                } else {
                    foreach ($fragments as $frag) {
                        $query->where(function ($q) use ($frag) {
                            $q->whereRaw('UPPER(name) LIKE UPPER(?)', ["%{$frag}%"])
                                ->orWhereRaw('UPPER(description) LIKE UPPER(?)', ["%{$frag}%"])
                                ->orWhereRaw('UPPER(office_notes) LIKE UPPER(?)', ["%{$frag}%"])
                                ->orWhereHas('tags', function ($tagQuery) use ($frag) {
                                    $tagQuery->whereRaw('UPPER(name) LIKE UPPER(?)', ["%{$frag}%"]);
                                });
                        });
                    }
                }
            }, function ($query) use ($searchTerm, $minSearchLength) {
                // Za krótka fraza: nie filtruj (browse pierwszych 50), chyba że użytkownik coś wpisał
                if ($searchTerm !== '' && strlen($searchTerm) < $minSearchLength) {
                    $query->whereRaw('1 = 0');
                }
            })
            ->orderBy('name')
            ->limit(50)
            ->get();

        if (is_null($availablePoints)) {
            Log::warning('EventProgramTreeEditor: EventTemplateProgramPoint::orderBy(\'name\')->get() zwróciło null. Ustawiono domyślnie pustą kolekcję.');
            $availablePoints = collect();
        }

        return view('livewire.event-program-tree-editor', [
            'programByDays' => $this->programByDays,
            'eventTemplate' => $this->eventTemplate,
            'availableProgramPoints' => $availablePoints,
            'duration_days' => $this->eventTemplate->duration_days + 1, // +1 dla punktów fakultatywnych
        ]);
    }

    public function selectProgramPoint($id)
    {
        $point = EventTemplateProgramPoint::query()->find($id);
        $this->modalData['program_point_id'] = $id;
        $this->selectedProgramPointName = $point?->name;
        $this->selectedProgramPointDescription = $point?->description
            ? Str::limit(strip_tags((string) $point->description), 200)
            : null;
        $this->searchProgramPoint = '';
        $this->searchDropdownOpen = false;
        $this->dispatch('point-selected', ['id' => $id]);
    }

    public function updateSearch($searchTerm)
    {
        $this->searchProgramPoint = $searchTerm;
        $trimmed = trim((string) $searchTerm);
        // Pusty input = browse; 2+ znaki = filtr
        $this->searchDropdownOpen = $trimmed === '' || strlen($trimmed) >= 2;
    }

    public function showAddModal()
    {
        $this->resetModalData();
        $this->editPoint = null;
        $this->modalData['day'] = 1;
        $this->showModal = true;
    }

    public function showEditModal($pivotId)
    {
        $pointPivot = DB::table('event_template_event_template_program_point')->where('id', $pivotId)->first();
        if ($pointPivot) {
            $this->editPoint = $pivotId;
            $this->modalData = [
                'id' => $pivotId,
                'program_point_id' => $pointPivot->event_template_program_point_id,
                'day' => $pointPivot->day,
                'notes' => $pointPivot->notes,
                'start_time' => $pointPivot->start_time ? substr((string) $pointPivot->start_time, 0, 5) : null,
                'end_time' => $pointPivot->end_time ? substr((string) $pointPivot->end_time, 0, 5) : null,
                'include_in_program' => (bool) $pointPivot->include_in_program,
                'include_in_calculation' => (bool) $pointPivot->include_in_calculation,
                'active' => (bool) $pointPivot->active,
            ];
            $point = EventTemplateProgramPoint::query()->find($pointPivot->event_template_program_point_id);
            $this->selectedProgramPointName = $point?->name;
            $this->selectedProgramPointDescription = $point?->description
                ? Str::limit(strip_tags((string) $point->description), 200)
                : null;
            $this->searchProgramPoint = '';
            $this->showModal = true;
        } else {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Nie znaleziono punktu programu.']);
        }
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->resetModalData();
    }

    private function resetModalData()
    {
        $this->modalData = [
            'id' => null,
            'program_point_id' => '',
            'day' => 1,
            'notes' => '',
            'start_time' => null,
            'end_time' => null,
            'include_in_program' => true,
            'include_in_calculation' => true,
            'active' => true,
        ];
        $this->searchProgramPoint = '';
        $this->selectedProgramPointName = null;
        $this->selectedProgramPointDescription = null;
        $this->searchDropdownOpen = false;
        $this->resetErrorBag();
    }

    public function savePoint()
    {
        $this->validate();

        $startTime = $this->normalizeTime($this->modalData['start_time'] ?? null);
        $endTime = $this->normalizeTime($this->modalData['end_time'] ?? null);

        if (($startTime && ! $endTime) || (! $startTime && $endTime)) {
            $this->addError('modalData.end_time', 'Podaj obie godziny: start i koniec.');

            return;
        }

        if ($startTime && $endTime && strtotime($endTime) <= strtotime($startTime)) {
            $this->addError('modalData.end_time', 'Godzina końca musi być późniejsza niż godzina startu.');

            return;
        }

        try {
            DB::beginTransaction();

            if ($this->editPoint) {
                $pointPivot = DB::table('event_template_event_template_program_point')->where('id', $this->editPoint);
                if (! $pointPivot->exists()) {
                    throw new \Exception('Nie znaleziono punktu programu do edycji.');
                }
                $pointPivot->update([
                    'event_template_program_point_id' => $this->modalData['program_point_id'],
                    'notes' => $this->modalData['notes'],
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'include_in_program' => $this->modalData['include_in_program'],
                    'include_in_calculation' => $this->modalData['include_in_calculation'],
                    'active' => $this->modalData['active'],
                    'updated_at' => now(),
                ]);
            } else {
                $maxOrder = DB::table('event_template_event_template_program_point')
                    ->where('event_template_id', $this->eventTemplate->id)
                    ->where('day', $this->modalData['day'])
                    ->max('order');
                DB::table('event_template_event_template_program_point')->insert([
                    'event_template_id' => $this->eventTemplate->id,
                    'event_template_program_point_id' => $this->modalData['program_point_id'],
                    'day' => $this->modalData['day'],
                    'order' => $maxOrder !== null ? $maxOrder + 1 : 0,
                    'notes' => $this->modalData['notes'],
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'include_in_program' => $this->modalData['include_in_program'],
                    'include_in_calculation' => $this->modalData['include_in_calculation'],
                    'active' => $this->modalData['active'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::commit();
            $this->loadProgramByDays();
            $this->closeModal();
            $this->dispatch('notify', ['type' => 'success', 'message' => 'Punkt programu zapisany pomyślnie!']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Błąd zapisu punktu programu: '.$e->getMessage());
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Wystąpił błąd podczas zapisu: '.$e->getMessage()]);
        }
    }

    public function deletePoint($pivotId)
    {
        $pivotId = (int) $pivotId;
        if ($pivotId <= 0) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Nieprawidłowy identyfikator punktu programu.']);

            return;
        }

        try {
            DB::transaction(function () use ($pivotId): void {
                $pointPivot = DB::table('event_template_event_template_program_point')
                    ->where('id', $pivotId)
                    ->first();

                if (! $pointPivot) {
                    throw new \RuntimeException('Nie znaleziono punktu programu do usunięcia.');
                }

                // Usuwamy wiersz pivota — FK wskazują na istniejące szablon/punkt, nie na ten wiersz.
                DB::table('event_template_event_template_program_point')
                    ->where('id', $pivotId)
                    ->delete();

                DB::table('event_template_event_template_program_point')
                    ->where('event_template_id', $pointPivot->event_template_id)
                    ->where('day', $pointPivot->day)
                    ->where('order', '>', $pointPivot->order)
                    ->decrement('order');
            });

            $this->loadProgramByDays();
            $this->dispatch('notify', ['type' => 'success', 'message' => 'Punkt programu usunięty.']);
        } catch (\RuntimeException $e) {
            $this->dispatch('notify', ['type' => 'error', 'message' => $e->getMessage()]);
        } catch (\Exception $e) {
            Log::error('Błąd usuwania punktu programu: '.$e->getMessage(), [
                'pivot_id' => $pivotId,
                'trace' => $e->getTraceAsString(),
            ]);
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Wystąpił błąd podczas usuwania punktu programu: '.$e->getMessage(),
            ]);
        }
    }

    public function updateProgramOrder($list)
    {
        try {
            DB::beginTransaction();
            foreach ($list as $dayNumber => $pointPivotIds) {
                foreach ($pointPivotIds as $index => $pointPivotId) {
                    DB::table('event_template_event_template_program_point')
                        ->where('id', $pointPivotId)
                        ->update([
                            'order' => $index,
                            'day' => $dayNumber,
                            'updated_at' => now(),
                        ]);
                }
            }
            DB::commit();

            // Don't reload data immediately after drag & drop to prevent conflicts
            $this->skipRender = true;

            $this->dispatch('notify', ['type' => 'success', 'message' => 'Kolejność zaktualizowana.']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Błąd aktualizacji kolejności: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Błąd aktualizacji kolejności: '.$e->getMessage()]);
        }
    }

    public function duplicatePoint($pivotId)
    {
        try {
            DB::beginTransaction();
            $pointPivot = DB::table('event_template_event_template_program_point')->where('id', $pivotId)->first();
            if ($pointPivot) {
                $maxOrder = DB::table('event_template_event_template_program_point')
                    ->where('event_template_id', $pointPivot->event_template_id)
                    ->where('day', $pointPivot->day)
                    ->max('order');

                DB::table('event_template_event_template_program_point')->insert([
                    'event_template_id' => $pointPivot->event_template_id,
                    'event_template_program_point_id' => $pointPivot->event_template_program_point_id,
                    'day' => $pointPivot->day,
                    'order' => ($maxOrder ?? 0) + 1,
                    'notes' => $pointPivot->notes,
                    'start_time' => $pointPivot->start_time,
                    'end_time' => $pointPivot->end_time,
                    'include_in_program' => $pointPivot->include_in_program,
                    'include_in_calculation' => $pointPivot->include_in_calculation,
                    'active' => $pointPivot->active,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::commit();
                $this->loadProgramByDays();
                $this->dispatch('notify', ['type' => 'success', 'message' => 'Punkt programu zduplikowany.']);
            } else {
                $this->dispatch('notify', ['type' => 'error', 'message' => 'Nie znaleziono punktu programu do duplikowania.']);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Błąd duplikowania punktu programu: '.$e->getMessage());
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Wystąpił błąd podczas duplikowania punktu programu.']);
        }
    }

    public function togglePivotProperty($pivotId, $property)
    {
        $allowed = ['include_in_program', 'include_in_calculation', 'active', 'show_title_style', 'show_description'];
        if (! in_array($property, $allowed)) {
            return;
        }

        $pivot = DB::table('event_template_event_template_program_point')->where('id', $pivotId)->first();
        if ($pivot) {
            $newValue = ! $pivot->$property;
            DB::table('event_template_event_template_program_point')
                ->where('id', $pivotId)
                ->update([$property => $newValue]);

            $this->loadProgramByDays();
            $this->dispatch('program-updated', 'Właściwość została zaktualizowana');
        }
    }

    public function toggleChildPivotProperty($childId, $property)
    {
        $allowed = ['include_in_program', 'include_in_calculation', 'active', 'show_title_style', 'show_description'];
        if (! in_array($property, $allowed)) {
            return;
        }

        $pivot = DB::table('event_template_program_point_child_pivot')
            ->where('event_template_id', $this->eventTemplate->id)
            ->where('program_point_child_id', $childId)
            ->first();

        if ($pivot) {
            $newValue = ! $pivot->$property;
            DB::table('event_template_program_point_child_pivot')
                ->where('id', $pivot->id)
                ->update([$property => $newValue]);
        } else {
            DB::table('event_template_program_point_child_pivot')->insert([
                'event_template_id' => $this->eventTemplate->id,
                'program_point_child_id' => $childId,
                'include_in_program' => $property === 'include_in_program',
                'include_in_calculation' => $property === 'include_in_calculation',
                'active' => $property === 'active',
                'show_title_style' => $property === 'show_title_style',
                'show_description' => $property === 'show_description',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        $this->loadProgramByDays();
        $this->dispatch('program-updated', 'Właściwość podpunktu została zaktualizowana');
    }

    public function getChildProperties($childId)
    {
        $pivot = DB::table('event_template_program_point_child_pivot')
            ->where('event_template_id', $this->eventTemplate->id)
            ->where('program_point_child_id', $childId)
            ->first();

        return [
            'include_in_program' => $pivot ? (bool) $pivot->include_in_program : true,
            'include_in_calculation' => $pivot ? (bool) $pivot->include_in_calculation : true,
            'active' => $pivot ? (bool) $pivot->active : true,
            'show_title_style' => $pivot ? (bool) $pivot->show_title_style : true,
            'show_description' => $pivot ? (bool) $pivot->show_description : true,
        ];
    }

    /**
     * Zaznacz/odznacz ubezpieczenie dla danego dnia
     */
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
        $this->eventTemplate->load('dayInsurances');
        $this->loadProgramByDays();
    }

    public function getTaskCreateUrlForPoint(int $programPointId): string
    {
        return $this->buildTaskCreateUrl($programPointId);
    }

    public function getTaskCreateUrlForChild(int $childProgramPointId): string
    {
        return $this->buildTaskCreateUrl($childProgramPointId);
    }

    private function buildTaskCreateUrl(int $programPointId): string
    {
        if ($programPointId <= 0) {
            return '#';
        }

        return TaskNavigation::createUrl(
            EventTemplateProgramPoint::class,
            $programPointId,
        );
    }

    private function normalizeTime($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
