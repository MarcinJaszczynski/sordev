<?php

namespace Tests\Feature;

use App\Filament\Resources\TaskResource\Pages\ListTasks;
use App\Models\Task;
use App\Models\User;
use App\Services\Tasks\TaskInboxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ListTasksTabsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\TaskStatusSeeder::class);

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    }

    public function test_default_scope_is_assigned_and_includes_assignee_or_author(): void
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

        Livewire::actingAs($user)
            ->test(ListTasks::class)
            ->assertSet('activeTab', 'active')
            ->assertSet('tasksScope', 'assigned')
            ->assertCanSeeTableRecords([$assigned, $authored])
            ->assertCountTableRecords(2);
    }

    public function test_list_can_show_all_tasks_when_scope_is_all(): void
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

        Task::create([
            'title' => 'Zlecone przeze mnie',
            'status_id' => Task::getDefaultStatusId(),
            'priority' => 'normal',
            'author_id' => $user->id,
            'assignee_id' => $other->id,
        ]);

        Livewire::actingAs($user)
            ->test(ListTasks::class)
            ->call('setTasksScope', 'all')
            ->assertCountTableRecords(2);
    }

    public function test_new_tab_shows_only_open_todo_tasks_for_user(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $todo = Task::create([
            'title' => 'Do zrobienia',
            'status_id' => Task::getDefaultStatusId(),
            'priority' => 'normal',
            'author_id' => $user->id,
            'assignee_id' => $user->id,
        ]);

        $inProgressStatusId = \App\Models\TaskStatus::query()
            ->where('name', 'W trakcie')
            ->value('id');

        Task::create([
            'title' => 'W trakcie',
            'status_id' => $inProgressStatusId,
            'priority' => 'normal',
            'author_id' => $user->id,
            'assignee_id' => $user->id,
        ]);

        Livewire::actingAs($user)
            ->test(ListTasks::class)
            ->set('activeTab', 'new')
            ->assertCanSeeTableRecords([$todo])
            ->assertCountTableRecords(1);
    }

    public function test_opening_list_marks_tasks_as_seen(): void
    {
        $user = User::factory()->create([
            'tasks_last_seen_at' => now()->subDay(),
        ]);
        $user->assignRole('admin');

        Task::create([
            'title' => 'Nowe zadanie',
            'status_id' => Task::getDefaultStatusId(),
            'priority' => 'normal',
            'author_id' => $user->id,
        ]);

        $this->assertSame(1, app(TaskInboxService::class)->unseenCount($user));

        Livewire::actingAs($user)
            ->test(ListTasks::class);

        $this->assertSame(0, app(TaskInboxService::class)->unseenCount($user->fresh()));
    }

    public function test_list_shows_subtasks_alongside_parent_tasks(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $parent = Task::create([
            'title' => 'Zadanie nadrzędne',
            'status_id' => Task::getDefaultStatusId(),
            'priority' => 'normal',
            'author_id' => $user->id,
            'assignee_id' => $user->id,
        ]);

        $subtask = Task::create([
            'title' => 'Podzadanie',
            'status_id' => Task::getDefaultStatusId(),
            'priority' => 'normal',
            'author_id' => $user->id,
            'assignee_id' => $user->id,
            'parent_id' => $parent->id,
        ]);

        Livewire::actingAs($user)
            ->test(ListTasks::class)
            ->call('setTasksScope', 'all')
            ->assertCanSeeTableRecords([$parent, $subtask]);
    }
}
