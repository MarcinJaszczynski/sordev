<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Contractor;
use App\Models\ContractorLocation;
use App\Models\ContractorType;
use App\Models\Event;
use App\Models\EventHotelStay;
use App\Models\EventProgramPoint;
use App\Models\EventQty;
use App\Models\User;
use App\Services\Documents\DriverInfoDataBuilder;
use App\Services\Documents\HotelAgendaDataBuilder;
use App\Services\EventDocumentGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use ZipArchive;

class EventDocumentGeneratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_hotel_agenda_builder_returns_one_hotel_per_contractor_location(): void
    {
        $user = User::factory()->create();
        [$hotelA] = $this->createHotel('Hotel A', '111');
        [$hotelB] = $this->createHotel('Hotel B', '222');

        $event = Event::factory()->create([
            'assigned_to' => $user->id,
            'participant_count' => 40,
            'start_date' => '2026-06-09',
            'end_date' => '2026-06-12',
            'diet_info' => '7x vege, 1x bez glutenu',
        ]);

        EventHotelStay::create(['event_id' => $event->id, 'day' => 1, 'contractor_id' => $hotelA->id]);
        EventHotelStay::create(['event_id' => $event->id, 'day' => 2, 'contractor_id' => $hotelA->id]);
        EventHotelStay::create(['event_id' => $event->id, 'day' => 3, 'contractor_id' => $hotelB->id]);

        EventProgramPoint::create([
            'event_id' => $event->id,
            'day' => 1,
            'order' => 1,
            'name' => 'Obiadokolacja Hotel A',
            'contractor_id' => $hotelA->id,
            'is_hotel_service' => true,
            'include_in_program' => true,
            'active' => true,
            'start_time' => '18:00',
            'end_time' => '19:30',
        ]);

        EventProgramPoint::create([
            'event_id' => $event->id,
            'day' => 1,
            'order' => 2,
            'name' => 'Zwiedzanie miasta',
            'include_in_program' => true,
            'active' => true,
            'start_time' => '10:00',
        ]);

        $builder = app(HotelAgendaDataBuilder::class);
        $hotels = $builder->hotelsForEvent($event->fresh(['hotelStays.contractor', 'hotelStays.contractorLocation']));

        $this->assertCount(2, $hotels);

        $agendaA = $builder->build($event->fresh(), $hotelA->id, null);
        $programs = collect($agendaA['scheduleRows'])->pluck('program')->implode(' | ');

        $this->assertStringContainsString('Obiadokolacja Hotel A', $programs);
        $this->assertStringNotContainsString('Zwiedzanie miasta', $programs);
        $this->assertStringContainsString('7x vege', (string) $agendaA['dietSummary']);
    }

    public function test_generate_hotel_agendas_creates_pdf_per_hotel(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        [$hotelA] = $this->createHotel('Alpha Hotel', '111');
        [$hotelB] = $this->createHotel('Beta Hotel', '222');

        $event = Event::factory()->create([
            'assigned_to' => $user->id,
            'participant_count' => 20,
            'start_date' => '2026-06-09',
            'end_date' => '2026-06-11',
            'name' => 'Praga test',
        ]);

        EventHotelStay::create(['event_id' => $event->id, 'day' => 1, 'contractor_id' => $hotelA->id]);
        EventHotelStay::create(['event_id' => $event->id, 'day' => 2, 'contractor_id' => $hotelB->id]);

        $paths = app(EventDocumentGeneratorService::class)->generateHotelAgendas($event->fresh());

        $this->assertCount(2, $paths);
        foreach ($paths as $path) {
            Storage::disk('local')->assertExists($path['path']);
            $this->assertGreaterThan(100, strlen((string) Storage::disk('local')->get($path['path'])));
        }
    }

    public function test_driver_info_builder_lists_all_hotels_and_times(): void
    {
        $user = User::factory()->create(['phone' => '500600700']);
        [$hotelA] = $this->createHotel('Alpha', '111');
        [$hotelB] = $this->createHotel('Beta', '222');

        $event = Event::factory()->create([
            'assigned_to' => $user->id,
            'participant_count' => 53,
            'start_date' => '2026-06-09',
            'end_date' => '2026-06-12',
            'departure_time' => '07:00',
            'return_time' => '23:30',
            'substitution_time' => '06:30',
            'pickup_place_details' => 'Parking pod Halą Torwar',
            'driver_name' => 'Jan Kierowca',
            'driver_phone' => '600700800',
            'name' => 'Praga',
            'code' => '20250926/1251',
        ]);

        EventQty::create([
            'event_id' => $event->id,
            'qty' => 53,
            'gratis' => 3,
            'staff' => 1,
            'driver' => 1,
        ]);

        EventHotelStay::create(['event_id' => $event->id, 'day' => 1, 'contractor_id' => $hotelA->id]);
        EventHotelStay::create(['event_id' => $event->id, 'day' => 2, 'contractor_id' => $hotelB->id]);

        $data = app(DriverInfoDataBuilder::class)->build($event->fresh());

        $this->assertSame('06:30', $data['pickup']['time']);
        $this->assertStringContainsString('Torwar', $data['pickup']['place']);
        $this->assertSame('07:00', $data['departure']['time']);
        $this->assertSame('23:30', $data['return']['time']);
        $this->assertCount(2, $data['hotels']);
        $this->assertArrayNotHasKey('programByDay', $data);
        $this->assertArrayHasKey('travelLegends', $data);
    }

    public function test_http_driver_and_hotel_agenda_download_pdf(): void
    {
        $user = User::factory()->create();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user->assignRole('admin');
        $this->actingAs($user);

        [$hotel] = $this->createHotel('Hotel Test', '333');
        $event = Event::factory()->create([
            'assigned_to' => $user->id,
            'name' => 'Wycieczka PDF',
            'start_date' => '2026-06-09',
            'end_date' => '2026-06-10',
            'departure_time' => '08:00',
        ]);
        EventHotelStay::create(['event_id' => $event->id, 'day' => 1, 'contractor_id' => $hotel->id]);

        $driver = $this->get(route('admin.events.pdf', [
            'event' => $event->id,
            'audience' => 'driver',
        ]));
        $driver->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $driver->headers->get('content-type'));

        $agenda = $this->get(route('admin.events.pdf', [
            'event' => $event->id,
            'audience' => 'hotel_agenda',
            'contractor_id' => $hotel->id,
        ]));
        $agenda->assertOk();
        $this->assertTrue(
            str_contains((string) $agenda->headers->get('content-type'), 'pdf')
            || str_contains((string) $agenda->headers->get('content-disposition'), 'pdf')
        );
    }

    public function test_full_trip_package_zip_contains_four_packages(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        [$hotel] = $this->createHotel('Hotel ZIP', '444');
        $event = Event::factory()->create([
            'assigned_to' => $user->id,
            'name' => 'Komplet ZIP',
            'start_date' => '2026-06-09',
            'end_date' => '2026-06-10',
        ]);
        EventHotelStay::create(['event_id' => $event->id, 'day' => 1, 'contractor_id' => $hotel->id]);

        $relative = app(EventDocumentGeneratorService::class)->generateFullTripPackage($event->fresh());
        Storage::disk('local')->assertExists($relative);

        $absolute = Storage::disk('local')->path($relative);
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($absolute) === true);

        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $names[] = $zip->getNameIndex($i);
        }
        $zip->close();

        $joined = implode("\n", $names);
        $this->assertStringContainsString('kierowca/', $joined);
        $this->assertStringContainsString('hotel/', $joined);
        $this->assertStringContainsString('pilot/', $joined);
        $this->assertStringContainsString('teczka/', $joined);
    }

    public function test_hotel_agendas_without_contractors_redirects_instead_of_404(): void
    {
        $user = User::factory()->create();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user->assignRole('admin');
        $this->actingAs($user);

        $event = Event::factory()->create([
            'assigned_to' => $user->id,
            'name' => 'Bez hoteli',
        ]);

        $response = $this->get(route('admin.events.pdf', [
            'event' => $event->id,
            'audience' => 'hotel_agendas',
        ]));

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_hotel_agenda_blade_renders_key_sections(): void
    {
        $event = Event::factory()->make([
            'id' => 99,
            'name' => 'Praga',
            'code' => 'ABC',
            'start_date' => '2026-06-09',
            'end_date' => '2026-06-12',
        ]);

        $html = view('documents.hotel-agenda', [
            'event' => $event,
            'company' => [
                'name' => 'Biuro Podróży RAFA',
                'nip' => '7162508761',
                'phone' => '123',
                'email' => 'a@b.c',
            ],
            'logoDataUri' => null,
            'generatedAt' => now(),
            'eventTitleLine' => 'ABC — Praga: 09.06.2026 – 12.06.2026',
            'hotel' => [
                'name' => 'Hotel Testowy',
                'branch' => null,
                'address' => 'ul. Test 1, 00-001 Warszawa',
                'phone' => '111222333',
            ],
            'pilot' => ['name' => 'Pilot Test', 'phone' => '500100200'],
            'driver' => ['name' => 'Kierowca', 'phone' => '600100200'],
            'termLine' => '09.06.2026 - 12.06.2026',
            'scheduleRows' => [
                ['datetime' => '09.06.2026 18:00', 'date' => '09.06.2026', 'time' => '18:00–19:00', 'program' => 'Obiadokolacja', 'notes' => 'Zupa + danie'],
            ],
            'participantSummary' => '53 uczestników (w tym 3 opiekunów) + 1 pilot + 1 kierowca',
            'dietSummary' => '7x vege',
            'hotelNotes' => '',
            'attachedFiles' => [
                ['label' => 'FV hotel', 'type' => 'Faktura'],
            ],
        ])->render();

        $this->assertStringContainsString('AGENDA DLA HOTELU', $html);
        $this->assertStringContainsString('Hotel Testowy', $html);
        $this->assertStringContainsString('Obiadokolacja', $html);
        $this->assertStringContainsString('7x vege', $html);
        $this->assertStringContainsString('7162508761', $html);
        $this->assertStringContainsString('Zamawiający', $html);
        $this->assertStringContainsString('Uczestnicy łącznie', $html);
        $this->assertStringContainsString('Dieta', $html);
        $this->assertStringContainsString('Pilot Test', $html);
    }

    /**
     * @return array{0: Contractor, 1: ?ContractorLocation}
     */
    private function createHotel(string $name, string $phone): array
    {
        $contractor = Contractor::create([
            'name' => $name,
            'street' => 'ul. Hotelowa',
            'house_number' => '1',
            'postal_code' => '00-001',
            'city' => 'Warszawa',
            'phone' => $phone,
            'status' => 'active',
        ]);

        $type = ContractorType::query()->firstOrCreate(['name' => 'hotel']);
        $contractor->types()->sync([$type->id]);

        return [$contractor, null];
    }
}
