<?php

namespace Tests\Feature;

use App\Filament\Resources\TaskResource\Pages\ListTasks;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ListTasksModalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\TaskStatusSeeder::class);

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    }

    public function test_list_tasks_row_action_opens_edit_modal(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $task = Task::create([
            'title' => 'Zadanie z listy',
            'due_date' => now()->addDay(),
            'status_id' => Task::getDefaultStatusId(),
            'priority' => 'normal',
            'author_id' => $user->id,
        ]);

        Livewire::actingAs($user)
            ->test(ListTasks::class)
            ->call('openEditTaskModal', $task->id)
            ->assertSet('editingTaskId', $task->id)
            ->assertSet('mountedActions', ['editTask']);
    }

    public function test_edit_task_query_opens_modal_on_mount(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $task = Task::create([
            'title' => 'Deep link',
            'status_id' => Task::getDefaultStatusId(),
            'priority' => 'normal',
            'author_id' => $user->id,
        ]);

        Livewire::actingAs($user)
            ->withQueryParams(['editTask' => $task->id])
            ->test(ListTasks::class)
            ->assertSet('editingTaskId', $task->id)
            ->assertSet('mountedActions', ['editTask']);
    }

    public function test_edit_modal_with_subtasks_renders_without_nested_full_editor(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $parent = Task::create([
            'title' => 'Rodzic z podzadaniami',
            'status_id' => Task::getDefaultStatusId(),
            'priority' => 'normal',
            'author_id' => $user->id,
        ]);

        Task::create([
            'title' => 'Podzadanie w modalu',
            'status_id' => Task::getDefaultStatusId(),
            'priority' => 'normal',
            'author_id' => $user->id,
            'parent_id' => $parent->id,
        ]);

        Livewire::actingAs($user)
            ->test(ListTasks::class)
            ->call('openEditTaskModal', $parent->id)
            ->assertSee('Podzadanie w modalu')
            ->assertSee('Podzadania');
    }
}
