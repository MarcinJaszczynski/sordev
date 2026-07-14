<?php

namespace Tests\Feature;

use App\Filament\Resources\TaskResource\Pages\ListTasks;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TaskArchiveBulkActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\TaskStatusSeeder::class);

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    }

    public function test_archive_bulk_action_moves_finished_tasks_to_archived_status(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $finishedId = TaskStatus::query()->where('name', 'Zakończone')->value('id');
        $archivedId = TaskStatus::query()->where('name', 'Zarchiwizowane')->value('id');

        $finishedTask = Task::create([
            'title' => 'Do archiwizacji',
            'status_id' => $finishedId,
            'priority' => 'normal',
            'author_id' => $user->id,
            'assignee_id' => $user->id,
        ]);

        Livewire::actingAs($user)
            ->test(ListTasks::class)
            ->call('setTasksScope', 'all')
            ->set('activeTab', 'all')
            ->filterTable('finished_visibility', true)
            ->callTableBulkAction('archive', [$finishedTask]);

        $this->assertSame((int) $archivedId, (int) $finishedTask->fresh()->status_id);
    }

    public function test_archived_tasks_are_hidden_from_default_list(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $archivedId = TaskStatus::query()->where('name', 'Zarchiwizowane')->value('id');

        $archivedTask = Task::create([
            'title' => 'Zarchiwizowane zadanie',
            'status_id' => $archivedId,
            'priority' => 'normal',
            'author_id' => $user->id,
            'assignee_id' => $user->id,
        ]);

        Livewire::actingAs($user)
            ->test(ListTasks::class)
            ->call('setTasksScope', 'all')
            ->assertCanNotSeeTableRecords([$archivedTask]);
    }
}
