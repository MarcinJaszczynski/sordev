<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\TaskSource;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Support\Tasks\TaskNavigation;
use App\Support\Tasks\TaskQueryFilters;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class CollapseSystemTaskDuplicatesCommand extends Command
{
    protected $signature = 'tasks:collapse-system-duplicates {--dry-run : Pokaż zmiany bez zapisu}';

    protected $description = 'Scala zduplikowane otwarte zadania systemowe tej samej osoby (1 kopia na fingerprint + assignee)';

    public function handle(): int
    {
        $cancelledStatusId = TaskStatus::query()->where('name', 'Anulowane')->value('id');

        if (! $cancelledStatusId) {
            $this->error('Brak statusu „Anulowane” — uruchom TaskStatusSeeder.');

            return self::FAILURE;
        }

        $query = Task::query()->where('source', TaskSource::System->value);
        TaskQueryFilters::excludeFinished($query);
        TaskQueryFilters::excludeArchived($query);

        /** @var Collection<int, Collection<int, Task>> $groups */
        $groups = $query
            ->orderBy('id')
            ->get()
            ->groupBy(function (Task $task): string {
                $fingerprint = $this->extractFingerprint((string) $task->description) ?? 'task:'.$task->id;
                $assignee = $task->assignee_id ? (string) $task->assignee_id : 'none';

                return $fingerprint.'|'.$assignee;
            })
            ->filter(fn (Collection $group, string $key): bool => ! str_starts_with($key, 'task:') && $group->count() > 1);

        if ($groups->isEmpty()) {
            $this->info('Brak duplikatów zadań systemowych do scalenia.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $kept = 0;
        $cancelled = 0;

        foreach ($groups as $fingerprint => $tasks) {
            $keeper = $this->pickKeeper($tasks);
            $duplicates = $tasks->where('id', '!=', $keeper->id);
            $kept++;
            $cancelled += $duplicates->count();

            $this->line(sprintf(
                '%s → zostaw #%d, anuluj: %s',
                $fingerprint,
                $keeper->id,
                $duplicates->pluck('id')->implode(', '),
            ));

            if ($dryRun) {
                continue;
            }

            foreach ($duplicates as $duplicate) {
                $note = "\n\n[collapsed-duplicate-of:{$keeper->id}] Scalone jako duplikat zadania systemowego.";
                $duplicate->update([
                    'status_id' => $cancelledStatusId,
                    'description' => rtrim((string) $duplicate->description).$note,
                ]);
            }
        }

        $prefix = $dryRun ? 'Dry-run: ' : '';
        $this->info("{$prefix}grup: {$groups->count()}, zachowanych: {$kept}, anulowanych: {$cancelled}.");

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, Task>  $tasks
     */
    private function pickKeeper(Collection $tasks): Task
    {
        $preferredAssigneeIds = $tasks
            ->map(function (Task $task): ?int {
                $event = TaskNavigation::resolveEvent($task);

                return $event?->assigned_to ? (int) $event->assigned_to : null;
            })
            ->filter()
            ->unique()
            ->values();

        if ($preferredAssigneeIds->isNotEmpty()) {
            $preferred = $tasks->first(
                fn (Task $task): bool => $task->assignee_id
                    && $preferredAssigneeIds->contains((int) $task->assignee_id)
            );

            if ($preferred) {
                return $preferred;
            }
        }

        return $tasks->sortBy('id')->first();
    }

    private function extractFingerprint(string $description): ?string
    {
        if (preg_match('/\[(?:reservation-task|payment-reminder):[^\]]+\]/', $description, $matches)) {
            return $matches[0];
        }

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
