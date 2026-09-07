<?php

namespace App\Filament\Resources\TaskResource\Pages;

use App\Filament\Concerns\MarksTaskInboxAsSeen;
use App\Filament\Resources\TaskResource;
use App\Models\Task;
use App\Support\Tasks\TaskAuthorization;
use App\Support\Tasks\TaskContextRegistry;
use App\Support\Tasks\TaskNavigation;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class EditTask extends EditRecord
{
    use MarksTaskInboxAsSeen;

    protected static string $resource = TaskResource::class;

    /**
     * Trait mountCanAuthorizeAccess odpala się PRZED mount() — wtedy $record to jeszcze klucz trasy.
     * Bez resolve EditTask pada TypeError (string/int zamiast Model) przy deep linkach / smoke.
     */
    public function mountCanAuthorizeAccess(): void
    {
        if (! $this->record instanceof Model) {
            $this->record = $this->resolveRecord($this->record);
        }

        abort_unless(static::canAccess(['record' => $this->getRecord()]), 403);
    }

    public function mount(int|string $record): void
    {
        // Resolve record first so any incidental render has a Model, then deep-link to modal editor.
        if (! $this->record instanceof Model) {
            $this->record = $this->resolveRecord($record);
        }

        $this->redirect(TaskNavigation::fullViewUrl($this->getRecord()));
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
            $typeLabel = TaskContextRegistry::labelForType($effectiveContextTask->taskable_type) ?? 'Powiązanie';
            $recordLabel = TaskContextRegistry::labelForRecord($effectiveContextTask->taskable) ?? ('#'.$effectiveContextTask->taskable_id);
            $effectiveContext = $typeLabel.': '.$recordLabel;
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
