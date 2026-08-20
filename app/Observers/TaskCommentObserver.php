<?php

namespace App\Observers;

use App\Models\TaskComment;
use App\Services\NotificationService;

class TaskCommentObserver
{
    public function created(TaskComment $comment): void
    {
        NotificationService::clearCacheForTaskCommentStakeholders($comment);
    }
}
