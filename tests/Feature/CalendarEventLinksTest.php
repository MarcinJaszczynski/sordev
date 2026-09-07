<?php

namespace Tests\Feature;

use App\Models\Contractor;
use App\Models\Event;
use App\Models\EventProgramPoint;
use App\Models\Task;
use App\Models\User;
use App\Services\CalendarEventAggregator;
use App\Support\Tasks\TaskContextRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarEventLinksTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\TaskStatusSeeder::class);
    }

    public function test_task_calendar_entry_includes_task_and_context_links(): void
    {
        $user = User::factory()->create();
        $contractor = Contractor::create(['name' => 'Hotel Test', 'status' => 'active']);
        $event = Event::factory()->create(['assigned_to' => $user->id]);

        $task = Task::create([
            'title' => 'Zadzwoń do hotelu',
            'due_date' => now()->addDay(),
            'status_id' => Task::getDefaultStatusId(),
            'priority' => 'normal',
            'author_id' => $user->id,
            'taskable_type' => Contractor::class,
            'taskable_id' => $contractor->id,
        ]);

        $items = app(CalendarEventAggregator::class)->events([
            'from' => now()->subDay()->toDateString(),
            'to' => now()->addWeek()->toDateString(),
            'types' => ['tasks'],
        ]);

        $entry = $items->firstWhere('id', 'task-'.$task->id);

        $this->assertNotNull($entry);
        $this->assertCount(2, $entry['links']);
        $this->assertSame('Zadanie', $entry['links'][0]['label']);
        $this->assertSame('Kontrahent: Hotel Test', $entry['links'][1]['label']);
    }

    public function test_task_linked_to_program_point_exposes_event_and_program_links(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create(['assigned_to' => $user->id]);
        $programPoint = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'name' => 'Przejazd autokarem',
        ]);

        $task = Task::create([
            'title' => 'Potwierdź autokar',
            'due_date' => now()->addDay(),
            'status_id' => Task::getDefaultStatusId(),
            'priority' => 'normal',
            'author_id' => $user->id,
            'taskable_type' => EventProgramPoint::class,
            'taskable_id' => $programPoint->id,
        ]);

        $contextLinks = TaskContextRegistry::linksForTask($task);

        $this->assertCount(2, $contextLinks);
        $this->assertSame('Punkt programu: Przejazd autokarem', $contextLinks[0]['label']);
        $this->assertSame('Impreza: '.$event->name, $contextLinks[1]['label']);

        $items = app(CalendarEventAggregator::class)->events([
            'from' => now()->subDay()->toDateString(),
            'to' => now()->addWeek()->toDateString(),
            'types' => ['tasks'],
        ]);

        $entry = $items->firstWhere('id', 'task-'.$task->id);

        $this->assertNotNull($entry);
        $this->assertCount(3, $entry['links']);
        $this->assertSame('Zadanie', $entry['links'][0]['label']);
        $this->assertSame('Punkt programu: Przejazd autokarem', $entry['links'][1]['label']);
        $this->assertSame('Impreza: '.$event->name, $entry['links'][2]['label']);
    }
}
