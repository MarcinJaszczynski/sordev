<?php

namespace Tests\Feature;

use App\Filament\Resources\EventResource\Pages\EditEvent;
use App\Models\Contractor;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\EventParticipantResignation;
use App\Models\EventTemplate;
use App\Models\Task;
use App\Models\User;
use App\Services\EventParticipantCountChangeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventParticipantCountChangeTest extends TestCase
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

    public function test_edit_event_saves_gratis_count_to_qty_variant(): void
    {
        [$admin] = $this->makeOfficeUsers();
        $contractor = Contractor::create([
            'name' => 'Klient testowy',
            'email' => 'klient@example.com',
            'status' => 'active',
        ]);

        $event = Event::factory()->create([
            'participant_count' => 30,
            'client_name' => 'Klient testowy',
            'contractor_id' => $contractor->id,
            'status' => Event::STATUS_CONFIRMED,
        ]);

        \App\Models\EventQty::create([
            'event_id' => $event->id,
            'qty' => 30,
            'gratis' => 2,
            'staff' => 1,
            'driver' => 1,
        ]);

        $this->actingAs($admin);

        Livewire::test(EditEvent::class, ['record' => $event->getKey()])
            ->fillForm([
                'gratis_count' => 5,
                'ordering_parties' => [
                    [
                        'contact_id' => null,
                        'contractor_id' => (string) $contractor->id,
                        'department_label' => null,
                    ],
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(5, $event->fresh()->resolveGratisCountForParticipantCount(30));
    }

    public function test_confirmed_resignation_decrements_count_and_creates_office_tasks(): void
    {
        [$admin, $biuro] = $this->makeOfficeUsers();
        $event = $this->makeEvent($admin, ['participant_count' => 12]);

        $this->actingAs($admin);

        EventParticipantResignation::create([
            'event_id' => $event->id,
            'participant_name' => 'Jan Kowalski',
            'resignation_type' => 'contractual',
            'status' => 'confirmed',
            'resigned_at' => now()->toDateString(),
            'amount_due_pln' => 500,
            'amount_paid_pln' => 500,
            'created_by' => $admin->id,
        ]);

        $event->refresh();

        $this->assertSame(11, (int) $event->participant_count);

        $tasks = Task::query()
            ->where('taskable_type', Event::class)
            ->where('taskable_id', $event->id)
            ->get();

        $this->assertCount(1, $tasks);
        $this->assertContains((int) $tasks->first()->assignee_id, [$admin->id, $biuro->id]);
        $this->assertStringContainsString('z 12 na 11', (string) $tasks->first()->description);
    }

    public function test_manual_edit_via_edit_event_creates_office_tasks_without_extra_count_change(): void
    {
        [$admin, $biuro] = $this->makeOfficeUsers();
        $contractor = Contractor::create([
            'name' => 'Klient testowy',
            'email' => 'klient@example.com',
            'status' => 'active',
        ]);

        $event = Event::factory()->create([
            'participant_count' => 20,
            'client_name' => 'Klient testowy',
            'contractor_id' => $contractor->id,
            'status' => Event::STATUS_CONFIRMED,
            'assigned_to' => $admin->id,
        ]);

        $this->actingAs($admin);

        Livewire::test(EditEvent::class, ['record' => $event->getKey()])
            ->fillForm([
                'participant_count' => 24,
                'ordering_parties' => [
                    [
                        'contact_id' => null,
                        'contractor_id' => (string) $contractor->id,
                        'department_label' => null,
                    ],
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $event->refresh();

        $this->assertSame(24, (int) $event->participant_count);

        $tasks = Task::query()
            ->where('taskable_type', Event::class)
            ->where('taskable_id', $event->id)
            ->get();

        $this->assertCount(1, $tasks);
        $this->assertContains((int) $tasks->first()->assignee_id, [$admin->id, $biuro->id]);
        $this->assertStringContainsString('z 20 na 24', (string) $tasks->first()->description);
        $this->assertStringContainsString('formularzu imprezy', (string) $tasks->first()->description);
    }

    public function test_manual_notify_office_creates_single_shared_task(): void
    {
        [$admin, $biuro] = $this->makeOfficeUsers();
        $event = $this->makeEvent($admin, [
            'participant_count' => 20,
            'assigned_to' => $admin->id,
        ]);

        app(EventParticipantCountChangeService::class)->notifyOffice(
            $event,
            20,
            24,
            EventParticipantCountChangeService::REASON_MANUAL_EDIT,
            ['editor_name' => $admin->name],
        );

        $tasks = Task::query()
            ->where('taskable_type', Event::class)
            ->where('taskable_id', $event->id)
            ->get();

        $this->assertCount(1, $tasks);
        $this->assertSame($admin->id, (int) $tasks->first()->assignee_id);
        $this->assertNotSame($biuro->id, (int) $tasks->first()->assignee_id);
        $this->assertStringContainsString('z 20 na 24', (string) $tasks->first()->description);
        $this->assertStringContainsString('formularzu imprezy', (string) $tasks->first()->description);
    }

    public function test_resignation_confirmed_to_settled_does_not_decrement_twice(): void
    {
        $admin = $this->makeOfficeUsers()[0];
        $event = $this->makeEvent($admin, ['participant_count' => 8]);

        $this->actingAs($admin);

        $resignation = EventParticipantResignation::create([
            'event_id' => $event->id,
            'participant_name' => 'Ewa Test',
            'resignation_type' => 'contractual',
            'status' => 'draft',
            'resigned_at' => now()->toDateString(),
            'amount_due_pln' => 100,
            'amount_paid_pln' => 100,
            'created_by' => $admin->id,
        ]);

        $event->refresh();
        $this->assertSame(8, (int) $event->participant_count);
        $this->assertSame(0, Task::query()->where('taskable_id', $event->id)->count());

        $resignation->update(['status' => 'confirmed']);

        $event->refresh();
        $this->assertSame(7, (int) $event->participant_count);

        $resignation->update(['status' => 'settled']);

        $event->refresh();
        $this->assertSame(7, (int) $event->participant_count);
        $this->assertSame(1, Task::query()->where('taskable_id', $event->id)->count());
    }

    public function test_cancelled_resignation_reverts_count_and_notifies_office(): void
    {
        [$admin, $biuro] = $this->makeOfficeUsers();
        $event = $this->makeEvent($admin, ['participant_count' => 6]);

        $this->actingAs($admin);

        $resignation = EventParticipantResignation::create([
            'event_id' => $event->id,
            'participant_name' => 'Piotr Test',
            'resignation_type' => 'contractual',
            'status' => 'confirmed',
            'resigned_at' => now()->toDateString(),
            'amount_due_pln' => 100,
            'amount_paid_pln' => 100,
            'created_by' => $admin->id,
        ]);

        $event->refresh();
        $this->assertSame(5, (int) $event->participant_count);

        $resignation->update(['status' => 'cancelled']);

        $event->refresh();
        $this->assertSame(6, (int) $event->participant_count);

        $tasks = Task::query()
            ->where('taskable_type', Event::class)
            ->where('taskable_id', $event->id)
            ->get();

        $this->assertCount(2, $tasks);
        $this->assertTrue(
            $tasks->contains(fn (Task $task): bool => str_contains((string) $task->description, 'z 5 na 6')),
        );
    }

    public function test_event_participant_list_change_does_not_create_tasks(): void
    {
        $admin = $this->makeOfficeUsers()[0];
        $event = $this->makeEvent($admin, ['participant_count' => 15]);

        EventParticipant::create([
            'event_id' => $event->id,
            'first_name' => 'Nowy',
            'last_name' => 'Uczestnik',
            'source' => EventParticipant::SOURCE_MANUAL,
            'status' => EventParticipant::STATUS_ACTIVE,
        ]);

        $event->refresh();

        $this->assertSame(15, (int) $event->participant_count);
        $this->assertSame(0, Task::query()->where('taskable_id', $event->id)->count());
    }

    public function test_notify_office_skips_duplicate_transition_within_five_minutes(): void
    {
        [$admin] = $this->makeOfficeUsers();
        $event = $this->makeEvent($admin, ['participant_count' => 10]);
        $service = app(EventParticipantCountChangeService::class);

        $service->notifyOffice(
            $event,
            10,
            9,
            EventParticipantCountChangeService::REASON_MANUAL_EDIT,
            ['editor_name' => 'Tester'],
        );

        $service->notifyOffice(
            $event,
            10,
            9,
            EventParticipantCountChangeService::REASON_MANUAL_EDIT,
            ['editor_name' => 'Tester'],
        );

        $this->assertSame(1, Task::query()->where('taskable_id', $event->id)->count());
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeEvent(User $creator, array $overrides = []): Event
    {
        $template = EventTemplate::factory()->create();

        return Event::create(array_merge([
            'event_template_id' => $template->id,
            'name' => 'Impreza testowa',
            'client_name' => 'Szkola',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'participant_count' => 10,
            'total_cost' => 1000,
            'status' => 'confirmed',
            'created_by' => $creator->id,
        ], $overrides));
    }

    /**
     * @return array{0: User, 1: User}
     */
    private function makeOfficeUsers(): array
    {
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('admin');

        $biuro = User::factory()->create(['status' => 'active']);
        $biuro->assignRole('biuro');

        return [$admin, $biuro];
    }
}
