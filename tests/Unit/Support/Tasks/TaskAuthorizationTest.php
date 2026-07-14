<?php

namespace Tests\Unit\Support\Tasks;

use App\Models\Task;
use App\Models\User;
use App\Support\Tasks\TaskAuthorization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\TaskStatusSeeder::class);
    }

    public function test_only_author_can_delete_task(): void
    {
        $author = User::factory()->create();
        $assignee = User::factory()->create();
        $other = User::factory()->create();

        $task = Task::create([
            'title' => 'Zadanie testowe',
            'status_id' => Task::getDefaultStatusId(),
            'priority' => 'normal',
            'author_id' => $author->id,
            'assignee_id' => $assignee->id,
        ]);

        $this->assertTrue(TaskAuthorization::canDelete($author, $task));
        $this->assertFalse(TaskAuthorization::canDelete($assignee, $task));
        $this->assertFalse(TaskAuthorization::canDelete($other, $task));
    }

    public function test_only_admin_can_force_delete(): void
    {
        $author = User::factory()->create();
        $admin = User::factory()->create();
        $admin->assignRole(\Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']));

        $task = Task::create([
            'title' => 'Zadanie testowe',
            'status_id' => Task::getDefaultStatusId(),
            'priority' => 'normal',
            'author_id' => $author->id,
        ]);

        $this->assertFalse(TaskAuthorization::canForceDelete($author, $task));
        $this->assertTrue(TaskAuthorization::canForceDelete($admin, $task));
    }

    public function test_admin_can_soft_delete_any_task(): void
    {
        $author = User::factory()->create();
        $admin = User::factory()->create();
        $admin->assignRole(\Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']));

        $task = Task::create([
            'title' => 'Zadanie testowe',
            'status_id' => Task::getDefaultStatusId(),
            'priority' => 'normal',
            'author_id' => $author->id,
        ]);

        $this->assertTrue(TaskAuthorization::canDelete($admin, $task));
    }
}
