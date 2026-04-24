<?php

namespace App\Filament\Resources\TaskResource\Pages;

use App\Filament\Resources\TaskResource;
use App\Models\Task;
use App\Support\Tasks\TaskContextRegistry;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Str;

class EditTask extends EditRecord
{
    protected static string $resource = TaskResource::class;

    public function getBreadcrumbs(): array
    {
        $breadcrumbs = [
            TaskResource::getUrl('index') => 'Zadania',
        ];

        foreach ($this->getParentChain($this->getRecord()) as $parentTask) {
            $breadcrumbs[TaskResource::getUrl('edit', ['record' => $parentTask])] = Str::limit($parentTask->title ?: 'Zadanie', 48);
        }

        $breadcrumbs[] = 'Edycja';

        return $breadcrumbs;
    }

    public function getSubheading(): ?string
    {
        $record = $this->getRecord();

        $parentChain = $this->getParentChain($record);
        $parentContext = count($parentChain)
            ? 'Nadrzedne: '.collect($parentChain)->pluck('title')->filter()->join(' -> ')
            : null;

        $effectiveContextTask = $this->resolveEffectiveContextTask($record);
        $effectiveContext = null;

        if ($effectiveContextTask && $effectiveContextTask->taskable_type && $effectiveContextTask->taskable_id) {
            $effectiveContextTask->loadMissing('taskable');
            $typeLabel = TaskContextRegistry::labelForType($effectiveContextTask->taskable_type) ?? 'Kontekst';
            $recordLabel = TaskContextRegistry::labelForRecord($effectiveContextTask->taskable) ?? ('#'.$effectiveContextTask->taskable_id);
            $effectiveContext = 'Kontekst: '.$typeLabel.' - '.$recordLabel;
        }

        return collect([$parentContext, $effectiveContext])->filter()->join(' | ') ?: null;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
            Actions\ForceDeleteAction::make(),
            Actions\RestoreAction::make(),
        ];
    }

    /**
     * After saving a task (e.g., comment, status, event), clear the notification cache for the user
     */
    protected function afterSave(): void
    {
        parent::afterSave();
        $userId = auth()->id();
        if ($userId) {
            \App\Services\NotificationService::clearCacheForUser($userId);
        }
    }

    /**
     * @return array<int, Task>
     */
    protected function getParentChain(Task $task): array
    {
        $chain = [];
        $seen = [];
        $cursor = $task;

        while ($cursor->parent_id) {
            $cursor->loadMissing('parent');

            if (! $cursor->parent || isset($seen[$cursor->parent->id])) {
                break;
            }

            $seen[$cursor->parent->id] = true;
            $chain[] = $cursor->parent;
            $cursor = $cursor->parent;
        }

        return array_reverse($chain);
    }

    protected function resolveEffectiveContextTask(Task $task): ?Task
    {
        $seen = [];
        $cursor = $task;

        while ($cursor) {
            if (isset($seen[$cursor->id])) {
                break;
            }

            $seen[$cursor->id] = true;

            if ($cursor->taskable_type && $cursor->taskable_id) {
                return $cursor;
            }

            if (! $cursor->parent_id) {
                break;
            }

            $cursor->loadMissing('parent');
            $cursor = $cursor->parent;
        }

        return null;
    }
}
