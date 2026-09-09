<?php

namespace App\Livewire;

use App\Models\Event;
use App\Models\EventProgramPoint;
use App\Models\Reservation;
use App\Models\VendorInvoice;
use App\Services\EventPaymentScheduleService;
use App\Services\EventProgramPointDeletionService;
use App\Services\EventProgramScheduleService;
use App\Services\ProgramPointPaymentStatusResolver;
use Carbon\Carbon;
use Filament\Notifications\Actions\Action as NotificationAction;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Livewire\Attributes\On;
use Livewire\Component;

class EventProgramPlanner extends Component
{
    public int $eventId;

    public Event $event;

    public bool $showEditModal = false;

    public ?int $editingPointId = null;

    /** @var array<string, mixed> */
    public array $editingData = [
        'name' => '',
        'start_time' => '',
        'end_time' => '',
        'hide_times' => false,
    ];

    public bool $showDeleteModal = false;

    public ?int $deletingPointId = null;

    public string $deletingPointName = '';

    public string $deletingModalHeading = 'Usunąć punkt?';

    public string $deletingModalDescription = '';

    /** Zwijanie podpunktów setów w kalendarzu (czytelność). */
    public bool $setChildrenCollapsed = false;

    /** @var array<int, array<string, mixed>>|null */
    protected ?array $calendarEventsCache = null;

    /** @var array<string, array{0: Carbon, 1: Carbon}> */
    protected array $scheduleWindowCache = [];

    protected ?bool $hasTimesManuallyLockedColumn = null;

    protected ?ProgramPointPaymentStatusResolver $paymentStatusResolver = null;

    public function mount(int $eventId): void
    {
        $this->eventId = $eventId;
        $this->event = Event::query()
            ->with('eventTemplate')
            ->findOrFail($eventId);
    }

    public function render()
    {
        $events = $this->getCalendarEvents();
        [$rangeStart, $rangeEnd, $durationDays] = $this->resolveCalendarWindow($events);

        return view('livewire.event-program-planner', [
            'plannerData' => [
                'events' => $events,
                'initialDate' => $rangeStart->toDateString(),
                'durationDays' => $durationDays,
                'rangeStart' => $rangeStart->toDateString(),
                'maxDate' => $rangeEnd->copy()->addDay()->toDateString(),
                'tripStart' => $this->resolveBaseDate()->toDateString(),
                'setChildrenCollapsed' => $this->setChildrenCollapsed,
            ],
        ]);
    }

    public function toggleSetChildrenCollapsed(): void
    {
        $this->setChildrenCollapsed = ! $this->setChildrenCollapsed;
        $this->calendarEventsCache = null;
        $this->dispatchCalendarUpdate();
    }

    #[On('event-program-planner-refresh')]
    public function refreshCalendarFromFinanceChange(): void
    {
        $this->calendarEventsCache = null;
        $this->dispatchCalendarUpdate();
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

        $effectiveTimes = $this->resolveEffectivePlannerTimes($point);

        $this->editingPointId = $pointId;
        $this->editingData = [
            'name' => $point->resolvedName(),
            'start_time' => $effectiveTimes['start_time'],
            'end_time' => $effectiveTimes['end_time'],
            'hide_times' => (bool) $point->hide_times,
        ];
        $this->showEditModal = true;
        $this->showDeleteModal = false;
        $this->resetErrorBag();
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

        $startTime = filled($this->editingData['start_time'] ?? null)
            ? substr((string) $this->editingData['start_time'], 0, 5)
            : null;
        $endTime = filled($this->editingData['end_time'] ?? null)
            ? substr((string) $this->editingData['end_time'], 0, 5)
            : null;
        $hideTimes = (bool) ($this->editingData['hide_times'] ?? false);

        if (! $hideTimes && (! $startTime || ! $endTime)) {
            $this->addError('editingData.end_time', 'Podaj godzinę startu i końca, albo ukryj godziny w programie.');

            return;
        }

        DB::table('event_program_points')
            ->where('id', $point->id)
            ->update([
                'hide_times' => $hideTimes,
                'updated_at' => now(),
            ]);

        $point = $point->fresh();

        if ($startTime && $endTime && ! $hideTimes) {
            try {
                $this->applyTypedTimes($point, $startTime, $endTime);
            } catch (InvalidArgumentException $e) {
                $this->addError('editingData.end_time', $e->getMessage());

                return;
            }
        } else {
            $this->reorderPlannerScheduleDays([(int) ($point->day ?? 1)]);
        }

        $this->event->refresh();
        $this->showEditModal = false;
        $this->editingPointId = null;

        $this->dispatch('event-program-points-refresh');
        $this->dispatchCalendarUpdate();
    }

