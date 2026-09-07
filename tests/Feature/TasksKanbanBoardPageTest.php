<?php

namespace Tests\Feature;

use App\Filament\Resources\TaskResource\Pages\TasksKanbanBoardPage;
use App\Models\Contractor;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TasksKanbanBoardPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\TaskStatusSeeder::class);

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    }

    public function test_kanban_defaults_to_assigned_scope(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');
        $other = User::factory()->create();

        $assigned = Task::create([
            'title' => 'Przypisane do mnie',
            'status_id' => Task::getDefaultStatusId(),
            'priority' => 'normal',
            'author_id' => $other->id,
            'assignee_id' => $user->id,
        ]);

        $authored = Task::create([
            'title' => 'Zlecone przeze mnie',
            'status_id' => Task::getDefaultStatusId(),
            'priority' => 'normal',
            'author_id' => $user->id,
            'assignee_id' => $other->id,
        ]);

        $tasks = Livewire::actingAs($user)
            ->test(TasksKanbanBoardPage::class)
            ->assertSet('tasksScope', 'assigned')
            ->instance()
            ->tasks();

        $this->assertTrue($tasks->contains('id', $assigned->id));
        $this->assertTrue($tasks->contains('id', $authored->id));
        $this->assertCount(2, $tasks);
    }

    public function test_kanban_can_switch_to_for_me_and_all_scopes(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');
        $other = User::factory()->create();

        $assigned = Task::create([
            'title' => 'Przypisane do mnie',
            'status_id' => Task::getDefaultStatusId(),
            'priority' => 'normal',
            'author_id' => $other->id,
            'assignee_id' => $user->id,
        ]);

        $authored = Task::create([
            'title' => 'Zlecone przeze mnie',
            'status_id' => Task::getDefaultStatusId(),
            'priority' => 'normal',
            'author_id' => $user->id,
            'assignee_id' => $other->id,
        ]);

        $forMeTasks = Livewire::actingAs($user)
            ->test(TasksKanbanBoardPage::class)
            ->call('setTasksScope', 'for_me')
            ->instance()
            ->tasks();

        $this->assertCount(1, $forMeTasks);
        $this->assertSame($assigned->id, $forMeTasks->first()->id);

        $allTasks = Livewire::actingAs($user)
            ->test(TasksKanbanBoardPage::class)
            ->call('setTasksScope', 'all')
            ->instance()
            ->tasks();

        $this->assertCount(2, $allTasks);
        $this->assertTrue($allTasks->contains('id', $authored->id));
    }

    public function test_kanban_shows_subtasks_on_board(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $parent = Task::create([
            'title' => 'Rodzic kanban',
            'status_id' => Task::getDefaultStatusId(),
            'priority' => 'normal',
            'author_id' => $user->id,
            'assignee_id' => $user->id,
        ]);

        Task::create([
            'title' => 'Podzadanie kanban',
            'status_id' => Task::getDefaultStatusId(),
            'priority' => 'normal',
            'author_id' => $user->id,
            'assignee_id' => $user->id,
            'parent_id' => $parent->id,
        ]);

        $tasks = Livewire::actingAs($user)
            ->test(TasksKanbanBoardPage::class)
            ->instance()
            ->tasks();

        $this->assertCount(2, $tasks);
        $this->assertTrue($tasks->pluck('title')->contains('Rodzic kanban'));
        $this->assertTrue($tasks->pluck('title')->contains('Podzadanie kanban'));
    }

    public function test_kanban_shows_finished_tasks_by_default(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $completedId = \App\Models\TaskStatus::query()->where('name', 'Zakończone')->value('id');

        Task::create([
            'title' => 'Aktywne na kanbanie',
            'status_id' => Task::getDefaultStatusId(),
            'priority' => 'normal',
            'author_id' => $user->id,
            'assignee_id' => $user->id,
        ]);

        Task::create([
            'title' => 'Zakończone na kanbanie',
            'status_id' => $completedId,
            'priority' => 'normal',
            'author_id' => $user->id,
            'assignee_id' => $user->id,
        ]);

        $tasks = Livewire::actingAs($user)
            ->test(TasksKanbanBoardPage::class)
            ->assertSet('showFinishedTasks', true)
            ->instance()
            ->tasks();

        $this->assertCount(2, $tasks);
        $this->assertTrue($tasks->pluck('title')->contains('Zakończone na kanbanie'));
    }

    public function test_kanban_can_hide_finished_tasks_when_filter_disabled(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $completedId = \App\Models\TaskStatus::query()->where('name', 'Zakończone')->value('id');

        Task::create([
            'title' => 'Aktywne na kanbanie',
            'status_id' => Task::getDefaultStatusId(),
            'priority' => 'normal',
            'author_id' => $user->id,
            'assignee_id' => $user->id,
        ]);

        Task::create([
            'title' => 'Zakończone na kanbanie',
            'status_id' => $completedId,
            'priority' => 'normal',
            'author_id' => $user->id,
            'assignee_id' => $user->id,
        ]);

        $tasks = Livewire::actingAs($user)
            ->test(TasksKanbanBoardPage::class)
            ->set('showFinishedTasks', false)
            ->instance()
            ->tasks();

        $this->assertCount(1, $tasks);
        $this->assertSame('Aktywne na kanbanie', $tasks->first()->title);
    }

    public function test_open_edit_task_modal_mounts_edit_action(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $task = Task::create([
            'title' => 'Zadanie kanban',
            'due_date' => now()->addDay(),
            'status_id' => Task::getDefaultStatusId(),
            'priority' => 'normal',
            'author_id' => $user->id,
        ]);

        Livewire::actingAs($user)
            ->test(TasksKanbanBoardPage::class)
            ->call('openEditTaskModal', $task->id)
            ->assertSet('editingTaskId', $task->id)
            ->assertSet('mountedActions', ['editTask']);
    }

    public function test_task_modal_context_links_include_contractor(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $contractor = Contractor::create(['name' => 'Hotel Test', 'status' => 'active']);

        $task = Task::create([
            'title' => 'Zadanie z kontekstem',
            'due_date' => now()->addDay(),
            'status_id' => Task::getDefaultStatusId(),
            'priority' => 'normal',
            'author_id' => $user->id,
            'taskable_type' => Contractor::class,
            'taskable_id' => $contractor->id,
        ]);

        $links = \App\Support\Tasks\TaskContextRegistry::linksForTask($task->fresh(['taskable']));

        $this->assertNotEmpty($links);
        $this->assertSame('Kontrahent: Hotel Test', $links[0]['label'] ?? null);
    }

    public function test_kanban_card_shows_description_context_and_comment_count(): void
    {
        $user = User::factory()->create(['name' => 'Anna Kanban']);
        $user->assignRole('admin');

        $contractor = Contractor::create(['name' => 'Hotel Alpejski', 'status' => 'active']);

        $task = Task::create([
            'title' => 'Zadanie z treścią na kanbanie',
            'description' => 'Pełna treść zadania do podglądu na karcie kanban.',
            'status_id' => Task::getDefaultStatusId(),
            'priority' => 'normal',
            'author_id' => $user->id,
            'assignee_id' => $user->id,
            'taskable_type' => Contractor::class,
            'taskable_id' => $contractor->id,
        ]);

        \App\Models\TaskComment::query()->create([
            'task_id' => $task->id,
            'user_id' => $user->id,
            'content' => 'Pierwszy komentarz na karcie.',
        ]);

        \App\Models\TaskComment::query()->create([
            'task_id' => $task->id,
            'user_id' => $user->id,
            'content' => 'Drugi komentarz — powinien być widoczny jako ostatni.',
        ]);

        $component = Livewire::actingAs($user)
            ->test(TasksKanbanBoardPage::class);

        $tasks = $component->instance()->tasks();
        $loaded = $tasks->firstWhere('id', $task->id);

        $this->assertNotNull($loaded);
        $this->assertSame(2, (int) $loaded->comments_count);
        $this->assertCount(2, $loaded->comments);
        $this->assertStringContainsString('Pełna treść zadania', (string) $loaded->description);
        $this->assertStringContainsString('Hotel Alpejski', $loaded->task_context_label);

        $component
            ->assertSee('Pełna treść zadania do podglądu na karcie kanban.', false)
            ->assertSee('Od Anna Kanban dla Anna Kanban', false)
            ->assertSee('Kontrahent', false)
            ->assertSee('Hotel Alpejski', false)
            ->assertSee('Drugi komentarz — powinien być widoczny jako ostatni.', false)
            ->assertSee('Wątek komentarzy (2)', false);
    }
}
