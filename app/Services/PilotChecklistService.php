<?php

namespace App\Services;

use App\Enums\ChecklistItemInputType;
use App\Enums\TaskPriority;
use App\Enums\TaskSource;
use App\Models\ChecklistTemplate;
use App\Models\ChecklistTemplateItem;
use App\Models\Event;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class PilotChecklistService
{
    public function tasksForEvent(Event $event): Collection
    {
        return Task::query()
            ->with('status')
            ->where('taskable_type', Event::class)
            ->where('taskable_id', $event->id)
            ->whereNull('parent_id')
            ->pilotChecklistOnly()
            ->orderBy('order')
            ->orderBy('id')
            ->get();
    }

    /**
     * Aktywne uniwersalne szablony checklisty do wyboru przez pilota.
     */
    public function availableTemplates(): Collection
    {
        return ChecklistTemplate::query()
            ->active()
            ->withCount('items')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * Załaduj punkty z wybranego szablonu do checklisty imprezy.
     * Pomija punkty już istniejące (po treści), aby uniknąć duplikatów.
     *
     * @return int liczba dodanych punktów
     */
    public function applyTemplate(Event $event, ChecklistTemplate $template, User $author): int
    {
        $defaultStatus = TaskStatus::query()->where('is_default', true)->first()
            ?? TaskStatus::query()->orderBy('order')->first();

        if (! $defaultStatus) {
            return 0;
        }

        $existingTitles = $this->tasksForEvent($event)
            ->map(fn (Task $task) => mb_strtolower(trim((string) $task->title)))
            ->all();

        $added = 0;

        foreach ($template->items()->get() as $item) {
            $title = trim((string) $item->title);

            if ($title === '' || in_array(mb_strtolower($title), $existingTitles, true)) {
                continue;
            }

            Task::create(array_merge(
                $this->inputConfigFromTemplateItem($item),
                [
                    'title' => $title,
                    'description' => $item->description,
                    'taskable_type' => Event::class,
                    'taskable_id' => $event->id,
                    'status_id' => $defaultStatus->id,
                    'author_id' => $author->id,
                    'assignee_id' => $event->assigned_to ?: $author->id,
                    'priority' => TaskPriority::Normal->value,
                    'source' => TaskSource::PilotChecklist->value,
                ]
            ));

            $existingTitles[] = mb_strtolower($title);
            $added++;
        }

        return $added;
    }

    public function addItem(Event $event, User $author, string $title): Task
    {
        $defaultStatus = TaskStatus::query()->where('is_default', true)->first()
            ?? TaskStatus::query()->orderBy('order')->first();

        return Task::create([
            'title' => trim($title),
            'taskable_type' => Event::class,
            'taskable_id' => $event->id,
            'status_id' => $defaultStatus?->id,
            'author_id' => $author->id,
            'assignee_id' => $event->assigned_to ?: $author->id,
            'priority' => TaskPriority::Normal->value,
            'source' => TaskSource::PilotChecklist->value,
            'checklist_input_type' => ChecklistItemInputType::CheckOnly->value,
            'checklist_input_required' => false,
        ]);
    }

    public function saveResponse(Task $task, mixed $value): Task
    {
        $inputType = $task->checklistInputType();

        if (! $inputType->requiresValue()) {
            return $task;
        }

        $normalized = $this->normalizeResponseValue($inputType, $value);

        if ($normalized === null && $task->checklistRequiresResponse()) {
            throw ValidationException::withMessages([
                'response' => 'Wypełnij wymaganą wartość przed odhaczeniem.',
            ]);
        }

        $task->checklist_response = $normalized;
        $task->save();

        return $task->fresh('status');
    }

    public function toggleDone(Task $task): Task
    {
        $doneStatus = TaskStatus::query()->where('name', 'Zakończone')->first();
        $todoStatus = TaskStatus::query()->where('is_default', true)->first()
            ?? TaskStatus::query()->orderBy('order')->first();

        if (! $doneStatus || ! $todoStatus) {
            return $task;
        }

        $isDone = (int) $task->status_id === (int) $doneStatus->id;

        if (! $isDone && $task->checklistRequiresResponse() && ! $task->hasChecklistResponse()) {
            throw ValidationException::withMessages([
                'response' => 'Wypełnij wymaganą wartość przed odhaczeniem.',
            ]);
        }

        $task->status_id = $isDone ? $todoStatus->id : $doneStatus->id;
        $task->save();

        return $task->fresh('status');
    }

    public function progressFor(Event $event): array
    {
        $tasks = $this->tasksForEvent($event);
        $doneStatusId = TaskStatus::query()->where('name', 'Zakończone')->value('id');
        $done = $doneStatusId
            ? $tasks->where('status_id', $doneStatusId)->count()
            : 0;

        return [
            'total' => $tasks->count(),
            'done' => $done,
            'percent' => $tasks->count() > 0 ? (int) round(($done / $tasks->count()) * 100) : 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function inputConfigFromTemplateItem(ChecklistTemplateItem $item): array
    {
        $inputType = ChecklistItemInputType::tryFrom((string) ($item->input_type ?? ''))
            ?? ChecklistItemInputType::CheckOnly;

        return [
            'checklist_input_type' => $inputType->value,
            'checklist_input_label' => filled($item->input_label)
                ? trim((string) $item->input_label)
                : null,
            'checklist_input_required' => (bool) ($item->input_required ?? false),
            'checklist_input_unit' => filled($item->input_unit)
                ? trim((string) $item->input_unit)
                : null,
        ];
    }

    protected function normalizeResponseValue(ChecklistItemInputType $inputType, mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : (string) $value;

        if ($value === '') {
            return null;
        }

        if ($inputType === ChecklistItemInputType::Number) {
            if (! is_numeric($value)) {
                throw ValidationException::withMessages([
                    'response' => 'Podaj poprawną liczbę.',
                ]);
            }

            return (string) $value;
        }

        return $value;
    }
}
