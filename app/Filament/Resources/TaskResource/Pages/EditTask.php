<?php

namespace App\Filament\Resources\TaskResource\Pages;

use App\Filament\Concerns\MarksTaskInboxAsSeen;
use App\Filament\Resources\TaskResource;
use App\Models\Task;
use App\Support\Tasks\TaskNavigation;
use App\Support\Tasks\TaskAuthorization;
use App\Support\Tasks\TaskContextRegistry;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Str;

class EditTask extends EditRecord
{
    use MarksTaskInboxAsSeen;

    protected static string $resource = TaskResource::class;

    public function mount(int | string $record): void
    {
        $this->redirect(TaskNavigation::fullViewUrl((int) $record));
    }

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

        $effectiveContextTask = TaskContextRegistry::resolveEffectiveContextTask($record);
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
        $contextActions = collect(TaskContextRegistry::linksForTask($this->getRecord()))
            ->values()
            ->map(fn (array $link, int $index): Actions\Action => Actions\Action::make('context_link_'.$index)
                ->label($link['label'])
                ->icon($link['icon'])
                ->url($link['url'])
                ->openUrlInNewTab()
                ->color('gray'))
            ->all();

        return array_merge($contextActions, [
            Actions\DeleteAction::make()
                ->visible(fn (): bool => TaskAuthorization::canDelete(auth()->user(), $this->getRecord())),
            Actions\ForceDeleteAction::make()
                ->visible(fn (): bool => TaskAuthorization::canForceDelete(auth()->user(), $this->getRecord())),
            Actions\RestoreAction::make(),
        ]);
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
}
