<?php

namespace App\Livewire;

use App\Models\Task;
use App\Models\TaskComment;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Wątek dyskusji pod zadaniem.
 *
 * Zachowanie: zawsze widoczny jest tylko ostatni komentarz.
 * Wcześniejsze komentarze są zwinięte pod jednym przełącznikiem,
 * żeby długie dyskusje (kilkanaście wpisów) nie zaśmiecały widoku.
 */
class TaskCommentThread extends Component
{
    public Task $task;

    public bool $showEarlier = false;

    public string $newComment = '';

    #[Computed]
    public function comments()
    {
        // Najstarszy -> najnowszy, żeby "latest" zawsze było ostatnim elementem kolekcji.
        return $this->task->comments()->with('author')->orderBy('created_at')->get();
    }

    #[Computed]
    public function latestComment()
    {
        return $this->comments->last();
    }

    #[Computed]
    public function earlierComments()
    {
        return $this->comments->count() > 1
            ? $this->comments->slice(0, -1)
            : collect();
    }

    public function toggleEarlier(): void
    {
        $this->showEarlier = ! $this->showEarlier;
    }

    public function submit(): void
    {
        $this->validate([
            'newComment' => 'required|string|max:5000',
        ]);

        $this->task->comments()->create([
            'body' => $this->newComment,
            'author_id' => auth()->id(),
        ]);

        $this->newComment = '';

        // Nowy komentarz staje się "latest" — zwijamy z powrotem starsze wpisy.
        $this->showEarlier = false;

        unset($this->comments, $this->latestComment, $this->earlierComments);
    }

    public function render()
    {
        return view('livewire.task-comment-thread');
    }
}
