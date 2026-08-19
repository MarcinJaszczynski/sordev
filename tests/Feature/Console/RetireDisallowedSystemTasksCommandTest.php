<?php

namespace Tests\Feature\Console;

use App\Enums\TaskSource;
use App\Models\Event;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RetireDisallowedSystemTasksCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\TaskStatusSeeder::class);
    }

    public function test_retires_disallowed_open_system_tasks_and_keeps_allowed(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create(['assigned_to' => $user->id]);
        $openStatusId = Task::getDefaultStatusId();
        $completedStatusId = TaskStatus::query()->where('name', 'Zakończone')->value('id');

        $allowed = Task::create([
            'title' => 'Impreza potwierdzona',
            'description' => "Lista.\n\nevent-status:{$event->id}:confirmed",
            'status_id' => $openStatusId,
            'priority' => 'urgent',
            'source' => TaskSource::System->value,
            'author_id' => $user->id,
            'assignee_id' => $user->id,
            'taskable_type' => Event::class,
            'taskable_id' => $event->id,
            'order' => 1,
        ]);

        $disallowed = Task::create([
            'title' => 'Termin zaliczki',
            'description' => '[payment-reminder:settlement_cost:99:advance]',
            'status_id' => $openStatusId,
            'priority' => 'urgent',
            'source' => TaskSource::System->value,
            'author_id' => $user->id,
            'assignee_id' => $user->id,
            'taskable_type' => Event::class,
            'taskable_id' => $event->id,
            'order' => 2,
        ]);

        $this->artisan('tasks:retire-disallowed-system')
            ->assertSuccessful();

        $this->assertSame($openStatusId, (int) $allowed->fresh()->status_id);
        $this->assertSame($completedStatusId, (int) $disallowed->fresh()->status_id);
    }
}
