<?php

namespace Tests\Unit\Support;

use App\Models\Event;
use App\Models\EventProgramPoint;
use App\Models\EventSettlement;
use App\Models\EventSettlementCost;
use App\Support\Reservations\ReservationFormDefaults;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservationFormDefaultsTest extends TestCase
{
    use RefreshDatabase;

    public function test_prefills_planned_amount_when_no_advance(): void
    {
        $event = Event::factory()->create(['participant_count' => 22]);
        $point = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'planned_price' => 3500,
            'name' => 'Hotel',
        ]);

        $defaults = ReservationFormDefaults::forProgramPoint($point);

        $this->assertSame(3500.0, $defaults['reserved_amount']);
        $this->assertSame(22, $defaults['participant_count']);
        $this->assertSame('Z planowanych / szablonu punktu', $defaults['amount_hint']);
    }

    public function test_prefers_existing_advance_amount_from_cost(): void
    {
        $event = Event::factory()->create(['participant_count' => 10]);
        $point = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'planned_price' => 5000,
        ]);
        $settlement = EventSettlement::findOrCreateActiveForEvent($event);
        $cost = EventSettlementCost::query()->create([
            'settlement_id' => $settlement->id,
            'source_type' => 'program_point',
            'source_id' => $point->id,
            'name' => $point->name,
            'planned_amount' => 5000,
            'advance_amount' => 1500,
            'payment_status' => 'reserved',
            'paid_by' => 'office',
        ]);

        $defaults = ReservationFormDefaults::forProgramPoint($point, $cost);

        $this->assertSame(1500.0, $defaults['reserved_amount']);
        $this->assertSame('Z planu kosztów (zaliczka)', $defaults['amount_hint']);
    }
}
