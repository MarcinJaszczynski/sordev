<?php

namespace Tests\Feature;

use App\Actions\Finance\RecordSettlementCostPaymentAction;
use App\Data\RecordSettlementCostPaymentData;
use App\Models\Contractor;
use App\Models\Event;
use App\Models\EventHotelRoomLine;
use App\Models\EventHotelStay;
use App\Models\EventSettlement;
use App\Models\EventSettlementCost;
use App\Models\EventTemplate;
use App\Models\EventTemplateHotelDay;
use App\Models\HotelRoom;
use App\Models\Place;
use App\Models\Reservation;
use App\Models\User;
use App\Services\EventHotelPlanService;
use App\Services\HotelStaySettlementSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HotelStaySettlementPreservationTest extends TestCase
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

    public function test_changing_hotel_keeps_existing_advance_payment(): void
    {
        [$event, $hotelA, $hotelB] = $this->eventWithTwoHotels();
        $stay = $event->hotelStays()->where('day', 1)->first();
        $this->assertNotNull($stay);

        app(HotelStaySettlementSync::class)->syncForEvent($event->fresh(['hotelStays.roomLines']));

        $settlement = EventSettlement::findOrCreateActiveForEvent($event);
        $hotelCost = $settlement->costs()
            ->where('source_type', HotelStaySettlementSync::SOURCE_HOTEL)
            ->where('source_id', $hotelA->id)
            ->first();

        $this->assertNotNull($hotelCost);

        app(RecordSettlementCostPaymentAction::class)(new RecordSettlementCostPaymentData(
            planCost: $hotelCost,
            amountPln: 400,
            advanceType: 'advance',
            paidBy: 'office',
        ));

        $stay->update(['contractor_id' => $hotelB->id]);
        app(HotelStaySettlementSync::class)->syncForEvent($event->fresh(['hotelStays.roomLines']));

        $this->assertTrue(
            EventSettlementCost::query()
                ->where('settlement_id', $settlement->id)
                ->where('source_type', HotelStaySettlementSync::SOURCE_HOTEL.'_payment')
                ->where('source_id', $hotelA->id)
                ->where('payment_status', '!=', 'cancelled')
                ->exists()
        );

        $oldCost = EventSettlementCost::query()
            ->where('settlement_id', $settlement->id)
            ->where('source_type', HotelStaySettlementSync::SOURCE_HOTEL)
            ->where('source_id', $hotelA->id)
            ->first();

        $this->assertNotNull($oldCost);
        $this->assertStringContainsString('odłączony od planu', (string) $oldCost->name);
        $this->assertEqualsWithDelta(
            400.0,
            (float) EventSettlementCost::query()
                ->where('settlement_id', $settlement->id)
                ->where('source_type', HotelStaySettlementSync::SOURCE_HOTEL.'_payment')
                ->where('source_id', $hotelA->id)
                ->value('actual_amount_pln'),
            0.01
        );
    }

    public function test_zero_room_prices_do_not_delete_hotel_payments(): void
    {
        [$event, $hotelA] = $this->eventWithTwoHotels();

        app(HotelStaySettlementSync::class)->syncForEvent($event->fresh(['hotelStays.roomLines']));
        $settlement = EventSettlement::findOrCreateActiveForEvent($event);
        $hotelCost = $settlement->costs()
            ->where('source_type', HotelStaySettlementSync::SOURCE_HOTEL)
            ->where('source_id', $hotelA->id)
            ->first();

        $this->assertNotNull($hotelCost);

        app(RecordSettlementCostPaymentAction::class)(new RecordSettlementCostPaymentData(
            planCost: $hotelCost,
            amountPln: 250,
            advanceType: 'advance',
            paidBy: 'office',
        ));

        $event->hotelStays()->each(function (EventHotelStay $stay): void {
            $stay->roomLines()->update(['unit_price' => 0]);
        });

        app(HotelStaySettlementSync::class)->syncForEvent($event->fresh(['hotelStays.roomLines']));

        $this->assertTrue(
            EventSettlementCost::query()
                ->where('settlement_id', $settlement->id)
                ->where('source_type', HotelStaySettlementSync::SOURCE_HOTEL.'_payment')
                ->where('source_id', $hotelA->id)
                ->exists()
        );

        $this->assertTrue(
            EventSettlementCost::query()
                ->where('settlement_id', $settlement->id)
                ->where('source_type', HotelStaySettlementSync::SOURCE_HOTEL)
                ->where('source_id', $hotelA->id)
                ->exists()
        );
    }

    public function test_empty_hotel_cost_without_payments_is_removed_when_hotel_changes(): void
    {
        [$event, $hotelA, $hotelB] = $this->eventWithTwoHotels();

        app(HotelStaySettlementSync::class)->syncForEvent($event->fresh(['hotelStays.roomLines']));
        $settlement = EventSettlement::findOrCreateActiveForEvent($event);

        $this->assertTrue(
            $settlement->costs()
                ->where('source_type', HotelStaySettlementSync::SOURCE_HOTEL)
                ->where('source_id', $hotelA->id)
                ->exists()
        );

        $event->hotelStays()->update(['contractor_id' => $hotelB->id]);
        app(HotelStaySettlementSync::class)->syncForEvent($event->fresh(['hotelStays.roomLines']));

        $this->assertFalse(
            EventSettlementCost::query()
                ->where('settlement_id', $settlement->id)
                ->where('source_type', HotelStaySettlementSync::SOURCE_HOTEL)
                ->where('source_id', $hotelA->id)
                ->exists()
        );
    }

    public function test_apply_same_hotel_still_copies_contractor(): void
    {
        $event = Event::factory()->create(['duration_days' => 3, 'participant_count' => 10]);
        $hotelA = Contractor::create(['name' => 'Hotel Wspólny', 'status' => 'active']);
        $hotelB = Contractor::create(['name' => 'Hotel Inny', 'status' => 'active']);

        $stay1 = EventHotelStay::create([
            'event_id' => $event->id,
            'day' => 1,
            'contractor_id' => $hotelA->id,
        ]);
        $stay2 = EventHotelStay::create([
            'event_id' => $event->id,
            'day' => 2,
            'contractor_id' => $hotelB->id,
        ]);

        $stay1->roomLines()->create([
            'label' => 'Double',
            'role' => 'qty',
            'quantity' => 2,
            'people_count' => 2,
            'unit_price' => 100,
            'price_basis' => EventHotelRoomLine::PRICE_BASIS_PER_ROOM,
            'convert_to_pln' => true,
            'order' => 0,
        ]);

        app(EventHotelPlanService::class)->applySameHotelAllNights($event->fresh(['hotelStays']), $hotelA->id, 1);

        $this->assertSame($hotelA->id, (int) $stay2->fresh()->contractor_id);
        $this->assertCount(1, $stay2->fresh()->roomLines);
    }

    public function test_template_refresh_does_not_wipe_existing_room_lines(): void
    {
        $place = Place::create(['name' => 'Gdańsk']);
        $template = EventTemplate::factory()->create([
            'duration_days' => 2,
            'start_place_id' => $place->id,
        ]);

        $catalogRoom = HotelRoom::create([
            'name' => 'Katalogowy',
            'people_count' => 2,
            'price' => 200,
            'currency' => 'PLN',
            'convert_to_pln' => true,
        ]);

        EventTemplateHotelDay::create([
            'event_template_id' => $template->id,
            'day' => 1,
            'hotel_room_ids_qty' => [$catalogRoom->id],
            'hotel_room_ids_gratis' => [],
            'hotel_room_ids_staff' => [],
            'hotel_room_ids_driver' => [],
        ]);

        $event = Event::factory()->create([
            'event_template_id' => $template->id,
            'duration_days' => 2,
            'participant_count' => 20,
        ]);

        $stay = EventHotelStay::create(['event_id' => $event->id, 'day' => 1]);
        $line = $stay->roomLines()->create([
            'label' => 'Ręczny układ',
            'role' => 'qty',
            'quantity' => 1,
            'people_count' => 2,
            'unit_price' => 99,
            'price_basis' => EventHotelRoomLine::PRICE_BASIS_PER_ROOM,
            'convert_to_pln' => true,
            'order' => 0,
        ]);

        app(EventHotelPlanService::class)->refreshRoomStructureFromTemplate($event->fresh(), onlyEmptyStays: true);

        $this->assertTrue($stay->roomLines()->whereKey($line->id)->exists());
        $this->assertSame('Ręczny układ', $stay->fresh()->roomLines()->first()?->label);
    }

    public function test_changing_hotel_does_not_delete_reservation_record(): void
    {
        [$event, $hotelA, $hotelB] = $this->eventWithTwoHotels();
        $stay = $event->hotelStays()->where('day', 1)->first();

        $reservation = Reservation::query()->create([
            'event_id' => $event->id,
            'contractor_id' => $hotelA->id,
            'status' => 'confirmed',
            'confirmed_at' => now()->toDateString(),
            'reserved_at' => now(),
            'participant_count' => 10,
            'deposit_paid_at' => now()->toDateString(),
            'reserved_amount' => 500,
        ]);
        $stay->update(['reservation_id' => $reservation->id]);

        app(HotelStaySettlementSync::class)->syncForEvent($event->fresh(['hotelStays.roomLines']));

        $stay->update(['contractor_id' => $hotelB->id, 'reservation_id' => null]);
        app(HotelStaySettlementSync::class)->syncForEvent($event->fresh(['hotelStays.roomLines']));

        $this->assertTrue(Reservation::query()->whereKey($reservation->id)->exists());
        $this->assertSame('confirmed', $reservation->fresh()->status);
    }

    /**
     * @return array{0: Event, 1: Contractor, 2: Contractor}
     */
    private function eventWithTwoHotels(): array
    {
        $hotelA = Contractor::create(['name' => 'Hotel Zaliczka A', 'status' => 'active']);
        $hotelB = Contractor::create(['name' => 'Hotel Zaliczka B', 'status' => 'active']);
        $event = Event::factory()->create(['duration_days' => 3, 'participant_count' => 10]);

        $stay = EventHotelStay::create([
            'event_id' => $event->id,
            'day' => 1,
            'contractor_id' => $hotelA->id,
        ]);
        $stay->roomLines()->create([
            'label' => 'Double',
            'role' => 'qty',
            'quantity' => 5,
            'people_count' => 2,
            'unit_price' => 100,
            'price_basis' => EventHotelRoomLine::PRICE_BASIS_PER_ROOM,
            'convert_to_pln' => true,
            'order' => 0,
        ]);

        return [$event, $hotelA, $hotelB];
    }
}
