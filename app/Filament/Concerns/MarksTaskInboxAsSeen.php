<?php

namespace App\Filament\Concerns;

use App\Services\Tasks\TaskInboxService;

trait MarksTaskInboxAsSeen
{
    public function mountMarksTaskInboxAsSeen(): void
    {
        app(TaskInboxService::class)->markAsSeen(auth()->user());
    }
}
