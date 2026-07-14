<?php

namespace Tests\Feature;

use App\Livewire\TaskFullEditor;
use App\Models\Contractor;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
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
            ->assertSee('Kontrahent');

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
            ->assertDispatched('task-full-editor-saved');

        $this->assertDatabaseHas('tasks', [
            'title' => 'Nowe z pełnego modala',
            'author_id' => $user->id,
        ]);
    }

    public function test_full_editor_can_create_task_with_attachments(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();

        $path = 'task-attachments/plan.pdf';
        Storage::put($path, 'pdf-content');

        Livewire::actingAs($user)
            ->test(TaskFullEditor::class)
            ->set('data.title', 'Zadanie z plikiem')
            ->set('data.status_id', Task::getDefaultStatusId())
            ->set('data.priority', 'normal')
            ->set('data.pending_attachments', [$path])
            ->call('save')
            ->assertDispatched('task-full-editor-saved');

        $task = Task::query()->where('title', 'Zadanie z plikiem')->first();

        $this->assertNotNull($task);
        $this->assertDatabaseHas('task_attachments', [
            'task_id' => $task->id,
            'user_id' => $user->id,
            'name' => 'plan.pdf',
        ]);
        $this->assertCount(3, Livewire::actingAs($user)
            ->test(TaskFullEditor::class, ['taskId' => $task->id])
            ->instance()
            ->getRelationManagers());
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
            ->assertDispatched('task-full-editor-saved');

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'status_id' => $newStatusId,
        ]);
    }
}
