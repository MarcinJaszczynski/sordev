<?php

declare(strict_types=1);

namespace App\Support\Tasks;

use App\Enums\TaskPriority;
use App\Enums\TaskSource;
use App\Models\Event;
use App\Models\Task;
use App\Models\User;
use App\Services\NotificationService;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Tworzy / aktualizuje jedno wspólne zadanie systemowe na fingerprint
 * (bez fan-outu na każdego użytkownika biura).
 */
final class SystemTaskFactory
{
    public static function resolveAssigneeId(?Event $event = null, ?int $preferredAssigneeId = null): ?int
    {
        if ($preferredAssigneeId) {
            $preferred = User::query()->find($preferredAssigneeId);

            if ($preferred) {
                return (int) $preferred->id;
            }
        }

        if ($event?->assigned_to) {
            $assigned = User::query()->find($event->assigned_to);

            if ($assigned) {
                return (int) $assigned->id;
            }
        }

        $office = OfficeTaskRecipients::users()->first();

        if ($office) {
            return (int) $office->id;
        }

        $authId = Auth::id();

        return $authId ? (int) $authId : null;
    }

    public static function upsertShared(
        Model $taskable,
        string $fingerprint,
        string $title,
        string $description,
        TaskPriority $priority = TaskPriority::Normal,
        ?CarbonInterface $dueDate = null,
        ?Event $eventForAssignee = null,
        ?int $preferredAssigneeId = null,
        ?string $url = null,
        bool $onlyOpenWhenFinding = true,
    ): ?Task {
        $statusId = Task::getDefaultStatusId();

        if (! $statusId) {
            return null;
        }

        $assigneeId = self::resolveAssigneeId($eventForAssignee, $preferredAssigneeId);

        if (! $assigneeId) {
            return null;
        }

        $body = trim($description."\n\n".$fingerprint.($url ? "\n\nLink: ".$url : ''));
        $due = $dueDate ?? now()->addDay();

        $query = Task::query()->where('description', 'like', '%'.$fingerprint.'%');

        if ($onlyOpenWhenFinding) {
            TaskQueryFilters::excludeFinished($query);
        }

        $existing = $query->orderBy('id')->first();

        if ($existing) {
            $existing->update([
                'title' => $title,
                'description' => $body,
                'due_date' => $due,
                'priority' => $priority->value,
                'source' => TaskSource::System->value,
                'taskable_type' => $taskable::class,
                'taskable_id' => $taskable->getKey(),
                'assignee_id' => $existing->assignee_id ?: $assigneeId,
            ]);

            self::clearCachesForStakeholders($existing);

            return $existing->fresh();
        }

        $maxOrder = (int) Task::query()->where('status_id', $statusId)->max('order');

        $task = Task::query()->create([
            'title' => $title,
            'description' => $body,
            'due_date' => $due,
            'status_id' => $statusId,
            'priority' => $priority->value,
            'source' => TaskSource::System->value,
            'author_id' => $assigneeId,
            'assignee_id' => $assigneeId,
            'taskable_type' => $taskable::class,
            'taskable_id' => $taskable->getKey(),
            'order' => $maxOrder + 1,
        ]);

        self::clearCachesForStakeholders($task);

        return $task;
    }

    private static function clearCachesForStakeholders(Task $task): void
    {
        // Wspólne zadanie systemowe — odśwież belkę wszystkim w biurze (odczyt i tak per user).
        NotificationService::clearCacheForOfficeUsers();

        if ($task->assignee_id) {
            NotificationService::clearCacheForUser((int) $task->assignee_id);
        }

        if ($task->author_id && (int) $task->author_id !== (int) $task->assignee_id) {
            NotificationService::clearCacheForUser((int) $task->author_id);
        }
    }
}
