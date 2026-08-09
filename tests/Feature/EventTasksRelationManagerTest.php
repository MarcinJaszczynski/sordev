<?php

namespace Tests\Feature;

use App\Filament\Resources\EventResource\Pages\ManageEventTasks;
use App\Filament\Resources\TaskResource\RelationManagers\TasksRelationManager;
use App\Models\Event;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventTasksRelationManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\TaskStatusSeeder::class);

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    }

    public function test_event_tasks_relation_manager_renders_without_server_error(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $event = Event::factory()->create();

        Livewire::actingAs($user)
            ->test(TasksRelationManager::class, [
                'ownerRecord' => $event,
                'pageClass' => ManageEventTasks::class,
            ])
            ->assertSuccessful();

        Livewire::actingAs($user)
            ->test(ManageEventTasks::class, ['record' => $event->getKey()])
            ->assertSuccessful();
    }

    public function test_event_tasks_use_admin_list_layout_and_open_create_modal(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $event = Event::factory()->create();

        Livewire::actingAs($user)
            ->test(TasksRelationManager::class, [
                'ownerRecord' => $event,
                'pageClass' => ManageEventTasks::class,
            ])
            ->assertSee('Dodaj zadanie')
            ->assertSee('Moje')
            ->call('openCreateTaskModal', [
                'taskable_type' => Event::class,
                'taskable_id' => $event->id,
            ])
            ->assertSet('mountedActions', ['createTask']);
    }

    public function test_event_tasks_create_deep_link_opens_modal_with_prefilled_context(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $event = Event::factory()->create();

        Livewire::actingAs($user)
            ->withQueryParams([
                'createTask' => 1,
                'taskable_type' => Event::class,
                'taskable_id' => $event->id,
            ])
            ->test(TasksRelationManager::class, [
                'ownerRecord' => $event,
                'pageClass' => ManageEventTasks::class,
            ])
            ->assertSet('mountedActions', ['createTask']);
    }

    public function test_event_tasks_edit_deep_link_opens_modal(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $event = Event::factory()->create();
        $task = Task::factory()->create([
            'taskable_type' => Event::class,
            'taskable_id' => $event->id,
            'author_id' => $user->id,
            'assignee_id' => $user->id,
            'status_id' => Task::getDefaultStatusId(),
        ]);

        Livewire::actingAs($user)
            ->withQueryParams(['editTask' => $task->id])
            ->test(TasksRelationManager::class, [
                'ownerRecord' => $event,
                'pageClass' => ManageEventTasks::class,
            ])
            ->assertSet('editingTaskId', $task->id)
            ->assertSet('mountedActions', ['editTask']);
    }

    public function test_event_tasks_edit_button_opens_page_modal(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $event = Event::factory()->create();
        $task = Task::factory()->create([
            'taskable_type' => Event::class,
            'taskable_id' => $event->id,
            'author_id' => $user->id,
            'assignee_id' => $user->id,
            'status_id' => Task::getDefaultStatusId(),
            'title' => 'Do edycji z przycisku',
        ]);

        Livewire::actingAs($user)
            ->test(TasksRelationManager::class, [
                'ownerRecord' => $event,
                'pageClass' => ManageEventTasks::class,
            ])
            ->call('mountTableAction', 'edit', (string) $task->getKey())
            ->assertSet('editingTaskId', $task->id)
            ->assertSet('mountedActions', ['editTask'])
            ->assertSee('Do edycji z przycisku');
    }
}