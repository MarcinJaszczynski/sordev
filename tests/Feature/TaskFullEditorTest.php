<?php

namespace Tests\Feature;

use App\Livewire\TaskFullEditor;
use App\Models\Contractor;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TaskFullEditorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\TaskStatusSeeder::class);
    }

    public function test_full_editor_renders_context_links_for_event_related_task(): void
    {
        $user = User::factory()->create();
        $contractor = Contractor::create(['name' => 'Hotel Test', 'status' => 'active']);

        $task = Task::create([
            'title' => 'Pełne zadanie',
            'due_date' => now()->addDay(),
            'status_id' => Task::getDefaultStatusId(),
            'priority' => 'normal',
            'author_id' => $user->id,
            'taskable_type' => Contractor::class,
            'taskable_id' => $contractor->id,
        ]);

        $component = Livewire::actingAs($user)
            ->test(TaskFullEditor::class, ['taskId' => $task->id])
            ->assertSet('data.title', 'Pełne zadanie')
            ->assertSee('Kontrahent')
            ->assertSee('Otwórz powiązany ekran w nowej karcie:');

        $this->assertSame(
            1,
            substr_count($component->html(), 'Otwórz powiązany ekran w nowej karcie:'),
            'Lista kontekstu w modalu zadania ma być renderowana jeden raz.',
        );

        $this->assertCount(3, $component->instance()->getRelationManagers());
    }

    public function test_full_editor_can_create_task(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(TaskFullEditor::class)
            ->set('data.title', 'Nowe z pełnego modala')
            ->set('data.status_id', Task::getDefaultStatusId())
            ->set('data.priority', 'normal')
            ->call('save')
            ->assertDispatched('task-full-editor-updated');

        $this->assertDatabaseHas('tasks', [
            'title' => 'Nowe z pełnego modala',
            'author_id' => $user->id,
        ]);
    }

    public function test_full_editor_can_create_task_and_show_inline_sections(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(TaskFullEditor::class)
            ->set('data.title', 'Zadanie z sekcjami')
            ->set('data.status_id', Task::getDefaultStatusId())
            ->set('data.priority', 'normal')
            ->call('save')
            ->assertDispatched('task-full-editor-updated')
            ->assertSee('Komentarze');

        $task = Task::query()->where('title', 'Zadanie z sekcjami')->first();

        $this->assertNotNull($task);
        $this->assertCount(2, Livewire::actingAs($user)
            ->test(TaskFullEditor::class, ['taskId' => $task->id])
            ->instance()
            ->getInlineRelationManagers());
    }

    public function test_full_editor_can_update_status_for_existing_task(): void
    {
        $user = User::factory()->create();
        $statuses = \App\Models\TaskStatus::query()->orderBy('order')->get();
        $this->assertGreaterThanOrEqual(2, $statuses->count());

        $task = Task::create([
            'title' => 'Status test',
            'status_id' => $statuses->first()->id,
            'priority' => 'normal',
            'author_id' => $user->id,
        ]);

        $newStatusId = $statuses->last()->id;

        Livewire::actingAs($user)
            ->test(TaskFullEditor::class, ['taskId' => $task->id])
            ->set('data.status_id', $newStatusId)
            ->call('save')
            ->assertDispatched('task-full-editor-updated');

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'status_id' => $newStatusId,
        ]);
    }

    public function test_full_editor_renders_inline_sections_without_tabs(): void
    {
        $user = User::factory()->create();
        $task = Task::create([
            'title' => 'Sekcje inline',
            'status_id' => Task::getDefaultStatusId(),
            'priority' => 'normal',
            'author_id' => $user->id,
        ]);

        $component = Livewire::actingAs($user)
            ->test(TaskFullEditor::class, ['taskId' => $task->id])
            ->assertSee('Komentarze')
            ->assertDontSee('Szczegóły zadania');

        $this->assertSame([
            \App\Filament\Resources\TaskResource\RelationManagers\AttachmentsRelationManager::class,
            \App\Filament\Resources\TaskResource\RelationManagers\SubtasksRelationManager::class,
        ], $component->instance()->getInlineRelationManagers());
    }

    public function test_full_editor_can_add_comment(): void
    {
        $user = User::factory()->create();
        $task = Task::create([
            'title' => 'Komentarz test',
            'status_id' => Task::getDefaultStatusId(),
            'priority' => 'normal',
            'author_id' => $user->id,
        ]);

        Livewire::actingAs($user)
            ->test(TaskFullEditor::class, ['taskId' => $task->id])
            ->assertSee('Napisz komentarz')
            ->set('newCommentContent', 'Nowy komentarz z modala')
            ->call('addComment')
            ->assertDispatched('task-full-editor-updated')
            ->assertSee('Nowy komentarz z modala');

        $this->assertDatabaseHas('task_comments', [
            'task_id' => $task->id,
            'user_id' => $user->id,
            'content' => 'Nowy komentarz z modala',
        ]);
    }

    public function test_full_editor_with_subtasks_does_not_register_nested_edit_modal(): void
    {
        $user = User::factory()->create();

        $parent = Task::create([
            'title' => 'Zadanie nadrzędne',
            'status_id' => Task::getDefaultStatusId(),
            'priority' => 'normal',
            'author_id' => $user->id,
        ]);

        Task::create([
            'title' => 'Podzadanie',
            'status_id' => Task::getDefaultStatusId(),
            'priority' => 'normal',
            'author_id' => $user->id,
            'parent_id' => $parent->id,
        ]);

        Livewire::actingAs($user)
            ->test(TaskFullEditor::class, ['taskId' => $parent->id])
            ->assertSee('Podzadanie')
            ->assertSee('Podzadania');
    }

    public function test_subtask_editor_shows_parent_banner_and_dispatches_open_parent(): void
    {
        $user = User::factory()->create();

        $parent = Task::create([
            'title' => 'Zadanie nadrzędne do otwarcia',
            'status_id' => Task::getDefaultStatusId(),
            'priority' => 'normal',
            'author_id' => $user->id,
        ]);

        $subtask = Task::create([
            'title' => 'Moje podzadanie',
            'status_id' => Task::getDefaultStatusId(),
            'priority' => 'normal',
            'author_id' => $user->id,
            'parent_id' => $parent->id,
        ]);

        Livewire::actingAs($user)
            ->test(TaskFullEditor::class, ['taskId' => $subtask->id])
            ->assertSee('Otwórz zadanie główne')
            ->assertSee('Zadanie nadrzędne do otwarcia')
            ->call('openParentTask')
            ->assertDispatched('open-edit-task-modal', taskId: $parent->id);
    }
}
