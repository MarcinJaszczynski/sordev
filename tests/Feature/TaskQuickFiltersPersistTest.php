<?php

namespace Tests\Feature;

use App\Filament\Resources\EventResource\Pages\ManageEventTasks;
use App\Filament\Resources\TaskResource\Pages\ListTasks;
use App\Filament\Resources\TaskResource\Pages\TasksKanbanBoardPage;
use App\Filament\Resources\TaskResource\RelationManagers\TasksRelationManager;
use App\Models\Event;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TaskQuickFiltersPersistTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\TaskStatusSeeder::class);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    }

    public function test_list_persists_quick_filters_on_user_after_session_flush(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        Livewire::actingAs($user)
            ->test(ListTasks::class)
            ->call('setTasksScope', 'for_me')
            ->call('setDueFilter', 'overdue')
            ->call('setSourceFilter', 'office')
            ->set('tasksOnlyUrgent', true)
            ->assertSet('tasksScope', 'for_me')
            ->assertSet('dueFilter', 'overdue')
            ->assertSet('sourceFilter', 'office')
            ->assertSet('tasksOnlyUrgent', true);

        $user->refresh();
        session()->flush();

        Livewire::actingAs($user)
            ->test(ListTasks::class)
            ->assertSet('tasksScope', 'for_me')
            ->assertSet('dueFilter', 'overdue')
            ->assertSet('sourceFilter', 'office')
            ->assertSet('tasksOnlyUrgent', true);
    }

    public function test_list_due_filter_hides_tasks_without_matching_date(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $overdue = Task::create([
            'title' => 'Zaległe',
            'status_id' => Task::getDefaultStatusId(),
            'priority' => 'normal',
            'source' => 'office',
            'author_id' => $user->id,
            'assignee_id' => $user->id,
            'due_date' => now()->subDay(),
        ]);

        $future = Task::create([
            'title' => 'Przyszłe',
            'status_id' => Task::getDefaultStatusId(),
            'priority' => 'normal',
            'source' => 'office',
            'author_id' => $user->id,
            'assignee_id' => $user->id,
            'due_date' => now()->addWeek(),
        ]);

        Livewire::actingAs($user)
            ->test(ListTasks::class)
            ->call('setDueFilter', 'overdue')
            ->assertCanSeeTableRecords([$overdue])
            ->assertCanNotSeeTableRecords([$future]);
    }

    public function test_kanban_persists_quick_filters_on_user(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        Livewire::actingAs($user)
            ->test(TasksKanbanBoardPage::class)
            ->call('setTasksScope', 'authored')
            ->call('setDueFilter', 'today')
            ->call('setSourceFilter', 'system');

        $user->refresh();
        session()->flush();

        Livewire::actingAs($user)
            ->test(TasksKanbanBoardPage::class)
            ->assertSet('tasksScope', 'authored')
            ->assertSet('dueFilter', 'today')
            ->assertSet('sourceFilter', 'system');
    }

    public function test_event_tasks_persist_and_show_chip_filters(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');
        $event = Event::factory()->create();

        Livewire::actingAs($user)
            ->test(TasksRelationManager::class, [
                'ownerRecord' => $event,
                'pageClass' => ManageEventTasks::class,
            ])
            ->assertSet('tasksScope', 'all')
            ->assertSee('Widoczność')
            ->assertSee('Termin')
            ->assertSee('Źródło')
            ->call('setTasksScope', 'for_me')
            ->call('setDueFilter', 'this_week')
            ->assertSet('tasksScope', 'for_me')
            ->assertSet('dueFilter', 'this_week');

        $user->refresh();
        session()->flush();

        Livewire::actingAs($user)
            ->test(TasksRelationManager::class, [
                'ownerRecord' => $event,
                'pageClass' => ManageEventTasks::class,
            ])
            ->assertSet('tasksScope', 'for_me')
            ->assertSet('dueFilter', 'this_week');
    }
}
