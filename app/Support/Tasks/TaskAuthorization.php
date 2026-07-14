<?php

namespace App\Support\Tasks;

use App\Models\Task;
use App\Models\User;

class TaskAuthorization
{
    public static function canDelete(?User $user, Task $task): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->hasRole(['super_admin', 'admin'])) {
            return true;
        }

        return (int) $task->author_id === (int) $user->id;
    }

    public static function canForceDelete(?User $user, Task $task): bool
    {
        if (! $user) {
            return false;
        }

        return $user->hasRole(['super_admin', 'admin']);
    }

    public static function canAccessTask(?User $user, Task $task): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->hasRole(['super_admin', 'admin'])) {
            return true;
        }

        return (int) $task->author_id === (int) $user->id
            || (int) $task->assignee_id === (int) $user->id;
    }
}
