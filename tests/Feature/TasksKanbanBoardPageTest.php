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

        Task::create([
            'title' => 'Obce zadanie',
            'status_id' => Task::getDefaultStatusId(),
            'priority' => 'normal',
            'author_id' => $other->id,
            'assignee_id' => $other->id,
        ]);

        $tasks = Livewire::actingAs($user)
            ->test(TasksKanbanBoardPage::class)
            ->assertSet('tasksScope', 'assigned')
            ->instance()
            ->tasks();

        // Scope "assigned" = Moje (assignee OR author), nie wyłącznie assignee_id.
        $this->assertTrue($tasks->contains('id', $assigned->id));
        $this->assertTrue($tasks->contains('id', $authored->id));
        $this->assertFalse($tasks->contains('title', 'Obce zadanie'));
        $this->assertCount(2, $tasks);
    }

    public function test_kanban_can_switch_to_authored_and_all_scopes(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');
        $other = User::factory()->create();

        Task::create([
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

        $authoredTasks = Livewire::actingAs($user)
            ->test(TasksKanbanBoardPage::class)
            ->call('setTasksScope', 'authored')
            ->instance()
            ->tasks();

        $this->assertCount(1, $authoredTasks);
        $this->assertSame($authored->id, $authoredTasks->first()->id);

        $allTasks = Livewire::actingAs($user)
            ->test(TasksKanbanBoardPage::class)
            ->call('setTasksScope', 'all')
            ->instance()
            ->tasks();

        $this->assertCount(2, $allTasks);
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

    public function test_kanban_hides_finished_tasks_by_default(): void
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
            ->assertSet('showFinishedTasks', false)
            ->instance()
            ->tasks();

        $this->assertCount(1, $tasks);
        $this->assertSame('Aktywne na kanbanie', $tasks->first()->title);
    }

    public function test_kanban_can_show_finished_tasks_when_filter_enabled(): void
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
            ->set('showFinishedTasks', true)
            ->instance()
            ->tasks();

        $this->assertCount(2, $tasks);
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
        $this->assertSame('Kontrahent', $links[0]['label'] ?? null);
    }
}
