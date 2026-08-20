<?php

namespace Tests\Feature;

use App\Filament\Pages\OperationsCalendarPage;
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

/**
 * Regresja: wszystkie ścieżki otwierania edycji zadania (modal).
 * Uruchamiaj po zmianach w topbarze / TaskResource / RelationManagerach.
 */
class TaskEditModalOpeningTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Task $task;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\TaskStatusSeeder::class);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $this->user = User::factory()->create();
        $this->user->assignRole('admin');

        $this->task = Task::factory()->create([
            'title' => 'Modal regression task',
            'author_id' => $this->user->id,
            'assignee_id' => $this->user->id,
            'status_id' => Task::getDefaultStatusId(),
        ]);
    }

    public function test_list_tasks_open_edit_modal_via_livewire_method(): void
    {
        Livewire::actingAs($this->user)
            ->test(ListTasks::class)
            ->call('openEditTaskModal', $this->task->id)
            ->assertSet('editingTaskId', $this->task->id)
            ->assertSet('mountedActions', ['editTask'])
            ->assertSee('Modal regression task');
    }

    public function test_list_tasks_deep_link_opens_edit_modal(): void
    {
        Livewire::actingAs($this->user)
            ->withQueryParams(['editTask' => $this->task->id])
            ->test(ListTasks::class)
            ->assertSet('editingTaskId', $this->task->id)
            ->assertSet('mountedActions', ['editTask'])
            ->assertSee('Modal regression task');
    }

    public function test_list_tasks_title_cell_wires_open_edit_modal(): void
    {
        Livewire::actingAs($this->user)
            ->test(ListTasks::class)
            ->assertSeeHtml('openEditTaskModal('.$this->task->id.')');
    }

    public function test_list_tasks_edit_table_action_opens_page_modal(): void
    {
        // callTableAction() asertuje brak open-modal przy akcji bez modala tabeli;
        // my odpalamy page modal editTask, więc wołamy mountTableAction bezpośrednio.
        Livewire::actingAs($this->user)
            ->test(ListTasks::class)
            ->call('mountTableAction', 'edit', (string) $this->task->getKey())
            ->assertSet('editingTaskId', $this->task->id)
            ->assertSet('mountedActions', ['editTask'])
            ->assertSee('Modal regression task');
    }

    public function test_list_tasks_create_modal_opens(): void
    {
        Livewire::actingAs($this->user)
            ->test(ListTasks::class)
            ->mountAction('createTask')
            ->assertSet('mountedActions', ['createTask']);
    }

    public function test_event_tasks_open_edit_modal_via_livewire_method(): void
    {
        $event = Event::factory()->create();
        $task = Task::factory()->create([
            'title' => 'Event modal task',
            'taskable_type' => Event::class,
            'taskable_id' => $event->id,
            'author_id' => $this->user->id,
            'assignee_id' => $this->user->id,
            'status_id' => Task::getDefaultStatusId(),
        ]);

        Livewire::actingAs($this->user)
            ->test(TasksRelationManager::class, [
                'ownerRecord' => $event,
                'pageClass' => ManageEventTasks::class,
            ])
            ->call('openEditTaskModal', $task->id)
            ->assertSet('editingTaskId', $task->id)
            ->assertSet('mountedActions', ['editTask'])
            ->assertSee('Event modal task');
    }

    public function test_event_tasks_deep_link_opens_edit_modal(): void
    {
        $event = Event::factory()->create();
        $task = Task::factory()->create([
            'title' => 'Event deep link task',
            'taskable_type' => Event::class,
            'taskable_id' => $event->id,
            'author_id' => $this->user->id,
            'assignee_id' => $this->user->id,
            'status_id' => Task::getDefaultStatusId(),
        ]);

        Livewire::actingAs($this->user)
            ->withQueryParams(['editTask' => $task->id])
            ->test(TasksRelationManager::class, [
                'ownerRecord' => $event,
                'pageClass' => ManageEventTasks::class,
            ])
            ->assertSet('editingTaskId', $task->id)
            ->assertSet('mountedActions', ['editTask'])
            ->assertSee('Event deep link task');
    }

    public function test_event_tasks_edit_table_action_opens_page_modal(): void
    {
        $event = Event::factory()->create();
        $task = Task::factory()->create([
            'title' => 'Event edit button task',
            'taskable_type' => Event::class,
            'taskable_id' => $event->id,
            'author_id' => $this->user->id,
            'assignee_id' => $this->user->id,
            'status_id' => Task::getDefaultStatusId(),
        ]);

        Livewire::actingAs($this->user)
            ->test(TasksRelationManager::class, [
                'ownerRecord' => $event,
                'pageClass' => ManageEventTasks::class,
            ])
            ->call('mountTableAction', 'edit', (string) $task->getKey())
            ->assertSet('editingTaskId', $task->id)
            ->assertSet('mountedActions', ['editTask'])
            ->assertSee('Event edit button task');
    }

    public function test_event_tasks_create_modal_opens(): void
    {
        $event = Event::factory()->create();

        Livewire::actingAs($this->user)
            ->test(TasksRelationManager::class, [
                'ownerRecord' => $event,
                'pageClass' => ManageEventTasks::class,
            ])
            ->call('openCreateTaskModal', [
                'taskable_type' => Event::class,
                'taskable_id' => $event->id,
            ])
            ->assertSet('mountedActions', ['createTask']);
    }

    public function test_event_tasks_relation_manager_is_not_lazy(): void
    {
        $this->assertFalse(TasksRelationManager::isLazy());
    }

    public function test_kanban_open_edit_modal(): void
    {
        Livewire::actingAs($this->user)
            ->test(TasksKanbanBoardPage::class)
            ->call('openEditTaskModal', $this->task->id)
            ->assertSet('editingTaskId', $this->task->id)
            ->assertSet('mountedActions', ['editTask'])
            ->assertSee('Modal regression task');
    }

    public function test_operations_calendar_deep_link_opens_edit_modal(): void
    {
        Livewire::actingAs($this->user)
            ->withQueryParams(['editTask' => $this->task->id])
            ->test(OperationsCalendarPage::class)
            ->assertSet('editingTaskId', $this->task->id)
            ->assertSet('mountedActions', ['editTask']);
    }

    public function test_list_tasks_comment_quick_action_modal_opens(): void
    {
        Livewire::actingAs($this->user)
            ->test(ListTasks::class)
            ->call('openAddCommentModal', $this->task->id)
            ->assertSet('commentingTaskId', $this->task->id)
            ->assertSet('mountedActions', ['addComment']);
    }

    public function test_list_tasks_attachment_quick_action_modal_opens(): void
    {
        Livewire::actingAs($this->user)
            ->test(ListTasks::class)
            ->call('openAddAttachmentModal', $this->task->id)
            ->assertSet('attachingTaskId', $this->task->id)
            ->assertSet('mountedActions', ['addAttachment']);
    }
}
