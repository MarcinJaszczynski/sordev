<?php

namespace Tests\Feature;

use App\Enums\TaskSource;
use App\Filament\Resources\EventResource\Pages\ManageEventTasks;
use App\Filament\Resources\TaskResource\RelationManagers\TasksRelationManager;
use App\Models\Event;
use App\Models\Reservation;
use App\Models\Task;
use App\Models\User;
use App\Support\Tasks\TaskNavigation;
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
            ->assertSee('Nowe zadanie')
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

    public function test_event_workflow_bar_opens_create_task_modal_on_current_page(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $event = Event::factory()->create();

        Livewire::actingAs($user)
            ->test(\App\Filament\Resources\EventResource\Pages\EditEvent::class, [
                'record' => $event->getKey(),
            ])
            ->call('openEventCreateTaskModal')
            ->assertSet('mountedActions', ['createTask'])
            ->assertSet('pendingCreateFormData', [
                'taskable_type' => Event::class,
                'taskable_id' => $event->id,
            ]);
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

    public function test_event_tasks_deep_link_mount_hook_is_idempotent(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $event = Event::factory()->create();
        $task = Task::factory()->create([
            'title' => 'Impreza potwierdzona — lista kontrolna',
            'taskable_type' => Event::class,
            'taskable_id' => $event->id,
            'author_id' => $user->id,
            'assignee_id' => $user->id,
            'status_id' => Task::getDefaultStatusId(),
        ]);

        $component = Livewire::actingAs($user)
            ->withQueryParams(['editTask' => $task->id])
            ->test(TasksRelationManager::class, [
                'ownerRecord' => $event,
                'pageClass' => ManageEventTasks::class,
            ]);

        $component->instance()->mountInteractsWithTaskEditModal();

        $component
            ->assertSet('editingTaskId', $task->id)
            ->assertSet('mountedActions', ['editTask']);
    }

    public function test_manage_event_tasks_page_does_not_open_duplicate_modal_from_page_component(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $event = Event::factory()->create();
        $task = Task::factory()->create([
            'title' => 'Impreza potwierdzona — lista kontrolna',
            'taskable_type' => Event::class,
            'taskable_id' => $event->id,
            'author_id' => $user->id,
            'assignee_id' => $user->id,
            'status_id' => Task::getDefaultStatusId(),
        ]);

        Livewire::actingAs($user)
            ->withQueryParams(['editTask' => $task->id])
            ->test(ManageEventTasks::class, ['record' => $event->getKey()])
            ->assertSet('mountedActions', [])
            ->assertSet('editingTaskId', null);
    }

    public function test_event_tasks_include_reservation_tasks_and_resolve_navigation(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $event = Event::factory()->create();
        $reservation = Reservation::create([
            'event_id' => $event->id,
            'status' => 'pending',
            'booking_reference' => 'RES-TEST-1',
            'created_by' => $user->id,
        ]);

        $task = Task::factory()->create([
            'title' => 'Potwierdź rezerwację testową',
            'taskable_type' => Reservation::class,
            'taskable_id' => $reservation->id,
            'author_id' => $user->id,
            'assignee_id' => $user->id,
            'source' => TaskSource::System->value,
            'status_id' => Task::getDefaultStatusId(),
        ]);

        Livewire::actingAs($user)
            ->test(TasksRelationManager::class, [
                'ownerRecord' => $event,
                'pageClass' => ManageEventTasks::class,
            ])
            ->assertCanSeeTableRecords([$task])
            ->assertSee('Systemowe');

        $this->assertSame($event->id, TaskNavigation::resolveEvent($task)?->id);
        $this->assertStringContainsString('/events/'.$event->id.'/tasks', TaskNavigation::fullViewUrl($task));
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
