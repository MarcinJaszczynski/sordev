<?php

namespace App\Support\Tasks;

use App\Enums\TaskSource;
use App\Filament\Pilot\Pages\PilotChecklistPage;
use App\Filament\Resources\EventResource;
use App\Filament\Resources\TaskResource;
use App\Models\Event;
use App\Models\EventProgramPoint;
use App\Models\Task;
use Illuminate\Database\Eloquent\Model;

final class TaskNavigation
{
    public static function isPilotChecklist(Task $task): bool
    {
        $source = $task->source instanceof TaskSource
            ? $task->source->value
            : (string) ($task->source ?? TaskSource::Office->value);

        return $source === TaskSource::PilotChecklist->value;
    }

    public static function commentsRelationManagerIndex(): int
    {
        return 0;
    }

    public static function editUrl(Task|int|null $task): string
    {
        if (! $task instanceof Task) {
            $task = $task ? Task::query()->find($task) : null;
        }

        if (! $task) {
            return TaskResource::getUrl('index');
        }

        return TaskResource::getUrl('edit', ['record' => $task->getKey()]);
    }

    public static function fullViewUrl(Task|int|null $task, ?int $activeRelationManager = null): string
    {
        if (! $task instanceof Task) {
            $task = $task ? Task::query()->find($task) : null;
        }

        if (! $task) {
            return TaskResource::getUrl('index');
        }

        $event = self::resolveEvent($task);

        $baseUrl = $event
            ? EventResource::getUrl('tasks', ['record' => $event->getKey()])
            : TaskResource::getUrl('index');

        $query = array_filter([
            'editTask' => $task->getKey(),
            'activeRelationManager' => $activeRelationManager,
        ], fn ($value): bool => $value !== null);

        if ($query === []) {
            return $baseUrl;
        }

        return $baseUrl.'?'.http_build_query($query);
    }

    public static function pilotWorkUrl(Task $task): ?string
    {
        if (! self::isPilotChecklist($task)) {
            return null;
        }

        $event = self::resolveEvent($task);

        return $event ? PilotChecklistPage::urlFor($event) : null;
    }

    public static function resolveEvent(Task $task): ?Event
    {
        $contextTask = TaskContextRegistry::resolveEffectiveContextTask($task);

        if (! $contextTask) {
            return null;
        }

        $contextTask->loadMissing('taskable');
        $record = $contextTask->taskable;

        return match (true) {
            $record instanceof Event => $record,
            $record instanceof EventProgramPoint => $record->event,
            default => self::resolveEventFromSettlementRecord($record),
        };
    }

    private static function resolveEventFromSettlementRecord(?Model $record): ?Event
    {
        if (! $record || ! method_exists($record, 'settlement')) {
            return null;
        }

        $record->loadMissing('settlement.event');

        return $record->settlement?->event;
    }
}
