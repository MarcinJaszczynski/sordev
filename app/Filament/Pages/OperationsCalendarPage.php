<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\InteractsWithTaskEditModal;
use App\Filament\Concerns\InteractsWithTaskOwnershipScope;
use App\Models\Event;
use App\Models\Task;
use App\Services\CalendarEventAggregator;
use App\Support\FilamentNavigation;
use App\Support\Tasks\TaskDueDates;
use App\Support\UserUiPreferences;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Livewire\Attributes\Computed;

class OperationsCalendarPage extends Page
{
    use InteractsWithTaskEditModal;
    use InteractsWithTaskOwnershipScope;

    private const SESSION_KEY = 'operations_calendar.filters';

    /** @var list<string> */
    private const AVAILABLE_TYPES = [
        'events',
        'tasks',
        'insurances',
        'ksef',
        'payments',
        'pilots',
        'reservations',
        'transport',
        'hotels',
    ];

    /** @var list<string> */
    private const DEFAULT_ENABLED_TYPES = [
        'events',
        'tasks',
        'insurances',
        'ksef',
        'payments',
        'pilots',
        'reservations',
        'transport',
        'hotels',
    ];

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static string $view = 'filament.pages.operations-calendar';

    protected static ?string $navigationGroup = FilamentNavigation::GROUP_OPERATIONS;

    protected static ?string $navigationLabel = 'Kalendarz';

    protected static ?int $navigationSort = 1;

    public array $enabledTypes = self::DEFAULT_ENABLED_TYPES;

    /** @var list<string> */
    public array $eventStatuses = [];

    /** Ukrywaj aktywności powiązane z imprezami spoza filtra statusów. */
    public bool $hideLinkedToFilteredEvents = true;

    public string $viewMode = 'dayGridMonth';

    public string $layoutMode = 'calendar';

    public ?string $clickedDate = null;

    /** @var array<string, mixed>|null */
    public ?array $selectedCalendarEntry = null;

    public ?string $visibleFrom = null;

