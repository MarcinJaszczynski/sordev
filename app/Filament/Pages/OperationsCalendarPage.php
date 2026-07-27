<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\InteractsWithTaskEditModal;
use App\Filament\Concerns\InteractsWithTaskOwnershipScope;
use App\Models\Task;
use App\Services\CalendarEventAggregator;
use App\Support\FilamentNavigation;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Livewire\Attributes\Computed;

class OperationsCalendarPage extends Page
{
    use InteractsWithTaskEditModal;
    use InteractsWithTaskOwnershipScope;
    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static string $view = 'filament.pages.operations-calendar';

    protected static ?string $navigationGroup = FilamentNavigation::GROUP_OPERATIONS;

    protected static ?string $navigationLabel = 'Kalendarz';

    protected static ?int $navigationSort = 1;

    public array $enabledTypes = ['events', 'tasks', 'ksef', 'payments', 'pilots', 'reservations', 'transport', 'hotels'];

    public bool $showFinishedTasks = false;

    public bool $tasksOnlyUrgent = false;

    public string $viewMode = 'dayGridMonth';

    public ?string $clickedDate = null;

    /** @var array<string, mixed>|null */
    public ?array $selectedCalendarEntry = null;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user && $user->hasRole(['admin', 'super_admin', 'ksiegowosc', 'biuro']);
    }

    public function getTitle(): string
    {
        return 'Kalendarz';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('createTask')
                ->label('Dodaj zadanie')
                ->icon('heroicon-m-plus')
                ->action(fn () => $this->mountAction('createTask')),
            Action::make('calendarEntryContext')
                ->modalHeading(fn (): string => (string) ($this->selectedCalendarEntry['title'] ?? 'Wpis kalendarza'))
                ->modalContent(fn (): \Illuminate\Contracts\View\View => view(
                    'filament.pages.partials.calendar-entry-links',
                    ['links' => $this->selectedCalendarEntry['links'] ?? []],
                ))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Zamknij')
                ->closeModalByClickingAway(true)
                ->action(function (): void {
                    $this->selectedCalendarEntry = null;
                }),
        ];
    }

    public function openCreateTaskModal(string $date): void
    {
        $this->clickedDate = $date;
        $this->mountAction('createTask');
    }

    protected function createTaskDefaultDueDate(): mixed
    {
        return $this->clickedDate
            ? \Carbon\Carbon::parse($this->clickedDate)->startOfDay()
            : $this->pendingCreateDueDate;
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

    public function resetTaskFilters(): void
    {
        $this->tasksScope = 'all';
        $this->tasksOnlyUrgent = false;
        $this->showFinishedTasks = false;

        if (! in_array('tasks', $this->enabledTypes, true)) {
            $this->enabledTypes[] = 'tasks';
        }

        unset($this->calendarEvents);
    }

    public function hasActiveTaskFilters(): bool
    {
        return $this->tasksScope !== 'assigned'
            || $this->tasksOnlyUrgent
            || $this->showFinishedTasks
            || ! in_array('tasks', $this->enabledTypes, true);
    }

    protected function afterTaskModalSaved(Task $task): void
    {
        unset($this->calendarEvents);
    }

    #[Computed]
    public function calendarEvents(): array
    {
        return app(CalendarEventAggregator::class)
            ->events([
                'types' => $this->enabledTypes,
                'show_finished_tasks' => $this->showFinishedTasks,
                'tasks_scope' => $this->tasksScope,
                'tasks_only_urgent' => $this->tasksOnlyUrgent,
                'user_id' => auth()->id(),
            ])
            ->all();
    }

    public function toggleType(string $type): void
    {
        if (in_array($type, $this->enabledTypes, true)) {
            $this->enabledTypes = array_values(array_filter(
                $this->enabledTypes,
                fn (string $item): bool => $item !== $type
            ));
        } else {
            $this->enabledTypes[] = $type;
        }

        unset($this->calendarEvents);
    }

    protected function afterTasksScopeChanged(): void
    {
        unset($this->calendarEvents);
    }

    public function updatedShowFinishedTasks(): void
    {
        unset($this->calendarEvents);
    }

    public function updatedTasksOnlyUrgent(): void
    {
        unset($this->calendarEvents);
    }
}
