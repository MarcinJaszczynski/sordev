<?php

namespace Tests\Feature;

use App\Enums\TaskSource;
use App\Filament\Resources\TaskResource\Pages\EditTask;
use App\Models\Event;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EditPilotChecklistTaskTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\TaskStatusSeeder::class);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    }

    public function test_admin_can_open_edit_page_for_pilot_checklist_task(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $event = Event::factory()->create();
        $task = Task::create([
            'title' => 'Potwierdzić autokar i dane kierowcy',
            'status_id' => Task::getDefaultStatusId(),
            'priority' => 'normal',
            'author_id' => $user->id,
            'assignee_id' => $user->id,
            'source' => TaskSource::PilotChecklist->value,
            'taskable_type' => Event::class,
            'taskable_id' => $event->id,
        ]);

        Livewire::actingAs($user)
            ->test(EditTask::class, ['record' => $task->id])
            ->assertRedirect(\App\Support\Tasks\TaskNavigation::fullViewUrl($task));
    }
}