    public ?string $visibleTo = null;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user && $user->hasRole(['admin', 'super_admin', 'ksiegowosc', 'biuro']);
    }

    public function mount(): void
    {
        // Kalendarz operacyjny: domyślnie imprezy confirmed-like; sesja nadpisuje ostatni układ filtrów.
        $this->eventStatuses = Event::getConfirmedLikeStatuses();
        $this->tasksScope = $this->defaultTasksScope();
        $this->restoreFiltersFromSession();
    }

    /** @return list<string> */
    public static function defaultEventStatuses(): array
    {
        return Event::getConfirmedLikeStatuses();
    }

    protected function defaultTasksScope(): string
    {
        return 'all';
    }

    public function getTitle(): string
    {
        return 'Kalendarz';
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->makeCreateTaskAction(
                defaultDueDate: fn (): mixed => $this->createTaskDefaultDueDate(),
                defaultFormData: fn (): array => array_merge(
                    $this->createTaskDefaultFormData(),
                    $this->pendingCreateFormData,
                ),
            )->tooltip('Utwórz zadanie z terminem na wybrany dzień kalendarza.'),
        ];
    }

    /**
     * Modal po kliknięciu wpisu kalendarza — skróty do powiązanych ekranów.
     * Zarejestrowany jako metoda Action (nie w nagłówku), żeby nie pokazywać angielskiego przycisku.
     */
    public function calendarEntryContextAction(): Action
    {
        return Action::make('calendarEntryContext')
            ->label('Powiązane miejsca')
            ->modalHeading(fn (): string => (string) ($this->selectedCalendarEntry['title'] ?? 'Wpis kalendarza'))
            ->modalDescription('Skróty do ekranów powiązanych z tym wpisem (impreza, finanse, dokumenty itd.).')
            ->modalContent(fn (): \Illuminate\Contracts\View\View => view(
                'filament.pages.partials.calendar-entry-links',
                ['links' => $this->selectedCalendarEntry['links'] ?? []],
            ))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Zamknij')
            ->closeModalByClickingAway(true)
            ->action(function (): void {
                $this->selectedCalendarEntry = null;
            });
    }

    public function openCreateTaskModal(string $date): void
    {
        $this->clickedDate = $date;
        $this->mountAction('createTask');
    }

    protected function createTaskDefaultDueDate(): mixed
    {
        if ($this->clickedDate) {
            return TaskDueDates::defaultForNew(Carbon::parse($this->clickedDate));
        }

        return $this->pendingCreateDueDate ?? TaskDueDates::defaultForNew();
    }

    public function openCalendarEntry(string $entryId): void
    {
        $entry = collect($this->calendarEvents)->first(
            fn (array $item): bool => (string) ($item['id'] ?? '') === $entryId
        );

        if (! is_array($entry)) {
            return;
        }

        if (($entry['type'] ?? '') === 'tasks' && preg_match('/^task-(\d+)$/', $entryId, $matches)) {
            $this->openEditTaskModal((int) $matches[1]);

            return;
        }

        $links = array_values($entry['links'] ?? []);

        if ($links === [] && filled($entry['url'] ?? null)) {
            $links = [[
                'label' => 'Otwórz',
                'url' => $entry['url'],
                'icon' => 'heroicon-o-arrow-top-right-on-square',
            ]];
        }

        $this->selectedCalendarEntry = array_merge($entry, ['links' => $links]);
        $this->mountAction('calendarEntryContext');
    }

    public function setVisibleRange(?string $from, ?string $to): void
    {
        $normalizedFrom = filled($from) ? Carbon::parse($from)->toDateString() : null;
        $normalizedTo = filled($to) ? Carbon::parse($to)->toDateString() : null;

        if ($this->visibleFrom === $normalizedFrom && $this->visibleTo === $normalizedTo) {
            return;
        }

        $this->visibleFrom = $normalizedFrom;
        $this->visibleTo = $normalizedTo;
        $this->persistFilters();
        $this->invalidateCalendarEvents();
    }

    public function resetTaskFilters(): void
    {
        $this->tasksScope = $this->defaultTasksScope();
        $this->tasksOnlyUrgent = false;
        $this->showFinishedTasks = false;
        $this->dueFilter = '';

        if (! in_array('tasks', $this->enabledTypes, true)) {
            $this->enabledTypes[] = 'tasks';
        }

        $this->persistFilters();
        $this->invalidateCalendarEvents();
    }

    public function hasActiveTaskFilters(): bool
    {
        return $this->tasksScope !== $this->defaultTasksScope()
            || $this->tasksOnlyUrgent
            || $this->showFinishedTasks
            || $this->dueFilter !== ''
            || ! in_array('tasks', $this->enabledTypes, true);
    }

    protected function afterTaskModalSaved(Task $task): void
    {
        $this->invalidateCalendarEvents();
    }

    #[Computed]
    public function calendarEvents(): array
    {
        return app(CalendarEventAggregator::class)
            ->events([
                'types' => $this->enabledTypes,
                'from' => $this->resolveRangeFrom()->toDateString(),
                'to' => $this->resolveRangeTo()->toDateString(),
                'event_statuses' => $this->eventStatuses,
                'hide_linked_to_filtered_events' => $this->hideLinkedToFilteredEvents,
                'show_finished_tasks' => $this->showFinishedTasks,
                'tasks_scope' => $this->tasksScope,
                'tasks_only_urgent' => $this->tasksOnlyUrgent,
                'tasks_due_filter' => $this->dueFilter,
                'user_id' => auth()->id(),
            ])
            ->all();
    }

    #[Computed]
    public function resourceTimeline(): array
    {
        return app(CalendarEventAggregator::class)->resourceTimeline([
            'from' => $this->resolveRangeFrom()->toDateString(),
            'to' => $this->resolveRangeTo()->toDateString(),
            'event_statuses' => $this->eventStatuses,
        ]);
    }

    public function setLayoutMode(string $mode): void
    {
        $this->layoutMode = in_array($mode, ['calendar', 'resources'], true) ? $mode : 'calendar';
        $this->persistFilters();
        unset($this->resourceTimeline);
    }

    #[Computed]
    public function initialCalendarDate(): string
    {
        $today = now()->startOfDay();
        $starts = collect($this->calendarEvents)
            ->pluck('start')
            ->filter()
            ->map(fn (mixed $date): Carbon => Carbon::parse((string) $date)->startOfDay())
            ->sortBy(fn (Carbon $date): int => $date->timestamp)
            ->values();

        if ($starts->isEmpty()) {
            return $today->toDateString();
        }

        $inCurrentMonth = $starts->contains(
            fn (Carbon $date): bool => $date->isSameMonth($today)
        );

        if ($inCurrentMonth) {
            return $today->toDateString();
        }

        $nearestPast = $starts
            ->filter(fn (Carbon $date): bool => $date->lte($today))
            ->last();

        return ($nearestPast ?? $starts->first())->toDateString();
    }

    public function toggleType(string $type): void
    {
        if (! in_array($type, self::AVAILABLE_TYPES, true)) {
            return;
        }

        if (in_array($type, $this->enabledTypes, true)) {
            $this->enabledTypes = array_values(array_filter(
                $this->enabledTypes,
                fn (string $item): bool => $item !== $type
            ));
        } else {
            $this->enabledTypes[] = $type;
        }

        $this->persistFilters();
        $this->invalidateCalendarEvents();
    }

    public function toggleEventStatus(string $status): void
    {
        $allowed = array_keys(Event::getStatusOptions());

        if (! in_array($status, $allowed, true)) {
            return;
        }

        if (in_array($status, $this->eventStatuses, true)) {
            $this->eventStatuses = array_values(array_filter(
                $this->eventStatuses,
                fn (string $item): bool => $item !== $status
            ));
        } else {
            $this->eventStatuses[] = $status;
        }

        $this->persistFilters();
        $this->invalidateCalendarEvents();
    }

    public function resetEventStatuses(): void
    {
        $this->eventStatuses = self::defaultEventStatuses();
        $this->hideLinkedToFilteredEvents = true;
        $this->persistFilters();
        $this->invalidateCalendarEvents();
    }

    public function hasActiveEventStatusFilters(): bool
    {
        return collect($this->eventStatuses)->sort()->values()->all()
            !== collect(self::defaultEventStatuses())->sort()->values()->all()
            || ! $this->hideLinkedToFilteredEvents;
    }

    public function toggleHideLinkedToFilteredEvents(): void
    {
        $this->hideLinkedToFilteredEvents = ! $this->hideLinkedToFilteredEvents;
        $this->persistFilters();
        $this->invalidateCalendarEvents();
    }

    protected function afterTasksScopeChanged(): void
    {
        $this->persistFilters();
        $this->invalidateCalendarEvents();
    }

    protected function invalidateCalendarEvents(): void
    {
        unset($this->calendarEvents);
        unset($this->initialCalendarDate);
        unset($this->resourceTimeline);
        $this->dispatch('operations-calendar-refresh');
    }

    protected function resolveRangeFrom(): Carbon
    {
        if (filled($this->visibleFrom)) {
            return Carbon::parse($this->visibleFrom)->subMonthNoOverflow()->startOfDay();
        }

        return now()->subMonths(6)->startOfMonth();
    }

    protected function resolveRangeTo(): Carbon
    {
        if (filled($this->visibleTo)) {
            return Carbon::parse($this->visibleTo)->addMonthNoOverflow()->endOfDay();
        }

        return now()->addMonths(12)->endOfMonth();
    }

    protected function filtersSessionKey(): string
    {
        return self::SESSION_KEY.'.'.(auth()->id() ?? 'guest');
    }

    protected function restoreFiltersFromSession(): void
    {
        $saved = session($this->filtersSessionKey(), []);

        if (! is_array($saved) || $saved === []) {
            $saved = UserUiPreferences::get('calendar_filters');
        }

        if (! is_array($saved) || $saved === []) {
            return;
        }

        if (isset($saved['enabledTypes']) && is_array($saved['enabledTypes'])) {
            $this->enabledTypes = array_values(array_filter(
                $saved['enabledTypes'],
                fn (mixed $type): bool => is_string($type) && in_array($type, self::AVAILABLE_TYPES, true),
            ));

            if ($this->enabledTypes === []) {
                $this->enabledTypes = self::DEFAULT_ENABLED_TYPES;
            }
        }

        if (array_key_exists('eventStatuses', $saved) && is_array($saved['eventStatuses'])) {
            $allowedStatuses = array_keys(Event::getStatusOptions());
            $this->eventStatuses = array_values(array_filter(
                $saved['eventStatuses'],
                fn (mixed $status): bool => is_string($status) && in_array($status, $allowedStatuses, true),
            ));
        }

        if (array_key_exists('hideLinkedToFilteredEvents', $saved)) {
            $this->hideLinkedToFilteredEvents = (bool) $saved['hideLinkedToFilteredEvents'];
        }

        $this->applyRestoredTaskQuickFilters($saved);

        if (isset($saved['layoutMode']) && in_array($saved['layoutMode'], ['calendar', 'resources'], true)) {
            $this->layoutMode = $saved['layoutMode'];
        }
    }

    protected function persistFilters(): void
    {
        $payload = [
            'enabledTypes' => array_values($this->enabledTypes),
            'eventStatuses' => array_values($this->eventStatuses),
            'hideLinkedToFilteredEvents' => $this->hideLinkedToFilteredEvents,
            ...$this->taskQuickFiltersState(),
            'layoutMode' => $this->layoutMode,
        ];

        session([$this->filtersSessionKey() => $payload]);
        UserUiPreferences::put('calendar_filters', $payload);
    }
}
