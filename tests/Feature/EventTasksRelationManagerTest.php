<?php

namespace Tests\Feature;

use App\Filament\Resources\EventResource\Pages\ManageEventTasks;
use App\Filament\Resources\TaskResource\RelationManagers\TasksRelationManager;
use App\Models\Event;
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
}
