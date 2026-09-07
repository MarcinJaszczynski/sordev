<?php

namespace App\Support\Tasks;

use App\Enums\TaskPriority;
use App\Enums\TaskSource;
use App\Models\Task;
use App\Models\TaskStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class TaskQueryFilters
{
    public const FINISHED_STATUS_NAMES = ['Zakończone', 'Anulowane', 'Zaakceptowane'];

    /** @var list<string> */
    public const OWNERSHIP_SCOPES = ['assigned', 'mine', 'for_me', 'authored', 'all'];

    /** @var list<string> */
    public const DUE_FILTERS = ['overdue', 'today', 'this_week', 'has_due_date', 'no_due_date'];

    /** @var list<string> */
    public const SOURCE_FILTERS = ['office', 'system', 'all'];

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

        // Systemowe kopie per user mają author_id = assignee (technicznie) —
        // to nie są zadania „zlecone przeze mnie”.
        return $query
            ->where('author_id', $userId)
            ->where(function (Builder $inner): void {
                $inner->whereNull('source')
                    ->orWhere('source', '!=', TaskSource::System->value);
            });
    }

    public static function applyOwnershipScope(Builder $query, string $scope, ?int $userId = null): Builder
    {
        return match ($scope) {
            // „Moje” — jak topbar: autor ∪ assignee (systemowe mają osobną kopię per user).
            'assigned', 'mine' => self::inboxFor($query, $userId),
            // „Dla mnie” — tylko assignee.
            'for_me' => self::assignedTo($query, $userId),
            'authored' => self::authoredBy($query, $userId),
            default => $query,
        };
    }

    public static function applyUrgentOnly(Builder $query, bool $only): Builder
    {
        if (! $only) {
            return $query;
        }

        return $query->where('priority', TaskPriority::Urgent->value);
    }

    public static function applyDueFilter(Builder $query, string $filter): Builder
    {
        return match ($filter) {
            'overdue' => $query->whereNotNull('due_date')->where('due_date', '<', now()),
            'today' => $query->whereNotNull('due_date')->whereDate('due_date', now()->toDateString()),
            'this_week' => $query
                ->whereNotNull('due_date')
                ->whereBetween('due_date', [
                    now()->startOfWeek(Carbon::MONDAY)->startOfDay(),
                    now()->endOfWeek(Carbon::SUNDAY)->endOfDay(),
                ]),
            'has_due_date' => $query->whereNotNull('due_date'),
            'no_due_date' => $query->whereNull('due_date'),
            default => $query,
        };
    }

    public static function applySourceFilter(Builder $query, string $source): Builder
    {
        if ($source === TaskSource::Office->value || $source === TaskSource::System->value) {
            return $query->where('source', $source);
        }

        return $query;
    }

    /**
     * Wspólny zestaw szybkich filtrów (lista / kanban / impreza / kalendarz).
     */
    public static function applyQuickFilters(
        Builder $query,
        string $scope,
        bool $onlyUrgent = false,
        string $dueFilter = '',
        string $sourceFilter = 'all',
        bool $showFinished = true,
        ?int $userId = null,
    ): Builder {
        self::applyOwnershipScope($query, $scope, $userId);
        self::applyUrgentOnly($query, $onlyUrgent);
        self::applyDueFilter($query, $dueFilter);
        self::applySourceFilter($query, $sourceFilter);

        if (! $showFinished) {
            self::excludeFinished($query);
        }

        return $query;
    }

    /**
     * Skrzynka jak topbar: autor ∪ assignee.
     * Zadania systemowe są per użytkownik — w skrzynce widać tylko własną kopię.
     *
     * @param  \App\Models\User|int|null  $user
     */
    public static function inboxFor(Builder $query, mixed $user = null): Builder
    {
        $resolved = self::resolveUserForOwnership($user);
        $userId = $resolved['id'];

        if (! $userId) {
            return $query;
        }

        return self::mine($query, $userId);
    }

    /**
     * @param  \App\Models\User|int|null  $user
     * @return array{id: int|null, model: \App\Models\User|null}
     */
    private static function resolveUserForOwnership(mixed $user): array
    {
        if ($user instanceof \App\Models\User) {
            return ['id' => (int) $user->id, 'model' => $user];
        }

        if (is_int($user) || (is_string($user) && ctype_digit($user))) {
            $userId = (int) $user;
            $model = Auth::id() === $userId
                ? Auth::user()
                : \App\Models\User::query()->find($userId);

            return ['id' => $userId, 'model' => $model instanceof \App\Models\User ? $model : null];
        }

        $userId = Auth::id() ? (int) Auth::id() : null;

        return [
            'id' => $userId,
            'model' => Auth::user() instanceof \App\Models\User ? Auth::user() : null,
        ];
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

    public static function orderByLatestActivity(Builder $query, string $direction = 'desc'): Builder
    {
        self::withLatestActivityAtColumn($query);

        $dir = strtolower($direction) === 'asc' ? 'asc' : 'desc';
        $tasksTable = $query->getModel()->getTable();
        $expression = self::latestActivitySqlExpression($tasksTable, $query->getConnection()->getDriverName());

        // orderByRaw z pełnym wyrażeniem — alias latest_activity_at bywa kwalifikowany
        // jako tasks.latest_activity_at i wtedy sort kolumny Filament pada.
        return $query->orderByRaw("({$expression}) {$dir}");
    }

    public static function orderByLatestActivityDesc(Builder $query): Builder
    {
        return self::orderByLatestActivity($query, 'desc');
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

    public static function onlyFinished(Builder $query): Builder
    {
        $finishedStatusIds = self::finishedStatusIds();

        if ($finishedStatusIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('status_id', $finishedStatusIds);
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
