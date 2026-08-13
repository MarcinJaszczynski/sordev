<?php

namespace Tests\Feature\Console;

use App\Enums\TaskSource;
use App\Models\Event;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CollapseSystemTaskDuplicatesCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\TaskStatusSeeder::class);
    }

    public function test_collapses_open_system_duplicates_by_fingerprint(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $event = Event::factory()->create(['assigned_to' => $owner->id]);
        $statusId = Task::getDefaultStatusId();
        $fingerprint = 'event-status:'.$event->id.':confirmed';

        $keeper = Task::factory()->create([
            'title' => 'Potwierdzenie A',
            'description' => "Opis\n\n{$fingerprint}",
            'source' => TaskSource::System->value,
            'author_id' => $owner->id,
            'assignee_id' => $owner->id,
            'status_id' => $statusId,
            'taskable_type' => Event::class,
            'taskable_id' => $event->id,
        ]);

        $duplicate = Task::factory()->create([
            'title' => 'Potwierdzenie B',
            'description' => "Opis\n\n{$fingerprint}",
            'source' => TaskSource::System->value,
            'author_id' => $other->id,
            'assignee_id' => $other->id,
            'status_id' => $statusId,
            'taskable_type' => Event::class,
            'taskable_id' => $event->id,
        ]);

        $this->artisan('tasks:collapse-system-duplicates')
            ->assertSuccessful();

        $cancelledId = TaskStatus::query()->where('name', 'Anulowane')->value('id');

        $this->assertSame($statusId, (int) $keeper->fresh()->status_id);
        $this->assertSame((int) $cancelledId, (int) $duplicate->fresh()->status_id);
        $this->assertStringContainsString('collapsed-duplicate-of:'.$keeper->id, (string) $duplicate->fresh()->description);
    }
}
