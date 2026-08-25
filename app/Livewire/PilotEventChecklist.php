<?php

namespace App\Livewire;

use App\Filament\Resources\ChecklistTemplateResource;
use App\Models\ChecklistTemplate;
use App\Models\Event;
use App\Models\Task;
use App\Services\PilotAccessService;
use App\Services\PilotChecklistService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class PilotEventChecklist extends Component
{
    public int $eventId;

    public string $newItemTitle = '';

    public ?int $selectedTemplateId = null;

    public bool $readOnly = false;

    /** @var array<int, string> */
    public array $responses = [];

    /** Biuro/administracja zarządza listą (wybór szablonu, dodawanie/usuwanie punktów). */
    public bool $canManage = false;

    public bool $forcePilotView = false;

    public function mount(int $eventId, bool $forcePilotView = false): void
    {
        $event = Event::query()->findOrFail($eventId);
        $user = Auth::user();
        $isOffice = (bool) $user?->hasRole(['admin', 'super_admin', 'biuro']);

        abort_unless($isOffice || $user?->can('view', $event), 403);

        $this->eventId = $eventId;
        $this->forcePilotView = $forcePilotView;
        $this->canManage = $this->resolveCanManage();

        // Pilot poza oknem pełnego dostępu (archiwum) nie może nawet odznaczać.
        $this->readOnly = ! $isOffice && ! app(PilotAccessService::class)->hasFullAccess($event, $user);

        $this->syncResponsesFromTasks();
    }

    protected function syncResponsesFromTasks(): void
    {
        $this->responses = app(PilotChecklistService::class)
            ->tasksForEvent($this->event())
            ->mapWithKeys(fn (Task $task): array => [
                $task->id => (string) ($task->checklist_response ?? ''),
            ])
            ->all();
    }

    protected function resolveCanManage(): bool
    {
        return (bool) Auth::user()?->hasRole(['admin', 'super_admin', 'biuro']) && ! $this->forcePilotView;
    }

    protected function event(): Event
    {
        return Event::query()->findOrFail($this->eventId);
    }

    public function applyTemplate(): void
    {
        if (! $this->resolveCanManage() || ! $this->selectedTemplateId) {
            return;
        }

        $template = ChecklistTemplate::query()->active()->find($this->selectedTemplateId);

        if (! $template) {
            return;
        }

        $added = app(PilotChecklistService::class)->applyTemplate($this->event(), $template, Auth::user());

        Notification::make()
            ->title($added > 0 ? "Dodano {$added} punktów z szablonu" : 'Wszystkie punkty z szablonu już są na liście')
            ->success()
            ->send();

        $this->selectedTemplateId = null;
        $this->dispatch('pilot-checklist-updated');
    }

    public function addItem(): void
    {
        if (! $this->resolveCanManage()) {
            return;
        }

        $title = trim($this->newItemTitle);
        if ($title === '') {
            return;
        }

        app(PilotChecklistService::class)->addItem($this->event(), Auth::user(), $title);
        $this->newItemTitle = '';
        $this->dispatch('pilot-checklist-updated');
    }

    public function deleteItem(int $taskId): void
    {
        if (! $this->resolveCanManage()) {
            return;
        }

        Task::query()
            ->whereKey($taskId)
            ->where('taskable_type', Event::class)
            ->where('taskable_id', $this->eventId)
            ->delete();

        $this->dispatch('pilot-checklist-updated');
    }

    public function toggleTask(int $taskId): void
    {
        if ($this->readOnly) {
            return;
        }

        $task = Task::query()
            ->whereKey($taskId)
            ->where('taskable_type', Event::class)
            ->where('taskable_id', $this->eventId)
            ->first();

        if (! $task) {
            return;
        }

        try {
            if ($task->checklistInputType()->requiresValue()) {
                app(PilotChecklistService::class)->saveResponse($task, $this->responses[$taskId] ?? '');
                $task->refresh();
            }

            app(PilotChecklistService::class)->toggleDone($task);
        } catch (\Illuminate\Validation\ValidationException $exception) {
            Notification::make()
                ->title(collect($exception->errors())->flatten()->first() ?? 'Nie udało się zaktualizować punktu.')
                ->danger()
                ->send();

            return;
        }

        $this->syncResponsesFromTasks();
        $this->dispatch('pilot-checklist-updated');
    }

    public function saveResponse(int $taskId): void
    {
        if ($this->readOnly) {
            return;
        }

        $task = Task::query()
            ->whereKey($taskId)
            ->where('taskable_type', Event::class)
            ->where('taskable_id', $this->eventId)
            ->first();

        if (! $task || ! $task->checklistInputType()->requiresValue()) {
            return;
        }

        try {
            app(PilotChecklistService::class)->saveResponse($task, $this->responses[$taskId] ?? '');

            Notification::make()
                ->title('Zapisano wartość')
                ->success()
                ->send();
        } catch (\Illuminate\Validation\ValidationException $exception) {
            Notification::make()
                ->title(collect($exception->errors())->flatten()->first() ?? 'Nie udało się zapisać wartości.')
                ->danger()
                ->send();
        }

        $this->syncResponsesFromTasks();
        $this->dispatch('pilot-checklist-updated');
    }

    public function render()
    {
        $service = app(PilotChecklistService::class);
        $canManage = $this->resolveCanManage();
        $this->canManage = $canManage;

        $doneStatusId = \App\Models\TaskStatus::query()->where('name', 'Zakończone')->value('id');

        return view('livewire.pilot-event-checklist', [
            'tasks' => $service->tasksForEvent($this->event()),
            'progress' => $service->progressFor($this->event()),
            'doneStatusId' => $doneStatusId,
            'canManage' => $canManage,
            'templates' => $canManage ? $service->availableTemplates() : collect(),
            'templatesUrl' => $canManage
                ? ChecklistTemplateResource::getUrl('index', panel: 'admin')
                : null,
            'createTemplateUrl' => $canManage
                ? ChecklistTemplateResource::getUrl('create', panel: 'admin')
                : null,
        ]);
    }
}
