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

    /**
     * Regresja: skrypt scroll jako osobny root Livewire sprawiał, że wire:click="selectTask"
     * lądował na ManageEventTasks (bez metody) zamiast na TasksRelationManager.
     */
    public function test_event_tasks_split_view_keeps_single_livewire_root_around_select_task(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $event = Event::factory()->create();
        $task = Task::factory()->create([
            'title' => 'Split root probe',
            'taskable_type' => Event::class,
            'taskable_id' => $event->id,
            'author_id' => $user->id,
            'assignee_id' => $user->id,
            'status_id' => Task::getDefaultStatusId(),
        ]);

        $html = Livewire::actingAs($user)
            ->test(ManageEventTasks::class, ['record' => $event->getKey()])
            ->assertSeeHtml('selectTask('.$task->id.')')
            ->html();

        $this->assertDoesNotMatchRegularExpression(
            '/<script\b[^>]*\bwire:id=/i',
            $html,
            'wire:id nie może być na <script> — wtedy selectTask wychodzi poza RM',
        );

        $this->assertMatchesRegularExpression(
            '/wire:id="[^"]+"[^>]*\bclass="[^"]*\btasks-split-view\b/s',
            $html,
            'Root RM musi być div.tasks-split-view obejmującym tabelę',
        );
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
            ->assertSet('selectedTaskId', $task->id)
            ->assertSet('mountedActions', []);
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
            ->assertSet('selectedTaskId', $task->id)
            ->assertSet('mountedActions', []);
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
            ->call('openEditTaskModal', $task->id)
            ->assertSet('selectedTaskId', $task->id)
            ->assertSet('mountedActions', [])
            ->assertSee('Do edycji z przycisku')
            ->assertSee('Zapisz');
    }

    public function test_event_tasks_show_finished_by_default_and_ownership_filters(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');
        $other = User::factory()->create();

        $event = Event::factory()->create();
        $finishedStatusId = \App\Models\TaskStatus::query()->where('name', 'Zakończone')->value('id');

        $openMine = Task::factory()->create([
            'title' => 'Otwarte moje imprezy',
            'taskable_type' => Event::class,
            'taskable_id' => $event->id,
            'author_id' => $user->id,
            'assignee_id' => $user->id,
            'status_id' => Task::getDefaultStatusId(),
        ]);

        $finishedMine = Task::factory()->create([
            'title' => 'Zakończone moje imprezy',
            'taskable_type' => Event::class,
            'taskable_id' => $event->id,
            'author_id' => $user->id,
            'assignee_id' => $user->id,
            'status_id' => $finishedStatusId,
        ]);

        $authoredOnly = Task::factory()->create([
            'title' => 'Utworzone przeze mnie cudze assignee',
            'taskable_type' => Event::class,
            'taskable_id' => $event->id,
            'author_id' => $user->id,
            'assignee_id' => $other->id,
            'status_id' => Task::getDefaultStatusId(),
        ]);

        $foreign = Task::factory()->create([
            'title' => 'Obce zadanie imprezy',
            'taskable_type' => Event::class,
            'taskable_id' => $event->id,
            'author_id' => $other->id,
            'assignee_id' => $other->id,
            'status_id' => Task::getDefaultStatusId(),
        ]);

        Livewire::actingAs($user)
            ->test(TasksRelationManager::class, [
                'ownerRecord' => $event,
                'pageClass' => ManageEventTasks::class,
            ])
            ->assertCanSeeTableRecords([$openMine, $finishedMine, $authoredOnly, $foreign])
            ->call('setTasksScope', 'assigned')
            ->assertCanSeeTableRecords([$openMine, $finishedMine, $authoredOnly])
            ->assertCanNotSeeTableRecords([$foreign])
            ->call('setTasksScope', 'for_me')
            ->assertCanSeeTableRecords([$openMine, $finishedMine])
            ->assertCanNotSeeTableRecords([$authoredOnly, $foreign]);
    }

    public function test_event_tasks_list_shows_from_and_to_ownership(): void
    {
        $author = User::factory()->create(['name' => 'Ewa Impreza']);
        $author->assignRole('admin');
        $assignee = User::factory()->create(['name' => 'Piotr Impreza']);

        $event = Event::factory()->create();
        Task::factory()->create([
            'title' => 'Zadanie imprezy z nadawcą',
            'taskable_type' => Event::class,
            'taskable_id' => $event->id,
            'author_id' => $author->id,
            'assignee_id' => $assignee->id,
            'status_id' => Task::getDefaultStatusId(),
        ]);

        Livewire::actingAs($author)
            ->test(TasksRelationManager::class, [
                'ownerRecord' => $event,
                'pageClass' => ManageEventTasks::class,
            ])
            ->assertSee('Od Ewa Impreza dla Piotr Impreza');
    }
}
