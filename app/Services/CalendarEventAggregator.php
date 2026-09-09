<?php

namespace App\Services;

use App\Enums\TaskPriority;
use App\Models\Event;
use App\Models\EventDayInsurance;
use App\Models\EventHotelStay;
use App\Models\Reservation;
use App\Models\Task;
use App\Models\VendorInvoice;
use App\Support\Calendar\CalendarEventLinks;
use App\Support\Tasks\TaskContextRegistry;
use App\Support\Tasks\TaskListColumn;
use App\Support\Tasks\TaskNavigation;
use App\Support\Tasks\TaskQueryFilters;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class CalendarEventAggregator
{
    /** @return Collection<int, array<string, mixed>> */
    public function events(array $filters = []): Collection
    {
        // Domyślnie szeroki zakres: bieżący miesiąc bywa pusty, a nawigacja FC
        // bez from/to inaczej pokazywałaby pusty kalendarz.
        $from = isset($filters['from'])
            ? Carbon::parse($filters['from'])
            : now()->subMonths(6)->startOfMonth();
        $to = isset($filters['to'])
            ? Carbon::parse($filters['to'])
            : now()->addMonths(12)->endOfMonth();
        $types = collect($filters['types'] ?? [])->filter()->values();

        $items = collect();

        if ($types->isEmpty() || $types->contains('events')) {
            $items = $items->merge($this->eventTrips($from, $to, $filters));
        }

        if ($types->isEmpty() || $types->contains('tasks')) {
            $items = $items->merge($this->tasks(
                $from,
                $to,
                (bool) ($filters['show_finished_tasks'] ?? $filters['show_completed_tasks'] ?? false),
                $filters,
            ));
        }

        if ($types->isEmpty() || $types->contains('insurances')) {
            $items = $items->merge($this->dayInsurances($from, $to, $filters));
        }

        if ($types->isEmpty() || $types->contains('ksef')) {
            $items = $items->merge($this->ksefInvoices($from, $to, $filters));
        }

        if ($types->isEmpty() || $types->contains('payments')) {
            $items = $items->merge($this->pendingPayments($from, $to, $filters));
        }

        if ($types->isEmpty() || $types->contains('pilots')) {
            $items = $items->merge($this->pilotAdvances($from, $to, $filters));
        }

        if ($types->isEmpty() || $types->contains('reservations')) {
            $items = $items->merge($this->reservations($from, $to, $filters));
        }

        if ($types->isEmpty() || $types->contains('payments') || $types->contains('reservations')) {
            $items = $items->merge($this->reservationDeposits($from, $to, $filters));
        }

        if ($types->isEmpty() || $types->contains('transport')) {
            $items = $items->merge($this->transportDepartures($from, $to, $filters));
        }

        if ($types->isEmpty() || $types->contains('hotels')) {
            $items = $items->merge($this->hotelStays($from, $to, $filters));
        }

        return $items->sortBy('start')->values();
    }

    /**
     * Zasoby do widoku Gantt-lite (piloci, transport, hotele).
     *
     * @return array{resources: array<int, array<string, mixed>>, events: Collection<int, array<string, mixed>>}
     */
    public function resourceTimeline(array $filters = []): array
    {
        $from = isset($filters['from'])
            ? Carbon::parse($filters['from'])
            : now()->subMonths(1)->startOfMonth();
        $to = isset($filters['to'])
            ? Carbon::parse($filters['to'])
            : now()->addMonths(6)->endOfMonth();

        $statuses = $this->resolveEventStatuses($filters);

        $trips = Event::query()
            ->with(['assignedUser', 'bus', 'transportContractor'])
            ->whereNotNull('start_date')
            ->whereDate('start_date', '<=', $to)
            ->where(function ($query) use ($from): void {
                $query->whereNull('end_date')
                    ->orWhereDate('end_date', '>=', $from);
            })
            ->whereIn('status', $statuses)
            ->limit(200)
            ->get();

        $resources = [];
        $events = collect();

        foreach ($trips as $event) {
            $start = $event->start_date?->toDateString();
            $end = ($event->end_date ?? $event->start_date)?->copy()->addDay()->toDateString();
            $title = ($event->code ? $event->code.' — ' : '').$event->name;

            if ($event->assigned_to) {
                $resourceId = 'pilot-'.$event->assigned_to;
                $resources[$resourceId] = [
                    'id' => $resourceId,
                    'title' => 'Pilot: '.($event->assignedUser?->name ?? '#'.$event->assigned_to),
                    'group' => 'pilots',
                ];
                $events->push([
                    'id' => 'pilot-event-'.$event->id,
                    'resourceId' => $resourceId,
                    'title' => $title,
                    'start' => $start,
                    'end' => $end,
                    'backgroundColor' => '#0f766e',
                    'borderColor' => '#0f766e',
                ]);
            }

            if ($event->bus_id || $event->transport_contractor_id) {
                $resourceId = $event->bus_id
                    ? 'bus-'.$event->bus_id
                    : 'transport-'.$event->transport_contractor_id;
                $resources[$resourceId] = [
                    'id' => $resourceId,
                    'title' => 'Transport: '.($event->bus?->name
                        ?? $event->transportContractor?->name
                        ?? $event->transport_company_name
                        ?? $resourceId),
                    'group' => 'transport',
                ];
                $events->push([
                    'id' => 'transport-event-'.$event->id,
                    'resourceId' => $resourceId,
                    'title' => $title,
                    'start' => $start,
                    'end' => $end,
                    'backgroundColor' => '#2563eb',
                    'borderColor' => '#1d4ed8',
                ]);
            }
        }

        if (Schema::hasTable('event_hotel_stays')) {
            EventHotelStay::query()
                ->with(['event', 'contractor'])
                ->whereHas('event', function ($query) use ($from, $to, $statuses): void {
                    $query->whereNotNull('start_date')
                        ->whereDate('start_date', '<=', $to)
                        ->where(function ($q) use ($from): void {
                            $q->whereNull('end_date')->orWhereDate('end_date', '>=', $from);
                        })
                        ->whereIn('status', $statuses);
                })
                ->limit(200)
                ->get()
                ->each(function (EventHotelStay $stay) use (&$resources, &$events): void {
                    $event = $stay->event;
                    if (! $event?->start_date) {
                        return;
                    }
                    $contractorId = $stay->contractor_id ?: 0;
                    $resourceId = 'hotel-'.($contractorId ?: 'day-'.$stay->day.'-'.$stay->id);
                    $resources[$resourceId] = [
                        'id' => $resourceId,
                        'title' => 'Hotel: '.($stay->contractor?->name ?? 'Dzień '.$stay->day),
                        'group' => 'hotels',
                    ];
                    $events->push([
                        'id' => 'hotel-stay-'.$stay->id,
                        'resourceId' => $resourceId,
                        'title' => ($event->code ? $event->code.' — ' : '').$event->name.' (dzień '.$stay->day.')',
                        'start' => $event->start_date->copy()->addDays(max(0, (int) $stay->day - 1))->toDateString(),
                        'end' => $event->start_date->copy()->addDays(max(1, (int) $stay->day))->toDateString(),
                        'backgroundColor' => '#a16207',
                        'borderColor' => '#a16207',
                    ]);
                });
        }

        return [
            'resources' => array_values($resources),
            'events' => $events->values(),
        ];
    }

    /**
     * Statusy imprez do kalendarza / zasobów.
     * Brak klucza w filtrach → confirmed + odprawa_ok; pusta lista → brak imprez.
     *
     * @param  array<string, mixed>  $filters
     * @return list<string>
     */
    protected function resolveEventStatuses(array $filters): array
    {
        if (! array_key_exists('event_statuses', $filters)) {
            return Event::getConfirmedLikeStatuses();
        }

        $allowed = array_keys(Event::getStatusOptions());

        return array_values(array_filter(
            (array) $filters['event_statuses'],
            fn (mixed $status): bool => is_string($status) && in_array($status, $allowed, true),
        ));
    }

    /**
     * Czy ukrywać aktywności (zadania, rezerwacje itd.) powiązane z imprezami
     * spoza wybranego filtra statusów. Domyślnie tak.
     *
     * @param  array<string, mixed>  $filters
     */
    protected function hidesLinkedToFilteredEvents(array $filters): bool
    {
        if (! array_key_exists('hide_linked_to_filtered_events', $filters)) {
            return true;
        }

        return (bool) $filters['hide_linked_to_filtered_events'];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    protected function shouldIncludeLinkedEvent(?Event $event, array $filters): bool
    {
        if (! $this->hidesLinkedToFilteredEvents($filters)) {
            return true;
        }

        if (! $event) {
            return true;
        }

        return in_array($event->status, $this->resolveEventStatuses($filters), true);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\Event>|\Illuminate\Database\Eloquent\Relations\Relation<\App\Models\Event, *, *>  $query
     * @param  array<string, mixed>  $filters
     */
    protected function constrainEventQueryByStatuses($query, array $filters): void
    {
        if (! $this->hidesLinkedToFilteredEvents($filters)) {
            return;
        }

        $query->whereIn('status', $this->resolveEventStatuses($filters));
    }

    /** @return Collection<int, array<string, mixed>> */
    protected function eventTrips(Carbon $from, Carbon $to, array $filters = []): Collection
    {
        $statuses = $this->resolveEventStatuses($filters);

        return Event::query()
            ->whereNotNull('start_date')
            ->whereDate('start_date', '<=', $to)
            ->where(function ($query) use ($from): void {
                $query->whereNull('end_date')
                    ->orWhereDate('end_date', '>=', $from);
            })
            ->whereIn('status', $statuses)
            ->limit(300)
            ->get()
            ->map(fn (Event $event): array => $this->withLinks([
                'id' => 'event-'.$event->id,
                'title' => $event->code.' — '.$event->name,
                'start' => $event->start_date?->toDateString(),
                'end' => ($event->end_date ?? $event->start_date)?->copy()->addDay()->toDateString(),
                'backgroundColor' => '#2563eb',
                'borderColor' => '#1d4ed8',
                'type' => 'events',
            ], [
                CalendarEventLinks::event($event->id),
            ]));
    }

    /** @return Collection<int, array<string, mixed>> */
    protected function tasks(Carbon $from, Carbon $to, bool $includeFinished = false, array $filters = []): Collection
    {
        $finishedStatusIds = TaskQueryFilters::finishedStatusIds();

        return Task::query()
            ->whereNotNull('due_date')
            ->whereBetween('due_date', [$from, $to])
            ->when(true, fn ($query) => TaskQueryFilters::topLevelOnly($query))
            ->when(
                (bool) ($filters['tasks_only_urgent'] ?? false),
                fn ($query) => TaskQueryFilters::applyUrgentOnly($query, true),
            )
            ->when(
                filled($filters['tasks_due_filter'] ?? null),
                fn ($query) => TaskQueryFilters::applyDueFilter($query, (string) $filters['tasks_due_filter']),
            )
            ->when(! $includeFinished, fn ($query) => TaskQueryFilters::excludeFinished($query))
            ->when(
                filled($filters['tasks_scope'] ?? null) && ($filters['tasks_scope'] ?? 'all') !== 'all',
                fn ($query) => TaskQueryFilters::applyOwnershipScope(
                    $query,
                    (string) $filters['tasks_scope'],
                    $filters['user_id'] ?? null,
                ),
            )
            ->when(
                ! filled($filters['tasks_scope'] ?? null) && (bool) ($filters['tasks_only_mine'] ?? false),
                fn ($query) => TaskQueryFilters::assignedTo($query, $filters['user_id'] ?? null),
            )
            ->with(['taskable', 'author', 'assignee', 'comments' => fn ($comments) => $comments->latest()->limit(1)])
            ->limit(200)
            ->get()
            ->filter(fn (Task $task): bool => $this->shouldIncludeLinkedEvent(
                TaskNavigation::resolveEvent($task),
                $filters,
            ))
            ->map(function (Task $task) use ($finishedStatusIds): array {
                $isFinished = in_array((int) $task->status_id, $finishedStatusIds, true);
                $descriptionPreview = filled($task->description)
                    ? Str::limit(trim(strip_tags((string) $task->description)), 180)
                    : null;
                $latestComment = $task->comments->first();
                $commentPreview = $latestComment
                    ? Str::limit(trim(strip_tags((string) $latestComment->content)), 180)
                    : null;
                $isUrgent = TaskPriority::normalize($task->priority) === TaskPriority::Urgent->value;

                return $this->withLinks([
                    'id' => 'task-'.$task->id,
                    'title' => ($isUrgent ? '⚠ Pilne: ' : 'Zadanie: ').$task->title,
                    'start' => $task->due_date?->toDateString(),
                    'backgroundColor' => $isFinished ? '#a78bfa' : ($isUrgent ? '#dc2626' : '#7c3aed'),
                    'borderColor' => $isFinished ? '#8b5cf6' : ($isUrgent ? '#b91c1c' : '#6d28d9'),
                    'type' => 'tasks',
                    'classNames' => array_values(array_filter([
                        $isFinished ? 'operations-calendar-completed' : null,
                        $isUrgent ? 'operations-calendar-urgent' : null,
                    ])),
                    'extendedProps' => [
                        'ownershipPreview' => TaskListColumn::ownershipLine($task),
                        'descriptionPreview' => $descriptionPreview,
                        'commentPreview' => $commentPreview,
                    ],
                ], array_merge(
                    [CalendarEventLinks::task($task->id)],
                    TaskContextRegistry::linksForTask($task),
                ));
            })
            ->values();
    }

    /** @return Collection<int, array<string, mixed>> */
    protected function ksefInvoices(Carbon $from, Carbon $to, array $filters = []): Collection
    {
        if (! class_exists(VendorInvoice::class)) {
            return collect();
        }

        return VendorInvoice::query()
            ->with(['event'])
            ->where(function ($query) use ($from, $to): void {
                $query->whereBetween('due_date', [$from, $to])
                    ->orWhereBetween('issue_date', [$from, $to]);
            })
            ->when(
                $this->hidesLinkedToFilteredEvents($filters),
                fn ($query) => $query->where(function ($inner) use ($filters): void {
                    $inner->whereNull('event_id')
                        ->orWhereHas('event', fn ($eventQuery) => $this->constrainEventQueryByStatuses($eventQuery, $filters));
                }),
            )
            ->limit(200)
            ->get()
            ->map(fn (VendorInvoice $invoice): array => $this->withLinks([
                'id' => 'ksef-'.$invoice->id,
                'title' => 'KSeF: '.($invoice->invoice_number ?: $invoice->ksef_number),
                'start' => ($invoice->due_date ?? $invoice->issue_date)?->toDateString(),
                'backgroundColor' => '#dc2626',
                'borderColor' => '#b91c1c',
                'type' => 'ksef',
            ], [
                CalendarEventLinks::vendorInvoice($invoice->id),
                CalendarEventLinks::event($invoice->event_id),
                CalendarEventLinks::contractor($invoice->contractor_id),
            ]));
    }

    /** @return Collection<int, array<string, mixed>> */
    protected function pendingPayments(Carbon $from, Carbon $to, array $filters = []): Collection
    {
        $rows = app(PendingPaymentAggregator::class)
            ->collect($from, $to)
            ->reject(fn (array $row): bool => ($row['type'] ?? '') === 'vendor_invoice');

        if ($this->hidesLinkedToFilteredEvents($filters)) {
            $statuses = $this->resolveEventStatuses($filters);
            $eventIds = $rows->pluck('event_id')->filter()->unique()->values();
            $allowedEventIds = $eventIds->isEmpty()
                ? collect()
                : Event::query()
                    ->whereIn('id', $eventIds)
                    ->whereIn('status', $statuses)
                    ->pluck('id')
                    ->map(fn ($id): int => (int) $id)
                    ->all();

            $rows = $rows->filter(function (array $row) use ($allowedEventIds): bool {
                $eventId = $row['event_id'] ?? null;

                if (! filled($eventId)) {
                    return true;
                }

                return in_array((int) $eventId, $allowedEventIds, true);
            });
        }

        return $rows
            ->map(fn (array $row): array => $this->withLinks([
                'id' => $row['id'],
                'title' => ($row['type_label'] ?? 'Płatność').': '.($row['title'] ?? ''),
                'start' => $row['due_date'],
                'backgroundColor' => $row['color'] ?? '#ea580c',
                'borderColor' => $row['color'] ?? '#c2410c',
                'type' => 'payments',
                'event_id' => $row['event_id'] ?? null,
            ], $this->paymentLinks($row)))
            ->values();
    }

    /** @return Collection<int, array<string, mixed>> */
    protected function pilotAdvances(Carbon $from, Carbon $to, array $filters = []): Collection
    {
        if (! Schema::hasColumn('events', 'pilot_advance_planned_amount')) {
            return collect();
        }

        return Event::query()
            ->tap(fn ($query) => app(\App\Services\PilotContractorAssignmentService::class)
                ->constrainEventsWithAssignedPilot($query))
            ->whereNotNull('start_date')
            ->whereBetween('start_date', [$from, $to])
            ->where('pilot_funds_paid', false)
            ->whereNotNull('pilot_advance_planned_amount')
            ->where('pilot_advance_planned_amount', '>', 0)
            ->tap(fn ($query) => $this->constrainEventQueryByStatuses($query, $filters))
            ->limit(100)
            ->get()
            ->map(fn (Event $event): array => $this->withLinks([
                'id' => 'advance-'.$event->id,
                'title' => 'Zaliczka pilota: '.$event->code,
                'start' => $event->start_date?->toDateString(),
                'backgroundColor' => '#0d9488',
                'borderColor' => '#0f766e',
                'type' => 'pilots',
            ], [
                CalendarEventLinks::link(
                    \App\Support\AdminPanelUrls::eventPilot($event),
                    'Zaliczka pilota',
                    'heroicon-o-banknotes',
                ),
                CalendarEventLinks::event($event->id),
            ]));
    }

    /** @return Collection<int, array<string, mixed>> */
    protected function reservations(Carbon $from, Carbon $to, array $filters = []): Collection
    {
        if (! class_exists(Reservation::class)) {
            return collect();
        }

        return Reservation::query()
            ->whereHas('event', function ($q) use ($from, $to, $filters): void {
                $q->whereBetween('start_date', [$from, $to]);
                $this->constrainEventQueryByStatuses($q, $filters);
            })
            ->limit(150)
            ->with('event')
            ->get()
            ->map(fn (Reservation $reservation): array => $this->withLinks([
                'id' => 'res-'.$reservation->id,
                'title' => 'Rezerwacja: '.($reservation->booking_reference ?: '#'.$reservation->id),
                'start' => $reservation->event?->start_date?->toDateString(),
                'backgroundColor' => '#0891b2',
                'borderColor' => '#0e7490',
                'type' => 'reservations',
            ], [
                CalendarEventLinks::reservation($reservation->id),
                CalendarEventLinks::event($reservation->event_id),
                CalendarEventLinks::contractor($reservation->contractor_id),
            ]));
    }

    /** @return Collection<int, array<string, mixed>> */
    protected function reservationDeposits(Carbon $from, Carbon $to, array $filters = []): Collection
    {
        if (! class_exists(Reservation::class) || ! Schema::hasColumn('reservations', 'deposit_due_at')) {
            return collect();
        }

        return Reservation::query()
            ->whereNotNull('deposit_due_at')
            ->whereNull('deposit_paid_at')
            ->whereBetween('deposit_due_at', [$from, $to])
            ->whereNotIn('status', ['cancelled', 'not_required'])
            ->when(
                $this->hidesLinkedToFilteredEvents($filters),
                fn ($query) => $query->whereHas(
                    'event',
                    fn ($eventQuery) => $this->constrainEventQueryByStatuses($eventQuery, $filters),
                ),
            )
            ->with(['event', 'programPoint'])
            ->limit(150)
            ->get()
            ->map(function (Reservation $reservation): array {
                $pointName = $reservation->programPoint?->name;
                $title = 'Zaliczka rezerwacji: '.($reservation->booking_reference ?: '#'.$reservation->id);
                if (filled($pointName)) {
                    $title .= ' — '.$pointName;
                }

                return $this->withLinks([
                    'id' => 'res-deposit-'.$reservation->id,
                    'title' => $title,
                    'start' => $reservation->deposit_due_at?->toDateString(),
                    'backgroundColor' => '#ea580c',
                    'borderColor' => '#c2410c',
                    'type' => 'payments',
                    'event_id' => $reservation->event_id,
                ], [
                    CalendarEventLinks::reservation($reservation->id),
                    CalendarEventLinks::event($reservation->event_id),
                    CalendarEventLinks::contractor($reservation->contractor_id),
                ]);
            });
    }

    /** @return Collection<int, array<string, mixed>> */
    protected function dayInsurances(Carbon $from, Carbon $to, array $filters = []): Collection
    {
        if (! Schema::hasTable('event_day_insurance')) {
            return collect();
        }

        $hasDoneColumn = Schema::hasColumn('event_day_insurance', 'is_done');

        return EventDayInsurance::query()
            ->with(['event', 'insurance'])
            ->whereNotNull('insurance_id')
            ->whereHas('event', function ($q) use ($filters): void {
                $q->whereNotNull('start_date');
                $this->constrainEventQueryByStatuses($q, $filters);
            })
            ->when($hasDoneColumn, fn ($q) => $q->where(function ($inner): void {
                $inner->where('is_done', false)->orWhereNull('is_done');
            }))
            ->limit(300)
            ->get()
            ->map(function (EventDayInsurance $row): ?array {
                $event = $row->event;
                if (! $event?->start_date) {
                    return null;
                }

                $date = $event->dateForProgramDay((int) $row->day);
                if (! $date) {
                    return null;
                }

                $product = $row->insurance?->name ?? 'Ubezpieczenie';
                $code = $event->code ? $event->code.' — ' : '';

                return $this->withLinks([
                    'id' => 'insurance-'.$row->id,
                    'title' => 'Ubezpieczenie: '.$code.$product.' (dzień '.$row->day.')',
                    'start' => $date->toDateString(),
                    'backgroundColor' => '#059669',
                    'borderColor' => '#047857',
                    'type' => 'insurances',
                ], [
                    CalendarEventLinks::link(
                        \App\Filament\Resources\EventResource::getUrl('day-insurances', ['record' => $event->id]),
                        'Ubezpieczenia imprezy',
                        'heroicon-o-shield-check',
                    ),
                    CalendarEventLinks::event($event->id),
                ]);
            })
            ->filter()
            ->filter(function (array $item) use ($from, $to): bool {
                $start = Carbon::parse($item['start']);

                return $start->between($from, $to);
            })
            ->values();
    }

    /** @return Collection<int, array<string, mixed>> */
    protected function transportDepartures(Carbon $from, Carbon $to, array $filters = []): Collection
    {
        return Event::query()
            ->whereNotNull('start_date')
            ->whereBetween('start_date', [$from, $to])
            ->where(function ($query): void {
                $query->whereNotNull('transport_contractor_id')
                    ->orWhereNotNull('transport_company_name')
                    ->orWhereHas('transportProgramPoints');
            })
            ->tap(fn ($query) => $this->constrainEventQueryByStatuses($query, $filters))
            ->with('transportContractor')
            ->limit(150)
            ->get()
            ->map(function (Event $event): array {
                $label = $event->transportContractor?->name
                    ?? $event->transport_company_name
                    ?? 'Transport';

                return $this->withLinks([
                    'id' => 'transport-'.$event->id,
                    'title' => 'Transport: '.$event->code.' — '.$label,
                    'start' => $event->start_date?->toDateString(),
                    'backgroundColor' => '#4f46e5',
                    'borderColor' => '#4338ca',
                    'type' => 'transport',
                ], [
                    CalendarEventLinks::link(
                        \App\Support\AdminPanelUrls::eventTransport($event),
                        'Transport imprezy',
                        'heroicon-o-truck',
                    ),
                    CalendarEventLinks::event($event->id),
                    CalendarEventLinks::contractor($event->transport_contractor_id),
                ]);
            });
    }

    /** @return Collection<int, array<string, mixed>> */
    protected function hotelStays(Carbon $from, Carbon $to, array $filters = []): Collection
    {
        if (! Schema::hasTable('event_hotel_stays')) {
            return collect();
        }

        return EventHotelStay::query()
            ->with(['event', 'contractor'])
            ->whereHas('event', function ($q) use ($filters): void {
                $q->whereNotNull('start_date');
                $this->constrainEventQueryByStatuses($q, $filters);
            })
            ->limit(300)
            ->get()
            ->map(function (EventHotelStay $stay): ?array {
                $event = $stay->event;
                if (! $event?->start_date) {
                    return null;
                }

                $stayDate = $event->dateForProgramDay((int) $stay->day);
                if (! $stayDate) {
                    return null;
                }

                $hotelName = $stay->contractor?->name ?? 'Hotel';

                return $this->withLinks([
                    'id' => 'hotel-'.$stay->id,
                    'title' => 'Hotel: '.$event->code.' — '.$hotelName.' (dzień '.$stay->day.')',
                    'start' => $stayDate->toDateString(),
                    'backgroundColor' => '#be185d',
                    'borderColor' => '#9d174d',
                    'type' => 'hotels',
                ], [
                    CalendarEventLinks::link(
                        \App\Support\AdminPanelUrls::eventHotelPlanning($event),
                        'Plan hotelu',
                        'heroicon-o-building-office-2',
                    ),
                    CalendarEventLinks::event($event->id),
                    CalendarEventLinks::contractor($stay->contractor_id),
                ]);
            })
            ->filter()
            ->filter(function (array $item) use ($from, $to): bool {
                $start = Carbon::parse($item['start']);

                return $start->between($from, $to);
            })
            ->values();
    }

    /**
     * @param  array<string, mixed>  $event
     * @param  array<int, array{label: string, url: string, icon?: string}|null>  $links
     * @return array<string, mixed>
     */
    protected function withLinks(array $event, array $links): array
    {
        $compactLinks = CalendarEventLinks::compact($links);
        $event['links'] = $compactLinks;

        return $event;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<int, array{label: string, url: string, icon?: string}|null>
     */
    protected function paymentLinks(array $row): array
    {
        $links = [
            CalendarEventLinks::link($row['url'] ?? null, $row['type_label'] ?? 'Płatność', 'heroicon-o-banknotes'),
            CalendarEventLinks::event($row['event_id'] ?? null),
            CalendarEventLinks::contractor($row['contractor_id'] ?? null),
        ];

        $id = (string) ($row['id'] ?? '');

        if (str_starts_with($id, 'contract-')) {
            $contractId = (int) substr($id, strlen('contract-'));
            $links[] = CalendarEventLinks::contract($contractId);
        }

        if (str_starts_with($id, 'vendor-')) {
            $invoiceId = (int) substr($id, strlen('vendor-'));
            $links[] = CalendarEventLinks::vendorInvoice($invoiceId);
        }

        if (str_starts_with($id, 'cost-')) {
            $settlementId = $row['settlement_id'] ?? null;
            if ($settlementId) {
                $links[] = CalendarEventLinks::settlement((int) $settlementId);
            }
        }

        return $links;
    }
}
