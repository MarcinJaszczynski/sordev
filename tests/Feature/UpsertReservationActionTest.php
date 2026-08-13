<?php

namespace Tests\Feature;

use App\Actions\Reservations\UpsertReservationAction;
use App\Data\UpsertReservationData;
use App\Models\Contractor;
use App\Models\Event;
use App\Models\EventProgramPoint;
use App\Models\EventSettlement;
use App\Models\EventTemplate;
use App\Models\Reservation;
use App\Models\Task;
use App\Models\User;
use App\Support\Tasks\TaskContextRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpsertReservationActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\TaskStatusSeeder::class);
    }

    public function test_upsert_from_program_point_links_event_contractor_and_settlement_cost(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $contractor = Contractor::create(['name' => 'Hotel Alfa', 'status' => 'active']);
        $template = EventTemplate::factory()->create();
        $event = Event::create([
            'event_template_id' => $template->id,
            'name' => 'Impreza upsert',
            'client_name' => 'Klient',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'participant_count' => 12,
            'total_cost' => 1000,
            'status' => 'confirmed',
            'created_by' => $user->id,
            'assigned_to' => $user->id,
        ]);

        $point = EventProgramPoint::create([
            'event_id' => $event->id,
            'name' => 'Nocleg',
            'day' => 1,
            'order' => 1,
            'total_price' => 800,
            'contractor_id' => $contractor->id,
        ]);

        $reservation = app(UpsertReservationAction::class)(new UpsertReservationData(
            attributes: [
                'booking_reference' => 'HTL-100',
                'status' => 'pending',
                'participant_count' => 12,
                'reserved_amount' => 800,
                'amount_basis' => 'lump_sum',
                'participant_scope' => 'all',
                'convert_to_pln' => true,
            ],
            programPoint: $point,
            createdBy: $user->id,
        ));

        $this->assertSame($event->id, $reservation->event_id);
        $this->assertSame($point->id, $reservation->program_point_id);
        $this->assertSame($contractor->id, $reservation->contractor_id);
        $this->assertSame('HTL-100', $reservation->booking_reference);
        $this->assertNotNull($reservation->settlement_cost_id);

        $settlement = EventSettlement::findOrCreateActiveForEvent($event->fresh());
        $cost = $settlement->costs()
            ->where('source_type', 'program_point')
            ->where('source_id', $point->id)
            ->first();

        $this->assertNotNull($cost);
        $this->assertSame($cost->id, $reservation->settlement_cost_id);
    }

    public function test_task_sync_creates_and_retires_confirm_and_deposit_tasks(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $template = EventTemplate::factory()->create();
        $event = Event::create([
            'event_template_id' => $template->id,
            'name' => 'Impreza zadania',
            'client_name' => 'Klient',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'participant_count' => 10,
            'total_cost' => 500,
            'status' => 'confirmed',
            'created_by' => $user->id,
            'assigned_to' => $user->id,
        ]);

        $point = EventProgramPoint::create([
            'event_id' => $event->id,
            'name' => 'Muzeum',
            'day' => 1,
            'order' => 1,
            'total_price' => 200,
        ]);

        $reservation = app(UpsertReservationAction::class)(new UpsertReservationData(
            attributes: [
                'status' => 'pending',
                'confirm_by' => now()->addDays(3)->toDateString(),
                'deposit_due_at' => now()->addDays(5)->toDateString(),
                'reserved_amount' => 200,
                'participant_count' => 10,
                'amount_basis' => 'lump_sum',
                'participant_scope' => 'all',
                'convert_to_pln' => true,
            ],
            programPoint: $point,
            createdBy: $user->id,
        ));

        $openTasks = Task::query()
            ->where('taskable_type', Reservation::class)
            ->where('taskable_id', $reservation->id)
            ->whereHas('status', fn ($q) => $q->where('name', '!=', 'Zakończone'))
            ->get();

        $this->assertGreaterThanOrEqual(2, $openTasks->count());
        $this->assertTrue($openTasks->contains(fn (Task $task) => str_contains((string) $task->description, '[reservation-task:'.$reservation->id.':confirm]')));
        $this->assertTrue($openTasks->contains(fn (Task $task) => str_contains((string) $task->description, '[reservation-task:'.$reservation->id.':deposit]')));

        app(UpsertReservationAction::class)(new UpsertReservationData(
            attributes: [
                'status' => 'confirmed',
                'confirmed_at' => now()->toDateString(),
                'deposit_paid_at' => now()->toDateString(),
                'confirm_by' => $reservation->confirm_by?->toDateString(),
                'deposit_due_at' => $reservation->deposit_due_at?->toDateString(),
                'reserved_amount' => 200,
                'participant_count' => 10,
                'amount_basis' => 'lump_sum',
                'participant_scope' => 'all',
                'convert_to_pln' => true,
            ],
            reservation: $reservation->fresh(),
            programPoint: $point,
            createdBy: $user->id,
        ));

        $stillOpen = Task::query()
            ->where('taskable_type', Reservation::class)
            ->where('taskable_id', $reservation->id)
            ->whereHas('status', fn ($q) => $q->where('name', '!=', 'Zakończone'))
            ->count();

        $this->assertSame(0, $stillOpen);
    }

    public function test_task_context_registry_supports_reservation(): void
    {
        $user = User::factory()->create();
        $template = EventTemplate::factory()->create();
        $event = Event::create([
            'event_template_id' => $template->id,
            'name' => 'Impreza context',
            'client_name' => 'Klient',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'participant_count' => 8,
            'total_cost' => 300,
            'status' => 'confirmed',
            'created_by' => $user->id,
        ]);

        $point = EventProgramPoint::create([
            'event_id' => $event->id,
            'name' => 'Zwiedzanie',
            'day' => 1,
            'order' => 1,
            'total_price' => 100,
        ]);

        $reservation = Reservation::create([
            'event_id' => $event->id,
            'program_point_id' => $point->id,
            'booking_reference' => 'REF-9',
            'status' => 'pending',
            'reserved_amount' => 100,
            'created_by' => $user->id,
        ]);

        $this->assertTrue(TaskContextRegistry::isSupported(Reservation::class));
        $this->assertSame('Rezerwacja u dostawcy', TaskContextRegistry::labelForType(Reservation::class));

        $label = TaskContextRegistry::labelForRecord($reservation->fresh(['event', 'programPoint', 'contractor']));
        $this->assertStringContainsString('REF-9', $label);
        $this->assertStringContainsString('Impreza context', $label);

        $links = TaskContextRegistry::linksForRecord($reservation->fresh());
        $labels = collect($links)->pluck('label')->all();

        $this->assertContains('Rezerwacja', $labels);
        $this->assertContains('Impreza', $labels);
        $this->assertContains('Program imprezy', $labels);
        $this->assertContains('Rezerwacje imprezy', $labels);
    }

    public function test_retire_all_on_delete(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $template = EventTemplate::factory()->create();
        $event = Event::create([
            'event_template_id' => $template->id,
            'name' => 'Impreza delete',
            'client_name' => 'Klient',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'participant_count' => 5,
            'total_cost' => 100,
            'status' => 'confirmed',
            'created_by' => $user->id,
            'assigned_to' => $user->id,
        ]);

        $point = EventProgramPoint::create([
            'event_id' => $event->id,
            'name' => 'Punkt',
            'day' => 1,
            'order' => 1,
            'total_price' => 50,
        ]);

        $reservation = app(UpsertReservationAction::class)(new UpsertReservationData(
            attributes: [
                'status' => 'pending',
                'confirm_by' => now()->addDay()->toDateString(),
                'reserved_amount' => 50,
                'participant_count' => 5,
                'amount_basis' => 'lump_sum',
                'participant_scope' => 'all',
                'convert_to_pln' => true,
            ],
            programPoint: $point,
            createdBy: $user->id,
        ));

        $this->assertGreaterThan(0, Task::query()
            ->where('taskable_type', Reservation::class)
            ->where('taskable_id', $reservation->id)
            ->whereHas('status', fn ($q) => $q->where('name', '!=', 'Zakończone'))
            ->count());

        $reservation->delete();

        $this->assertSame(0, Task::query()
            ->where('taskable_type', Reservation::class)
            ->where('taskable_id', $reservation->id)
            ->whereHas('status', fn ($q) => $q->where('name', '!=', 'Zakończone'))
            ->count());
    }
}
