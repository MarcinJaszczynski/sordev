<?php

namespace App\Filament\Resources\TaskResource\Pages;

use App\Filament\Resources\TaskResource;
use App\Support\Tasks\TaskNavigation;
use Filament\Resources\Pages\CreateRecord;

class CreateTask extends CreateRecord
{
    protected static string $resource = TaskResource::class;

    public function mount(): void
    {
        $this->redirect(static::redirectUrl());
    }

    public static function redirectUrl(): string
    {
        return TaskNavigation::createUrl(
            request()->query('taskable_type'),
            request()->has('taskable_id') ? (int) request()->query('taskable_id') : null,
            request()->query('dueDate'),
        );
    }
}
