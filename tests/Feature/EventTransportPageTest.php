<?php

namespace Tests\Feature;

use App\Filament\Resources\EventResource\Pages\ManageEventTransport;
use App\Models\Bus;
use App\Models\Contact;
use App\Models\Contractor;
use App\Models\ContractorType;
use App\Models\Event;
use App\Models\EventQty;
use App\Models\EventVehicle;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\EventOrderingPartyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventTransportPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin']);
    }

    public function test_transport_page_saves_start_date_and_transport_times(): void
    {
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('admin');

        $event = Event::factory()->create([
            'status' => Event::STATUS_CONFIRMED,
            'start_date' => '2026-06-10',
            'end_date' => '2026-06-12',
            'duration_days' => 3,
            'substitution_time' => null,
            'departure_time' => null,
            'return_time' => null,
        ]);

        $this->actingAs($admin);

        Livewire::test(ManageEventTransport::class, ['record' => $event->getKey()])
            ->assertSee('Finanse transportu')
            ->assertSee('Terminy transportu')
            ->fillForm([
                'start_date' => '2026-06-15',
                'end_date' => '2026-06-17',
                'duration_days' => 3,
                'substitution_time' => '07:30',
                'departure_time' => '08:00',
                'return_time' => '20:00',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $event->refresh();

        $this->assertSame('2026-06-15', $event->start_date?->format('Y-m-d'));
        $this->assertSame('2026-06-17', $event->end_date?->format('Y-m-d'));
        $this->assertSame(3, (int) $event->duration_days);
        $this->assertSame('07:30', substr((string) $event->substitution_time, 0, 5));
        $this->assertSame('08:00', substr((string) $event->departure_time, 0, 5));
        $this->assertSame('20:00', substr((string) $event->return_time, 0, 5));
    }

    public function test_transport_page_saves_contractor_and_driver_fields(): void
    {
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('admin');

        $carrier = $this->createTransportContractor('Firma Transportowa Test');
        $driver = $this->createContractorWithType('Jan Kierowca', 'kierowca');
        $driver->update(['phone' => '+48111222333', 'email' => 'kierowca@example.com']);

        $event = Event::factory()->create([
            'status' => Event::STATUS_CONFIRMED,
            'transport_contractor_id' => null,
            'driver_contractor_id' => null,
            'driver_name' => null,
        ]);

        $this->actingAs($admin);

        Livewire::test(ManageEventTransport::class, ['record' => $event->getKey()])
            ->fillForm([
                'transport_contractor_id' => (string) $carrier->id,
                'driver_contractor_id' => (string) $driver->id,
                'transfer_km' => 120,
                'program_km' => 450,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $event->refresh();

        $this->assertSame($carrier->id, $event->transport_contractor_id);
        $this->assertSame($driver->id, $event->driver_contractor_id);
        $this->assertSame('Jan Kierowca', $event->driver_name);
        $this->assertSame('+48111222333', $event->driver_phone);
        $this->assertSame(120, (int) $event->transfer_km);
        $this->assertSame(450, (int) $event->program_km);
    }

    public function test_transport_page_shows_contacts_for_carrier_and_driver(): void
    {
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('admin');

        $carrier = $this->createTransportContractor('Przewoźnik Kontakty');
        $carrier->update(['phone' => '+48100100100', 'email' => 'firma@example.com']);
        $carrierContact = Contact::query()->create([
            'first_name' => 'Anna',
            'last_name' => 'Dyspozytor',
            'phone' => '+48200200200',
            'email' => 'anna@example.com',
        ]);
        $carrier->contacts()->syncWithoutDetaching([$carrierContact->id]);

        $driver = $this->createContractorWithType('Piotr Kierowca', 'kierowca');
        $driver->update(['phone' => '+48300300300']);
        $driverContact = Contact::query()->create([
            'first_name' => 'Marek',
            'last_name' => 'Zastepca',
            'phone' => '+48400400400',
        ]);
        $driver->contacts()->syncWithoutDetaching([$driverContact->id]);

        $event = Event::factory()->create([
            'status' => Event::STATUS_CONFIRMED,
            'transport_contractor_id' => $carrier->id,
            'driver_contractor_id' => $driver->id,
        ]);

        $this->actingAs($admin);

        Livewire::test(ManageEventTransport::class, ['record' => $event->getKey()])
            ->assertSuccessful()
            ->assertSee('Przewoźnik Kontakty')
            ->assertSee('Anna Dyspozytor')
            ->assertSee('anna@example.com')
            ->assertSee('Piotr Kierowca')
            ->assertSee('Marek Zastepca')
            ->assertSee('Dodaj kontakt')
            ->assertSee('Edytuj dane kontrahenta')
            ->assertSee('Edytuj kontakt');
    }

    public function test_transport_page_can_add_contact_to_selected_carrier(): void
    {
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('admin');

        $carrier = $this->createTransportContractor('Firma Bez Kontaktu');
        $event = Event::factory()->create([
            'status' => Event::STATUS_CONFIRMED,
            'transport_contractor_id' => $carrier->id,
        ]);

        $this->actingAs($admin);

        $contactId = app(EventOrderingPartyService::class)->createContact([
            'first_name' => 'Ewa',
            'last_name' => 'Biuro',
            'phone' => '+48500500500',
            'email' => 'ewa@example.com',
        ], $carrier->id);

        $this->assertDatabaseHas('contacts', [
            'id' => $contactId,
            'first_name' => 'Ewa',
            'last_name' => 'Biuro',
        ]);
        $this->assertTrue($carrier->fresh()->contacts()->whereKey($contactId)->exists());

        Livewire::test(ManageEventTransport::class, ['record' => $event->getKey()])
            ->assertSee('Ewa Biuro')
            ->assertSee('ewa@example.com');
    }

    public function test_transport_contractor_search_all_includes_non_transport_type(): void
    {
        $lookup = app(\App\Services\ContractorLookupService::class);
        $hotel = $this->createContractorWithType('Hotel bez transportu', 'hotel');

        $filtered = $lookup->searchOptions(
            search: 'Hotel bez',
            typeNames: ContractorType::transportTypeNames(),
        );

        $this->assertSame([], $filtered);

        $all = $lookup->searchOptions(
            search: 'Hotel bez',
            typeNames: ContractorType::transportTypeNames(),
            searchAll: true,
        );

        $this->assertArrayHasKey($hotel->id, $all);
    }

    public function test_transport_page_shows_red_warning_when_group_exceeds_fleet_capacity(): void
    {
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('admin');

        $bus = Bus::factory()->create([
            'name' => 'Bus Cennik 49',
            'capacity' => 49,
        ]);
        $vehicle = Vehicle::factory()->create([
            'registration_number' => 'WW TEST19',
            'capacity' => 19,
        ]);

        $event = Event::factory()->create([
            'status' => Event::STATUS_CONFIRMED,
            'bus_id' => $bus->id,
            'participant_count' => 18,
        ]);
        EventVehicle::query()->create([
            'event_id' => $event->id,
            'vehicle_id' => $vehicle->id,
            'role' => 'main',
        ]);
        EventQty::query()->create([
            'event_id' => $event->id,
            'qty' => 18,
            'gratis' => 3,
            'staff' => 0,
            'driver' => 0,
        ]);

        $this->actingAs($admin);

        Livewire::test(ManageEventTransport::class, ['record' => $event->getKey()])
            ->assertSee('przekracza pojemność autokaru')
            ->assertSee('WW TEST19')
            ->assertSee('Brakuje 2 miejsc');
    }

    public function test_transport_page_hides_capacity_warning_when_fleet_group_fits(): void
    {
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('admin');

        $bus = Bus::factory()->create([
            'name' => 'Bus Cennik 19',
            'capacity' => 19,
        ]);
        $vehicle = Vehicle::factory()->create([
            'registration_number' => 'WW OK49',
            'capacity' => 49,
        ]);

        $event = Event::factory()->create([
            'status' => Event::STATUS_CONFIRMED,
            'bus_id' => $bus->id,
            'participant_count' => 40,
        ]);
        EventVehicle::query()->create([
            'event_id' => $event->id,
            'vehicle_id' => $vehicle->id,
            'role' => 'main',
        ]);
        EventQty::query()->create([
            'event_id' => $event->id,
            'qty' => 40,
            'gratis' => 5,
            'staff' => 0,
            'driver' => 0,
        ]);

        $this->actingAs($admin);

        Livewire::test(ManageEventTransport::class, ['record' => $event->getKey()])
            ->assertDontSee('przekracza pojemność autokaru');
    }

    public function test_transport_page_does_not_warn_about_catalog_bus_without_fleet(): void
    {
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('admin');

        $bus = Bus::factory()->create([
            'name' => 'Bus Test 19',
            'capacity' => 19,
        ]);

        $event = Event::factory()->create([
            'status' => Event::STATUS_CONFIRMED,
            'bus_id' => $bus->id,
            'participant_count' => 18,
        ]);
        EventQty::query()->create([
            'event_id' => $event->id,
            'qty' => 18,
            'gratis' => 3,
            'staff' => 0,
            'driver' => 0,
        ]);

        $this->actingAs($admin);

        Livewire::test(ManageEventTransport::class, ['record' => $event->getKey()])
            ->assertDontSee('przekracza pojemność autokaru');
    }

    public function test_transport_page_shows_live_cost_preview_when_bus_changes(): void
    {
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('admin');

        $cheapBus = Bus::factory()->create([
            'name' => 'Autokar Tani',
            'capacity' => 49,
            'package_price_per_day' => 1000,
            'package_km_per_day' => 5000,
            'extra_km_price' => 1,
            'currency' => 'PLN',
        ]);
        $expensiveBus = Bus::factory()->create([
            'name' => 'Autokar Drogi',
            'capacity' => 49,
            'package_price_per_day' => 3000,
            'package_km_per_day' => 5000,
            'extra_km_price' => 1,
            'currency' => 'PLN',
        ]);

        $event = Event::factory()->create([
            'status' => Event::STATUS_CONFIRMED,
            'bus_id' => $cheapBus->id,
            'transfer_km' => 10,
            'program_km' => 20,
            'duration_days' => 2,
            'participant_count' => 30,
            'use_manual_transport_cost' => false,
        ]);

        $this->actingAs($admin);

        // 2 dni × 1000 = 2000 PLN (km w pakiecie)
        Livewire::test(ManageEventTransport::class, ['record' => $event->getKey()])
            ->assertSeeHtml('Autokar Tani')
            ->assertSee('2 000,00 PLN', escape: false)
            ->set('data.bus_id', $expensiveBus->id)
            ->assertSeeHtml('Koszt transportu:</b> Autokar Drogi')
            ->assertSee('6 000,00 PLN', escape: false)
            ->assertDontSee('2 000,00 PLN', escape: false);
    }

    public function test_transport_page_shows_manual_ryczalt_in_preview(): void
    {
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('admin');

        $bus = Bus::factory()->create([
            'name' => 'Autokar Ryczalt',
            'package_price_per_day' => 1000,
            'package_km_per_day' => 5000,
            'currency' => 'PLN',
        ]);

        $event = Event::factory()->create([
            'status' => Event::STATUS_CONFIRMED,
            'bus_id' => $bus->id,
            'transfer_km' => 10,
            'program_km' => 20,
            'duration_days' => 2,
            'use_manual_transport_cost' => false,
            'manual_transport_cost' => null,
        ]);

        $this->actingAs($admin);

        // set() — fillForm na Toggle bywa zawodny w testach Livewire/Filament.
        Livewire::test(ManageEventTransport::class, ['record' => $event->getKey()])
            ->set('data.use_manual_transport_cost', true)
            ->set('data.manual_transport_cost', 4500.50)
            ->assertSeeHtml('Koszt transportu (ręczny):')
            ->assertSee('4 500,50 PLN', escape: false);
    }

    public function test_transport_page_recalculates_event_after_bus_save(): void
    {
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('admin');

        $busA = Bus::factory()->create([
            'name' => 'Bus A',
            'capacity' => 49,
            'package_price_per_day' => 1000,
            'package_km_per_day' => 5000,
            'extra_km_price' => 1,
            'currency' => 'PLN',
        ]);
        $busB = Bus::factory()->create([
            'name' => 'Bus B',
            'capacity' => 49,
            'package_price_per_day' => 2500,
            'package_km_per_day' => 5000,
            'extra_km_price' => 1,
            'currency' => 'PLN',
        ]);

        $event = Event::factory()->create([
            'status' => Event::STATUS_CONFIRMED,
            'bus_id' => $busA->id,
            'transfer_km' => 10,
            'program_km' => 20,
            'duration_days' => 2,
            'participant_count' => 30,
            'use_manual_transport_cost' => false,
            'total_cost' => 0,
        ]);

        $this->actingAs($admin);

        Livewire::test(ManageEventTransport::class, ['record' => $event->getKey()])
            ->fillForm([
                'bus_id' => (string) $busB->id,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $event->refresh();

        $this->assertSame($busB->id, (int) $event->bus_id);

        $transport = (new \App\Services\EventTransportCostCalculator($event))->effectiveTransportCost([
            'qty' => 30,
            'gratis' => 0,
            'staff' => 1,
            'driver' => 1,
        ]);
        // 2 × 2500 = 5000
        $this->assertSame(5000.0, $transport);
        $this->assertGreaterThanOrEqual(5000.0, (float) $event->total_cost);
    }

    private function createTransportContractor(string $name): Contractor
    {
        return $this->createContractorWithType($name, 'przewoźnik');
    }

    private function createContractorWithType(string $name, string $typeName): Contractor
    {
        $contractor = Contractor::create([
            'name' => $name,
            'status' => 'active',
        ]);

        $type = ContractorType::query()->firstOrCreate(['name' => $typeName]);
        $contractor->types()->sync([$type->id]);

        return $contractor;
    }
}
