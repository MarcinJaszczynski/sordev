<?php

namespace Tests\Feature;

use App\Models\Contractor;
use App\Models\Currency;
use App\Models\Event;
use App\Models\EventHotelRoomLine;
use App\Models\EventHotelStay;
use App\Models\EventQty;
use App\Models\EventTemplate;
use App\Models\EventTemplateHotelDay;
use App\Models\HotelRoom;
use App\Models\Place;
use App\Models\User;
use App\Services\EventHotelOccupantsImporter;
use App\Services\EventHotelOccupantsTemplateBuilder;
use App\Services\EventHotelPlanService;
use App\Services\HotelStaySettlementSync;
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
        $this->assertNull($stay2->contractor_id);
        $this->assertSame('Kolacja', $stay2->offer_notes);
        $this->assertCount(1, $stay2->roomLines);
    }

    public function test_copy_structure_keeps_target_hotel_and_reservation(): void
    {
        $event = Event::factory()->create(['duration_days' => 3, 'participant_count' => 20]);
        $hotelA = Contractor::create(['name' => 'Hotel A', 'status' => 'active']);
        $hotelB = Contractor::create(['name' => 'Hotel B', 'status' => 'active']);

        $reservation = \App\Models\Reservation::query()->create([
            'event_id' => $event->id,
            'contractor_id' => $hotelB->id,
            'status' => 'confirmed',
            'confirmed_at' => now()->toDateString(),
            'reserved_at' => now(),
            'participant_count' => 10,
        ]);

        $stay1 = EventHotelStay::create([
            'event_id' => $event->id,
            'day' => 1,
            'contractor_id' => $hotelA->id,
        ]);
        $stay2 = EventHotelStay::create([
            'event_id' => $event->id,
            'day' => 2,
            'contractor_id' => $hotelB->id,
            'reservation_id' => $reservation->id,
        ]);

        $room = HotelRoom::create([
            'name' => 'Twin',
            'people_count' => 2,
            'price' => 250,
            'currency' => 'PLN',
            'convert_to_pln' => true,
        ]);

        $stay1->roomLines()->create([
            'hotel_room_id' => $room->id,
            'role' => 'qty',
            'quantity' => 3,
            'unit_price' => 250,
            'convert_to_pln' => true,
            'order' => 0,
        ]);

        app(EventHotelPlanService::class)->copyStructureToAllStays($event->fresh(), 1);

        $stay2->refresh();
        $this->assertSame($hotelB->id, (int) $stay2->contractor_id);
        $this->assertSame((int) $reservation->id, (int) $stay2->reservation_id);
        $this->assertCount(1, $stay2->roomLines);
        $this->assertSame(3, (int) $stay2->roomLines->first()->quantity);
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
        $this->assertStringContainsString('Pokoje', $html);
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

    public function test_import_occupants_accepts_second_bed_from_catalog_room_capacity(): void
    {
        $event = Event::factory()->create(['duration_days' => 2, 'participant_count' => 10]);

        $stay = EventHotelStay::create(['event_id' => $event->id, 'day' => 1]);
        $room = HotelRoom::create([
            'name' => 'Karkonosze Twin Szablon',
            'people_count' => 2,
            'price' => 200,
            'currency' => 'PLN',
            'convert_to_pln' => true,
        ]);

        $stay->roomLines()->create([
            'hotel_room_id' => $room->id,
            'role' => 'qty',
            'quantity' => 2,
            'unit_price' => 200,
            'convert_to_pln' => true,
            'order' => 0,
        ]);

        $path = storage_path('framework/testing/hotel-occupants-import-twin.csv');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, "\xEF\xBB\xBF"."Noc;Hotel;Typ pokoju;Pokój;Miejsce;Imię i nazwisko\n"
            ."1;;Karkonosze Twin Szablon;1/2;1/2;Jan Kowalski\n"
            ."1;;Karkonosze Twin Szablon;1/2;2/2;Anna Nowak\n");

        $result = app(EventHotelOccupantsImporter::class)->importFromPath($event->fresh(), $path);

        $this->assertSame(2, $result['imported']);
        $this->assertSame(0, $result['skipped']);
        $occupants = $stay->fresh()->roomLines->first()->occupants->sortBy('bed_index')->values();
        $this->assertSame('Jan Kowalski', $occupants[0]->name);
        $this->assertSame(1, $occupants[0]->bed_index);
        $this->assertSame('Anna Nowak', $occupants[1]->name);
        $this->assertSame(2, $occupants[1]->bed_index);
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
        $this->markTestSkipped('Flat pricing wycofane — kalkulacja tylko z linii pokoi (S/P).');
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
        $this->markTestSkipped('Flat pricing wycofane — kalkulacja tylko z linii pokoi (S/P).');
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

    public function test_flat_night_per_person_multiplies_by_required_beds(): void
    {
        $this->markTestSkipped('Flat pricing wycofane — kalkulacja tylko z linii pokoi (S/P).');
        $event = Event::factory()->create([
            'duration_days' => 2,
            'participant_count' => 10,
        ]);

        EventQty::create([
            'event_id' => $event->id,
            'qty' => 10,
            'gratis' => 2,
            'staff' => 0,
            'driver' => 0,
        ]);

        $stay = EventHotelStay::create([
            'event_id' => $event->id,
            'day' => 1,
            'pricing_mode' => 'flat_night_per_person',
            'flat_amount' => 100,
        ]);
        $stay->roomLines()->create([
            'role' => 'qty',
            'quantity' => 5,
            'people_count' => 2,
            'unit_price' => 999,
            'convert_to_pln' => true,
            'order' => 0,
        ]);

        // 10 + 2 gratis = 12 osób
        $this->assertSame(1200.0, $stay->fresh()->totalPln());
        $this->assertSame(1200.0, app(EventHotelPlanService::class)->totalPlnForEvent($event->fresh()));
    }

    public function test_flat_stay_per_person_multiplies_by_required_beds_once(): void
    {
        $this->markTestSkipped('Flat pricing wycofane — kalkulacja tylko z linii pokoi (S/P).');
        $event = Event::factory()->create([
            'duration_days' => 3,
            'participant_count' => 10,
            'hotel_pricing_mode' => 'flat_stay_per_person',
            'hotel_flat_stay_amount' => 200,
        ]);

        EventQty::create([
            'event_id' => $event->id,
            'qty' => 10,
            'gratis' => 0,
            'staff' => 0,
            'driver' => 0,
        ]);

        EventHotelStay::create(['event_id' => $event->id, 'day' => 1, 'pricing_mode' => 'lines']);
        EventHotelStay::create(['event_id' => $event->id, 'day' => 2, 'pricing_mode' => 'lines']);

        // Stawka za pobyt × osoby — raz na imprezę, nie × noce.
        $this->assertSame(2000.0, app(EventHotelPlanService::class)->totalPlnForEvent($event->fresh()));
    }

    public function test_flat_night_eur_without_convert_flows_to_calculation_and_settlement(): void
    {
        $this->markTestSkipped('Flat pricing wycofane — kalkulacja tylko z linii pokoi (S/P).');
        $eur = Currency::query()->firstOrCreate(
            ['symbol' => 'EUR'],
            ['exchange_rate' => 4.3, 'name' => 'Euro']
        );

        $event = Event::factory()->create([
            'duration_days' => 1,
            'participant_count' => 10,
            'use_manual_transport_cost' => true,
            'manual_transport_cost' => 0,
        ]);

        $stay = EventHotelStay::create([
            'event_id' => $event->id,
            'day' => 1,
            'pricing_mode' => 'flat_night',
            'flat_amount' => 80,
            'flat_currency_id' => $eur->id,
            'flat_convert_to_pln' => false,
        ]);
        $stay->roomLines()->create([
            'label' => 'Nocleg autokar',
            'role' => 'qty',
            'quantity' => 44,
            'people_count' => 1,
            'unit_price' => 0,
            'convert_to_pln' => true,
            'order' => 0,
        ]);

        $service = app(EventHotelPlanService::class);
        $totals = $service->totalsByCurrencyForEvent($event->fresh());

        $this->assertSame(0.0, (float) ($totals['PLN'] ?? 0));
        $this->assertEqualsWithDelta(80.0, (float) ($totals['EUR'] ?? 0), 0.01);
        $this->assertSame(0.0, $service->totalPlnForEvent($event->fresh()));

        $structure = $service->buildHotelStructureForCalculation($event->fresh())->first();
        $this->assertEqualsWithDelta(80.0, (float) ($structure['day_total']['EUR'] ?? 0), 0.01);

        $calc = \App\Services\EventCostCalculator::for($event->fresh())->calculate(10, 0);
        $this->assertEqualsWithDelta(80.0, (float) ($calc['foreign']['EUR']['base'] ?? 0), 0.01);

        $settlement = \App\Models\EventSettlement::findOrCreateActiveForEvent($event);
        $event->refreshActiveSettlementCosts();
        $cost = $settlement->costs()
            ->where('source_type', \App\Services\HotelStaySettlementSync::SOURCE_STAY)
            ->where('source_id', $stay->id)
            ->first();

        $this->assertNotNull($cost);
        $this->assertEqualsWithDelta(80.0, (float) $cost->planned_amount, 0.01);
        $this->assertFalse((bool) $cost->planned_convert_to_pln);
        $this->assertSame((int) $eur->id, (int) $cost->planned_currency_id);
        $this->assertNull($cost->planned_amount_pln);
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

    public function test_per_person_basis_resolves_people_count_from_catalog_room_when_line_null(): void
    {
        $event = Event::factory()->create(['duration_days' => 2]);
        $room = HotelRoom::create([
            'name' => 'Triple Catalog',
            'people_count' => 3,
            'price' => 210,
            'currency' => 'PLN',
            'convert_to_pln' => true,
        ]);

        $stay = EventHotelStay::create(['event_id' => $event->id, 'day' => 1]);
        $stay->roomLines()->create([
            'hotel_room_id' => $room->id,
            'role' => 'qty',
            'quantity' => 10,
            'people_count' => null,
            'unit_price' => 40,
            'price_basis' => EventHotelRoomLine::PRICE_BASIS_PER_PERSON,
            'convert_to_pln' => true,
            'order' => 0,
        ]);

        // 10 pokoi × 40 zł/os. × 3 os. z katalogu = 1200 (bez fallbacku byłoby 400).
        $this->assertEqualsWithDelta(
            1200.0,
            app(EventHotelPlanService::class)->totalPlnForEvent($event->fresh()),
            0.01,
        );

        $payload = app(EventHotelPlanService::class)->staysToPayload($event->fresh())[0];
        $this->assertEqualsWithDelta(
            1200.0,
            \App\Support\EventHotelPlanFormatting::stayTotalPln($payload, 'lines'),
            0.01,
        );
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

    public function test_hotel_structure_for_calculation_exposes_unit_price_not_line_total(): void
    {
        $event = Event::factory()->create(['duration_days' => 1]);
        $stay = EventHotelStay::create(['event_id' => $event->id, 'day' => 1]);
        $stay->roomLines()->create([
            'label' => 'Triple',
            'role' => 'qty',
            'quantity' => 5,
            'people_count' => 3,
            'unit_price' => 670,
            'price_basis' => EventHotelRoomLine::PRICE_BASIS_PER_ROOM,
            'convert_to_pln' => true,
            'order' => 0,
        ]);

        $structure = app(EventHotelPlanService::class)
            ->buildHotelStructureForCalculation($event->fresh())
            ->values()
            ->all();

        $this->assertCount(1, $structure);
        $this->assertCount(1, $structure[0]['rooms']);

        $room = $structure[0]['rooms'][0];
        $this->assertSame(670.0, (float) $room['cost']);
        $this->assertSame(5, (int) $room['room_count']);
        $this->assertSame(3350.0, (float) $room['line_total']);
        $this->assertSame(5, (int) ($room['alloc']['qty'] ?? 0));
        $this->assertSame(3350.0, (float) ($structure[0]['day_total']['PLN'] ?? 0));
    }

    public function test_saving_hotel_plan_updates_price_per_person_and_settlement_planned_cost(): void
    {
        $event = Event::factory()->create([
            'duration_days' => 1,
            'participant_count' => 10,
            'use_manual_transport_cost' => true,
            'manual_transport_cost' => 0,
            'hotel_calculation_source' => \App\Support\HotelCalculationSource::NEGOTIATED,
        ]);

        $stay = EventHotelStay::create(['event_id' => $event->id, 'day' => 1]);
        $stay->roomLines()->create([
            'label' => 'Twin',
            'role' => 'qty',
            'quantity' => 5,
            'people_count' => 2,
            'unit_price' => 200,
            'offer_unit_price' => 200,
            'price_basis' => EventHotelRoomLine::PRICE_BASIS_PER_ROOM,
            'convert_to_pln' => true,
            'order' => 0,
        ]);

        $settlement = \App\Models\EventSettlement::findOrCreateActiveForEvent($event);
        $event->refreshActiveSettlementCosts();

        $this->assertEqualsWithDelta(
            1000.0,
            (float) $settlement->costs()->where('source_type', 'accommodation_hotel_stay')->value('planned_amount_pln'),
            0.01,
        );

        $pppBefore = $event->fresh()->resolvedPricePerPerson(10);
        $this->assertGreaterThan(0, $pppBefore);

        $service = app(EventHotelPlanService::class);
        $payloads = $service->staysToPayload($event->fresh());
        $payloads[0]['room_lines'][0]['unit_price'] = 400;
        $payloads[0]['room_lines'][0]['quantity'] = 5;

        $service->savePlan($event->fresh(), $payloads);

        $event = $event->fresh();
        $settlement->refresh();

        $this->assertEqualsWithDelta(2000.0, $service->totalPlnForEvent($event), 0.01);
        $this->assertEqualsWithDelta(
            2000.0,
            (float) $settlement->costs()->where('source_type', 'accommodation_hotel_stay')->value('planned_amount_pln'),
            0.01,
        );
        $this->assertGreaterThan($pppBefore, $event->resolvedPricePerPerson(10));
        // total_cost może zawierać inne pozycje z factory — hotel ma wnieść 2000 PLN.
        $this->assertGreaterThanOrEqual(2000.0, (float) $event->total_cost);
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
        Role::firstOrCreate(['name' => 'pilot', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

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
        $type = \App\Models\ContractorType::query()->firstOrCreate(['name' => 'hotel']);
        $contractor->types()->sync([$type->id]);

        EventHotelStay::create(['event_id' => $event->id, 'day' => 1]);
        EventHotelStay::create(['event_id' => $event->id, 'day' => 2]);

        Livewire::test(\App\Livewire\EventHotelPlanEditor::class, ['eventId' => $event->id])
            ->set('hotelContractorSearch', 'Auto Save')
            ->assertSet('showHotelSearchResults', true)
            ->assertSee('Hotel Auto Save')
            ->call('selectHotel', $contractor->id)
            ->assertHasNoErrors()
            ->assertSee('Hotel tej nocy')
            ->assertDontSee('Wyszukaj hotel po nazwie');

        $this->assertSame(
            $contractor->id,
            (int) $event->hotelStays()->where('day', 1)->value('contractor_id'),
        );
    }

    public function test_hotel_livesearch_requires_minimum_query_length(): void
    {
        $user = User::factory()->create();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user->assignRole('admin');
        $this->actingAs($user);

        $event = Event::factory()->create(['duration_days' => 2]);
        EventHotelStay::create(['event_id' => $event->id, 'day' => 1]);

        Livewire::test(\App\Livewire\EventHotelPlanEditor::class, ['eventId' => $event->id])
            ->set('hotelContractorSearch', 'H')
            ->assertSet('showHotelSearchResults', false)
            ->set('hotelContractorSearch', 'Ho')
            ->assertSet('showHotelSearchResults', true);
    }

    public function test_link_stays_does_not_attach_hotel_contractor_to_przejazd_do_hotelu(): void
    {
        $event = Event::factory()->create(['duration_days' => 2]);
        $contractor = Contractor::create(['name' => 'Hotel Górski', 'status' => 'active']);

        $transfer = \App\Models\EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'day' => 1,
            'order' => 1,
            'name' => 'Przejazd do hotelu',
            'is_hotel' => true,
            'contractor_id' => $contractor->id,
        ]);

        $hotelPoint = \App\Models\EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'day' => 1,
            'order' => 2,
            'name' => 'Nocleg',
            'is_hotel' => true,
            'contractor_id' => null,
        ]);

        $stay = EventHotelStay::create([
            'event_id' => $event->id,
            'day' => 1,
            'contractor_id' => $contractor->id,
            'event_program_point_id' => $transfer->id,
        ]);

        app(EventHotelPlanService::class)->linkStaysToProgramPoints($event->fresh(['hotelStays']));

        $transfer->refresh();
        $hotelPoint->refresh();
        $stay->refresh();

        $this->assertFalse((bool) $transfer->is_hotel);
        $this->assertNull($transfer->contractor_id);
        $this->assertSame($hotelPoint->id, (int) $stay->event_program_point_id);
        $this->assertSame($contractor->id, (int) $hotelPoint->contractor_id);
        $this->assertTrue((bool) $hotelPoint->is_hotel);
    }

    public function test_hotel_group_consolidated_settlement_cost_for_same_contractor(): void
    {
        $event = Event::factory()->create(['duration_days' => 3]);
        $contractor = Contractor::create(['name' => 'Hotel Central', 'status' => 'active']);

        foreach ([1, 2] as $day) {
            $stay = EventHotelStay::create([
                'event_id' => $event->id,
                'day' => $day,
                'contractor_id' => $contractor->id,
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
        }

        app(HotelStaySettlementSync::class)->syncForEvent($event->fresh(['hotelStays.roomLines']));

        $settlement = \App\Models\EventSettlement::findOrCreateActiveForEvent($event);
        $cost = $settlement->costs()
            ->where('source_type', HotelStaySettlementSync::SOURCE_HOTEL)
            ->where('source_id', $contractor->id)
            ->first();

        $this->assertNotNull($cost);
        $this->assertEqualsWithDelta(1000.0, (float) $cost->planned_amount_pln, 0.01);
        $this->assertStringContainsString('D1, D2', (string) $cost->name);
        $this->assertSame(0, $settlement->costs()->where('source_type', 'accommodation')->count());
    }

    public function test_hotel_plan_editor_uses_sticky_save_actions(): void
    {
        $user = User::factory()->create();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user->assignRole('admin');
        $this->actingAs($user);

        $event = Event::factory()->create(['duration_days' => 2]);
        EventHotelStay::create(['event_id' => $event->id, 'day' => 1]);

        Livewire::test(\App\Livewire\EventHotelPlanEditor::class, ['eventId' => $event->id])
            ->assertSee('hotel-sticky-actions')
            ->assertSee('Zapisz i przejdź do listy osób')
            ->assertDontSee('Zapisz plan')
            ->call('goToStep', 2)
            ->assertSee('Wróć do struktury pokoi')
            ->assertSee('Zapisz');
    }

    public function test_clearing_hotel_is_not_restored_from_program_point(): void
    {
        $user = User::factory()->create();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user->assignRole('admin');
        $this->actingAs($user);

        $event = Event::factory()->create(['duration_days' => 2]);
        $contractor = Contractor::create(['name' => 'Hotel Do Usunięcia', 'status' => 'active']);
        $type = \App\Models\ContractorType::query()->firstOrCreate(['name' => 'hotel']);
        $contractor->types()->sync([$type->id]);

        $hotelPoint = \App\Models\EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'day' => 1,
            'order' => 1,
            'name' => 'Nocleg',
            'is_hotel' => true,
            'contractor_id' => $contractor->id,
        ]);

        $stay = EventHotelStay::create([
            'event_id' => $event->id,
            'day' => 1,
            'contractor_id' => $contractor->id,
            'event_program_point_id' => $hotelPoint->id,
        ]);

        Livewire::test(\App\Livewire\EventHotelPlanEditor::class, ['eventId' => $event->id])
            ->call('clearHotelSelection')
            ->assertHasNoErrors()
            ->assertSee('Wyszukaj hotel po nazwie');

        $stay->refresh();
        $hotelPoint->refresh();

        $this->assertNull($stay->contractor_id);
        $this->assertNull($hotelPoint->contractor_id);
        $this->assertSame($hotelPoint->id, (int) $stay->event_program_point_id);
    }

    public function test_copy_to_selected_days_uses_user_selection_not_day_one(): void
    {
        $user = User::factory()->create();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user->assignRole('admin');
        $this->actingAs($user);

        $event = Event::factory()->create(['duration_days' => 4, 'participant_count' => 10]);

        $roomA = HotelRoom::create([
            'name' => 'Twin A',
            'people_count' => 2,
            'price' => 100,
            'currency' => 'PLN',
            'convert_to_pln' => true,
        ]);
        $roomB = HotelRoom::create([
            'name' => 'Triple B',
            'people_count' => 3,
            'price' => 200,
            'currency' => 'PLN',
            'convert_to_pln' => true,
        ]);

        $stay1 = EventHotelStay::create(['event_id' => $event->id, 'day' => 1]);
        $stay1->roomLines()->create([
            'hotel_room_id' => $roomA->id,
            'role' => 'qty',
            'quantity' => 1,
            'unit_price' => 100,
            'convert_to_pln' => true,
            'order' => 0,
        ]);

        $stay2 = EventHotelStay::create(['event_id' => $event->id, 'day' => 2]);
        $stay2->roomLines()->create([
            'hotel_room_id' => $roomB->id,
            'role' => 'qty',
            'quantity' => 4,
            'unit_price' => 200,
            'convert_to_pln' => true,
            'order' => 0,
        ]);

        $stay3 = EventHotelStay::create(['event_id' => $event->id, 'day' => 3]);

        Livewire::test(\App\Livewire\EventHotelPlanEditor::class, ['eventId' => $event->id])
            ->set('copySourceDay', 2)
            ->set('copyTargetDays', [3])
            ->call('copyToSelectedDays')
            ->assertHasNoErrors()
            ->assertSet('copySourceDay', 2)
            ->assertSet('copyTargetDays', [3]);

        $stay3->refresh()->load('roomLines');
        $this->assertCount(1, $stay3->roomLines);
        $this->assertSame($roomB->id, (int) $stay3->roomLines->first()->hotel_room_id);
        $this->assertSame(4, (int) $stay3->roomLines->first()->quantity);

        $stay1->refresh()->load('roomLines');
        $this->assertSame($roomA->id, (int) $stay1->roomLines->first()->hotel_room_id);
        $this->assertSame(1, (int) $stay1->roomLines->first()->quantity);
    }

    public function test_ensure_stays_does_not_refill_intentionally_empty_night_from_template(): void
    {
        $place = Place::create(['name' => 'Kraków']);
        $template = EventTemplate::factory()->create([
            'name' => 'Szablon z hotelami',
            'duration_days' => 3,
            'start_place_id' => $place->id,
        ]);

        $catalogRoom = HotelRoom::create([
            'name' => 'Katalogowy Twin',
            'people_count' => 2,
            'price' => 180,
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
        EventTemplateHotelDay::create([
            'event_template_id' => $template->id,
            'day' => 2,
            'hotel_room_ids_qty' => [$catalogRoom->id],
            'hotel_room_ids_gratis' => [],
            'hotel_room_ids_staff' => [],
            'hotel_room_ids_driver' => [],
        ]);

        $event = Event::factory()->create([
            'event_template_id' => $template->id,
            'duration_days' => 3,
            'participant_count' => 20,
        ]);

        EventHotelStay::create(['event_id' => $event->id, 'day' => 1]);
        $emptyCoachNight = EventHotelStay::create(['event_id' => $event->id, 'day' => 2]);

        app(EventHotelPlanService::class)->ensureStaysForEvent($event->fresh());

        $this->assertSame(0, $emptyCoachNight->fresh()->roomLines()->count());
    }
}