    public function requestRemoveEditingPoint(): void
    {
        if (! $this->editingPointId) {
            return;
        }

        $pointId = $this->editingPointId;
        $this->showEditModal = false;
        $this->editingPointId = null;
        $this->openDeleteModal($pointId);
    }

    public function openDeleteModal(int $pointId): void
    {
        $point = EventProgramPoint::query()
            ->where('event_id', $this->event->id)
            ->with(['templatePoint', 'parent.templatePoint', 'children'])
            ->find($pointId);

        if (! $point) {
            return;
        }

        $description = app(EventProgramPointDeletionService::class)->describeDeletion($point);

        $this->deletingPointId = $pointId;
        $this->deletingPointName = $description['label'];
        $this->deletingModalHeading = $description['heading'];
        $this->deletingModalDescription = $description['description'];
        $this->showDeleteModal = true;
    }

    public function confirmRemovePoint(): void
    {
        if (! $this->deletingPointId) {
            return;
        }

        $pointId = $this->deletingPointId;
        $this->closeModals();
        $this->softDeletePoint($pointId);
    }

    public function closeModals(): void
    {
        $this->showEditModal = false;
        $this->editingPointId = null;
        $this->showDeleteModal = false;
        $this->deletingPointId = null;
        $this->deletingPointName = '';
        $this->deletingModalHeading = 'Usunąć punkt?';
        $this->deletingModalDescription = '';
        $this->resetErrorBag();
    }

    /**
     * Soft delete — ta sama semantyka co Lista / Dzień (nie tylko include_in_program=false).
     */
    public function softDeletePoint(int $pointId): void
    {
        $point = EventProgramPoint::query()
            ->where('event_id', $this->event->id)
            ->find($pointId);

        if (! $point) {
            return;
        }

        $result = app(EventProgramPointDeletionService::class)->softDelete($point);
        $name = $result['label'] !== ''
            ? $result['label']
            : $point->resolvedName();

        Notification::make()
            ->title($result['title'] !== '' ? $result['title'] : ('Usunięto: '.$name))
            ->body($result['body'])
            ->success()
            ->persistent()
            ->actions([
                NotificationAction::make('undo')
                    ->label('Cofnij')
                    ->button()
                    ->dispatch('undo-last-program-point-deletion'),
            ])
            ->send();

        $this->reorderPlannerScheduleDays([(int) ($point->day ?? 1)]);
        $this->event->refresh();
        $this->dispatch('event-program-points-refresh');
        $this->dispatchCalendarUpdate();
    }

    #[On('undo-last-program-point-deletion')]
    public function undoLastProgramPointDeletion(): void
    {
        $service = app(EventProgramPointDeletionService::class);
        $pending = $service->pendingUndo((int) $this->eventId);

        if ($pending === null) {
            Notification::make()
                ->title('Brak usunięcia do cofnięcia')
                ->warning()
                ->send();

            return;
        }

        $restored = $service->restoreByIds((int) $this->eventId, $pending['point_ids']);

        Notification::make()
            ->title($restored > 0 ? 'Cofnięto usunięcie' : 'Nie przywrócono punktów')
            ->body($restored > 0
                ? 'Przywrócono „'.$pending['label'].'”.'
                : 'Punkty nie były już w koszu.')
            ->success()
            ->send();

        $this->event->refresh();
        $this->dispatch('event-program-points-refresh');
        $this->dispatchCalendarUpdate();
    }

    public function repairOrderNow(): void
    {
        app(\App\Services\EventProgramPointOrderService::class)->repairOrderByStartTimes($this->event);
        $this->event->refresh();

        $this->dispatchCalendarUpdate();
    }

    #[On('event-program-points-refresh')]
    public function refreshFromProgramList(): void
    {
        $this->event->refresh();
        $this->dispatchCalendarUpdate();
    }

