<?php

namespace App\Livewire;

use App\Models\Event;
use App\Models\EventProgramPoint;
use App\Models\Reservation;
use App\Models\VendorInvoice;
use App\Services\EventProgramScheduleService;
use App\Services\ProgramPointPaymentStatusResolver;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\On;
use Livewire\Component;

class EventProgramPlanner extends Component
{
    public int $eventId;

    public Event $event;

    public bool $showDeleteModal = false;

    public ?int $deletingPointId = null;

    public string $deletingPointName = '';

    /** @var array<int, array<string, mixed>>|null */
    protected ?array $calendarEventsCache = null;

    public function mount(int $eventId): void
    {
        $this->eventId = $eventId;
        $this->event = Event::query()
            ->with('eventTemplate')
            ->findOrFail($eventId);
    }

    public function render()
    {
        return view('livewire.event-program-planner', [
            'plannerData' => [
                'events' => $this->getCalendarEvents(),
                'initialDate' => $this->resolveBaseDate()->toDateString(),
                'durationDays' => $this->resolveDurationDays(),
                'maxDate' => $this->resolveBaseDate()->copy()->addDays($this->resolveDurationDays())->toDateString(),
            ],
        ]);
    }

    public function openDeleteModal(int $pointId): void
    {
        $point = EventProgramPoint::query()
            ->where('event_id', $this->event->id)
            ->with('templatePoint')
            ->find($pointId);

        if (! $point) {
            return;
        }

        $this->deletingPointId = $pointId;
        $this->deletingPointName = $point->templatePoint?->name ?? $point->name ?? ('Punkt #'.$point->id);
        $this->showDeleteModal = true;
    }

    public function confirmRemovePoint(): void
    {
        if (! $this->deletingPointId) {
            return;
        }

        $pointId = $this->deletingPointId;
        $this->closeModals();
        $this->removePointFromProgram($pointId);
    }

    public function closeModals(): void
    {
        $this->showDeleteModal = false;
        $this->deletingPointId = null;
        $this->deletingPointName = '';
    }

    public function removePointFromProgram(int $pointId): void
    {
        $this->setPointIncludedInProgram($pointId, false);
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
        $newDay = $baseDate->copy()->startOfDay()->diffInDays($start->copy()->startOfDay()) + 1;
        $newDay = max(1, min($this->resolveDurationDays(), $newDay));

        DB::table('event_program_points')
            ->where('id', $point->id)
            ->update([
                'day' => $newDay,
                'updated_at' => now(),
            ]);

        $point = $point->fresh();

        if (! (bool) ($point->hide_times ?? false)) {
            if ($point->parent_id === null) {
                app(EventProgramScheduleService::class)->applyManualTimeChange(
                    $point,
                    $start->format('H:i'),
                    $end->format('H:i'),
                );
            } else {
                $payload = [
                    'start_time' => $start->format('H:i:s'),
                    'end_time' => $end->format('H:i:s'),
                    'updated_at' => now(),
                ];

                if (Schema::hasColumn('event_program_points', 'times_manually_locked')) {
                    $payload['times_manually_locked'] = true;
                }

                $point->update($payload);
                $this->reorderPlannerSchedule();
            }
        }

        $this->event->refresh();

        $this->dispatchCalendarUpdate();
    }

    protected function reorderPlannerSchedule(): void
    {
        app(\App\Services\EventProgramPointOrderService::class)->repairOrderByStartTimes($this->event);
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
        $this->dispatch("planner-data-updated-{$this->getId()}", events: $this->refreshCalendarEvents());
    }

    protected function buildCalendarEvents(): array
    {
        $service = app(\App\Services\EventProgramPointOrderService::class);
        $points = $service->visibleProgramPoints($this->event, requireActive: false);
        app(ProgramPointPaymentStatusResolver::class)->preloadSettlementCosts($points, $this->event);
        $this->preloadVendorInvoices($points);
        $baseDate = $this->resolveBaseDate();
        $parentsById = $points->whereNull('parent_id')->keyBy('id');
        $parentDisplayOrderPerDay = [];

        return $points->map(function (EventProgramPoint $point) use ($baseDate, $parentsById, $points, &$parentDisplayOrderPerDay) {
            $day = max(1, (int) ($point->day ?? 1));
            $date = $baseDate->copy()->addDays($day - 1);
            $isChild = $point->parent_id !== null;

            if (! $isChild) {
                $parentDisplayOrderPerDay[$day] = ($parentDisplayOrderPerDay[$day] ?? 0) + 1;
            }

            [$start, $end] = $this->resolvePointScheduleWindow($point, $date, $parentsById, $points);

            $name = $point->templatePoint?->name ?? $point->name ?? ('Punkt #'.$point->id);
            $title = $isChild
                ? '↳ '.$name
                : sprintf(
                    '%02d. %s',
                    $parentDisplayOrderPerDay[$day] ?? 1,
                    $name
                );

            $paymentInfo = app(ProgramPointPaymentStatusResolver::class)->resolve($point, $this->event);
            $invoiceInfo = $this->resolveInvoiceBadge($point);
            $payerInfo = $this->resolvePayerBadge($point);
            $reservationInfo = $this->resolveReservationBadge($point);
            $notesPreview = $this->resolveNotesPreview($point);

            $event = [
                'id' => (string) $point->id,
                'title' => $title,
                'start' => $start->toIso8601String(),
                'end' => $end->toIso8601String(),
                'allDay' => false,
                'classNames' => $isChild ? ['event-program-child'] : ['event-program-parent'],
                'extendedProps' => [
                    'isChild' => $isChild,
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
        })->values()->all();
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

        return [$start, $end];
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
        $latestReservation = $point->reservations
            ->sortByDesc('id')
            ->first();

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
        $latestReservation = $point->reservations->sortByDesc('id')->first();

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

        $this->reorderPlannerSchedule();
        $this->event->refresh();

        $this->dispatchCalendarUpdate();
    }
}
