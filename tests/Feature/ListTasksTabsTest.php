<?php

namespace Tests\Feature;

use App\Filament\Resources\TaskResource\Pages\ListTasks;
use App\Models\Task;
use App\Models\User;
use App\Services\Tasks\TaskInboxService;
use App\Support\Tasks\TaskQueryFilters;
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
            'source' => 'office',
            'author_id' => $other->id,
            'assignee_id' => $user->id,
        ]);

        Task::create([
            'title' => 'Zlecone przeze mnie',
            'status_id' => Task::getDefaultStatusId(),
            'priority' => 'normal',
            'source' => 'office',
            'author_id' => $user->id,
            'assignee_id' => $other->id,
        ]);

        Task::create([
            'title' => 'Obce zadanie',
            'status_id' => Task::getDefaultStatusId(),
            'priority' => 'normal',
            'source' => 'office',
            'author_id' => $other->id,
            'assignee_id' => $other->id,
        ]);

        Livewire::actingAs($user)
            ->test(ListTasks::class)
            ->assertSet('activeTab', 'active')
            ->assertSet('tasksScope', 'assigned')
            ->assertCanSeeTableRecords([$assigned])
            ->assertCountTableRecords(1);
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
        $parent->forceFill([
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ])->saveQuietly();

        $subtask = Task::create([
            'title' => 'Podzadanie',
            'status_id' => Task::getDefaultStatusId(),
            'priority' => 'normal',
            'author_id' => $user->id,
            'assignee_id' => $user->id,
            'parent_id' => $parent->id,
        ]);
        $subtask->forceFill([
            'created_at' => now(),
            'updated_at' => now(),
        ])->saveQuietly();

        Livewire::actingAs($user)
            ->test(ListTasks::class)
            ->call('setTasksScope', 'all')
            ->assertCanSeeTableRecords([$parent, $subtask]);

        // Flat sort: świeższe podzadanie przed starszym rodzicem, nie „pod” nim.
        $ids = TaskQueryFilters::orderByLatestActivityDesc(Task::query())
            ->pluck('id')
            ->all();

        $this->assertSame([$subtask->id, $parent->id], array_slice($ids, 0, 2));
    }

    public function test_list_column_sorts_override_default_activity_order(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');
        $statusId = Task::getDefaultStatusId();

        $beta = Task::create([
            'title' => 'Beta zadanie',
            'status_id' => $statusId,
            'priority' => 'normal',
            'source' => 'office',
            'author_id' => $user->id,
            'assignee_id' => $user->id,
            'due_date' => now()->addDays(3),
        ]);
        $beta->forceFill([
            'created_at' => now()->subHour(),
            'updated_at' => now()->subHour(),
        ])->saveQuietly();

        $alpha = Task::create([
            'title' => 'Alpha zadanie',
            'status_id' => $statusId,
            'priority' => 'normal',
            'source' => 'office',
            'author_id' => $user->id,
            'assignee_id' => $user->id,
            'due_date' => now()->addDay(),
        ]);
        $alpha->forceFill([
            'created_at' => now(),
            'updated_at' => now(),
        ])->saveQuietly();

        Livewire::actingAs($user)
            ->test(ListTasks::class)
            ->call('setTasksScope', 'all')
            ->sortTable('task_summary', 'asc')
            ->assertCanSeeTableRecords([$alpha, $beta], inOrder: true)
            ->sortTable('due_date', 'asc')
            ->assertCanSeeTableRecords([$alpha, $beta], inOrder: true)
            ->sortTable('due_date', 'desc')
            ->assertCanSeeTableRecords([$beta, $alpha], inOrder: true)
            ->sortTable('modified_at', 'desc')
            ->assertCanSeeTableRecords([$alpha, $beta], inOrder: true);
    }
}
