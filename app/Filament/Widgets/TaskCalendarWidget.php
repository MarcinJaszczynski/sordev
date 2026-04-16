<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\TaskResource;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class TaskCalendarWidget extends Widget
{
    protected static string $view = 'filament.widgets.task-calendar-widget';

    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = -5;

    protected static bool $isLazy = false;

    public string $search = '';

    public string $statusId = '';

    public string $assigneeId = '';

    public string $dateFrom = '';

    public string $dateTo = '';

    public bool $onlyMine = false;

    public function resetFilters(): void
    {
        $this->search = '';
        $this->statusId = '';
        $this->assigneeId = '';
        $this->dateFrom = '';
        $this->dateTo = '';
        $this->onlyMine = false;
    }

    protected function getViewData(): array
    {
        $query = Task::query()
            ->with(['status', 'assignee'])
            ->whereNotNull('due_date');

        if ($this->search !== '') {
            $query->where(function (Builder $builder): void {
                $builder->where('title', 'like', '%' . $this->search . '%')
                    ->orWhere('description', 'like', '%' . $this->search . '%');
            });
        }

        if ($this->statusId !== '') {
            $query->where('status_id', (int) $this->statusId);
        }

        if ($this->assigneeId !== '') {
            $query->where('assignee_id', (int) $this->assigneeId);
        }

        if ($this->onlyMine && Auth::check()) {
            $query->where('assignee_id', Auth::id());
        }

        if ($this->dateFrom !== '') {
            $query->whereDate('due_date', '>=', $this->dateFrom);
        }

        if ($this->dateTo !== '') {
            $query->whereDate('due_date', '<=', $this->dateTo);
        }

        $tasks = $query
            ->orderBy('due_date')
            ->limit(300)
            ->get();

        $calendarEvents = $tasks->map(function (Task $task): array {
            return [
                'id' => (string) $task->id,
                'title' => trim($task->title . ' [' . ($task->status?->name ?? 'Brak statusu') . ']'),
                'start' => optional($task->due_date)->toIso8601String(),
                'url' => TaskResource::getUrl('edit', ['record' => $task]),
                'backgroundColor' => match ($task->priority) {
                    'high' => '#dc2626',
                    'medium' => '#d97706',
                    'low' => '#16a34a',
                    default => '#2563eb',
                },
                'borderColor' => match ($task->priority) {
                    'high' => '#991b1b',
                    'medium' => '#92400e',
                    'low' => '#166534',
                    default => '#1d4ed8',
                },
                'textColor' => '#ffffff',
            ];
        })->values()->all();

        return [
            'calendarEvents' => $calendarEvents,
            'eventsCount' => count($calendarEvents),
            'statusOptions' => TaskStatus::query()->orderBy('order')->pluck('name', 'id')->all(),
            'assigneeOptions' => User::query()->orderBy('name')->pluck('name', 'id')->all(),
        ];
    }
}
