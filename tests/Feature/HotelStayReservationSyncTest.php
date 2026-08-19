<?php

namespace Tests\Feature;

use App\Livewire\EventHotelPlanEditor;
use App\Models\Contractor;
use App\Models\ContractorType;
use App\Models\Event;
use App\Models\EventHotelStay;
use App\Models\Reservation;
use App\Models\User;
use App\Services\HotelStayReservationSync;
use App\Support\EventReadinessIndicators;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HotelStayReservationSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_one_reservation_is_shared_across_nights_of_same_hotel(): void
    {
        $user = User::factory()->create();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user->assignRole('admin');
        $this->actingAs($user);

        $event = Event::factory()->create(['duration_days' => 4]);
        $hotel = $this->createHotel('Hotel Grupowy');

        $stay1 = EventHotelStay::create([
            'event_id' => $event->id,
            'day' => 1,
            'contractor_id' => $hotel->id,
        ]);
        $stay2 = EventHotelStay::create([
            'event_id' => $event->id,
            'day' => 2,
            'contractor_id' => $hotel->id,
        ]);
        $stay3 = EventHotelStay::create([
            'event_id' => $event->id,
            'day' => 3,
            'contractor_id' => $hotel->id,
        ]);

        $sync = app(HotelStayReservationSync::class);
        $reservation = $sync->ensureForStay($stay1->fresh(['event']));

        $this->assertNotNull($reservation);
        $this->assertSame($reservation->id, (int) $stay2->fresh()->reservation_id);
        $this->assertSame($reservation->id, (int) $stay3->fresh()->reservation_id);
        $this->assertSame(1, Reservation::query()->where('event_id', $event->id)->count());
    }

    public function test_updating_status_from_hotel_editor_updates_shared_reservation(): void
    {
        $user = User::factory()->create();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user->assignRole('admin');
        $this->actingAs($user);

        $event = Event::factory()->create(['duration_days' => 3]);
        $hotel = $this->createHotel('Hotel Status');

        EventHotelStay::create(['event_id' => $event->id, 'day' => 1, 'contractor_id' => $hotel->id]);
        EventHotelStay::create(['event_id' => $event->id, 'day' => 2, 'contractor_id' => $hotel->id]);

        Livewire::test(EventHotelPlanEditor::class, ['eventId' => $event->id])
            ->assertSee('Rezerwacja hotelu')
            ->set('hotelReservationStatus', 'confirmed')
            ->assertSet('hotelReservationStatus', 'confirmed');

        $reservation = Reservation::query()->where('event_id', $event->id)->where('contractor_id', $hotel->id)->first();
        $this->assertNotNull($reservation);
        $this->assertSame('confirmed', $reservation->status);
        $this->assertNotNull($reservation->confirmed_at);
    }

    public function test_readiness_hotel_ok_when_reservation_confirmed(): void
    {
        $event = Event::factory()->create(['duration_days' => 3]);
        $hotel = $this->createHotel('Hotel Gotowość');

        $reservation = Reservation::query()->create([
            'event_id' => $event->id,
            'contractor_id' => $hotel->id,
            'status' => 'pending',
            'reserved_at' => now(),
            'participant_count' => 10,
        ]);

        EventHotelStay::create([
            'event_id' => $event->id,
            'day' => 1,
            'contractor_id' => $hotel->id,
            'reservation_id' => $reservation->id,
        ]);
        EventHotelStay::create([
            'event_id' => $event->id,
            'day' => 2,
            'contractor_id' => $hotel->id,
            'reservation_id' => $reservation->id,
        ]);

        $items = collect(EventReadinessIndicators::forEvent($event->fresh(['hotelStays.reservation', 'hotelStays.contractor'])));
        $hotelItem = $items->firstWhere('key', 'hotel');
        $this->assertSame('brak', $hotelItem['short']);

        $reservation->update(['status' => 'confirmed', 'confirmed_at' => now()->toDateString()]);

        $items = collect(EventReadinessIndicators::forEvent($event->fresh(['hotelStays.reservation', 'hotelStays.contractor'])));
        $hotelItem = $items->firstWhere('key', 'hotel');
        $this->assertSame('OK', $hotelItem['short']);
        $this->assertSame('ok', $hotelItem['tone']);
    }

    public function test_backfill_creates_one_reservation_for_existing_hotel_nights(): void
    {
        $event = Event::factory()->create(['duration_days' => 3, 'participant_count' => 20]);
        $hotel = $this->createHotel('Hotel Backfill');
        $point = \App\Models\EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'name' => 'Nocleg',
            'day' => 1,
            'is_hotel' => true,
            'contractor_id' => $hotel->id,
            'planned_price' => 3500,
        ]);

        EventHotelStay::create([
            'event_id' => $event->id,
            'day' => 1,
            'contractor_id' => $hotel->id,
            'event_program_point_id' => $point->id,
        ]);
        EventHotelStay::create([
            'event_id' => $event->id,
            'day' => 2,
            'contractor_id' => $hotel->id,
            'event_program_point_id' => $point->id,
        ]);

        $this->assertSame(0, Reservation::query()->where('event_id', $event->id)->count());

        $ensured = app(HotelStayReservationSync::class)->backfillForEvent($event->fresh(['hotelStays']));

        $this->assertSame(1, $ensured);
        $reservation = Reservation::query()->where('event_id', $event->id)->first();
        $this->assertNotNull($reservation);
        $this->assertSame($hotel->id, (int) $reservation->contractor_id);
        $this->assertSame($point->id, (int) $reservation->program_point_id);
        $this->assertSame($event->id, (int) $reservation->event_id);
        $this->assertSame($reservation->id, (int) $point->fresh()->reservation_id);
        $this->assertSame(
            [(int) $reservation->id, (int) $reservation->id],
            EventHotelStay::query()
                ->where('event_id', $event->id)
                ->orderBy('day')
                ->pluck('reservation_id')
                ->map(fn ($id) => (int) $id)
                ->all()
        );
    }

    public function test_two_hotels_require_each_confirmation(): void
    {
        $event = Event::factory()->create(['duration_days' => 3]);
        $hotelA = $this->createHotel('Hotel A');
        $hotelB = $this->createHotel('Hotel B');

        $resA = Reservation::query()->create([
            'event_id' => $event->id,
            'contractor_id' => $hotelA->id,
            'status' => 'confirmed',
            'confirmed_at' => now()->toDateString(),
            'reserved_at' => now(),
            'participant_count' => 10,
        ]);
        $resB = Reservation::query()->create([
            'event_id' => $event->id,
            'contractor_id' => $hotelB->id,
            'status' => 'pending',
            'reserved_at' => now(),
            'participant_count' => 10,
        ]);

        EventHotelStay::create([
            'event_id' => $event->id,
            'day' => 1,
            'contractor_id' => $hotelA->id,
            'reservation_id' => $resA->id,
        ]);
        EventHotelStay::create([
            'event_id' => $event->id,
            'day' => 2,
            'contractor_id' => $hotelB->id,
            'reservation_id' => $resB->id,
        ]);

        $hotelItem = collect(EventReadinessIndicators::forEvent(
            $event->fresh(['hotelStays.reservation', 'hotelStays.contractor'])
        ))->firstWhere('key', 'hotel');

        $this->assertSame('w toku', $hotelItem['short']);
        $this->assertSame('warn', $hotelItem['tone']);
        $this->assertStringContainsString('Hotel B', $hotelItem['title']);
    }

    private function createHotel(string $name): Contractor
    {
        $contractor = Contractor::create([
            'name' => $name,
            'status' => 'active',
            'city' => 'Warszawa',
        ]);
        $type = ContractorType::query()->firstOrCreate(['name' => 'hotel']);
        $contractor->types()->sync([$type->id]);

        return $contractor;
    }
}
