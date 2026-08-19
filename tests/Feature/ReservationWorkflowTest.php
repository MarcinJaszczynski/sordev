<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventProgramPoint;
use App\Models\EventSettlement;
use App\Models\EventTemplate;
use App\Models\Reservation;
use App\Models\User;
use App\Support\Reservations\ReservationWorkflowDisplay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_reservation_workflow_dates_sync_advance_due_date_from_deposit_due_at(): void
    {
        $user = User::factory()->create();
        $template = EventTemplate::factory()->create();

        $event = Event::create([
            'event_template_id' => $template->id,
            'name' => 'Impreza rezerwacja workflow',
            'client_name' => 'Klient',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'participant_count' => 10,
            'total_cost' => 1000,
            'status' => 'confirmed',
            'created_by' => $user->id,
        ]);

        $point = EventProgramPoint::create([
            'event_id' => $event->id,
            'name' => 'Muzeum',
            'day' => 1,
            'order' => 1,
            'total_price' => 100,
        ]);

        Reservation::create([
            'program_point_id' => $point->id,
            'event_id' => $event->id,
            'status' => 'pending',
            'deposit_due_at' => '2026-12-30',
            'reserved_amount' => 500,
            'created_by' => $user->id,
        ]);

        $settlement = EventSettlement::findOrCreateActiveForEvent($event->fresh());
        $cost = $settlement->costs()
            ->where('source_type', 'program_point')
            ->where('source_id', $point->id)
            ->first();

        $this->assertNotNull($cost);
        $this->assertSame('2026-12-30', $cost->advance_due_date->toDateString());
    }

    public function test_contractor_is_taken_from_program_point_on_save(): void
    {
        $user = User::factory()->create();
        $template = EventTemplate::factory()->create();
        $contractor = \App\Models\Contractor::create(['name' => 'Hotel Testowy', 'status' => 'active']);

        $event = Event::create([
            'event_template_id' => $template->id,
            'name' => 'Impreza',
            'client_name' => 'Klient',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'participant_count' => 10,
            'total_cost' => 1000,
            'status' => 'confirmed',
            'created_by' => $user->id,
        ]);

        $point = EventProgramPoint::create([
            'event_id' => $event->id,
            'name' => 'Nocleg',
            'day' => 1,
            'order' => 1,
            'contractor_id' => $contractor->id,
            'total_price' => 100,
        ]);

        $reservation = Reservation::create([
            'program_point_id' => $point->id,
            'event_id' => $event->id,
            'status' => 'pending',
            'created_by' => $user->id,
        ]);

        $this->assertSame($contractor->id, $reservation->fresh()->contractor_id);
    }

    public function test_deposit_paid_at_syncs_settlement_cost_payment_status(): void
    {
        $user = User::factory()->create();
        $template = EventTemplate::factory()->create();

        $event = Event::create([
            'event_template_id' => $template->id,
            'name' => 'Impreza',
            'client_name' => 'Klient',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'participant_count' => 10,
            'total_cost' => 1000,
            'status' => 'confirmed',
            'created_by' => $user->id,
        ]);

        $point = EventProgramPoint::create([
            'event_id' => $event->id,
            'name' => 'Restauracja',
            'day' => 1,
            'order' => 1,
            'total_price' => 200,
        ]);

        $reservation = Reservation::create([
            'program_point_id' => $point->id,
            'event_id' => $event->id,
            'status' => 'confirmed',
            'deposit_due_at' => '2026-08-01',
            'deposit_paid_at' => '2026-07-15',
            'reserved_amount' => 100,
            'created_by' => $user->id,
        ]);

        $cost = $reservation->fresh()->settlementCost;

        $this->assertNotNull($cost);

        $payment = \App\Models\EventSettlementCost::query()
            ->where('source_type', 'program_point_payment')
            ->where('source_id', $point->id)
            ->first();

        $this->assertNotNull($payment);
        $this->assertEqualsWithDelta(100.0, (float) $payment->actual_amount, 0.01);
        $this->assertSame('2026-07-15', $payment->paid_at->toDateString());
        if (\Illuminate\Support\Facades\Schema::hasColumn('event_settlement_costs', 'reservation_id')) {
            $this->assertSame($reservation->id, (int) $payment->reservation_id);
        }

        $settlement = $cost->settlement()->first();
        $this->assertNotNull($settlement);
        $eval = app(\App\Services\SettlementPaymentHealthService::class)
            ->evaluatePlanCost($cost->fresh(), $settlement->costs()->get());
        $this->assertEqualsWithDelta(100.0, $eval['paid_pln'], 0.01);
    }

    public function test_reservation_history_logs_status_change(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $template = EventTemplate::factory()->create();
        $event = Event::create([
            'event_template_id' => $template->id,
            'name' => 'Impreza',
            'client_name' => 'Klient',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'participant_count' => 10,
            'total_cost' => 1000,
            'status' => 'confirmed',
            'created_by' => $user->id,
        ]);

        $point = EventProgramPoint::create([
            'event_id' => $event->id,
            'name' => 'Punkt',
            'day' => 1,
            'order' => 1,
            'total_price' => 50,
        ]);

        $reservation = Reservation::create([
            'program_point_id' => $point->id,
            'event_id' => $event->id,
            'status' => 'pending',
            'created_by' => $user->id,
        ]);

        $reservation->update(['status' => 'confirmed']);

        $this->assertDatabaseHas('reservation_history', [
            'reservation_id' => $reservation->id,
            'action' => 'status_changed',
            'field' => 'status',
        ]);
    }

    public function test_deposit_status_helper(): void
    {
        $reservation = new Reservation([
            'status' => 'pending',
            'deposit_due_at' => now()->subDay()->toDateString(),
        ]);

        $this->assertSame('overdue', ReservationWorkflowDisplay::depositStatus($reservation));

        $reservation->deposit_paid_at = now()->toDateString();

        $this->assertSame('paid', ReservationWorkflowDisplay::depositStatus($reservation));
    }

    public function test_reservation_and_deposit_lines_show_when_and_who(): void
    {
        $user = User::factory()->create(['name' => 'Anna Biuro']);
        $this->actingAs($user);

        $template = EventTemplate::factory()->create();
        $event = Event::create([
            'event_template_id' => $template->id,
            'name' => 'Impreza linie rezerwacji',
            'client_name' => 'Klient',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'participant_count' => 10,
            'total_cost' => 1000,
            'status' => 'confirmed',
            'created_by' => $user->id,
        ]);
        $point = EventProgramPoint::create([
            'event_id' => $event->id,
            'name' => 'Hotel',
            'day' => 1,
            'order' => 1,
            'total_price' => 100,
        ]);

        $reservation = Reservation::create([
            'program_point_id' => $point->id,
            'event_id' => $event->id,
            'status' => 'confirmed',
            'confirmed_at' => '2026-05-12',
            'deposit_due_at' => '2026-05-20',
            'booking_reference' => 'HTL-9',
            'created_by' => $user->id,
        ]);
        $reservation->update(['deposit_paid_at' => '2026-05-18']);
        $reservation->load('historyEntries.user');

        $reservationLine = ReservationWorkflowDisplay::reservationLine($reservation);
        $this->assertStringContainsString('Rez. potwierdzona', $reservationLine['text']);
        $this->assertStringContainsString('12.05.2026', $reservationLine['text']);
        $this->assertStringContainsString('HTL-9', $reservationLine['text']);

        $depositLine = ReservationWorkflowDisplay::depositLine($reservation);
        $this->assertSame('paid', $depositLine['status']);
        $this->assertStringContainsString('18.05.2026', $depositLine['text']);
        $this->assertStringContainsString('Anna Biuro', $depositLine['text']);

        $remaining = ReservationWorkflowDisplay::remainingLine([
            'paidBy' => 'pilot',
            'remaining' => '1 200,00 zł',
            'dueDateLabel' => '01.06.2026',
            'paidStatus' => 'partial',
        ]);
        $this->assertNotNull($remaining);
        $this->assertSame('Pilot · 1 200,00 zł · do 01.06.2026', $remaining['text']);
    }

    public function test_updating_plan_due_date_syncs_reservation_deposit_due_at(): void
    {
        \Spatie\Permission\Models\Role::findOrCreate('admin');
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('admin');
        $this->actingAs($user);

        $template = EventTemplate::factory()->create();
        $event = Event::create([
            'event_template_id' => $template->id,
            'name' => 'Impreza plan→rez',
            'client_name' => 'Klient',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'participant_count' => 10,
            'total_cost' => 1000,
            'status' => 'confirmed',
            'created_by' => $user->id,
        ]);

        $point = EventProgramPoint::create([
            'event_id' => $event->id,
            'name' => 'Muzeum',
            'day' => 1,
            'order' => 1,
            'total_price' => 100,
            'planned_price' => 100,
        ]);

        $reservation = Reservation::create([
            'program_point_id' => $point->id,
            'event_id' => $event->id,
            'status' => 'pending',
            'deposit_due_at' => '2026-12-30',
            'reserved_amount' => 50,
            'created_by' => $user->id,
        ]);

        $cost = $reservation->fresh()->settlementCost;
        $this->assertNotNull($cost);

        app(\App\Actions\Finance\UpdateSettlementCostPlanAction::class)(new \App\Data\UpdateSettlementCostPlanData(
            planCost: $cost,
            plannedAmountPln: (float) ($cost->planned_amount_pln ?? 100),
            plannedAmount: (float) ($cost->planned_amount ?? 100),
            dueDate: \Carbon\Carbon::parse('2027-01-15'),
        ));

        $this->assertSame('2027-01-15', $reservation->fresh()->deposit_due_at?->toDateString());
    }
}
