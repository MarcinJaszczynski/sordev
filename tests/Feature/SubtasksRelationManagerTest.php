<?php

namespace Tests\Feature;

use App\Filament\Resources\TaskResource\Pages\EditTask;
use App\Filament\Resources\TaskResource\RelationManagers\SubtasksRelationManager;
use App\Models\Task;
use App\Models\User;
use App\Services\NotificationService;
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

    public function test_creating_subtask_bumps_assignee_topbar_task_count(): void
    {
        $author = User::factory()->create();
        $author->assignRole('admin');
        $assignee = User::factory()->create();

        $parent = Task::create([
            'title' => 'Zadanie nadrzędne',
            'status_id' => Task::getDefaultStatusId(),
            'priority' => 'normal',
            'author_id' => $author->id,
            'assignee_id' => $assignee->id,
        ]);
        $parent->forceFill([
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ])->saveQuietly();

        NotificationService::markTaskAsRead($assignee->id, $parent->fresh());
        NotificationService::clearCacheForUser($assignee->id);

        $before = NotificationService::getTopbarDataForUser($assignee->id, fresh: true);
        $this->assertSame(0, $before['counts']['tasks']);

        Livewire::actingAs($author)
            ->test(SubtasksRelationManager::class, [
                'ownerRecord' => $parent->fresh(),
                'pageClass' => EditTask::class,
            ])
            ->callTableAction('create', data: [
                'title' => 'Nowe podzadanie topbar',
                'status_id' => Task::getDefaultStatusId(),
                'assignee_id' => $assignee->id,
            ])
            ->assertHasNoTableActionErrors();

        $after = NotificationService::getTopbarDataForUser($assignee->id, fresh: true);

        $this->assertSame(1, $after['counts']['tasks']);
        $taskItems = collect($after['items_by_type']['task']);
        $this->assertTrue(
            $taskItems->contains(fn (array $row): bool => ($row['title'] ?? '') === 'Nowe podzadanie topbar')
        );
        $this->assertFalse(
            $taskItems->contains(fn (array $row): bool => ($row['title'] ?? '') === 'Zadanie nadrzędne')
        );

        $subtask = Task::query()->where('title', 'Nowe podzadanie topbar')->first();
        $this->assertNotNull($subtask);
        $subtaskItem = $taskItems->first(fn (array $row): bool => ($row['title'] ?? '') === 'Nowe podzadanie topbar');
        $this->assertStringContainsString('editTask='.$subtask->id, (string) ($subtaskItem['url'] ?? ''));
        $this->assertStringContainsString('Podzadanie → Zadanie nadrzędne', (string) ($subtaskItem['meta'] ?? ''));
    }
}
