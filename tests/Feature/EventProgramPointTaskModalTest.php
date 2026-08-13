<?php

namespace Tests\Feature;

use App\Filament\Resources\EventResource\Pages\EditEventProgram;
use App\Filament\Resources\EventResource\Pages\ManageEventTasks;
use App\Filament\Resources\EventResource\RelationManagers\ProgramPointsRelationManager;
use App\Filament\Resources\TaskResource\RelationManagers\TasksRelationManager;
use App\Models\Event;
use App\Models\EventProgramPoint;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventProgramPointTaskModalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\TaskStatusSeeder::class);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    }

    public function test_program_point_opens_create_task_modal_on_program_page(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $event = Event::factory()->create(['duration_days' => 5]);
        $point = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'day' => 5,
            'name' => 'Zwiedzanie',
        ]);

        Livewire::actingAs($user)
            ->test(ProgramPointsRelationManager::class, [
                'ownerRecord' => $event,
                'pageClass' => EditEventProgram::class,
            ])
            ->call('openCreateTaskForProgramPoint', $point->id)
            ->assertSet('mountedActions', ['createTask'])
            ->assertSet('pendingCreateFormData.taskable_type', EventProgramPoint::class)
            ->assertSet('pendingCreateFormData.taskable_id', $point->id);
    }

    public function test_event_tasks_list_includes_program_point_tasks_and_stays_simple(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $event = Event::factory()->create();
        $point = EventProgramPoint::factory()->create(['event_id' => $event->id]);

        Task::factory()->create([
            'title' => 'Zadanie z punktu',
            'taskable_type' => EventProgramPoint::class,
            'taskable_id' => $point->id,
            'author_id' => $user->id,
            'assignee_id' => $user->id,
            'status_id' => Task::getDefaultStatusId(),
        ]);

        Livewire::actingAs($user)
            ->test(TasksRelationManager::class, [
                'ownerRecord' => $event,
                'pageClass' => ManageEventTasks::class,
            ])
            ->assertSuccessful()
            ->assertSee('Zadanie z punktu')
            ->assertDontSee('Moje')
            ->call('openCreateTaskModal', [
                'taskable_type' => Event::class,
                'taskable_id' => $event->id,
            ])
            ->assertSet('mountedActions', ['createTask']);
    }
}
