<?php

namespace Tests\Unit\Support\Tasks;

use App\Enums\TaskPriority;
use App\Enums\TaskSource;
use App\Models\Event;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use App\Support\Tasks\SystemTaskFactory;
use App\Support\Tasks\TaskDueDates;
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

    public function test_upsert_creates_one_task_per_office_user_and_updates_in_place(): void
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

        $created = SystemTaskFactory::upsertForOfficeUsers(
            taskable: $event,
            fingerprint: 'event-status:'.$event->id.':confirmed',
            title: 'Impreza potwierdzona',
            description: 'Lista kontrolna',
            priority: TaskPriority::Urgent,
            eventForAssignee: $event,
            url: 'https://example.test/event',
        );

        $updated = SystemTaskFactory::upsertForOfficeUsers(
            taskable: $event,
            fingerprint: 'event-status:'.$event->id.':confirmed',
            title: 'Impreza potwierdzona — update',
            description: 'Lista kontrolna zaktualizowana',
            priority: TaskPriority::Urgent,
            eventForAssignee: $event,
            url: 'https://example.test/event',
        );

        $this->assertCount(3, $created);
        $this->assertCount(3, $updated);
        $this->assertEqualsCanonicalizing(
            $created->pluck('id')->all(),
            $updated->pluck('id')->all(),
        );
        $this->assertSame(3, Task::query()->where('taskable_id', $event->id)->count());
        $this->assertEqualsCanonicalizing(
            [$admin->id, $biuro->id, $owner->id],
            Task::query()->where('taskable_id', $event->id)->pluck('assignee_id')->all(),
        );
        $this->assertTrue(
            Task::query()->where('taskable_id', $event->id)->get()->every(
                fn (Task $task): bool => $task->source === TaskSource::System
                    && str_contains((string) $task->title, 'Impreza potwierdzona — update')
            )
        );
    }

    public function test_finishing_one_copy_does_not_close_other_users_copies(): void
    {
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('admin');
        $biuro = User::factory()->create(['status' => 'active']);
        $biuro->assignRole('biuro');

        $event = Event::factory()->create(['office_caretaker_id' => $admin->id]);
        $finishedId = TaskStatus::query()->where('name', 'Zakończone')->value('id');

        SystemTaskFactory::upsertForOfficeUsers(
            taskable: $event,
            fingerprint: 'event-status:'.$event->id.':confirmed',
            title: 'Impreza potwierdzona',
            description: 'Lista kontrolna',
            priority: TaskPriority::Urgent,
            eventForAssignee: $event,
        );

        $adminCopy = Task::query()
            ->where('taskable_id', $event->id)
            ->where('assignee_id', $admin->id)
            ->first();
        $this->assertNotNull($adminCopy);
        $adminCopy->update(['status_id' => $finishedId]);

        $biuroCopy = Task::query()
            ->where('taskable_id', $event->id)
            ->where('assignee_id', $biuro->id)
            ->first();

        $this->assertNotNull($biuroCopy);
        $this->assertSame(Task::getDefaultStatusId(), (int) $biuroCopy->status_id);
        $this->assertSame((int) $finishedId, (int) $adminCopy->fresh()->status_id);
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

    public function test_upsert_assigns_each_office_user_without_caretaker(): void
    {
        $admin = User::factory()->create(['status' => 'active', 'name' => 'AAA Admin']);
        $admin->assignRole('admin');

        $event = Event::factory()->create(['assigned_to' => null, 'office_caretaker_id' => null]);

        $tasks = SystemTaskFactory::upsertForOfficeUsers(
            taskable: $event,
            fingerprint: 'event-inquiry:'.$event->id,
            title: 'Nowe zapytanie',
            description: 'Opis',
            priority: TaskPriority::Urgent,
            eventForAssignee: $event,
        );

        $this->assertCount(1, $tasks);
        $this->assertSame($admin->id, (int) $tasks->first()->assignee_id);
        $this->assertSame($admin->id, (int) $tasks->first()->author_id);
    }

    public function test_resolve_assignee_ignores_pilot_assigned_to(): void
    {
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('admin');
        $pilot = User::factory()->create(['status' => 'active']);
        Role::firstOrCreate(['name' => 'pilot', 'guard_name' => 'web']);
        $pilot->assignRole('pilot');

        $event = Event::factory()->create([
            'assigned_to' => $pilot->id,
            'office_caretaker_id' => null,
        ]);

        $tasks = SystemTaskFactory::upsertForOfficeUsers(
            taskable: $event,
            fingerprint: 'event-status:'.$event->id.':confirmed',
            title: 'Impreza potwierdzona',
            description: 'Lista kontrolna',
            priority: TaskPriority::Urgent,
            eventForAssignee: $event,
        );

        $this->assertCount(1, $tasks);
        $this->assertSame($admin->id, (int) $tasks->first()->assignee_id);
        $this->assertNotSame($pilot->id, (int) $tasks->first()->assignee_id);
        $this->assertFalse(
            Task::query()->where('taskable_id', $event->id)->where('assignee_id', $pilot->id)->exists()
        );
    }

    public function test_default_due_date_is_end_of_creation_day(): void
    {
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('admin');
        $event = Event::factory()->create(['assigned_to' => $admin->id]);

        $task = SystemTaskFactory::upsertShared(
            taskable: $event,
            fingerprint: 'event-inquiry:'.$event->id,
            title: 'Nowe zapytanie',
            description: 'Opis',
            priority: TaskPriority::Urgent,
            eventForAssignee: $event,
        );

        $this->assertNotNull($task);
        $this->assertNotNull($task->due_date);
        $this->assertTrue($task->due_date->isSameDay(now()));
        $this->assertSame(
            TaskDueDates::defaultForNew()->format('Y-m-d H:i:s'),
            $task->due_date->format('Y-m-d H:i:s'),
        );
    }

    public function test_expand_open_creates_missing_copies_for_office_users(): void
    {
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('admin');
        $biuro = User::factory()->create(['status' => 'active']);
        $biuro->assignRole('biuro');

        $event = Event::factory()->create();
        $fingerprint = 'event-status:'.$event->id.':confirmed';

        Task::factory()->create([
            'title' => 'Wspólna potwierdzona',
            'description' => "Opis\n\n{$fingerprint}",
            'source' => TaskSource::System->value,
            'author_id' => $admin->id,
            'assignee_id' => $admin->id,
            'status_id' => Task::getDefaultStatusId(),
            'taskable_type' => Event::class,
            'taskable_id' => $event->id,
        ]);

        $created = SystemTaskFactory::expandOpenForOfficeUsers();

        $this->assertSame(1, $created);
        $this->assertTrue(
            Task::query()
                ->where('taskable_id', $event->id)
                ->where('assignee_id', $biuro->id)
                ->exists()
        );
        $this->assertSame(2, Task::query()->where('taskable_id', $event->id)->count());
    }
}
