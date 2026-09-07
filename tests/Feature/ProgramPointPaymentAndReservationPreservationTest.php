<?php

namespace Tests\Feature;

use App\Actions\Finance\RecordSettlementCostPaymentAction;
use App\Data\RecordSettlementCostPaymentData;
use App\Models\Contractor;
use App\Models\Event;
use App\Models\EventProgramPoint;
use App\Models\EventSettlement;
use App\Models\EventSettlementCost;
use App\Models\Reservation;
use App\Models\User;
use App\Services\EventProgramPointDeletionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProgramPointPaymentAndReservationPreservationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('admin');
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('admin');
        $this->actingAs($user);
    }

    public function test_deleting_program_point_keeps_advance_payment(): void
    {
        [$event, $point, $settlement] = $this->eventWithPoint();
        $settlement->importFromEvent();

        $plan = $settlement->costs()
            ->where('source_type', 'program_point')
            ->where('source_id', $point->id)
            ->first();
        $this->assertNotNull($plan);

        app(RecordSettlementCostPaymentAction::class)(new RecordSettlementCostPaymentData(
            planCost: $plan,
            amountPln: 300,
            advanceType: 'advance',
            paidBy: 'office',
        ));

        $point->delete();

        $keptPlan = EventSettlementCost::query()
            ->where('settlement_id', $settlement->id)
            ->where('source_type', 'program_point')
            ->where('source_id', $point->id)
            ->first();

        $this->assertNotNull($keptPlan);
        $this->assertStringContainsString('odłączony od programu', (string) $keptPlan->name);
        $this->assertTrue(
            EventSettlementCost::query()
                ->where('settlement_id', $settlement->id)
                ->where('source_type', 'program_point_payment')
                ->where('source_id', $point->id)
                ->exists()
        );
    }

    public function test_deleting_program_point_keeps_reservation(): void
    {
        [$event, $point] = $this->eventWithPoint();

        $reservation = Reservation::query()->create([
            'event_id' => $event->id,
            'program_point_id' => $point->id,
            'status' => 'confirmed',
            'confirmed_at' => now()->toDateString(),
            'reserved_at' => now(),
            'participant_count' => 10,
            'booking_reference' => 'MUZ-1',
        ]);
        $point->forceFill(['reservation_id' => $reservation->id])->saveQuietly();

        $point->delete();

        $this->assertTrue(Reservation::query()->whereKey($reservation->id)->exists());
        $this->assertSame('confirmed', $reservation->fresh()->status);
        $this->assertSame($point->id, (int) $reservation->fresh()->program_point_id);
        $this->assertNotNull($reservation->fresh()->programPoint);
        $this->assertTrue($reservation->fresh()->programPoint->trashed());
    }

    public function test_force_deleting_program_point_does_not_delete_reservation(): void
    {
        [$event, $point] = $this->eventWithPoint();

        $reservation = Reservation::query()->create([
            'event_id' => $event->id,
            'program_point_id' => $point->id,
            'status' => 'confirmed',
            'confirmed_at' => now()->toDateString(),
            'reserved_at' => now(),
            'participant_count' => 8,
            'booking_reference' => 'MUZ-FORCE',
        ]);

        app(EventProgramPointDeletionService::class)->forceDelete($point);

        $fresh = $reservation->fresh();
        $this->assertNotNull($fresh);
        $this->assertSame('confirmed', $fresh->status);
        $this->assertNull($fresh->program_point_id);
        $this->assertSame($event->id, (int) $fresh->event_id);
    }

    public function test_excluding_point_from_calculation_keeps_payment_after_import(): void
    {
        [$event, $point, $settlement] = $this->eventWithPoint();
        $settlement->importFromEvent();

        $plan = $settlement->costs()
            ->where('source_type', 'program_point')
            ->where('source_id', $point->id)
            ->first();
        $this->assertNotNull($plan);

        app(RecordSettlementCostPaymentAction::class)(new RecordSettlementCostPaymentData(
            planCost: $plan,
            amountPln: 150,
            advanceType: 'advance',
            paidBy: 'office',
        ));

        $point->update([
            'include_in_calculation' => false,
            'active' => false,
        ]);

        $settlement->importFromEvent();

        $this->assertTrue(
            EventSettlementCost::query()
                ->where('settlement_id', $settlement->id)
                ->where('source_type', 'program_point_payment')
                ->where('source_id', $point->id)
                ->exists()
        );

        $keptPlan = EventSettlementCost::query()
            ->where('settlement_id', $settlement->id)
            ->where('source_type', 'program_point')
            ->where('source_id', $point->id)
            ->first();
        $this->assertNotNull($keptPlan);
        $this->assertStringContainsString('odłączony od programu', (string) $keptPlan->name);
    }

    public function test_changing_contractor_does_not_drop_payment_or_reservation(): void
    {
        [$event, $point, $settlement] = $this->eventWithPoint();
        $old = Contractor::create(['name' => 'Stary kontrahent', 'status' => 'active']);
        $new = Contractor::create(['name' => 'Nowy kontrahent', 'status' => 'active']);
        $point->update(['contractor_id' => $old->id]);

        $settlement->importFromEvent();
        $plan = $settlement->costs()
            ->where('source_type', 'program_point')
            ->where('source_id', $point->id)
            ->first();
        $this->assertNotNull($plan);

        app(RecordSettlementCostPaymentAction::class)(new RecordSettlementCostPaymentData(
            planCost: $plan,
            amountPln: 80,
            advanceType: 'advance',
            paidBy: 'office',
        ));

        $reservation = Reservation::query()->create([
            'event_id' => $event->id,
            'program_point_id' => $point->id,
            'contractor_id' => $old->id,
            'status' => 'confirmed',
            'confirmed_at' => now()->toDateString(),
            'reserved_at' => now(),
            'participant_count' => 10,
        ]);

        $point->update(['contractor_id' => $new->id]);
        $settlement->importFromEvent();

        $this->assertTrue(Reservation::query()->whereKey($reservation->id)->exists());
        $this->assertSame($new->id, (int) $reservation->fresh()->contractor_id);
        $this->assertTrue(
            EventSettlementCost::query()
                ->where('settlement_id', $settlement->id)
                ->where('source_type', 'program_point_payment')
                ->where('source_id', $point->id)
                ->exists()
        );
    }

    /**
     * @return array{0: Event, 1: EventProgramPoint, 2: EventSettlement}
     */
    private function eventWithPoint(): array
    {
        $event = Event::factory()->create(['participant_count' => 10]);
        $point = EventProgramPoint::create([
            'event_id' => $event->id,
            'name' => 'Muzeum',
            'day' => 1,
            'order' => 1,
            'unit_price' => 100,
            'quantity' => 1,
            'planned_price' => 1000,
            'include_in_calculation' => true,
            'include_in_program' => true,
            'active' => true,
        ]);
        $settlement = EventSettlement::findOrCreateActiveForEvent($event);

        return [$event, $point, $settlement];
    }
}
