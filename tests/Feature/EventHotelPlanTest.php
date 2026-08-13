<?php

namespace Tests\Feature;

use App\Models\Contractor;
use App\Models\Event;
use App\Models\EventHotelRoomLine;
use App\Models\EventHotelStay;
use App\Models\EventTemplate;
use App\Models\EventTemplateHotelDay;
use App\Models\HotelRoom;
use App\Models\Place;
use App\Models\User;
use App\Services\EventHotelOccupantsImporter;
use App\Services\EventHotelOccupantsTemplateBuilder;
use App\Services\EventHotelPlanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventHotelPlanTest extends TestCase
{
    use RefreshDatabase;

    public function test_snapshot_from_template_creates_stays_and_room_lines(): void
    {
        $user = User::factory()->create();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user->assignRole('admin');
        $this->actingAs($user);

        $place = Place::create(['name' => 'Warszawa']);
        $template = EventTemplate::factory()->create([
            'name' => 'Szablon hotel',
            'duration_days' => 3,
            'start_place_id' => $place->id,
        ]);

        $room = HotelRoom::create([
            'name' => 'Dwuosobowy',
            'people_count' => 2,
            'price' => 200,
            'currency' => 'PLN',
            'convert_to_pln' => true,
        ]);

        EventTemplateHotelDay::create([
            'event_template_id' => $template->id,
            'day' => 1,
            'hotel_room_ids_qty' => [$room->id],
            'hotel_room_ids_gratis' => [],
            'hotel_room_ids_staff' => [],
            'hotel_room_ids_driver' => [],
            'notes' => 'Śniadanie w cenie',
        ]);

        EventTemplateHotelDay::create([
            'event_template_id' => $template->id,
            'day' => 2,
            'hotel_room_ids_qty' => [$room->id],
            'hotel_room_ids_gratis' => [],
            'hotel_room_ids_staff' => [],
            'hotel_room_ids_driver' => [],
        ]);

        $event = Event::createFromTemplate($template, [
            'name' => 'Wycieczka hotelowa',
            'client_name' => 'Klient',
            'start_date' => now()->format('Y-m-d'),
            'participant_count' => 10,
        ]);

        $this->assertDatabaseCount('event_hotel_stays', 2);
        $this->assertTrue($event->hotelStays()->first()->roomLines()->exists());

        $stay = $event->hotelStays()->where('day', 1)->first();
        $this->assertSame('Śniadanie w cenie', $stay->notes);
    }

    public function test_create_from_template_allocates_all_hotel_roles_after_qty_variants(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $place = Place::create(['name' => 'Kraków']);
        $template = EventTemplate::factory()->create([
            'duration_days' => 2,
            'start_place_id' => $place->id,
        ]);

        $roomQty = HotelRoom::create([
            'name' => 'Trzyosobowy',
            'people_count' => 3,
            'price' => 300,
            'currency' => 'PLN',
            'convert_to_pln' => true,
        ]);
        $roomStaff = HotelRoom::create([
            'name' => 'Jedynka',
            'people_count' => 1,
            'price' => 150,
            'currency' => 'PLN',
            'convert_to_pln' => true,
        ]);

        \App\Models\EventTemplateQty::create([
            'event_template_id' => $template->id,
            'qty' => 30,
            'gratis' => 2,
            'staff' => 1,
            'driver' => 1,
        ]);

        EventTemplateHotelDay::create([
            'event_template_id' => $template->id,
            'day' => 1,
            'hotel_room_ids_qty' => [$roomQty->id],
            'hotel_room_ids_gratis' => [$roomStaff->id],
            'hotel_room_ids_staff' => [$roomStaff->id],
            'hotel_room_ids_driver' => [$roomStaff->id],
        ]);

        $event = Event::createFromTemplate($template, [
            'name' => 'Wycieczka z pełnym hotelem',
            'client_name' => 'Klient',
            'start_date' => now()->format('Y-m-d'),
            'participant_count' => 30,
            'gratis_count' => 2,
        ]);

        $stay = $event->hotelStays()->where('day', 1)->first();
        $this->assertNotNull($stay);

        $roles = $stay->roomLines()->pluck('role')->unique()->sort()->values()->all();
        $this->assertContains('qty', $roles);
        $this->assertContains('gratis', $roles);
        $this->assertContains('staff', $roles);
        $this->assertContains('driver', $roles);
    }

    public function test_copy_structure_to_all_stays(): void
    {
        $event = Event::factory()->create(['duration_days' => 3, 'participant_count' => 20]);
        $contractor = Contractor::create(['name' => 'Hotel Test', 'nip' => '1234567890']);

        $stay1 = EventHotelStay::create([
            'event_id' => $event->id,
            'day' => 1,
            'contractor_id' => $contractor->id,
            'offer_notes' => 'Kolacja',
        ]);
        $stay2 = EventHotelStay::create(['event_id' => $event->id, 'day' => 2]);

        $room = HotelRoom::create([
            'name' => 'Suite',
            'people_count' => 2,
            'price' => 300,
            'currency' => 'PLN',
            'convert_to_pln' => true,
        ]);

        $stay1->roomLines()->create([
            'hotel_room_id' => $room->id,
            'role' => 'qty',
            'quantity' => 2,
            'unit_price' => 300,
            'convert_to_pln' => true,
            'order' => 0,
        ]);

        app(EventHotelPlanService::class)->copyStructureToAllStays($event->fresh(), 1);

        $stay2->refresh();
        $this->assertSame($contractor->id, $stay2->contractor_id);
        $this->assertSame('Kolacja', $stay2->offer_notes);
        $this->assertCount(1, $stay2->roomLines);
    }

    public function test_validate_duplicate_occupants_on_same_night(): void
    {
        $service = app(EventHotelPlanService::class);

        $this->expectException(ValidationException::class);

        $service->validateNoDuplicateOccupants([
            [
                'room_lines' => [
                    [
                        'occupants' => [
                            ['event_agreement_id' => 5, 'name' => 'Jan Kowalski'],
                        ],
                    ],
                    [
                        'occupants' => [
                            ['event_agreement_id' => 5, 'name' => 'Jan Kowalski'],
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function test_pdf_renders_offer_notes_from_event_plan(): void
    {
        $event = Event::factory()->create([
            'name' => 'Impreza PDF',
            'duration_days' => 2,
            'hotel_notes' => 'Globalne uwagi',
        ]);

        $stay = EventHotelStay::create([
            'event_id' => $event->id,
            'day' => 1,
            'offer_notes' => 'Śniadanie bufet + parking',
        ]);

        $stay->roomLines()->create([
            'hotel_room_id' => null,
            'label' => 'Apartament',
            'role' => 'qty',
            'quantity' => 1,
            'unit_price' => 450,
            'convert_to_pln' => true,
            'order' => 0,
        ]);

        $plan = app(EventHotelPlanService::class)->buildHotelPlanForPdf($event->fresh());

        $html = view('pdf.packages.hotel', [
            'audience' => 'hotel',
            'audienceLabel' => 'Pakiet dla hotelu',
            'event' => $event,
            'company' => ['name' => 'Test', 'phone' => '1', 'email' => 'a@b.c'],
            'logoDataUri' => null,
            'generatedAt' => now(),
            'participantCount' => 10,
            'staffCount' => 0,
            'driverCount' => 1,
            'gratisCount' => 0,
            'participantSummaryLine' => '10 uczestników',
            'travelLegends' => [],
            'hotelNotes' => 'Globalne uwagi',
            'hotelProgramPoints' => collect(),
            'hotelPlan' => $plan,
            'usesEventHotelPlan' => true,
            'programByDay' => collect(),
            'agreements' => collect(),
            'individualAgreementRows' => collect(),
            'agreementsSummary' => [],
            'documentFocus' => [],
            'selectedSettlementDocuments' => collect(),
            'attachedFiles' => collect(),
        ])->render();

        $this->assertStringContainsString('Śniadanie bufet + parking', $html);
        $this->assertStringContainsString('Apartament', $html);
        $this->assertStringContainsString('Plan pokoi (impreza)', $html);
    }

    public function test_import_occupants_from_spreadsheet(): void
    {
        $event = Event::factory()->create(['duration_days' => 2, 'participant_count' => 10]);

        $stay = EventHotelStay::create(['event_id' => $event->id, 'day' => 1]);
        $room = HotelRoom::create([
            'name' => 'Pokój 2-osobowy',
            'people_count' => 2,
            'price' => 200,
            'currency' => 'PLN',
            'convert_to_pln' => true,
        ]);

        $stay->roomLines()->create([
            'hotel_room_id' => $room->id,
            'role' => 'qty',
            'quantity' => 1,
            'unit_price' => 200,
            'convert_to_pln' => true,
            'order' => 0,
        ]);

        $path = storage_path('framework/testing/hotel-occupants-import.csv');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, "\xEF\xBB\xBF"."Noc;Hotel;Typ pokoju;Pokój;Miejsce;Imię i nazwisko\n1;;Pokój 2-osobowy;1/1;1/2;Jan Kowalski\n");

        $result = app(EventHotelOccupantsImporter::class)->importFromPath($event->fresh(), $path);

        $this->assertSame(1, $result['imported']);
        $occupant = $stay->fresh()->roomLines->first()->occupants->first();
        $this->assertSame('Jan Kowalski', $occupant->name);
        $this->assertSame('manual', $occupant->source);
        $this->assertSame(1, $occupant->unit_index);
        $this->assertSame(1, $occupant->bed_index);
    }

    public function test_import_template_csv_contains_event_rooms(): void
    {
        $user = User::factory()->create();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user->assignRole('admin');
        $this->actingAs($user);

        $event = Event::factory()->create(['duration_days' => 2, 'name' => 'Test Wycieczka']);
        $stay = EventHotelStay::create(['event_id' => $event->id, 'day' => 1]);
        $room = HotelRoom::create([
            'name' => 'Studio',
            'people_count' => 2,
            'price' => 200,
            'currency' => 'PLN',
            'convert_to_pln' => true,
        ]);
        $stay->roomLines()->create([
            'hotel_room_id' => $room->id,
            'role' => 'qty',
            'quantity' => 1,
            'unit_price' => 200,
            'convert_to_pln' => true,
            'order' => 0,
        ]);

        $response = $this->get(route('admin.events.hotel-occupants.import-template', [
            'event' => $event->id,
            'format' => 'csv',
        ]));

        $response->assertOk();
        $content = $response->streamedContent();
        $this->assertStringContainsString('Typ pokoju', $content);
        $this->assertStringContainsString('Studio', $content);
        $this->assertStringContainsString('# INSTRUKCJA', $content);
    }

    public function test_template_has_one_row_per_person_slot(): void
    {
        $event = Event::factory()->create(['duration_days' => 2, 'name' => 'Slot test']);
        $stay = EventHotelStay::create(['event_id' => $event->id, 'day' => 1]);
        $room = HotelRoom::create([
            'name' => 'Triple',
            'people_count' => 3,
            'price' => 300,
            'currency' => 'PLN',
            'convert_to_pln' => true,
        ]);
        $stay->roomLines()->create([
            'hotel_room_id' => $room->id,
            'role' => 'qty',
            'quantity' => 2,
            'people_count' => 3,
            'unit_price' => 300,
            'convert_to_pln' => true,
            'order' => 0,
        ]);

        $rows = app(EventHotelOccupantsTemplateBuilder::class)->dataRows($event->fresh());

        $this->assertCount(6, $rows);
    }

    public function test_flat_stay_pricing_overrides_line_totals(): void
    {
        $event = Event::factory()->create([
            'duration_days' => 3,
            'hotel_pricing_mode' => 'flat_stay',
            'hotel_flat_stay_amount' => 12000,
        ]);

        $stay = EventHotelStay::create(['event_id' => $event->id, 'day' => 1]);
        $stay->roomLines()->create([
            'role' => 'qty',
            'quantity' => 10,
            'people_count' => 3,
            'unit_price' => 670,
            'convert_to_pln' => true,
            'order' => 0,
        ]);

        $service = app(EventHotelPlanService::class);

        $this->assertSame(12000.0, $service->totalPlnForEvent($event->fresh()));
    }

    public function test_flat_night_pricing_overrides_line_totals_for_stay(): void
    {
        $event = Event::factory()->create(['duration_days' => 3]);

        $stay = EventHotelStay::create([
            'event_id' => $event->id,
            'day' => 1,
            'pricing_mode' => 'flat_night',
            'flat_amount' => 4500,
        ]);
        $stay->roomLines()->create([
            'role' => 'qty',
            'quantity' => 5,
            'people_count' => 2,
            'unit_price' => 300,
            'convert_to_pln' => true,
            'order' => 0,
        ]);

        $this->assertSame(4500.0, $stay->fresh()->totalPln());
        $this->assertSame(4500.0, app(EventHotelPlanService::class)->totalPlnForEvent($event->fresh()));
    }

    public function test_per_person_price_basis_multiplies_by_people_count(): void
    {
        $event = Event::factory()->create(['duration_days' => 1]);

        $stay = EventHotelStay::create(['event_id' => $event->id, 'day' => 1]);
        $stay->roomLines()->create([
            'role' => 'qty',
            'quantity' => 5,
            'people_count' => 3,
            'unit_price' => 100,
            'price_basis' => EventHotelRoomLine::PRICE_BASIS_PER_PERSON,
            'convert_to_pln' => true,
            'order' => 0,
        ]);

        $line = $stay->fresh()->roomLines->first();

        $this->assertSame(1500.0, $line->lineTotal());
        $this->assertSame(1500.0, $stay->fresh()->totalPln());
        $this->assertSame(1500.0, app(EventHotelPlanService::class)->totalPlnForEvent($event->fresh()));
    }

    public function test_per_room_price_basis_is_default_and_uses_quantity_only(): void
    {
        $event = Event::factory()->create(['duration_days' => 1]);

        $stay = EventHotelStay::create(['event_id' => $event->id, 'day' => 1]);
        $stay->roomLines()->create([
            'role' => 'qty',
            'quantity' => 5,
            'people_count' => 3,
            'unit_price' => 100,
            'convert_to_pln' => true,
            'order' => 0,
        ]);

        $line = $stay->fresh()->roomLines->first();

        $this->assertSame(EventHotelRoomLine::PRICE_BASIS_PER_ROOM, $line->resolvedPriceBasis());
        $this->assertSame(500.0, $line->lineTotal());
    }

    public function test_copy_occupants_to_all_stays_with_matching_structure(): void
    {
        $event = Event::factory()->create(['duration_days' => 3, 'participant_count' => 10]);

        $stay1 = EventHotelStay::create(['event_id' => $event->id, 'day' => 1]);
        $stay2 = EventHotelStay::create(['event_id' => $event->id, 'day' => 2, 'same_as_day' => 1]);

        $line1 = $stay1->roomLines()->create([
            'role' => 'qty',
            'quantity' => 1,
            'people_count' => 2,
            'unit_price' => 200,
            'convert_to_pln' => true,
            'order' => 0,
        ]);
        $stay2->roomLines()->create([
            'role' => 'qty',
            'quantity' => 1,
            'people_count' => 2,
            'unit_price' => 200,
            'convert_to_pln' => true,
            'order' => 0,
        ]);

        $line1->occupants()->create([
            'name' => 'Jan Kowalski',
            'source' => 'manual',
            'unit_index' => 1,
            'bed_index' => 1,
            'order' => 0,
        ]);

        app(EventHotelPlanService::class)->copyOccupantsToAllStays($event->fresh(), 1);

        $stay2Occupant = $stay2->fresh()->roomLines->first()->occupants->first();
        $this->assertSame('Jan Kowalski', $stay2Occupant->name);
        $this->assertSame(1, $stay2Occupant->unit_index);
        $this->assertSame(1, $stay2Occupant->bed_index);
    }

    public function test_pilot_can_save_room_numbers_for_assigned_event(): void
    {
        Role::firstOrCreate(['name' => 'pilot']);
        Role::firstOrCreate(['name' => 'admin']);

        $pilot = User::factory()->create(['status' => 'active']);
        $pilot->assignRole('pilot');

        $event = Event::factory()->create(['duration_days' => 2, 'assigned_to' => $pilot->id]);
        $stay = EventHotelStay::create(['event_id' => $event->id, 'day' => 1]);
        $line = $stay->roomLines()->create([
            'role' => 'qty',
            'quantity' => 2,
            'unit_price' => 300,
            'people_count' => 3,
            'convert_to_pln' => true,
            'order' => 0,
        ]);

        app(EventHotelPlanService::class)->syncRoomUnits($line);
        $unit = $line->fresh()->units->first();

        app(EventHotelPlanService::class)->updateUnitRoomNumbers($event, [
            $unit->id => '214',
        ]);

        $this->assertSame('214', $unit->fresh()->room_number);
    }

    public function test_pilot_hotel_plan_page_accessible_for_pilot_and_admin(): void
    {
        Role::firstOrCreate(['name' => 'pilot']);
        Role::firstOrCreate(['name' => 'admin']);

        $pilot = User::factory()->create(['status' => 'active']);
        $pilot->assignRole('pilot');
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('admin');

        $this->assertFalse(\App\Filament\Pilot\Pages\PilotHotelPlanPage::canAccess());
        $this->actingAs($pilot);
        $this->assertTrue(\App\Filament\Pilot\Pages\PilotHotelPlanPage::canAccess());
        $this->actingAs($admin);
        $this->assertTrue(\App\Filament\Pilot\Pages\PilotHotelPlanPage::canAccess());
    }

    public function test_selecting_hotel_persists_contractor_without_full_plan_save(): void
    {
        $user = User::factory()->create();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user->assignRole('admin');
        $this->actingAs($user);

        $event = Event::factory()->create(['duration_days' => 3]);
        $contractor = Contractor::create(['name' => 'Hotel Auto Save', 'nip' => '9876543210']);

        EventHotelStay::create(['event_id' => $event->id, 'day' => 1]);
        EventHotelStay::create(['event_id' => $event->id, 'day' => 2]);

        Livewire::test(\App\Livewire\EventHotelPlanEditor::class, ['eventId' => $event->id])
            ->set('stays.0.contractor_id', $contractor->id)
            ->assertHasNoErrors();

        $this->assertSame(
            $contractor->id,
            (int) $event->hotelStays()->where('day', 1)->value('contractor_id'),
        );
    }
}
