<?php

namespace Tests\Unit\Services\Tasks;

use App\Models\Task;
use App\Models\User;
use App\Services\Tasks\TaskInboxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskInboxServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\TaskStatusSeeder::class);
    }

    public function test_unseen_count_includes_tasks_changed_since_last_visit(): void
    {
        $user = User::factory()->create([
            'tasks_last_seen_at' => now()->subHour(),
        ]);
        $other = User::factory()->create();

        $old = Task::create([
            'title' => 'Stare zadanie',
            'status_id' => Task::getDefaultStatusId(),
            'priority' => 'normal',
            'author_id' => $user->id,
        ]);
        $old->forceFill([
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ])->saveQuietly();

        $changed = Task::create([
            'title' => 'Nowe zadanie',
            'status_id' => Task::getDefaultStatusId(),
            'priority' => 'normal',
            'author_id' => $user->id,
        ]);

        Task::create([
            'title' => 'Obce zadanie',
            'status_id' => Task::getDefaultStatusId(),
            'priority' => 'normal',
            'author_id' => $other->id,
            'assignee_id' => $other->id,
        ]);

        $count = app(TaskInboxService::class)->unseenCount($user);

        $this->assertSame(1, $count);

        $ids = app(TaskInboxService::class)->unseenQuery($user)->pluck('id')->all();

        $this->assertSame([$changed->id], $ids);
    }

    public function test_first_visit_without_last_seen_shows_all_open_mine_tasks(): void
    {
        $user = User::factory()->create([
            'tasks_last_seen_at' => null,
        ]);

        Task::create([
            'title' => 'Moje otwarte',
            'status_id' => Task::getDefaultStatusId(),
            'priority' => 'normal',
            'author_id' => $user->id,
        ]);

        $this->assertSame(1, app(TaskInboxService::class)->unseenCount($user));
    }

    public function test_mark_as_seen_clears_unseen_count(): void
    {
        $user = User::factory()->create([
            'tasks_last_seen_at' => now()->subDay(),
        ]);

        Task::create([
            'title' => 'Nowe po wizycie',
            'status_id' => Task::getDefaultStatusId(),
            'priority' => 'normal',
            'author_id' => $user->id,
        ]);

        app(TaskInboxService::class)->markAsSeen($user->fresh());

        $this->assertSame(0, app(TaskInboxService::class)->unseenCount($user->fresh()));
    }
}
