<?php

namespace Tests\Feature;

use App\Actions\Reservations\UpsertReservationAction;
use App\Data\UpsertReservationData;
use App\Models\Contractor;
use App\Models\Event;
use App\Models\EventProgramPoint;
use App\Models\EventTemplateProgramPoint;
use App\Models\Reservation;
use App\Models\User;
use App\Services\ProgramPointReservationSync;
use App\Support\Reservations\ProgramPointReservationGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProgramPointReservationSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_same_template_without_contractor_does_not_share_reservation(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $template = EventTemplateProgramPoint::factory()->create(['name' => 'Przewodnik']);
        $event = Event::factory()->create(['duration_days' => 3]);
        $paris = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'event_template_program_point_id' => $template->id,
            'name' => 'Przewodnik',
            'day' => 2,
        ]);
        $versailles = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'event_template_program_point_id' => $template->id,
            'name' => 'Przewodnik',
            'day' => 3,
        ]);

        $this->assertNull(ProgramPointReservationGroup::coverageLabel($paris));

        $reservation = app(UpsertReservationAction::class)(new UpsertReservationData(
            attributes: [
                'status' => 'confirmed',
                'booking_reference' => 'GUIDE-PARIS',
                'participant_count' => 10,
            ],
            programPoint: $paris,
            createdBy: $user->id,
        ));

        $this->assertSame($reservation->id, (int) $paris->fresh()->reservation_id);
        $this->assertNull($versailles->fresh()->reservation_id);
        $this->assertNull($versailles->fresh()->latestVisibleReservation());
    }

    public function test_points_with_same_contractor_share_one_reservation(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $guide = Contractor::create(['name' => 'Przewodnik Paris', 'status' => 'active']);
        $event = Event::factory()->create(['duration_days' => 3]);
        $day2 = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'name' => 'Przewodnik Paryż 5 godzin',
            'day' => 2,
            'contractor_id' => $guide->id,
        ]);
        $day3 = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'name' => 'Przewodnik Paryż cały dzień',
            'day' => 3,
            'contractor_id' => $guide->id,
        ]);

        $reservation = app(UpsertReservationAction::class)(new UpsertReservationData(
            attributes: [
                'status' => 'pending',
                'participant_count' => 10,
            ],
            programPoint: $day2,
            createdBy: $user->id,
        ));

        $this->assertSame($reservation->id, (int) $day3->fresh()->reservation_id);
        $this->assertSame(
            'Wspólna rezerwacja u Przewodnik Paris — dni 2, 3',
            ProgramPointReservationGroup::coverageLabel($day3->fresh(['contractor']))
        );
    }

    public function test_same_name_with_different_contractors_stays_separate(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $parisGuide = Contractor::create(['name' => 'Guide Paris', 'status' => 'active']);
        $lyonGuide = Contractor::create(['name' => 'Guide Lyon', 'status' => 'active']);
        $template = EventTemplateProgramPoint::factory()->create(['name' => 'Przewodnik']);
        $event = Event::factory()->create(['duration_days' => 4]);
        $paris = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'event_template_program_point_id' => $template->id,
            'name' => 'Przewodnik',
            'day' => 2,
            'contractor_id' => $parisGuide->id,
        ]);
        $lyon = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'event_template_program_point_id' => $template->id,
            'name' => 'Przewodnik',
            'day' => 4,
            'contractor_id' => $lyonGuide->id,
        ]);

        app(UpsertReservationAction::class)(new UpsertReservationData(
            attributes: ['status' => 'pending', 'participant_count' => 10],
            programPoint: $paris,
            createdBy: $user->id,
        ));
        app(UpsertReservationAction::class)(new UpsertReservationData(
            attributes: ['status' => 'pending', 'participant_count' => 10],
            programPoint: $lyon,
            createdBy: $user->id,
        ));

        $this->assertSame(2, Reservation::query()->where('event_id', $event->id)->count());
        $this->assertNotSame(
            (int) $paris->fresh()->reservation_id,
            (int) $lyon->fresh()->reservation_id
        );
    }

    public function test_transport_points_with_same_contractor_share_reservation(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $bus = Contractor::create(['name' => 'Autokar Grupowy', 'status' => 'active']);
        $event = Event::factory()->create(['duration_days' => 3]);
        $day1 = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'name' => 'Przejazd autokarem do Paryża',
            'day' => 1,
            'is_transport' => true,
            'contractor_id' => $bus->id,
        ]);
        $day4 = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'name' => 'Przejazd powrotny',
            'day' => 4,
            'is_transport' => true,
            'contractor_id' => $bus->id,
        ]);

        $reservation = app(UpsertReservationAction::class)(new UpsertReservationData(
            attributes: [
                'status' => 'pending',
                'participant_count' => 10,
            ],
            programPoint: $day1,
            createdBy: $user->id,
        ));

        $this->assertSame($reservation->id, (int) $day4->fresh()->reservation_id);
        $this->assertSame(
            'Wspólna rezerwacja u Autokar Grupowy — dni 1, 4',
            ProgramPointReservationGroup::coverageLabel($day4->fresh(['contractor']))
        );
    }

    public function test_backfill_unlinks_template_siblings_without_contractor(): void
    {
        $template = EventTemplateProgramPoint::factory()->create(['name' => 'Drugi kierowca']);
        $event = Event::factory()->create(['duration_days' => 2]);
        $day1 = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'event_template_program_point_id' => $template->id,
            'name' => 'Drugi kierowca',
            'day' => 1,
        ]);
        $day2 = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'event_template_program_point_id' => $template->id,
            'name' => 'Drugi kierowca',
            'day' => 2,
        ]);

        $reservation = Reservation::query()->create([
            'event_id' => $event->id,
            'program_point_id' => $day1->id,
            'status' => 'confirmed',
            'reserved_at' => now(),
            'participant_count' => 10,
        ]);

        $day1->forceFill(['reservation_id' => $reservation->id])->saveQuietly();
        $day2->forceFill(['reservation_id' => $reservation->id])->saveQuietly();

        app(ProgramPointReservationSync::class)->backfillForEvent($event);

        $this->assertSame($reservation->id, (int) $day1->fresh()->reservation_id);
        $this->assertNull($day2->fresh()->reservation_id);
    }

    public function test_link_points_does_not_backfill_contractor_on_przejazd_do_hotelu(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $hotel = Contractor::create(['name' => 'Hotel Central', 'status' => 'active']);
        $event = Event::factory()->create(['duration_days' => 2]);

        $transfer = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'day' => 1,
            'order' => 1,
            'name' => 'Przejazd do hotelu',
            'is_hotel' => true,
            'contractor_id' => $hotel->id,
        ]);
        $hotelPoint = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'day' => 1,
            'order' => 2,
            'name' => 'Nocleg',
            'is_hotel' => true,
            'contractor_id' => $hotel->id,
        ]);

        $reservation = app(UpsertReservationAction::class)(new UpsertReservationData(
            attributes: [
                'status' => 'pending',
                'participant_count' => 10,
            ],
            programPoint: $hotelPoint,
            createdBy: $user->id,
        ));

        app(ProgramPointReservationSync::class)->linkPoints($reservation, $hotelPoint);

        $transfer->refresh();
        $hotelPoint->refresh();

        $this->assertSame($hotel->id, (int) $transfer->contractor_id);
        $this->assertNull($transfer->reservation_id);
        $this->assertSame($hotel->id, (int) $hotelPoint->contractor_id);
        $this->assertSame($reservation->id, (int) $hotelPoint->reservation_id);
    }
}
