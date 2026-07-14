<?php

namespace App\Livewire;

use App\Filament\Resources\EventResource;
use App\Models\Event;
use App\Models\EventProgramPoint;
use App\Models\EventTemplateProgramPoint;
use App\Services\EventProgramPointCreator;
use App\Services\EventProgramPointOrderService;
use App\Support\ProgramTimeSlots;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class EventProgramDayTree extends Component
{
    public int $eventId;

    public int $activeDay = 1;

    /** @var array<int, int> */
    public array $selectedPointIds = [];

    public bool $showEditModal = false;

    public ?int $editingPointId = null;

    public bool $showAddModal = false;

    public string $addModalMode = 'block';

    public ?int $addParentId = null;

    public string $templateSearch = '';

    public ?int $selectedTemplatePointId = null;

    /** @var array<string, mixed> */
    public array $editForm = [];

    /** @var array<string, mixed> */
    public array $addForm = [];

    protected $listeners = ['event-program-points-refresh' => '$refresh'];

    public function mount(int $eventId): void
    {
        $this->eventId = $eventId;
        $this->activeDay = $this->resolveInitialDay();
    }

    public function setActiveDay(int $day): void
    {
        $event = Event::query()->findOrFail($this->eventId);
        $maxDay = max(1, (int) ($event->duration_days ?? 1));
        $this->activeDay = max(1, min($day, $maxDay));
        $this->selectedPointIds = [];
    }

    public function toggleSelectPoint(int $pointId): void
    {
        if (in_array($pointId, $this->selectedPointIds, true)) {
            $this->selectedPointIds = array_values(array_filter(
                $this->selectedPointIds,
                fn (int $id): bool => $id !== $pointId,
            ));
        } else {
            $this->selectedPointIds[] = $pointId;
        }
    }

    public function toggleSelectAllDay(): void
    {
        $ids = $this->pointIdsForDay($this->activeDay);

        if ($ids !== [] && count(array_intersect($this->selectedPointIds, $ids)) === count($ids)) {
            $this->selectedPointIds = array_values(array_diff($this->selectedPointIds, $ids));
        } else {
            $this->selectedPointIds = array_values(array_unique([...$this->selectedPointIds, ...$ids]));
        }
    }

    public function clearSelection(): void
    {
        $this->selectedPointIds = [];
    }

    public function bulkSetProperty(string $property, bool $value): void
    {
        $allowed = ['include_in_program', 'include_in_calculation', 'active'];

        if (! in_array($property, $allowed, true) || $this->selectedPointIds === []) {
            return;
        }

        EventProgramPoint::query()
            ->where('event_id', $this->eventId)
            ->whereIn('id', $this->selectedPointIds)
            ->update([$property => $value]);

        $this->dispatch('event-program-points-refresh');

        $labels = [
            'include_in_program' => 'program',
            'include_in_calculation' => 'kalkulacja',
            'active' => 'aktywność',
        ];

        $this->dispatch(
            'notify',
            type: 'success',
            message: 'Zaktualizowano '.($labels[$property] ?? $property).' dla '.count($this->selectedPointIds).' punktów',
        );
    }

    public function reorderDayBlocks(int $day, array $orderedParentIds): void
    {
        $event = Event::query()->findOrFail($this->eventId);
        app(EventProgramPointOrderService::class)->reorderDayBlockOrder($event, $day, $orderedParentIds);

        $this->dispatch('event-program-points-refresh');
        $this->dispatch('notify', type: 'success', message: 'Zapisano kolejność bloków w dniu '.$day);
    }

    public function reorderSetChildren(int $parentId, array $orderedChildIds): void
    {
        $event = Event::query()->findOrFail($this->eventId);
        app(EventProgramPointOrderService::class)->reorderSetChildren($event, $parentId, $orderedChildIds);

        $this->dispatch('event-program-points-refresh');
        $this->dispatch('notify', type: 'success', message: 'Zapisano kolejność podpunktów');
    }

    public function openAddBlock(int $day): void
    {
        $this->resetAddForm();
        $this->addModalMode = 'block';
        $this->addParentId = null;
        $this->addForm['day'] = $day;
        $this->showAddModal = true;
    }

    public function openAddChild(int $parentId): void
    {
        $parent = $this->findEventPoint($parentId);

        $this->resetAddForm();
        $this->addModalMode = 'child';
        $this->addParentId = $parent->id;
        $this->addForm['day'] = (int) ($parent->day ?? 1);
        $this->showAddModal = true;
    }

    public function selectTemplatePoint(int $templatePointId): void
    {
        $this->selectedTemplatePointId = $templatePointId;
        $template = EventTemplateProgramPoint::query()->find($templatePointId);

        if ($template) {
            $this->addForm['name'] = $template->name;
        }
    }

    public function closeAdd(): void
    {
        $this->showAddModal = false;
        $this->resetAddForm();
    }

    public function saveAdd(): void
    {
        $event = Event::query()->findOrFail($this->eventId);
        $maxDay = max(1, (int) ($event->duration_days ?? 1));
        $creator = app(EventProgramPointCreator::class);

        $this->validate([
            'addForm.day' => 'required|integer|min:1|max:'.$maxDay,
            'addForm.name' => 'nullable|string|max:255',
            'addForm.existing_point_id' => 'nullable|integer',
            'addForm.start_time' => 'nullable|date_format:H:i',
            'addForm.end_time' => 'nullable|date_format:H:i',
            'addForm.hide_times' => 'boolean',
            'addForm.include_in_program' => 'boolean',
            'addForm.include_in_calculation' => 'boolean',
            'addForm.active' => 'boolean',
        ]);

        if (($this->addForm['hide_times'] ?? false) !== true
            && filled($this->addForm['start_time'] ?? null)
            && filled($this->addForm['end_time'] ?? null)
            && $this->addForm['end_time'] <= $this->addForm['start_time']) {
            throw ValidationException::withMessages([
                'addForm.end_time' => 'Godzina końca musi być późniejsza niż startu.',
            ]);
        }

        $day = (int) $this->addForm['day'];
        $parentId = $this->addModalMode === 'child' ? $this->addParentId : null;
        $options = $this->addOptionsFromForm();

        if (filled($this->addForm['existing_point_id'] ?? null) && $parentId) {
            $child = $this->findEventPoint((int) $this->addForm['existing_point_id']);
            $parent = $this->findEventPoint($parentId);

            if ($child->parent_id !== null) {
                throw ValidationException::withMessages([
                    'addForm.existing_point_id' => 'Wybrany punkt jest już podpunktem innego setu.',
                ]);
            }

            $creator->attachToParent($child, $parent);
        } elseif ($this->selectedTemplatePointId) {
            $template = EventTemplateProgramPoint::query()->findOrFail($this->selectedTemplatePointId);
            $creator->addFromTemplate(
                $event,
                $template,
                $day,
                $parentId,
                cloneTemplateChildren: $parentId === null,
                options: array_merge($options, [
                    'name' => filled($this->addForm['name'] ?? null) ? $this->addForm['name'] : null,
                ]),
            );
        } elseif (filled($this->addForm['name'] ?? null)) {
            $creator->addBlank($event, (string) $this->addForm['name'], $day, $parentId, $options);
        } else {
            throw ValidationException::withMessages([
                'addForm.name' => 'Wybierz punkt z katalogu lub podaj nazwę nowego punktu.',
            ]);
        }

        $this->closeAdd();
        $this->dispatch('event-program-points-refresh');
        $this->dispatch('notify', type: 'success', message: $this->addModalMode === 'child'
            ? 'Dodano podpunkt do setu'
            : 'Dodano blok programu');
    }

    public function openEdit(int $pointId): void
    {
        $point = $this->findEventPoint($pointId);

        $this->editingPointId = $point->id;
        $this->editForm = [
            'name' => $point->name,
            'day' => (int) ($point->day ?? 1),
            'start_time' => $point->start_time ? substr((string) $point->start_time, 0, 5) : null,
            'end_time' => $point->end_time ? substr((string) $point->end_time, 0, 5) : null,
            'hide_times' => (bool) $point->hide_times,
            'include_in_program' => (bool) $point->include_in_program,
            'include_in_calculation' => (bool) $point->include_in_calculation,
            'active' => (bool) $point->active,
        ];
        $this->showEditModal = true;
    }

    public function closeEdit(): void
    {
        $this->showEditModal = false;
        $this->editingPointId = null;
        $this->editForm = [];
    }

    public function saveEdit(): void
    {
        if (! $this->editingPointId) {
            return;
        }

        $event = Event::query()->findOrFail($this->eventId);
        $maxDay = max(1, (int) ($event->duration_days ?? 1));

        $this->validate([
            'editForm.name' => 'nullable|string|max:255',
            'editForm.day' => 'required|integer|min:1|max:'.$maxDay,
            'editForm.start_time' => 'nullable|date_format:H:i',
            'editForm.end_time' => 'nullable|date_format:H:i',
            'editForm.hide_times' => 'boolean',
            'editForm.include_in_program' => 'boolean',
            'editForm.include_in_calculation' => 'boolean',
            'editForm.active' => 'boolean',
        ]);

        if (($this->editForm['hide_times'] ?? false) !== true
            && filled($this->editForm['start_time'] ?? null)
            && filled($this->editForm['end_time'] ?? null)
            && $this->editForm['end_time'] <= $this->editForm['start_time']) {
            throw ValidationException::withMessages([
                'editForm.end_time' => 'Godzina końca musi być późniejsza niż startu.',
            ]);
        }

        if (($this->editForm['hide_times'] ?? false) === true) {
            $this->editForm['start_time'] = null;
            $this->editForm['end_time'] = null;
        }

        $point = $this->findEventPoint($this->editingPointId);
        $newDay = (int) $this->editForm['day'];
        $oldDay = (int) ($point->day ?? 1);

        $point->update([
            'name' => filled($this->editForm['name'] ?? null) ? $this->editForm['name'] : null,
            'day' => $newDay,
            'start_time' => $this->editForm['start_time'] ?? null,
            'end_time' => $this->editForm['end_time'] ?? null,
            'hide_times' => (bool) ($this->editForm['hide_times'] ?? false),
            'include_in_program' => (bool) ($this->editForm['include_in_program'] ?? true),
            'include_in_calculation' => (bool) ($this->editForm['include_in_calculation'] ?? true),
            'active' => (bool) ($this->editForm['active'] ?? true),
        ]);

        if ($newDay !== $oldDay && $point->parent_id === null) {
            EventProgramPoint::query()
                ->where('event_id', $event->id)
                ->where('parent_id', $point->id)
                ->update(['day' => $newDay]);
        }

        $this->closeEdit();
        $this->dispatch('event-program-points-refresh');
        $this->dispatch('notify', type: 'success', message: 'Zapisano punkt programu');
    }

    public function deletePoint(int $pointId): void
    {
        $point = $this->findEventPoint($pointId);
        $name = $point->name ?? $point->templatePoint?->name ?? ('Punkt #'.$point->id);

        if ($point->parent_id === null) {
            EventProgramPoint::query()
                ->where('event_id', $this->eventId)
                ->where('parent_id', $point->id)
                ->each(fn (EventProgramPoint $child) => $child->delete());
        }

        $point->delete();

        $this->dispatch('event-program-points-refresh');
        $this->dispatch('notify', type: 'success', message: 'Usunięto: '.$name);
    }

    public function detachFromSet(int $pointId): void
    {
        $point = $this->findEventPoint($pointId);

        if ($point->parent_id === null) {
            return;
        }

        $name = $point->name ?? $point->templatePoint?->name ?? ('Punkt #'.$point->id);
        app(EventProgramPointCreator::class)->detachFromParent($point);

        $this->dispatch('event-program-points-refresh');
        $this->dispatch('notify', type: 'success', message: 'Odpięto z setu: '.$name);
    }

    public function togglePointProperty(int $pointId, string $property): void
    {
        $allowed = ['include_in_program', 'include_in_calculation', 'active'];

        if (! in_array($property, $allowed, true)) {
            return;
        }

        $point = $this->findEventPoint($pointId);
        $point->update([$property => ! $point->{$property}]);

        $this->dispatch('event-program-points-refresh');
    }

    public function duplicatePoint(int $pointId): void
    {
        $event = Event::query()->findOrFail($this->eventId);
        $point = $this->findEventPoint($pointId);
        $name = $point->name ?? $point->templatePoint?->name ?? ('Punkt #'.$point->id);

        app(EventProgramPointCreator::class)->duplicate(
            $event,
            $point,
            withChildren: $point->parent_id === null,
        );

        $this->dispatch('event-program-points-refresh');
        $this->dispatch('notify', type: 'success', message: 'Zduplikowano: '.$name);
    }

    public function render()
    {
        $event = Event::query()->findOrFail($this->eventId);
        $service = app(EventProgramPointOrderService::class);
        $points = $service->sortedForDisplay($event, $service->loadPoints($event));
        $creator = app(EventProgramPointCreator::class);
        $days = $this->groupByDays($points, $event);
        $dayTabs = $this->buildDayTabs($event, $days);
        $this->activeDay = max(1, min($this->activeDay, count($dayTabs) ?: 1));
        $activeDayData = $days->firstWhere('day', $this->activeDay) ?? [
            'day' => $this->activeDay,
            'label' => 'Dzień '.$this->activeDay,
            'date' => $event->dateForProgramDay($this->activeDay)?->format('d.m.Y'),
            'blocks' => collect(),
        ];
        $dayPointIds = $this->pointIdsForDay($this->activeDay);
        $allDaySelected = $dayPointIds !== []
            && count(array_intersect($this->selectedPointIds, $dayPointIds)) === count($dayPointIds);

        return view('livewire.event-program-day-tree', [
            'event' => $event,
            'dayTabs' => $dayTabs,
            'activeDay' => $this->activeDay,
            'activeDayData' => $activeDayData,
            'dayPointIds' => $dayPointIds,
            'allDaySelected' => $allDaySelected,
            'editProgramUrl' => EventResource::getUrl('edit-program', ['record' => $event->id]),
            'timeSlots' => ProgramTimeSlots::options(),
            'dayOptions' => $this->dayOptions($event),
            'templateResults' => $this->showAddModal
                ? $creator->searchTemplatePoints($this->templateSearch)
                : collect(),
            'detachablePointsByDay' => $this->detachablePointsByDay($points),
        ]);
    }

    protected function resetAddForm(): void
    {
        $this->addForm = [
            'day' => 1,
            'name' => '',
            'existing_point_id' => null,
            'start_time' => null,
            'end_time' => null,
            'hide_times' => false,
            'include_in_program' => true,
            'include_in_calculation' => true,
            'active' => true,
        ];
        $this->templateSearch = '';
        $this->selectedTemplatePointId = null;
        $this->resetErrorBag();
    }

    /** @return array<string, mixed> */
    protected function addOptionsFromForm(): array
    {
        $options = [
            'include_in_program' => (bool) ($this->addForm['include_in_program'] ?? true),
            'include_in_calculation' => (bool) ($this->addForm['include_in_calculation'] ?? true),
            'active' => (bool) ($this->addForm['active'] ?? true),
            'hide_times' => (bool) ($this->addForm['hide_times'] ?? false),
        ];

        if (($this->addForm['hide_times'] ?? false) !== true) {
            if (filled($this->addForm['start_time'] ?? null)) {
                $options['start_time'] = $this->addForm['start_time'];
            }
            if (filled($this->addForm['end_time'] ?? null)) {
                $options['end_time'] = $this->addForm['end_time'];
            }
        }

        return $options;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, EventProgramPoint>  $points
     * @return array<int, Collection<int, EventProgramPoint>>
     */
    protected function detachablePointsByDay($points): array
    {
        $grouped = [];

        foreach ($points->whereNull('parent_id') as $point) {
            $day = (int) ($point->day ?? 1);
            $grouped[$day] ??= collect();
            $grouped[$day]->push($point);
        }

        return $grouped;
    }

    /** @return array<int, string> */
    protected function dayOptions(Event $event): array
    {
        $maxDay = max(1, (int) ($event->duration_days ?? 1));
        $options = [];

        for ($day = 1; $day <= $maxDay; $day++) {
            $date = $event->dateForProgramDay($day)?->format('d.m.Y');
            $options[$day] = $date ? "Dzień {$day} ({$date})" : "Dzień {$day}";
        }

        return $options;
    }

    protected function findEventPoint(int $pointId): EventProgramPoint
    {
        $point = EventProgramPoint::query()
            ->where('event_id', $this->eventId)
            ->find($pointId);

        if (! $point) {
            throw ValidationException::withMessages([
                'point' => 'Nie znaleziono punktu programu.',
            ]);
        }

        return $point;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, EventProgramPoint>  $points
     * @return Collection<int, array{day: int, label: string, date: ?string, blocks: Collection}>
     */
    protected function groupByDays($points, Event $event): Collection
    {
        $parents = $points->whereNull('parent_id');
        $childrenByParent = $points->whereNotNull('parent_id')->groupBy('parent_id');

        return $parents
            ->groupBy(fn (EventProgramPoint $point) => (int) ($point->day ?? 1))
            ->sortKeys()
            ->map(function (Collection $dayParents, int|string $day) use ($childrenByParent, $event): array {
                $day = (int) $day;

                $blocks = $dayParents->map(function (EventProgramPoint $parent) use ($childrenByParent): array {
                    $children = $childrenByParent->get($parent->id, collect())->sortBy('order')->values();

                    return [
                        'parent' => $parent,
                        'children' => $children,
                        'is_set' => $children->isNotEmpty(),
                    ];
                })->values();

                return [
                    'day' => $day,
                    'label' => 'Dzień '.$day,
                    'date' => $event->dateForProgramDay($day)?->format('d.m.Y'),
                    'blocks' => $blocks,
                ];
            })
            ->values();
    }

    protected function resolveInitialDay(): int
    {
        $event = Event::query()->find($this->eventId);

        if (! $event) {
            return 1;
        }

        $service = app(EventProgramPointOrderService::class);
        $points = $service->loadPoints($event);
        $firstWithPoints = (int) ($points->whereNull('parent_id')->min('day') ?? 1);

        return max(1, min($firstWithPoints, (int) ($event->duration_days ?? 1)));
    }

    /**
     * @param  Collection<int, array{day: int, label: string, date: ?string, blocks: Collection}>  $days
     * @return array<int, array{day: int, date: ?string, count: int}>
     */
    protected function buildDayTabs(Event $event, Collection $days): array
    {
        $maxDay = max(1, (int) ($event->duration_days ?? 1));
        $byDay = $days->keyBy('day');
        $tabs = [];

        for ($day = 1; $day <= $maxDay; $day++) {
            $group = $byDay->get($day);
            $tabs[] = [
                'day' => $day,
                'date' => $event->dateForProgramDay($day)?->format('d.m.Y'),
                'count' => $group ? $group['blocks']->count() : 0,
            ];
        }

        return $tabs;
    }

    /** @return array<int, int> */
    protected function pointIdsForDay(int $day): array
    {
        $event = Event::query()->findOrFail($this->eventId);
        $service = app(EventProgramPointOrderService::class);
        $points = $service->sortedForDisplay($event, $service->loadPoints($event));
        $childrenByParent = $points->whereNotNull('parent_id')->groupBy('parent_id');

        $ids = [];

        foreach ($points->whereNull('parent_id')->where('day', $day) as $parent) {
            $ids[] = (int) $parent->id;

            foreach ($childrenByParent->get($parent->id, collect()) as $child) {
                $ids[] = (int) $child->id;
            }
        }

        return $ids;
    }
}
