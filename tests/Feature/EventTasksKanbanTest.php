<?php

namespace Tests\Feature;

use App\Filament\Resources\TaskResource\Pages\TasksKanbanBoardPage;
use App\Models\Event;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventTasksKanbanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\TaskStatusSeeder::class);

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    }

    public function test_kanban_filters_tasks_by_event_query_param(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $event = Event::factory()->create();
        $otherEvent = Event::factory()->create();

        $eventTask = Task::create([
            'title' => 'Zadanie imprezy A',
            'status_id' => Task::getDefaultStatusId(),
            'priority' => 'normal',
            'author_id' => $user->id,
            'assignee_id' => $user->id,
            'taskable_type' => Event::class,
            'taskable_id' => $event->id,
        ]);

        Task::create([
            'title' => 'Zadanie imprezy B',
            'status_id' => Task::getDefaultStatusId(),
            'priority' => 'normal',
            'author_id' => $user->id,
            'assignee_id' => $user->id,
            'taskable_type' => Event::class,
            'taskable_id' => $otherEvent->id,
        ]);

        $tasks = Livewire::actingAs($user)
            ->withQueryParams(['event' => $event->id])
            ->test(TasksKanbanBoardPage::class)
            ->assertSet('eventFilter', $event->id)
            ->instance()
            ->tasks();

        $this->assertTrue($tasks->contains('id', $eventTask->id));
        $this->assertCount(1, $tasks);
    }

    public function test_kanban_shows_subtasks_for_event(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $event = Event::factory()->create();

        $parent = Task::create([
            'title' => 'Rodzic',
            'status_id' => Task::getDefaultStatusId(),
            'priority' => 'normal',
            'author_id' => $user->id,
            'assignee_id' => $user->id,
            'taskable_type' => Event::class,
            'taskable_id' => $event->id,
        ]);

        $subtask = Task::create([
            'title' => 'Podzadanie',
            'status_id' => Task::getDefaultStatusId(),
            'priority' => 'normal',
            'author_id' => $user->id,
            'assignee_id' => $user->id,
            'parent_id' => $parent->id,
            'taskable_type' => Event::class,
            'taskable_id' => $event->id,
        ]);

        $tasks = Livewire::actingAs($user)
            ->withQueryParams(['event' => $event->id])
            ->test(TasksKanbanBoardPage::class)
            ->instance()
            ->tasks();

        $this->assertTrue($tasks->contains('id', $parent->id));
        $this->assertTrue($tasks->contains('id', $subtask->id));
    }
}
