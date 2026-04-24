<?php

namespace Tests\Feature\Api\V1;

use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskApiTest extends TestCase
{
    use RefreshDatabase;

    private function apiAs(User $user, string $method, string $uri, array $data = [])
    {
        return $this->actingAs($user, 'sanctum')
            ->json($method, '/api/v1'.$uri, $data, ['Accept' => 'application/json']);
    }

    private function makeStatus(array $overrides = []): TaskStatus
    {
        return TaskStatus::factory()->create(array_merge([
            'name'  => 'To do',
            'color' => '#cccccc',
            'order' => 1,
        ], $overrides));
    }

    // ── board ─────────────────────────────────────────────────────────────

    public function test_board_returns_statuses_and_tasks(): void
    {
        $user   = User::factory()->create();
        $status = $this->makeStatus();
        Task::factory()->create([
            'status_id'   => $status->id,
            'author_id'   => $user->id,
            'assignee_id' => $user->id,
        ]);

        $response = $this->apiAs($user, 'GET', '/tasks/board');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure(['data' => ['statuses', 'tasks']]);
    }

    public function test_board_without_auth_returns_401(): void
    {
        $response = $this->json('GET', '/api/v1/tasks/board', [], ['Accept' => 'application/json']);

        $response->assertStatus(401);
    }

    public function test_board_filters_assigned_to_me(): void
    {
        $user  = User::factory()->create();
        $other = User::factory()->create();
        $status = $this->makeStatus();

        Task::factory()->create(['status_id' => $status->id, 'author_id' => $user->id, 'assignee_id' => $user->id]);
        Task::factory()->create(['status_id' => $status->id, 'author_id' => $other->id, 'assignee_id' => $other->id]);

        $response = $this->apiAs($user, 'GET', '/tasks/board?assigned_to_me=1');

        $response->assertStatus(200);
        $tasks = $response->json('data.tasks');
        $this->assertCount(1, $tasks);
        $this->assertSame($user->id, $tasks[0]['assignee_id']);
    }

    public function test_board_filters_by_status_id(): void
    {
        $user    = User::factory()->create();
        $s1      = $this->makeStatus(['name' => 'To do', 'order' => 1]);
        $s2      = $this->makeStatus(['name' => 'Done', 'order' => 2]);

        Task::factory()->create(['status_id' => $s1->id, 'author_id' => $user->id]);
        Task::factory()->create(['status_id' => $s2->id, 'author_id' => $user->id]);

        $response = $this->apiAs($user, 'GET', '/tasks/board?status_id='.$s1->id);

        $response->assertStatus(200);
        $tasks = $response->json('data.tasks');
        $this->assertCount(1, $tasks);
        $this->assertSame($s1->id, $tasks[0]['status_id']);
    }

    public function test_board_rejects_invalid_status_id(): void
    {
        $user = User::factory()->create();

        $response = $this->apiAs($user, 'GET', '/tasks/board?status_id=99999');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['status_id']);
    }

    // ── move ──────────────────────────────────────────────────────────────

    public function test_author_can_move_task(): void
    {
        $user   = User::factory()->create();
        $s1     = $this->makeStatus(['name' => 'To do', 'order' => 1]);
        $s2     = $this->makeStatus(['name' => 'In progress', 'order' => 2]);

        $task = Task::factory()->create([
            'status_id' => $s1->id,
            'author_id' => $user->id,
        ]);

        $response = $this->apiAs($user, 'POST', '/tasks/'.$task->id.'/move', [
            'status_id' => $s2->id,
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonPath('data.status_id', $s2->id);

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'status_id' => $s2->id]);
    }

    public function test_assignee_can_move_task(): void
    {
        $author   = User::factory()->create();
        $assignee = User::factory()->create();
        $s1       = $this->makeStatus(['order' => 1]);
        $s2       = $this->makeStatus(['name' => 'Done', 'order' => 2]);

        $task = Task::factory()->create([
            'status_id'   => $s1->id,
            'author_id'   => $author->id,
            'assignee_id' => $assignee->id,
        ]);

        $response = $this->apiAs($assignee, 'POST', '/tasks/'.$task->id.'/move', [
            'status_id' => $s2->id,
        ]);

        $response->assertStatus(200);
    }

    public function test_unrelated_user_cannot_move_task(): void
    {
        $author      = User::factory()->create();
        $unrelated   = User::factory()->create();
        $s1          = $this->makeStatus(['order' => 1]);
        $s2          = $this->makeStatus(['name' => 'Done', 'order' => 2]);

        $task = Task::factory()->create([
            'status_id' => $s1->id,
            'author_id' => $author->id,
        ]);

        $response = $this->apiAs($unrelated, 'POST', '/tasks/'.$task->id.'/move', [
            'status_id' => $s2->id,
        ]);

        $response->assertStatus(403);
    }

    public function test_move_with_order_updates_order(): void
    {
        $user   = User::factory()->create();
        $status = $this->makeStatus();

        $task = Task::factory()->create([
            'status_id' => $status->id,
            'author_id' => $user->id,
        ]);

        $response = $this->apiAs($user, 'POST', '/tasks/'.$task->id.'/move', [
            'status_id' => $status->id,
            'order'     => 5,
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'order' => 5]);
    }

    public function test_move_requires_status_id(): void
    {
        $user   = User::factory()->create();
        $status = $this->makeStatus();

        $task = Task::factory()->create([
            'status_id' => $status->id,
            'author_id' => $user->id,
        ]);

        $response = $this->apiAs($user, 'POST', '/tasks/'.$task->id.'/move', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['status_id']);
    }

    public function test_move_without_auth_returns_401(): void
    {
        $status = $this->makeStatus();
        $task   = Task::factory()->create(['status_id' => $status->id]);

        $response = $this->json('POST', '/api/v1/tasks/'.$task->id.'/move', [
            'status_id' => $status->id,
        ], ['Accept' => 'application/json']);

        $response->assertStatus(401);
    }
}
