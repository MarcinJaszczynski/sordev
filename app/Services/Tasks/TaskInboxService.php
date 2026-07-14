<?php

namespace App\Services\Tasks;

use App\Models\Task;
use App\Models\User;
use App\Support\Tasks\TaskQueryFilters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class TaskInboxService
{
    public function unseenCount(?User $user): int
    {
        if (! $user) {
            return 0;
        }

        return $this->unseenQuery($user)->count();
    }

    public function unseenQuery(User $user): Builder
    {
        $query = Task::query();
        TaskQueryFilters::officeOnly($query);
        TaskQueryFilters::mine($query, $user->id);
        TaskQueryFilters::excludeCompleted($query);

        if (! $this->supportsLastSeenTracking()) {
            return $query;
        }

        $lastSeen = $user->tasks_last_seen_at;

        if (! $lastSeen) {
            return $query;
        }

        return TaskQueryFilters::changedSince($query, Carbon::parse($lastSeen));
    }

    public function markAsSeen(?User $user): void
    {
        if (! $user || ! $this->supportsLastSeenTracking()) {
            return;
        }

        $user->forceFill(['tasks_last_seen_at' => now()])->save();
    }

    protected function supportsLastSeenTracking(): bool
    {
        return Schema::hasColumn('users', 'tasks_last_seen_at');
    }
}
