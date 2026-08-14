<?php

namespace App\Support\Tasks;

use App\Enums\TaskSource;
use App\Models\Task;
use App\Models\TaskStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class TaskQueryFilters
{
    public const FINISHED_STATUS_NAMES = ['Zakończone', 'Anulowane', 'Zaakceptowane'];

    public const ARCHIVED_STATUS_NAMES = ['Zarchiwizowane'];

    /** @var array<int, int>|null */
    private static ?array $finishedStatusIds = null;

    /** @var array<int, int>|null */
    private static ?array $archivedStatusIds = null;

    /** @return array<int, int> */
    public static function finishedStatusIds(): array
    {
        if (is_array(self::$finishedStatusIds)) {
            return self::$finishedStatusIds;
        }

        self::$finishedStatusIds = TaskStatus::query()
            ->whereIn('name', self::FINISHED_STATUS_NAMES)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        return self::$finishedStatusIds;
    }

    /** @return array<int, int> */
    public static function archivedStatusIds(): array
    {
        if (is_array(self::$archivedStatusIds)) {
            return self::$archivedStatusIds;
        }

        self::$archivedStatusIds = TaskStatus::query()
            ->whereIn('name', self::ARCHIVED_STATUS_NAMES)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        return self::$archivedStatusIds;
    }

    /**
     * Czyści cache ID statusów — wymagane między testami (RefreshDatabase + static).
     */
    public static function clearStatusIdCaches(): void
    {
        self::$finishedStatusIds = null;
        self::$archivedStatusIds = null;
    }

    public static function archivedStatusId(): ?int
    {
        $ids = self::archivedStatusIds();

        return $ids[0] ?? null;
    }

    public static function officeOnly(Builder $query): Builder
    {
        return $query->officeOnly();
    }

    public static function pilotChecklistOnly(Builder $query): Builder
    {
        if (! \Illuminate\Support\Facades\Schema::hasColumn('tasks', 'source')) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('source', TaskSource::PilotChecklist->value);
    }

    public static function mine(Builder $query, ?int $userId = null): Builder
    {
        $userId ??= Auth::id();

        if (! $userId) {
            return $query;
        }

        return $query->where(function (Builder $inner) use ($userId): void {
            $inner->where('author_id', $userId)
                ->orWhere('assignee_id', $userId);
        });
    }

    public static function assignedTo(Builder $query, ?int $userId = null): Builder
    {
        $userId ??= Auth::id();

        if (! $userId) {
            return $query;
        }

        return $query->where('assignee_id', $userId);
    }

    public static function authoredBy(Builder $query, ?int $userId = null): Builder
    {
        $userId ??= Auth::id();

        if (! $userId) {
            return $query;
        }

        return $query->where('author_id', $userId);
    }

    public static function applyOwnershipScope(Builder $query, string $scope, ?int $userId = null): Builder
    {
        return match ($scope) {
            'assigned' => self::assignedTo($query, $userId),
            'authored' => self::authoredBy($query, $userId),
            default => $query,
        };
    }

    public static function topLevelOnly(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    public static function openTodo(Builder $query): Builder
    {
        $statusId = Task::getDefaultStatusId();

        if (! $statusId) {
            return $query;
        }

        return $query->where('status_id', $statusId);
    }

    public static function orderByCreatedAtDesc(Builder $query): Builder
    {
        return $query->orderByDesc('created_at');
    }

    public static function latestActivitySqlExpression(string $tasksTable = 'tasks', ?string $driver = null): string
    {
        $driver ??= Task::query()->getConnection()->getDriverName();

        $commentsSub = "(SELECT MAX(COALESCE(updated_at, created_at)) FROM task_comments WHERE task_id = {$tasksTable}.id)";
        $attachmentsSub = "(SELECT MAX(COALESCE(updated_at, created_at)) FROM task_attachments WHERE task_id = {$tasksTable}.id)";

        // Podzadania mają własną aktywność i sortują się osobno — nie podbijają rodzica.
        $parts = [
            "COALESCE({$tasksTable}.updated_at, {$tasksTable}.created_at, '1970-01-01 00:00:00')",
            "COALESCE({$tasksTable}.created_at, '1970-01-01 00:00:00')",
            "COALESCE({$commentsSub}, '1970-01-01 00:00:00')",
            "COALESCE({$attachmentsSub}, '1970-01-01 00:00:00')",
        ];

        if ($driver === 'sqlite') {
            $union = implode(' UNION ALL ', array_map(
                fn (string $part): string => "SELECT {$part} AS activity_at",
                $parts,
            ));

            return "(SELECT MAX(activity_at) FROM ({$union}))";
        }

        return 'GREATEST('.implode(', ', $parts).')';
    }

    public static function withLatestActivityAtColumn(Builder $query, string $tasksTable = 'tasks'): Builder
    {
        $baseQuery = $query->getQuery();
        $driver = $query->getConnection()->getDriverName();

        if ($baseQuery->columns === null) {
            $query->select("{$tasksTable}.*");
        }

        if (! collect($baseQuery->columns ?? [])->contains(fn ($column): bool => is_string($column) && str_contains($column, 'latest_activity_at'))) {
            $expression = self::latestActivitySqlExpression($tasksTable, $driver);
            $query->selectRaw("({$expression}) as latest_activity_at");
        }

        return $query;
    }

    public static function orderByLatestActivityDesc(Builder $query): Builder
    {
        self::withLatestActivityAtColumn($query);

        return $query->orderByDesc('latest_activity_at');
    }

    public static function orderByHierarchyThenLatestActivityDesc(Builder $query): Builder
    {
        self::withLatestActivityAtColumn($query);

        return $query
            ->orderByRaw('COALESCE(parent_id, id) DESC')
            ->orderByRaw('parent_id IS NOT NULL')
            ->orderByDesc('latest_activity_at');
    }

    public static function orderByHierarchyThenCreatedAtDesc(Builder $query): Builder
    {
        return $query
            ->orderByRaw('COALESCE(parent_id, id) DESC')
            ->orderByRaw('parent_id IS NOT NULL')
            ->orderByDesc('created_at');
    }

    public static function orderByDueDateThenActivity(Builder $query): Builder
    {
        return $query
            ->orderByRaw('due_date IS NULL')
            ->orderBy('due_date')
            ->orderByDesc('updated_at')
            ->orderByDesc('created_at');
    }

    public static function orderByActivity(Builder $query): Builder
    {
        return self::orderByLatestActivityDesc($query);
    }

    public static function orderByManual(Builder $query): Builder
    {
        return $query
            ->orderBy('order')
            ->orderBy('id');
    }

    public static function excludeFinished(Builder $query): Builder
    {
        $finishedStatusIds = self::finishedStatusIds();

        if ($finishedStatusIds === []) {
            return $query;
        }

        return $query->whereNotIn('status_id', $finishedStatusIds);
    }

    public static function excludeArchived(Builder $query): Builder
    {
        $archivedStatusIds = self::archivedStatusIds();

        if ($archivedStatusIds === []) {
            return $query;
        }

        return $query->whereNotIn('status_id', $archivedStatusIds);
    }

    public static function excludeCompleted(Builder $query): Builder
    {
        return self::excludeFinished($query);
    }

    public static function applyDefaultListScopes(Builder $query, bool $officeOnly = true): Builder
    {
        if ($officeOnly) {
            self::officeOnly($query);
        }

        self::excludeArchived($query);

        return $query->with([
            'status',
            'assignee',
            'author',
            'taskable',
            'parent',
            'attachments' => fn ($attachments) => $attachments->orderBy('created_at'),
            'comments' => fn ($comments) => $comments->latest()->limit(1)->with('author'),
        ])->withCount(['attachments', 'comments']);
    }

    public static function changedSince(Builder $query, Carbon $since): Builder
    {
        return $query->where(function (Builder $inner) use ($since): void {
            $inner->where('tasks.created_at', '>', $since)
                ->orWhere('tasks.updated_at', '>', $since)
                ->orWhereHas('comments', fn (Builder $comments) => $comments
                    ->where('task_comments.updated_at', '>', $since))
                ->orWhereHas('attachments', fn (Builder $attachments) => $attachments
                    ->where('task_attachments.updated_at', '>', $since))
                ->orWhereHas('subtasks', fn (Builder $subtasks) => $subtasks
                    ->where('tasks.updated_at', '>', $since));
        });
    }
}
