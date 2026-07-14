<?php

namespace Tests\Unit;

use App\Models\Task;
use App\Models\User;
use App\Support\Tasks\TaskQueryFilters;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskOwnershipScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_assigned_scope_includes_self_assigned_tasks(): void
    {
        $this->seed(\Database\Seeders\TaskStatusSeeder::class);

        $user = User::factory()->create();

        $task = Task::factory()->create([
            'author_id' => $user->id,
            'assignee_id' => $user->id,
            'title' => 'Zadanie dla siebie',
        ]);

        $ids = TaskQueryFilters::applyOwnershipScope(Task::query(), 'assigned', $user->id)
            ->pluck('id')
            ->all();

        $this->assertContains($task->id, $ids);
    }
}
