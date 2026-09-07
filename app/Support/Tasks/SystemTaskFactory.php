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
use Illuminate\Support\Collection;

/**
 * Tworzy / aktualizuje osobne zadanie systemowe dla każdego użytkownika biura.
 *
 * Dokończenie kopii jednej osoby nie zamyka zadania pozostałym.
 * Stare wspólne kopie (assignee_id = null) są przejmowane przy pierwszym upsertcie.
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

        $caretakerId = $event?->office_caretaker_id;
        if ($caretakerId) {
            $caretaker = User::query()->find($caretakerId);

            if ($caretaker) {
                return (int) $caretaker->id;
            }
        }

        return null;
    }

    /**
     * @return Collection<int, Task>
     */
    public static function upsertForOfficeUsers(
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
    ): Collection {
        if (! SystemTaskPolicy::allowsFingerprint($fingerprint)) {
            return collect();
        }

        $statusId = Task::getDefaultStatusId();

        if (! $statusId) {
            return collect();
        }

        $recipients = self::resolveRecipients($eventForAssignee, $preferredAssigneeId);

        if ($recipients->isEmpty()) {
            return collect();
        }

        $body = trim($description."\n\n".$fingerprint.($url ? "\n\nLink: ".$url : ''));
        $due = $dueDate ?? TaskDueDates::defaultForNew();
        $created = collect();

        foreach ($recipients as $recipient) {
            $task = self::upsertForAssignee(
                taskable: $taskable,
                fingerprint: $fingerprint,
                title: $title,
                body: $body,
                due: $due,
                priority: $priority,
                statusId: $statusId,
                assigneeId: (int) $recipient->id,
                onlyOpenWhenFinding: $onlyOpenWhenFinding,
            );

            if ($task) {
                $created->push($task);
            }
        }

        if ($created->isNotEmpty()) {
            NotificationService::clearCacheForOfficeUsers();
        }

        return $created;
    }

    /**
     * Backward-compatible wrapper — zwraca pierwszą kopię (np. do asercji).
     */
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
        return self::upsertForOfficeUsers(
            taskable: $taskable,
            fingerprint: $fingerprint,
            title: $title,
            description: $description,
            priority: $priority,
            dueDate: $dueDate,
            eventForAssignee: $eventForAssignee,
            preferredAssigneeId: $preferredAssigneeId,
            url: $url,
            onlyOpenWhenFinding: $onlyOpenWhenFinding,
        )->first();
    }

    /**
     * @return Collection<int, User>
     */
    private static function resolveRecipients(?Event $event, ?int $preferredAssigneeId): Collection
    {
        $recipients = OfficeTaskRecipients::users()->keyBy('id');

        if ($preferredAssigneeId) {
            $preferred = User::query()->find($preferredAssigneeId);
            if ($preferred && ! $recipients->has($preferred->id)) {
                $recipients->put($preferred->id, $preferred);
            }
        }

        $caretakerId = $event?->office_caretaker_id;
        if ($caretakerId) {
            $caretaker = User::query()->find($caretakerId);
            if ($caretaker && ! $recipients->has($caretaker->id)) {
                $recipients->put($caretaker->id, $caretaker);
            }
        }

        return $recipients->sortBy('id')->values();
    }

    private static function upsertForAssignee(
        Model $taskable,
        string $fingerprint,
        string $title,
        string $body,
        CarbonInterface $due,
        TaskPriority $priority,
        int $statusId,
        int $assigneeId,
        bool $onlyOpenWhenFinding,
    ): ?Task {
        $existing = self::findExistingCopy($fingerprint, $assigneeId, $onlyOpenWhenFinding);

        if ($existing) {
            $existing->update([
                'title' => $title,
                'description' => $body,
                'due_date' => $due,
                'priority' => $priority->value,
                'source' => TaskSource::System->value,
                'taskable_type' => $taskable::class,
                'taskable_id' => $taskable->getKey(),
                'assignee_id' => $assigneeId,
            ]);

            return $existing->fresh();
        }

        $maxOrder = (int) Task::query()->where('status_id', $statusId)->max('order');

        return Task::query()->create([
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
    }

    private static function findExistingCopy(string $fingerprint, int $assigneeId, bool $onlyOpen): ?Task
    {
        $owned = Task::query()
            ->where('source', TaskSource::System->value)
            ->where('description', 'like', '%'.$fingerprint.'%')
            ->where('assignee_id', $assigneeId);

        if ($onlyOpen) {
            TaskQueryFilters::excludeFinished($owned);
        }

        $existing = $owned->orderBy('id')->first();

        if ($existing) {
            return $existing;
        }

        // Stara wspólna kopia bez assignee — przejmij jedną na pierwszego odbiorcę.
        $orphan = Task::query()
            ->where('source', TaskSource::System->value)
            ->where('description', 'like', '%'.$fingerprint.'%')
            ->whereNull('assignee_id');

        if ($onlyOpen) {
            TaskQueryFilters::excludeFinished($orphan);
        }

        return $orphan->orderBy('id')->first();
    }

    /**
     * Dla otwartych, dozwolonych zadań systemowych — dopina brakujące kopie biura.
     * Istniejące wspólne (assignee = null) są przejmowane, nie duplikowane.
     */
    public static function expandOpenForOfficeUsers(): int
    {
        $recipients = OfficeTaskRecipients::users()->sortBy('id')->values();

        if ($recipients->isEmpty()) {
            return 0;
        }

        $query = Task::query()->where('source', TaskSource::System->value);
        TaskQueryFilters::excludeFinished($query);
        TaskQueryFilters::excludeArchived($query);
        SystemTaskPolicy::constrainAllowedSystem($query);

        $groups = $query
            ->orderBy('id')
            ->get()
            ->groupBy(fn (Task $task): string => self::fingerprintFromDescription((string) $task->description) ?? 'task:'.$task->id);

        $created = 0;

        foreach ($groups as $fingerprint => $copies) {
            if (! is_string($fingerprint) || $fingerprint === '' || str_starts_with($fingerprint, 'task:')) {
                continue;
            }

            $template = $copies->first();
            if (! $template instanceof Task) {
                continue;
            }

            $ownedIds = $copies
                ->pluck('assignee_id')
                ->filter()
                ->map(fn ($id): int => (int) $id)
                ->all();

            $orphan = $copies->first(fn (Task $task): bool => $task->assignee_id === null);

            foreach ($recipients as $recipient) {
                $recipientId = (int) $recipient->id;

                if (in_array($recipientId, $ownedIds, true)) {
                    continue;
                }

                if ($orphan instanceof Task) {
                    $orphan->update([
                        'assignee_id' => $recipientId,
                        'author_id' => $orphan->author_id ?: $recipientId,
                    ]);
                    $ownedIds[] = $recipientId;
                    $orphan = null;

                    continue;
                }

                $statusId = (int) ($template->status_id ?: Task::getDefaultStatusId());
                $maxOrder = (int) Task::query()->where('status_id', $statusId)->max('order');

                Task::query()->create([
                    'title' => $template->title,
                    'description' => $template->description,
                    'due_date' => $template->due_date,
                    'status_id' => $statusId,
                    'priority' => $template->priority,
                    'source' => TaskSource::System->value,
                    'author_id' => $recipientId,
                    'assignee_id' => $recipientId,
                    'taskable_type' => $template->taskable_type,
                    'taskable_id' => $template->taskable_id,
                    'order' => $maxOrder + 1,
                ]);

                $ownedIds[] = $recipientId;
                $created++;
            }
        }

        if ($created > 0) {
            NotificationService::clearCacheForOfficeUsers();
        }

        return $created;
    }

    public static function fingerprintFromDescription(string $description): ?string
    {
        if (preg_match('/\b(event-status:\d+:[a-z0-9_-]+)\b/i', $description, $matches)) {
            return $matches[1];
        }

        if (preg_match('/\b(event-inquiry:\d+)\b/', $description, $matches)) {
            return $matches[1];
        }

        if (preg_match('/\b(event-participant-count:\d+:\d+:\d+)\b/', $description, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
