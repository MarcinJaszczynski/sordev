<?php

namespace Tests\Unit\Support\Tasks;

use App\Enums\TaskPriority;
use App\Enums\TaskSource;
use App\Models\Event;
use App\Models\Task;
use App\Models\User;
use App\Support\Tasks\SystemTaskFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SystemTaskFactoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\TaskStatusSeeder::class);

        foreach (['admin', 'super_admin', 'biuro'] as $roleName) {
            Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        }
    }

    public function test_upsert_creates_single_shared_task_for_multiple_office_users(): void
    {
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('admin');
        $biuro = User::factory()->create(['status' => 'active']);
        $biuro->assignRole('biuro');
        $owner = User::factory()->create(['status' => 'active']);
        $owner->assignRole('biuro');

        $event = Event::factory()->create([
            'assigned_to' => $owner->id,
            'office_caretaker_id' => $owner->id,
        ]);

        $first = SystemTaskFactory::upsertShared(
            taskable: $event,
            fingerprint: 'event-status:'.$event->id.':confirmed',
            title: 'Impreza potwierdzona',
            description: 'Lista kontrolna',
            priority: TaskPriority::Urgent,
            eventForAssignee: $event,
            url: 'https://example.test/event',
        );

        $second = SystemTaskFactory::upsertShared(
            taskable: $event,
            fingerprint: 'event-status:'.$event->id.':confirmed',
            title: 'Impreza potwierdzona — update',
            description: 'Lista kontrolna zaktualizowana',
            priority: TaskPriority::Urgent,
            eventForAssignee: $event,
            url: 'https://example.test/event',
        );

        $this->assertNotNull($first);
        $this->assertSame($first->id, $second?->id);
        $this->assertSame(1, Task::query()->where('taskable_id', $event->id)->count());
        $this->assertSame($owner->id, (int) $first->assignee_id);
        $this->assertSame(TaskSource::System, $first->fresh()->source);
        $this->assertStringContainsString('Impreza potwierdzona — update', (string) $second?->title);
    }

    public function test_upsert_rejects_disallowed_fingerprint(): void
    {
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('admin');
        $event = Event::factory()->create(['assigned_to' => $admin->id]);

        $task = SystemTaskFactory::upsertShared(
            taskable: $event,
            fingerprint: 'event-status:'.$event->id.':offer',
            title: 'Oferta',
            description: 'Nie powinno powstać',
            eventForAssignee: $event,
        );

        $this->assertNull($task);
        $this->assertSame(0, Task::query()->where('taskable_id', $event->id)->count());
    }

    public function test_resolve_assignee_falls_back_to_first_office_user(): void
    {
        $admin = User::factory()->create(['status' => 'active', 'name' => 'AAA Admin']);
        $admin->assignRole('admin');

        $event = Event::factory()->create(['assigned_to' => null]);

        $task = SystemTaskFactory::upsertShared(
            taskable: $event,
            fingerprint: 'event-inquiry:'.$event->id,
            title: 'Nowe zapytanie',
            description: 'Opis',
            priority: TaskPriority::Urgent,
            eventForAssignee: $event,
        );

        $this->assertNotNull($task);
        $this->assertSame($admin->id, (int) $task->assignee_id);
    }

    public function test_resolve_assignee_ignores_pilot_assigned_to(): void
    {
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('admin');
        $pilot = User::factory()->create(['status' => 'active']);
        Role::firstOrCreate(['name' => 'pilot', 'guard_name' => 'web']);
        $pilot->assignRole('pilot');

        $event = Event::factory()->create(['assigned_to' => $pilot->id]);

        $task = SystemTaskFactory::upsertShared(
            taskable: $event,
            fingerprint: 'event-status:'.$event->id.':confirmed',
            title: 'Impreza potwierdzona',
            description: 'Lista kontrolna',
            priority: TaskPriority::Urgent,
            eventForAssignee: $event,
        );

        $this->assertNotNull($task);
        $this->assertSame($admin->id, (int) $task->assignee_id);
        $this->assertNotSame($pilot->id, (int) $task->assignee_id);
    }
}