    public function updatePointSchedule(int $pointId, string $startInput, ?string $endInput = null): void
    {
        $point = EventProgramPoint::query()
            ->where('event_id', $this->event->id)
            ->find($pointId);

        if (! $point) {
            return;
        }

        $previousDay = (int) ($point->day ?? 1);

        $start = Carbon::parse($startInput);
        $end = $endInput ? Carbon::parse($endInput) : $start->copy()->addHour();

        if ($end->lte($start)) {
            $end = $start->copy()->addMinutes(30);
        }

        if ($point->parent_id) {
            $parent = EventProgramPoint::query()
                ->where('event_id', $this->event->id)
                ->find($point->parent_id);

            if ($parent?->start_time && $parent?->end_time) {
                $baseDate = $this->resolveBaseDate();
                $day = max(1, (int) ($point->day ?? 1));
                $date = $baseDate->copy()->addDays($day - 1);
                $parentStart = Carbon::parse($date->toDateString().' '.$parent->start_time);
                $parentEnd = Carbon::parse($date->toDateString().' '.$parent->end_time);

                if ($start->lt($parentStart)) {
                    $start = $parentStart->copy();
                }

                if ($end->gt($parentEnd)) {
                    $end = $parentEnd->copy();
                }

                if ($end->lte($start)) {
                    $end = $start->copy()->addMinutes(15);
                }
            }
        }

        $baseDate = $this->resolveBaseDate();
        $newDay = (int) $baseDate->copy()->startOfDay()->diffInDays($start->copy()->startOfDay()) + 1;
        $newDay = max(1, min($this->resolveDurationDays(), $newDay));

        if (! (bool) ($point->hide_times ?? false)) {
            try {
                app(EventProgramScheduleService::class)->applyPlannerTimeChange(
                    $point,
                    $start->format('H:i'),
                    $end->format('H:i'),
                    $newDay,
                );
            } catch (InvalidArgumentException $e) {
                $this->dispatchCalendarUpdate();

                return;
            }
        } elseif ($previousDay !== $newDay) {
            DB::table('event_program_points')
                ->where('id', $point->id)
                ->update([
                    'day' => $newDay,
                    'updated_at' => now(),
                ]);
            $this->reorderPlannerScheduleDays([$previousDay, $newDay]);
        }

        $this->event->refresh();
        $this->dispatch('event-program-points-refresh');
        $this->dispatchCalendarUpdate();
    }

    protected function applyTypedTimes(EventProgramPoint $point, string $startTime, string $endTime): void
    {
        app(EventProgramScheduleService::class)->applyPlannerTimeChange($point, $startTime, $endTime);
    }

    protected function supportsTimesManuallyLockedColumn(): bool
    {
        if ($this->hasTimesManuallyLockedColumn === null) {
            $this->hasTimesManuallyLockedColumn = Schema::hasColumn('event_program_points', 'times_manually_locked');
        }

        return $this->hasTimesManuallyLockedColumn;
    }

    /**
     * @return array{start_time: string, end_time: string}
     */
    protected function resolveEffectivePlannerTimes(EventProgramPoint $point): array
    {
        if ($point->start_time && $point->end_time) {
            return [
                'start_time' => substr((string) $point->start_time, 0, 5),
                'end_time' => substr((string) $point->end_time, 0, 5),
            ];
        }

        $baseDate = $this->resolveBaseDate();
        $day = max(1, (int) ($point->day ?? 1));
        $date = $baseDate->copy()->addDays($day - 1);
        $orderService = app(\App\Services\EventProgramPointOrderService::class);
        $visible = $orderService->visibleProgramPoints(
            $this->event,
            requireActive: false,
            preloaded: $orderService->loadPointsForPlanner($this->event),
        );
        $parentsById = $visible->whereNull('parent_id')->keyBy('id');
        [$start, $end] = $this->resolvePointScheduleWindow($point, $date, $parentsById, $visible);

        return [
            'start_time' => $start->format('H:i'),
            'end_time' => $end->format('H:i'),
        ];
    }

    protected function reorderPlannerSchedule(): void
    {
        app(\App\Services\EventProgramPointOrderService::class)->repairOrderByStartTimes($this->event);
    }

