<?php

namespace Tests\Feature;

use App\Filament\Resources\TaskResource\Pages\EditTask;
use App\Filament\Resources\TaskResource\RelationManagers\SubtasksRelationManager;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SubtasksRelationManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\TaskStatusSeeder::class);

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    }

    public function test_can_create_subtask_with_assignee_without_manual_status_selection(): void
    {
        $author = User::factory()->create();
        $author->assignRole('admin');
        $assignee = User::factory()->create();

        $parent = Task::create([
            'title' => 'Zadanie nadrzędne',
            'status_id' => Task::getDefaultStatusId(),
            'priority' => 'normal',
            'author_id' => $author->id,
        ]);

        Livewire::actingAs($author)
            ->test(SubtasksRelationManager::class, [
                'ownerRecord' => $parent,
                'pageClass' => EditTask::class,
            ])
            ->callTableAction('create', data: [
                'title' => 'Podzadanie z przypisaniem',
                'status_id' => Task::getDefaultStatusId(),
                'priority' => 'normal',
                'assignee_id' => $assignee->id,
            ])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('tasks', [
            'title' => 'Podzadanie z przypisaniem',
            'parent_id' => $parent->id,
            'assignee_id' => $assignee->id,
            'author_id' => $author->id,
            'source' => 'office',
        ]);
    }
}
