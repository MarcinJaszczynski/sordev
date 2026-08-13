<?php

namespace App\Support\Tasks;

use App\Enums\TaskSource;
use App\Filament\Pilot\Pages\PilotChecklistPage;
use App\Filament\Resources\ContractorResource;
use App\Filament\Resources\EventResource;
use App\Filament\Resources\EventTemplateProgramPointResource;
use App\Filament\Resources\EventTemplateResource;
use App\Filament\Resources\TaskResource;
use App\Models\Contractor;
use App\Models\Event;
use App\Models\EventDocument;
use App\Models\EventProgramPoint;
use App\Models\EventSettlementCost;
use App\Models\EventSettlementDocument;
use App\Models\EventSettlementParticipantPayment;
use App\Models\EventTemplate;
use App\Models\EventTemplateProgramPoint;
use App\Models\PilotCashPreparation;
use App\Models\Reservation;
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
        return self::fullViewUrl($task);
    }

    public static function createUrl(?string $taskableType = null, ?int $taskableId = null, ?string $dueDate = null): string
    {
        $baseUrl = self::listUrlForTaskable($taskableType, $taskableId);

        $query = array_filter([
            'createTask' => 1,
            'taskable_type' => $taskableType,
            'taskable_id' => $taskableId,
            'dueDate' => $dueDate,
        ], fn ($value): bool => $value !== null && $value !== '');

        return $baseUrl.'?'.http_build_query($query);
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
            $record instanceof Reservation => self::loadReservationEvent($record),
            default => self::resolveEventFromSettlementRecord($record),
        };
    }

    public static function listUrlForTaskable(?string $taskableType, ?int $taskableId): string
    {
        if (! filled($taskableType) || ! filled($taskableId)) {
            return TaskResource::getUrl('index');
        }

        if ($event = self::resolveEventFromTaskable($taskableType, (int) $taskableId)) {
            return EventResource::getUrl('tasks', ['record' => $event->getKey()]);
        }

        return match ($taskableType) {
            Contractor::class => ContractorResource::getUrl('edit', ['record' => $taskableId]),
            EventTemplate::class => EventTemplateResource::getUrl('edit', ['record' => $taskableId]),
            EventTemplateProgramPoint::class => EventTemplateProgramPointResource::getUrl('edit', ['record' => $taskableId]),
            default => TaskResource::getUrl('index'),
        };
    }

    public static function resolveEventFromTaskable(?string $taskableType, ?int $taskableId): ?Event
    {
        if (! filled($taskableType) || ! filled($taskableId)) {
            return null;
        }

        /** @var Model|null $record */
        $record = $taskableType::query()->find($taskableId);

        if (! $record) {
            return null;
        }

        return match (true) {
            $record instanceof Event => $record,
            $record instanceof EventProgramPoint => $record->event,
            $record instanceof EventDocument => $record->event,
            $record instanceof Reservation => self::loadReservationEvent($record),
            default => self::resolveEventFromSettlementRecord($record),
        };
    }

    private static function loadReservationEvent(Reservation $reservation): ?Event
    {
        $reservation->loadMissing('event');

        return $reservation->event;
    }

    private static function resolveEventFromSettlementRecord(?Model $record): ?Event
    {
        if (! $record) {
            return null;
        }

        if ($record instanceof EventSettlementCost
            || $record instanceof EventSettlementDocument
            || $record instanceof EventSettlementParticipantPayment
            || $record instanceof PilotCashPreparation) {
            $record->loadMissing('settlement.event');

            return $record->settlement?->event;
        }

        if (method_exists($record, 'settlement')) {
            $record->loadMissing('settlement.event');

            return $record->settlement?->event;
        }

        return null;
    }
}