    /**
     * @param  array<int>  $days
     */
    protected function reorderPlannerScheduleDays(array $days): void
    {
        $service = app(\App\Services\EventProgramPointOrderService::class);

        foreach (array_values(array_unique(array_filter($days))) as $day) {
            $service->repairOrderByStartTimes($this->event, (int) $day);
        }
    }

    protected function paymentStatusResolver(): ProgramPointPaymentStatusResolver
    {
        return $this->paymentStatusResolver ??= app(ProgramPointPaymentStatusResolver::class);
    }

    protected function resolveDurationDays(): int
    {
        return $this->event->resolveCoreProgramDaysCount();
    }

    protected function resolveBaseDate(): Carbon
    {
        if (! empty($this->event->start_date)) {
            return Carbon::parse($this->event->start_date)->startOfDay();
        }

        return now()->startOfDay();
    }

    protected function getCalendarEvents(): array
    {
        if ($this->calendarEventsCache === null) {
            $this->calendarEventsCache = $this->buildCalendarEvents();
        }

        return $this->calendarEventsCache;
    }

    protected function refreshCalendarEvents(): array
    {
        $this->calendarEventsCache = $this->buildCalendarEvents();

        return $this->calendarEventsCache;
    }

    protected function dispatchCalendarUpdate(): void
    {
        $events = $this->refreshCalendarEvents();
        [$rangeStart, $rangeEnd, $durationDays] = $this->resolveCalendarWindow($events);

        $this->dispatch("planner-data-updated-{$this->getId()}", plannerData: [
            'events' => $events,
            'initialDate' => $rangeStart->toDateString(),
            'durationDays' => $durationDays,
            'rangeStart' => $rangeStart->toDateString(),
            'maxDate' => $rangeEnd->copy()->addDay()->toDateString(),
            'setChildrenCollapsed' => $this->setChildrenCollapsed,
        ]);
    }

    protected function buildCalendarEvents(): array
    {
        $this->scheduleWindowCache = [];

        $orderService = app(\App\Services\EventProgramPointOrderService::class);
        $points = $orderService->visibleProgramPoints(
            $this->event,
            requireActive: false,
            preloaded: $orderService->loadPointsForPlanner($this->event),
        );

        $paymentResolver = $this->paymentStatusResolver();
        $paymentResolver->preloadSettlementCosts($points, $this->event);
        $paymentResolver->preloadPaymentStacks($points, $this->event);
        $this->preloadVendorInvoices($points);
        $baseDate = $this->resolveBaseDate();
        $parentsById = $points->whereNull('parent_id')->keyBy('id');
        $childCountByParent = $points
            ->whereNotNull('parent_id')
            ->groupBy('parent_id')
            ->map(fn (Collection $children): int => $children->count());
        $parentDisplayOrderPerDay = [];

        $pointEvents = $points->map(function (EventProgramPoint $point) use (
            $baseDate,
            $parentsById,
            $points,
            &$parentDisplayOrderPerDay,
            $paymentResolver,
            $childCountByParent,
        ) {
            $day = max(1, (int) ($point->day ?? 1));
            $date = $baseDate->copy()->addDays($day - 1);
            $isChild = $point->parent_id !== null;

            if ($isChild && $this->setChildrenCollapsed) {
                return null;
            }

            if (! $isChild) {
                $parentDisplayOrderPerDay[$day] = ($parentDisplayOrderPerDay[$day] ?? 0) + 1;
            }

            [$start, $end] = $this->resolvePointScheduleWindow($point, $date, $parentsById, $points);

            $name = $point->resolvedName();
            $childCount = (int) ($childCountByParent->get($point->id) ?? 0);
            $isSetParent = ! $isChild && $childCount > 0;

            $title = $isChild
                ? '↳ '.$name
                : sprintf(
                    '%02d. %s',
                    $parentDisplayOrderPerDay[$day] ?? 1,
                    $name
                );

            if ($isSetParent && $this->setChildrenCollapsed) {
                $title .= ' · '.$childCount;
            }

            $paymentInfo = $paymentResolver->resolve($point, $this->event);
            $invoiceInfo = $this->resolveInvoiceBadge($point);
            $payerInfo = $this->resolvePayerBadge($point);
            $reservationInfo = $this->resolveReservationBadge($point);
            $notesPreview = $this->resolveNotesPreview($point);

            $classNames = $isChild ? ['event-program-child'] : ['event-program-parent'];
            if ($isSetParent) {
                $classNames[] = 'event-program-set';
                if ($this->setChildrenCollapsed) {
                    $classNames[] = 'event-program-set--collapsed';
                }
            }

            $event = [
                'id' => (string) $point->id,
                'title' => $title,
                'start' => $start->format('Y-m-d\TH:i:s'),
                'end' => $end->format('Y-m-d\TH:i:s'),
                'allDay' => false,
                'classNames' => $classNames,
                'extendedProps' => [
                    'isChild' => $isChild,
                    'isSetParent' => $isSetParent,
                    'childCount' => $childCount,
                    'parentId' => $point->parent_id ? (string) $point->parent_id : null,
                    'notesPreview' => $notesPreview,
                    'payment' => $paymentInfo,
                    'invoice' => $invoiceInfo,
                    'payer' => $payerInfo,
                    'reservation' => $reservationInfo,
                    'isHotelService' => (bool) $point->is_hotel_service,
                ],
            ];

            if ($isChild && $point->parent_id) {
                $event['constraint'] = (string) $point->parent_id;
            }

            return $event;
        })->filter()->values()->all();

        $paymentDueEvents = $this->buildPaymentDueCalendarEvents($points);

        return array_merge($pointEvents, $paymentDueEvents);
    }

