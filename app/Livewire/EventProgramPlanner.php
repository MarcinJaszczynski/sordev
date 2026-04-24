<?php

namespace App\Livewire;

use App\Models\Event;
use App\Models\EventProgramPoint;
use App\Models\EventTemplateProgramPoint;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class EventProgramPlanner extends Component
{
    public int $eventId;

    public Event $event;

    public ?int $selectedBacklogPointId = null;

    public bool $showEditModal = false;

    public ?int $editingPointId = null;

    public array $editingData = [
        'name' => '',
        'is_custom' => false,
        'custom_name' => '',
        'description' => '',
        'parent_id' => null,
        'day' => 1,
        'start_time' => '',
        'end_time' => '',
        'office_notes' => '',
        'pilot_notes' => '',
    ];

    public bool $showAddModal = false;

    public string $plannerSearch = '';

    public array $templateResults = [];

    public array $newPointData = [
        'name' => '',
        'description' => '',
        'parent_id' => null,
        'day' => 1,
        'start_time' => '08:00',
        'end_time' => '09:00',
        'unit_price' => '',
        'quantity' => 1,
        'group_size' => '',
        'include_in_calculation' => false,
        'template_id' => null,
    ];

    public function mount(int $eventId): void
    {
        $this->eventId = $eventId;
        $this->event = Event::query()
            ->with('eventTemplate')
            ->findOrFail($eventId);

        $this->normalizePlannerOrderOnMount();
        $this->event->refresh();
    }

    public function render()
    {
        return view('livewire.event-program-planner', [
            'plannerData' => [
                'events' => $this->buildCalendarEvents(),
                'initialDate' => $this->resolveBaseDate()->toDateString(),
                'durationDays' => $this->resolveDurationDays(),
                'maxDate' => $this->resolveBaseDate()->copy()->addDays($this->resolveDurationDays())->toDateString(),
            ],
            'backlogPoints' => $this->getBacklogPoints(),
            'programPoints' => $this->getProgramPoints(),
            'parentPointOptions' => $this->getParentPointOptions(),
        ]);
    }

    public function addSelectedPointToProgram(): void
    {
        if (! $this->selectedBacklogPointId) {
            return;
        }

        $this->setPointIncludedInProgram($this->selectedBacklogPointId, true);
        $this->selectedBacklogPointId = null;
    }

    public function removePointFromProgram(int $pointId): void
    {
        $this->setPointIncludedInProgram($pointId, false);
    }

    public function repairOrderNow(): void
    {
        $this->normalizePlannerOrderOnMount();
        $this->event->refresh();

        $this->dispatch("planner-data-updated-{$this->getId()}", events: $this->buildCalendarEvents());
    }

    public function openEditModal(int $pointId): void
    {
        $point = EventProgramPoint::query()
            ->where('event_id', $this->event->id)
            ->with('templatePoint')
            ->find($pointId);

        if (! $point) {
            return;
        }

        $isCustom = $point->event_template_program_point_id === null;
        $effectiveTimes = $this->resolveEffectivePlannerTimes($point);

        $this->editingPointId = $pointId;
        $this->editingData = [
            'name' => $point->templatePoint?->name ?? $point->name ?? '',
            'is_custom' => $isCustom,
            'custom_name' => $point->name ?? '',
            'description' => $point->description ?? '',
            'parent_id' => $point->parent_id,
            'day' => (int) ($point->day ?? 1),
            'start_time' => $effectiveTimes['start_time'],
            'end_time' => $effectiveTimes['end_time'],
            'office_notes' => $point->office_notes ?? '',
            'pilot_notes' => $point->pilot_notes ?? '',
        ];
        $this->showEditModal = true;
    }

    public function saveEditingPoint(): void
    {
        if (! $this->editingPointId) {
            return;
        }

        $point = EventProgramPoint::query()
            ->where('event_id', $this->event->id)
            ->find($this->editingPointId);

        if (! $point) {
            $this->showEditModal = false;

            return;
        }

        $oldDay = (int) ($point->day ?? 1);
        $newDay = max(1, min($this->resolveDurationDays(), (int) ($this->editingData['day'] ?? 1)));

        $updateData = [
            'day' => $newDay,
            'start_time' => $this->editingData['start_time'] ?: null,
            'end_time' => $this->editingData['end_time'] ?: null,
            'description' => $this->editingData['description'] ?: null,
            'parent_id' => ($this->editingData['parent_id'] ?? null) ?: null,
            'office_notes' => $this->editingData['office_notes'] ?: null,
            'pilot_notes' => $this->editingData['pilot_notes'] ?: null,
            'updated_at' => now(),
        ];

        if ((int) ($updateData['parent_id'] ?? 0) === (int) $point->id) {
            $updateData['parent_id'] = null;
        }

        if ($point->event_template_program_point_id === null) {
            $updateData['name'] = trim($this->editingData['custom_name'] ?? $this->editingData['name'] ?? '');
        }

        DB::table('event_program_points')
            ->where('id', $point->id)
            ->update($updateData);

        $this->reorderDayPoints($newDay);

        if ($oldDay !== $newDay) {
            $this->reorderDayPoints($oldDay);
        }

        $this->event->refresh();
        $this->showEditModal = false;
        $this->editingPointId = null;

        $this->dispatch("planner-data-updated-{$this->getId()}", events: $this->buildCalendarEvents());
    }

    public function removeEditingPoint(): void
    {
        if (! $this->editingPointId) {
            return;
        }

        $pointId = $this->editingPointId;
        $this->showEditModal = false;
        $this->editingPointId = null;
        $this->removePointFromProgram($pointId);
    }

    public function openAddModal(string $startIso, string $endIso): void
    {
        $start = Carbon::parse($startIso);
        $end = Carbon::parse($endIso);
        $baseDate = $this->resolveBaseDate();
        $day = $baseDate->copy()->startOfDay()->diffInDays($start->copy()->startOfDay()) + 1;
        $day = max(1, min($this->resolveDurationDays(), $day));

        $this->plannerSearch = '';
        $this->templateResults = [];
        $this->newPointData = [
            'name' => '',
            'description' => '',
            'parent_id' => null,
            'day' => $day,
            'start_time' => $start->format('H:i'),
            'end_time' => $end->format('H:i'),
            'unit_price' => '',
            'quantity' => 1,
            'group_size' => '',
            'include_in_calculation' => false,
            'template_id' => null,
        ];
        $this->showAddModal = true;
    }

    public function updatedPlannerSearch(): void
    {
        if (strlen($this->plannerSearch) < 2) {
            $this->templateResults = [];

            return;
        }

        $this->templateResults = EventTemplateProgramPoint::query()
            ->where('name', 'like', '%'.$this->plannerSearch.'%')
            ->limit(8)
            ->get(['id', 'name', 'unit_price', 'group_size'])
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'unit_price' => $p->unit_price,
                'group_size' => $p->group_size,
            ])
            ->toArray();
    }

    public function selectPlannerTemplate(int $id): void
    {
        $template = EventTemplateProgramPoint::find($id);
        if (! $template) {
            return;
        }

        $this->newPointData['template_id'] = $id;
        $this->newPointData['name'] = $template->name;
        $this->newPointData['description'] = $template->description ?? '';
        $this->newPointData['unit_price'] = $template->unit_price ?? '';
        $this->newPointData['group_size'] = $template->group_size ?? '';
        $this->plannerSearch = $template->name;
        $this->templateResults = [];
    }

    public function clearPlannerTemplate(): void
    {
        $this->newPointData['template_id'] = null;
        $this->newPointData['unit_price'] = '';
        $this->newPointData['group_size'] = '';
        $this->plannerSearch = '';
        $this->templateResults = [];
    }

    public function saveNewPoint(): void
    {
        $name = trim($this->newPointData['name'] ?? '');

        if (empty($name)) {
            return;
        }

        $day = max(1, min($this->resolveDurationDays(), (int) ($this->newPointData['day'] ?? 1)));

        $maxOrder = EventProgramPoint::query()
            ->where('event_id', $this->event->id)
            ->where('day', $day)
            ->max('order') ?? 0;

        $templateId = $this->newPointData['template_id'] ?? null;
        $unitPrice = filled($this->newPointData['unit_price']) ? (float) $this->newPointData['unit_price'] : 0;
        $quantity = max(1, (int) ($this->newPointData['quantity'] ?? 1));
        $groupSize = filled($this->newPointData['group_size']) ? (int) $this->newPointData['group_size'] : null;

        EventProgramPoint::create([
            'event_id' => $this->event->id,
            'event_template_program_point_id' => $templateId,
            'name' => $name,
            'description' => $this->newPointData['description'] ?: null,
            'parent_id' => $this->newPointData['parent_id'] ?: null,
            'day' => $day,
            'start_time' => $this->newPointData['start_time'] ?: null,
            'end_time' => $this->newPointData['end_time'] ?: null,
            'order' => $maxOrder + 1,
            'unit_price' => $unitPrice,
            'quantity' => $quantity,
            'total_price' => $unitPrice * $quantity,
            'group_size' => $groupSize,
            'include_in_program' => true,
            'include_in_calculation' => (bool) ($this->newPointData['include_in_calculation'] ?? false),
            'active' => true,
        ]);

        $this->reorderDayPoints($day);
        $this->event->refresh();
        $this->showAddModal = false;
        $this->plannerSearch = '';
        $this->templateResults = [];
        $this->newPointData = [
            'name' => '',
            'description' => '',
            'parent_id' => null,
            'day' => 1,
            'start_time' => '08:00',
            'end_time' => '09:00',
            'unit_price' => '',
            'quantity' => 1,
            'group_size' => '',
            'include_in_calculation' => false,
            'template_id' => null,
        ];

        $this->dispatch("planner-data-updated-{$this->getId()}", events: $this->buildCalendarEvents());
    }

    public function closeModals(): void
    {
        $this->showEditModal = false;
        $this->showAddModal = false;
        $this->editingPointId = null;
        $this->plannerSearch = '';
        $this->templateResults = [];
    }

    public function updatePointSchedule(int $pointId, string $startInput, ?string $endInput = null): void
    {
        $point = EventProgramPoint::query()
            ->where('event_id', $this->event->id)
            ->find($pointId);

        if (! $point) {
            return;
        }

        $start = Carbon::parse($startInput);
        $end = $endInput ? Carbon::parse($endInput) : $start->copy()->addHour();

        if ($end->lte($start)) {
            $end = $start->copy()->addMinutes(30);
        }

        $baseDate = $this->resolveBaseDate();
        $oldDay = (int) ($point->day ?? 1);
        $newDay = $baseDate->copy()->startOfDay()->diffInDays($start->copy()->startOfDay()) + 1;
        $newDay = max(1, min($this->resolveDurationDays(), $newDay));

        DB::table('event_program_points')
            ->where('id', $point->id)
            ->update([
                'day' => $newDay,
                'start_time' => $start->format('H:i:s'),
                'end_time' => $end->format('H:i:s'),
                'updated_at' => now(),
            ]);

        $this->reorderDayPoints($newDay);

        if ($oldDay !== $newDay) {
            $this->reorderDayPoints($oldDay);
        }

        $this->event->refresh();

        $this->dispatch("planner-data-updated-{$this->getId()}", events: $this->buildCalendarEvents());
    }

    protected function resolveEffectivePlannerTimes(EventProgramPoint $point): array
    {
        if ($point->start_time && $point->end_time) {
            return [
                'start_time' => substr((string) $point->start_time, 0, 5),
                'end_time' => substr((string) $point->end_time, 0, 5),
            ];
        }

        $calendarEvent = collect($this->buildCalendarEvents())
            ->firstWhere('id', (string) $point->id);

        if (! $calendarEvent) {
            return [
                'start_time' => '08:00',
                'end_time' => '09:00',
            ];
        }

        return [
            'start_time' => Carbon::parse($calendarEvent['start'])->format('H:i'),
            'end_time' => Carbon::parse($calendarEvent['end'])->format('H:i'),
        ];
    }

    protected function reorderDayPoints(int $day): void
    {
        if ($day < 1) {
            return;
        }

        $points = EventProgramPoint::query()
            ->where('event_id', $this->event->id)
            ->where('day', $day)
            ->orderByRaw('CASE WHEN start_time IS NULL THEN 1 ELSE 0 END')
            ->orderBy('start_time')
            ->orderBy('order')
            ->orderBy('id')
            ->get(['id', 'order']);

        foreach ($points as $index => $point) {
            $expectedOrder = $index + 1;

            if ((int) $point->order === $expectedOrder) {
                continue;
            }

            DB::table('event_program_points')
                ->where('id', $point->id)
                ->update([
                    'order' => $expectedOrder,
                    'updated_at' => now(),
                ]);
        }
    }

    protected function resolveDurationDays(): int
    {
        return max(1, (int) ($this->event->eventTemplate?->duration_days ?? $this->event->duration_days ?? 1));
    }

    protected function resolveBaseDate(): Carbon
    {
        if (! empty($this->event->start_date)) {
            return Carbon::parse($this->event->start_date)->startOfDay();
        }

        return now()->startOfDay();
    }

    protected function buildCalendarEvents(): array
    {
        $baseDate = $this->resolveBaseDate();

        $points = EventProgramPoint::query()
            ->where('event_id', $this->event->id)
            ->where('include_in_program', true)
            ->with('templatePoint')
            ->orderBy('day')
            ->orderByRaw('CASE WHEN start_time IS NULL THEN 1 ELSE 0 END')
            ->orderBy('start_time')
            ->orderBy('order')
            ->orderBy('id')
            ->get();

        $displayOrderPerDay = [];

        return $points->map(function (EventProgramPoint $point) use ($baseDate, &$displayOrderPerDay) {
            $day = max(1, (int) ($point->day ?? 1));
            $displayOrderPerDay[$day] = ($displayOrderPerDay[$day] ?? 0) + 1;
            $displayOrder = $displayOrderPerDay[$day];

            $date = $baseDate->copy()->addDays($day - 1);

            $defaultStart = $date->copy()
                ->setTime(8, 0)
                ->addMinutes(max(0, ($displayOrder - 1) * 60));

            $start = $point->start_time
                ? Carbon::parse($date->toDateString().' '.$point->start_time)
                : $defaultStart;

            $durationMinutes = ((int) ($point->duration_hours ?? 0) * 60) + (int) ($point->duration_minutes ?? 0);
            if ($durationMinutes <= 0) {
                $durationMinutes = 60;
            }

            $end = $point->end_time
                ? Carbon::parse($date->toDateString().' '.$point->end_time)
                : $start->copy()->addMinutes($durationMinutes);

            if ($end->lte($start)) {
                $end = $start->copy()->addMinutes(max(30, $durationMinutes));
            }

            return [
                'id' => (string) $point->id,
                'title' => sprintf(
                    '%02d. %s',
                    $displayOrder,
                    $point->templatePoint->name ?? $point->name ?? ('Punkt #'.$point->id)
                ),
                'start' => $start->toIso8601String(),
                'end' => $end->toIso8601String(),
                'allDay' => false,
            ];
        })->values()->all();
    }

    protected function getBacklogPoints()
    {
        return EventProgramPoint::query()
            ->where('event_id', $this->event->id)
            ->where('include_in_program', false)
            ->with('templatePoint')
            ->orderBy('day')
            ->orderByRaw('CASE WHEN start_time IS NULL THEN 1 ELSE 0 END')
            ->orderBy('start_time')
            ->orderBy('order')
            ->orderBy('id')
            ->get();
    }

    protected function getProgramPoints()
    {
        return EventProgramPoint::query()
            ->where('event_id', $this->event->id)
            ->where('include_in_program', true)
            ->with('templatePoint')
            ->orderBy('day')
            ->orderByRaw('CASE WHEN start_time IS NULL THEN 1 ELSE 0 END')
            ->orderBy('start_time')
            ->orderBy('order')
            ->orderBy('id')
            ->get();
    }

    protected function getParentPointOptions(): array
    {
        return EventProgramPoint::query()
            ->where('event_id', $this->event->id)
            ->with('templatePoint')
            ->orderBy('day')
            ->orderBy('order')
            ->orderBy('id')
            ->get()
            ->mapWithKeys(fn (EventProgramPoint $point) => [
                $point->id => sprintf(
                    'Dzień %d • %02d. %s',
                    (int) ($point->day ?? 1),
                    (int) ($point->order ?? 1),
                    $point->templatePoint?->name ?? $point->name ?? ('Punkt #'.$point->id)
                ),
            ])
            ->all();
    }

    protected function setPointIncludedInProgram(int $pointId, bool $include): void
    {
        $point = EventProgramPoint::query()
            ->where('event_id', $this->event->id)
            ->find($pointId);

        if (! $point) {
            return;
        }

        DB::table('event_program_points')
            ->where('id', $point->id)
            ->update([
                'include_in_program' => $include,
                'updated_at' => now(),
            ]);

        $this->reorderDayPoints((int) ($point->day ?? 1));
        $this->event->refresh();

        $this->dispatch("planner-data-updated-{$this->getId()}", events: $this->buildCalendarEvents());
    }

    protected function normalizePlannerOrderOnMount(): void
    {
        $days = EventProgramPoint::query()
            ->where('event_id', $this->event->id)
            ->whereNotNull('day')
            ->distinct()
            ->pluck('day')
            ->map(fn ($day) => (int) $day)
            ->filter(fn ($day) => $day > 0)
            ->sort()
            ->values();

        foreach ($days as $day) {
            $this->reorderDayPoints($day);
        }
    }
}
