<?php

namespace App\Livewire;

use App\Models\StickyNote;
use App\Services\StickyNoteService;
use App\Support\StickyNotes\StickyNoteCategory;
use App\Support\Tasks\TaskContextRegistry;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Livewire\Component;

class StickyNotesStack extends Component
{
    public string $notableType;

    public int $notableId;

    public string $title = 'Karteczki';

    public bool $compact = false;

    public ?string $filterCategory = null;

    public bool $historyExpanded = false;

    public string $newBody = '';

    public string $newCategory = StickyNoteCategory::GENERAL;

    public ?int $editingNoteId = null;

    public string $editBody = '';

    public string $editCategory = StickyNoteCategory::GENERAL;

    protected StickyNoteService $stickyNoteService;

    public function boot(StickyNoteService $stickyNoteService): void
    {
        $this->stickyNoteService = $stickyNoteService;
    }

    public function mount(string $notableType, int $notableId, ?string $title = null, bool $compact = false, ?string $filterCategory = null): void
    {
        abort_unless(Auth::check(), 403);
        abort_unless(TaskContextRegistry::isSupported($notableType), 404);

        $this->notableType = $notableType;
        $this->notableId = $notableId;
        $this->title = $title ?? 'Karteczki';
        $this->compact = $compact;
        $this->filterCategory = $filterCategory;

        if ($this->filterCategory && StickyNoteCategory::isValid($this->filterCategory)) {
            $this->newCategory = $this->filterCategory;
        }

        $this->resolveNotable();
    }

    public function getNotesProperty(): Collection
    {
        return $this->stickyNoteService->getStack($this->resolveNotable(), $this->filterCategory);
    }

    public function addNote(): void
    {
        $user = Auth::user();
        abort_unless($user, 403);

        try {
            $this->stickyNoteService->addNote(
                $this->resolveNotable(),
                $user,
                $this->newBody,
                $this->filterCategory ?? StickyNoteCategory::GENERAL,
            );
        } catch (InvalidArgumentException $exception) {
            Notification::make()
                ->title($exception->getMessage())
                ->danger()
                ->send();

            return;
        }

        $this->reset(['newBody']);
        if (! $this->filterCategory) {
            $this->newCategory = StickyNoteCategory::GENERAL;
        }
        $this->historyExpanded = false;
        $this->cancelEdit();

        Notification::make()
            ->title('Dodano karteczkę')
            ->success()
            ->send();
    }

    public function startEdit(int $noteId): void
    {
        $user = Auth::user();
        abort_unless($user, 403);

        $note = $this->findNote($noteId);

        if (! $this->stickyNoteService->canEdit($note, $user)) {
            Notification::make()
                ->title('Nie możesz edytować tej karteczki')
                ->body('Edytować można tylko swoją najnowszą karteczkę, która nie została nadpisana kolejną.')
                ->warning()
                ->send();

            return;
        }

        $this->editingNoteId = $note->getKey();
        $this->editBody = $note->body;
        $this->editCategory = $note->category ?? StickyNoteCategory::GENERAL;
    }

    public function saveEdit(): void
    {
        $user = Auth::user();
        abort_unless($user, 403);

        if (! $this->editingNoteId) {
            return;
        }

        $note = $this->findNote($this->editingNoteId);

        try {
            $this->stickyNoteService->updateNote(
                $note,
                $user,
                $this->editBody,
                $this->filterCategory ?? $note->category ?? StickyNoteCategory::GENERAL,
            );
        } catch (InvalidArgumentException $exception) {
            Notification::make()
                ->title($exception->getMessage())
                ->danger()
                ->send();

            return;
        }

        $this->cancelEdit();

        Notification::make()
            ->title('Zapisano karteczkę')
            ->success()
            ->send();
    }

    public function cancelEdit(): void
    {
        $this->editingNoteId = null;
        $this->editBody = '';
        $this->editCategory = StickyNoteCategory::GENERAL;
    }

    public function toggleHistory(): void
    {
        $this->historyExpanded = ! $this->historyExpanded;
    }

    public function canEditNote(StickyNote $note): bool
    {
        $user = Auth::user();
        $latestNoteId = $this->notes->first()?->getKey();

        return $user && $this->stickyNoteService->canEdit(
            $note,
            $user,
            $latestNoteId ? (int) $latestNoteId : null,
        );
    }

    public function render()
    {
        return view('livewire.sticky-notes-stack', [
            'categoryOptions' => StickyNoteCategory::options(),
            'latestNoteId' => $this->notes->first()?->getKey(),
        ]);
    }

    protected function resolveNotable(): Model
    {
        return $this->notableType::query()->findOrFail($this->notableId);
    }

    protected function findNote(int $noteId): StickyNote
    {
        return StickyNote::query()
            ->whereKey($noteId)
            ->where('notable_type', $this->notableType)
            ->where('notable_id', $this->notableId)
            ->firstOrFail();
    }
}