    /**
     * @param  Collection<int, EventProgramPoint>  $points
     * @return list<array<string, mixed>>
     */
    protected function buildPaymentDueCalendarEvents(Collection $points): array
    {
        if ($points->isEmpty()) {
            return [];
        }

        $scheduleService = app(EventPaymentScheduleService::class);
        $scheduleService->warmCacheForProgramPoints($points, $this->event);

        $events = [];

        foreach ($points as $point) {
            $pointName = $point->resolvedName();

            foreach ($scheduleService->collectForProgramPoint($point, $this->event) as $row) {
                if (empty($row['due_date'])) {
                    continue;
                }

                if (($row['status'] ?? null) === 'paid') {
                    continue;
                }

                if (in_array($row['kind'] ?? null, ['advance_paid', 'payment'], true)) {
                    continue;
                }

                $dueDate = Carbon::parse((string) $row['due_date'])->toDateString();
                $kindLabel = (string) ($row['kind_label'] ?? 'Termin płatności');
                $amountLabel = (string) ($row['amount_label'] ?? '');
                $phrase = (string) ($row['phrase'] ?? '');
                $isOverdue = (bool) ($row['is_overdue'] ?? false);
                $rowId = (string) ($row['id'] ?? ('point-'.$point->id.'-'.$dueDate));

                $events[] = [
                    'id' => 'payment-due-'.$rowId,
                    'title' => $kindLabel.': '.$pointName,
                    'start' => $dueDate.'T07:00:00',
                    'end' => $dueDate.'T07:30:00',
                    'allDay' => false,
                    'editable' => false,
                    'startEditable' => false,
                    'durationEditable' => false,
                    'backgroundColor' => $isOverdue ? '#dc2626' : '#ea580c',
                    'borderColor' => $isOverdue ? '#991b1b' : '#c2410c',
                    'classNames' => ['event-program-payment-due'],
                    'extendedProps' => [
                        'isPaymentDue' => true,
                        'programPointId' => (string) $point->id,
                        'amountLabel' => $amountLabel,
                        'tooltip' => trim(collect([$phrase, $amountLabel])->filter()->implode(' · ')),
                    ],
                ];
            }
        }

        return $events;
    }

    /**
     * @param  list<array<string, mixed>>  $events
     * @return array{0: Carbon, 1: Carbon, 2: int}
     */
    protected function resolveCalendarWindow(array $events): array
    {
        $tripStart = $this->resolveBaseDate();
        $tripEnd = $tripStart->copy()->addDays(max(0, $this->resolveDurationDays() - 1));

        $dates = collect($events)
            ->map(function (array $event): ?Carbon {
                $start = $event['start'] ?? null;

                if (! filled($start)) {
                    return null;
                }

                return Carbon::parse(substr((string) $start, 0, 10))->startOfDay();
            })
            ->filter()
            ->push($tripStart, $tripEnd);

        $rangeStart = $dates->min()->copy()->startOfDay();
        $rangeEnd = $dates->max()->copy()->startOfDay();
        $durationDays = max(1, (int) $rangeStart->diffInDays($rangeEnd) + 1);

        return [$rangeStart, $rangeEnd, $durationDays];
    }

    /**
     * @param  Collection<int, EventProgramPoint>  $parentsById
     * @param  Collection<int, EventProgramPoint>  $allVisible
     * @return array{0: Carbon, 1: Carbon}
     */
    protected function resolvePointScheduleWindow(
        EventProgramPoint $point,
        Carbon $date,
        Collection $parentsById,
        Collection $allVisible,
    ): array {
        $cacheKey = (int) $point->id.'|'.$date->toDateString();

        if (isset($this->scheduleWindowCache[$cacheKey])) {
            return $this->scheduleWindowCache[$cacheKey];
        }

        $fallbackIndex = max(1, (int) ($point->order ?? 1));

        if ($point->start_time) {
            $start = Carbon::parse($date->toDateString().' '.$point->start_time);
            $durationMinutes = ((int) ($point->duration_hours ?? 0) * 60) + (int) ($point->duration_minutes ?? 0);
            if ($durationMinutes <= 0) {
                $durationMinutes = 60;
            }

            $end = $point->end_time
                ? Carbon::parse($date->toDateString().' '.$point->end_time)
                : $start->copy()->addMinutes($durationMinutes);
        } elseif ($point->parent_id && ($parent = $parentsById->get($point->parent_id))) {
            [$parentStart, $parentEnd] = $this->resolvePointScheduleWindow($parent, $date, $parentsById, $allVisible);
            $siblings = $allVisible
                ->where('parent_id', $point->parent_id)
                ->values();
            $siblingIndex = max(0, $siblings->search(fn (EventProgramPoint $sibling) => (int) $sibling->id === (int) $point->id));
            $slotMinutes = max(15, (int) $parentStart->diffInMinutes($parentEnd) / max(1, $siblings->count()));

            $start = $parentStart->copy()->addMinutes($siblingIndex * $slotMinutes);
            $end = $start->copy()->addMinutes(min($slotMinutes, 30));
        } else {
            $dayStart = $this->event->programDayStartTimeLabel((int) $point->day);
            [$startHour, $startMinute] = array_map('intval', explode(':', substr($dayStart, 0, 5)));

            $start = $date->copy()
                ->setTime($startHour, $startMinute)
                ->addMinutes(max(0, ($fallbackIndex - 1) * 60));

            $durationMinutes = ((int) ($point->duration_hours ?? 0) * 60) + (int) ($point->duration_minutes ?? 0);
            if ($durationMinutes <= 0) {
                $durationMinutes = 60;
            }

            $end = $start->copy()->addMinutes($durationMinutes);
        }

        if ($end->lte($start)) {
            $end = $start->copy()->addMinutes(30);
        }

        if ($point->parent_id && ($parent = $parentsById->get($point->parent_id))) {
            [$parentStart, $parentEnd] = $this->resolvePointScheduleWindow($parent, $date, $parentsById, $allVisible);

            if ($start->lt($parentStart)) {
                $start = $parentStart->copy();
            }

            if ($end->gt($parentEnd)) {
                $end = $parentEnd->copy();
            }

            if ($end->lte($start)) {
                $end = $start->copy()->addMinutes(15);
            }
        }

        $result = [$start, $end];
        $this->scheduleWindowCache[$cacheKey] = $result;

        return $result;
    }

    protected function resolvePayerBadge(EventProgramPoint $point): array
    {
        $hasOfficeNote = filled($point->office_notes);
        $hasPilotNote = filled($point->pilot_notes);

        if ($hasOfficeNote && $hasPilotNote) {
            return [
                'code' => 'B/P',
                'color' => 'blue',
                'tooltip' => 'Kto placi: biuro i pilot (na podstawie notatek).',
            ];
        }

        if ($hasPilotNote) {
            return [
                'code' => 'P',
                'color' => 'indigo',
                'tooltip' => 'Kto placi: pilot (na podstawie notatek).',
            ];
        }

        if ($hasOfficeNote) {
            return [
                'code' => 'B',
                'color' => 'slate',
                'tooltip' => 'Kto placi: biuro (na podstawie notatek).',
            ];
        }

        return [
            'code' => 'B',
            'color' => 'gray',
            'tooltip' => 'Kto placi: biuro (domyslnie, brak wskazowki).',
        ];
    }

    protected function resolveReservationBadge(EventProgramPoint $point): array
    {
        $latestReservation = $point->latestVisibleReservation();

        if (! $latestReservation) {
            return [
                'code' => 'R',
                'color' => 'gray',
                'tooltip' => 'Rezerwacja: brak.',
            ];
        }

        $statusLabel = Reservation::$statuses[$latestReservation->status] ?? $latestReservation->status;
        $depositLabel = \App\Support\Reservations\ReservationWorkflowDisplay::depositStatusLabel($latestReservation);
        $workflow = implode(' | ', \App\Support\Reservations\ReservationWorkflowDisplay::workflowLines($latestReservation));

        return match ($latestReservation->status) {
            'confirmed', 'completed' => [
                'code' => 'R',
                'color' => 'green',
                'tooltip' => "{$statusLabel}. {$depositLabel}. {$workflow}",
            ],
            'partially_confirmed' => [
                'code' => 'R',
                'color' => 'amber',
                'tooltip' => "{$statusLabel}. {$depositLabel}. {$workflow}",
            ],
            'pending' => [
                'code' => 'R',
                'color' => 'orange',
                'tooltip' => "{$statusLabel}. {$depositLabel}. {$workflow}",
            ],
            'not_required' => [
                'code' => 'R',
                'color' => 'slate',
                'tooltip' => "{$statusLabel}. {$workflow}",
            ],
            'cancelled' => [
                'code' => 'R',
                'color' => 'red',
                'tooltip' => "{$statusLabel}. {$workflow}",
            ],
            default => [
                'code' => 'R',
                'color' => 'gray',
                'tooltip' => "{$statusLabel}. {$depositLabel}. {$workflow}",
            ],
        };
    }

    protected function resolveNotesPreview(EventProgramPoint $point): ?string
    {
        $latestReservation = $point->latestVisibleReservation();

        $notes = collect([
            $point->notes,
            $point->office_notes,
            $point->pilot_notes,
            $latestReservation?->office_notes,
        ])
            ->filter(fn (?string $value) => filled($value))
            ->map(fn (string $value) => trim(preg_replace('/\s+/', ' ', strip_tags($value)) ?? ''))
            ->first();

        if (! $notes) {
            return null;
        }

        if (mb_strlen($notes) <= 96) {
            return $notes;
        }

        return rtrim(mb_substr($notes, 0, 93)).'...';
    }

    /**
     * @param  Collection<int, EventProgramPoint>  $points
     */
    protected function preloadVendorInvoices(Collection $points): void
    {
        if (! Schema::hasTable('vendor_invoices')) {
            $points->each(fn (EventProgramPoint $point) => $point->setRelation('linkedVendorInvoices', collect()));

            return;
        }

        $invoices = VendorInvoice::queryForProgramPoints($points->pluck('id'))
            ->orderBy('issue_date')
            ->with(['programPoints:id'])
            ->get();

        $points->each(function (EventProgramPoint $point) use ($invoices): void {
            $point->setRelation(
                'linkedVendorInvoices',
                $invoices
                    ->filter(fn (VendorInvoice $invoice): bool => $invoice->isLinkedToProgramPoint((int) $point->id))
                    ->values(),
            );
        });
    }

    /**
     * @return array{code: string, color: string, tooltip: string}
     */
    protected function resolveInvoiceBadge(EventProgramPoint $point): array
    {
        $invoices = $point->relationLoaded('linkedVendorInvoices')
            ? $point->getRelation('linkedVendorInvoices')
            : collect();

        $count = $invoices instanceof Collection ? $invoices->count() : 0;

        if ($count === 0) {
            return [
                'code' => 'F',
                'color' => 'gray',
                'tooltip' => 'Faktura: brak podpiętego dokumentu.',
            ];
        }

        $labels = $invoices
            ->take(3)
            ->map(fn (VendorInvoice $invoice): string => $invoice->invoice_number ?: ('KSeF '.$invoice->ksef_number))
            ->filter()
            ->implode(', ');

        return [
            'code' => 'F',
            'color' => 'green',
            'tooltip' => $count === 1
                ? 'Faktura: '.$labels
                : sprintf('Faktury (%d): %s', $count, $labels),
        ];
    }
}
