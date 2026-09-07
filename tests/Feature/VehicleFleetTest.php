<?php

namespace Tests\Feature;

use App\Enums\EventVehicleRole;
use App\Enums\VehicleStatus;
use App\Enums\VehicleType;
use App\Filament\Forms\EventVehicleFields;
use App\Models\Contractor;
use App\Models\ContractorType;
use App\Models\Event;
use App\Models\EventVehicle;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class VehicleFleetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin']);
    }

    public function test_vehicle_belongs_to_contractor_and_stores_equipment(): void
    {
        $carrier = $this->createTransportContractor('Flota Test');

        $vehicle = Vehicle::factory()->forContractor($carrier)->create([
            'type' => VehicleType::Microbus,
            'brand' => 'Mercedes',
            'model' => 'Sprinter',
            'manufacture_year' => 2020,
            'registration_number' => 'WX 1234A',
            'capacity' => 19,
            'equipment' => [Vehicle::EQUIPMENT_WIFI, Vehicle::EQUIPMENT_AC],
            'status' => VehicleStatus::Active,
        ]);

        $this->assertTrue($carrier->vehicles()->whereKey($vehicle->id)->exists());
        $this->assertSame(VehicleType::Microbus, $vehicle->fresh()->type);
        $this->assertSame(['WiFi', 'Klimatyzacja'], $vehicle->equipmentLabels());
        $this->assertStringContainsString('WX 1234A', $vehicle->displayLabel());
        $this->assertStringContainsString('2020', $vehicle->displayLabel());
    }

    public function test_event_vehicle_assignment_syncs_main_registration_snapshot(): void
    {
        $carrier = $this->createTransportContractor('Przewoźnik Flota');
        $vehicle = Vehicle::factory()->forContractor($carrier)->create([
            'registration_number' => 'KR 9876B',
            'brand' => 'Setra',
            'model' => 'S 516',
        ]);

        $event = Event::factory()->create([
            'status' => Event::STATUS_CONFIRMED,
            'transport_contractor_id' => $carrier->id,
            'vehicle_registration' => null,
        ]);

        EventVehicle::query()->create([
            'event_id' => $event->id,
            'vehicle_id' => $vehicle->id,
            'role' => EventVehicleRole::Main,
            'starts_on' => '2026-06-10',
            'ends_on' => '2026-06-12',
            'sort_order' => 0,
        ]);

        $event->syncVehicleRegistrationFromFleet();
        $event->refresh();

        $this->assertDatabaseHas('event_vehicles', [
            'event_id' => $event->id,
            'vehicle_id' => $vehicle->id,
            'role' => EventVehicleRole::Main->value,
        ]);
        $this->assertSame('KR 9876B', $event->vehicle_registration);
        $this->assertSame(1, EventVehicle::query()->where('event_id', $event->id)->count());
    }

    public function test_ad_hoc_vehicle_can_be_created_without_contractor(): void
    {
        $vehicle = Vehicle::factory()->adHoc()->create([
            'registration_number' => 'WA ADHOC1',
            'type' => VehicleType::Bus,
        ]);

        $this->assertTrue($vehicle->is_ad_hoc);
        $this->assertNull($vehicle->contractor_id);
        $this->assertTrue(
            Vehicle::query()->forContractor(null)->whereKey($vehicle->id)->exists()
        );
    }

    public function test_persist_main_vehicle_creates_assignment_and_syncs_registration(): void
    {
        $carrier = $this->createTransportContractor('Persist Flota');
        $vehicle = Vehicle::factory()->forContractor($carrier)->create([
            'registration_number' => 'POZ 1111A',
        ]);

        $event = Event::factory()->create([
            'status' => Event::STATUS_CONFIRMED,
            'transport_contractor_id' => $carrier->id,
            'vehicle_registration' => null,
        ]);

        EventVehicleFields::persistMainVehicle($event, $vehicle->id);
        $event->refresh();

        $this->assertDatabaseHas('event_vehicles', [
            'event_id' => $event->id,
            'vehicle_id' => $vehicle->id,
            'role' => EventVehicleRole::Main->value,
        ]);
        $this->assertSame('POZ 1111A', $event->vehicle_registration);
    }

    public function test_vehicle_stores_attachments_and_notes(): void
    {
        $vehicle = Vehicle::factory()->create([
            'registration_number' => 'WA FILES1',
            'primary_image' => 'vehicles/primary/cover.jpg',
            'gallery' => ['vehicles/gallery/a.jpg'],
            'attachments' => ['vehicles/attachments/oc.pdf'],
            'notes' => 'WiFi na życzenie, bagażownik duży.',
        ]);

        $vehicle->refresh();

        $this->assertSame('vehicles/primary/cover.jpg', $vehicle->primary_image);
        $this->assertSame(['vehicles/gallery/a.jpg'], $vehicle->gallery);
        $this->assertSame(['vehicles/attachments/oc.pdf'], $vehicle->attachments);
        $this->assertSame('WiFi na życzenie, bagażownik duży.', $vehicle->notes);
        $this->assertStringContainsString('oc.pdf', \App\Filament\Forms\EventVehicleFields::mediaSummaryHtml($vehicle));
    }

    public function test_vehicle_resource_form_schema_is_available(): void
    {
        $this->assertNotEmpty(\App\Filament\Forms\VehicleFields::schema());
        $this->assertNotEmpty(\App\Filament\Forms\VehicleFields::mediaAndNotesFields());
        $this->assertNotEmpty(\App\Filament\Forms\VehicleFields::editFromTransportSchema());
        $this->assertNotEmpty(\App\Filament\Forms\EventVehicleFields::mainVehicleSelect());
    }

    public function test_payload_from_form_normalizes_registration_and_media(): void
    {
        $payload = \App\Filament\Forms\VehicleFields::payloadFromForm([
            'type' => VehicleType::Bus->value,
            'brand' => 'Setra',
            'model' => 'S 516',
            'manufacture_year' => '2019',
            'registration_number' => ' wa 55aa ',
            'capacity' => '49',
            'crew_seats' => '2',
            'equipment' => [Vehicle::EQUIPMENT_WIFI],
            'status' => VehicleStatus::Active->value,
            'is_ad_hoc' => false,
            'primary_image' => 'vehicles/primary/a.jpg',
            'gallery' => ['vehicles/gallery/b.jpg', null],
            'attachments' => [],
            'notes' => 'Test',
        ]);

        $this->assertSame('WA 55AA', $payload['registration_number']);
        $this->assertSame(49, $payload['capacity']);
        $this->assertSame(2019, $payload['manufacture_year']);
        $this->assertSame(['vehicles/gallery/b.jpg'], $payload['gallery']);
        $this->assertSame('Test', $payload['notes']);
    }

    public function test_create_from_transport_attaches_vehicle_to_contractor(): void
    {
        $carrier = $this->createTransportContractor('Create Flota');

        $payload = \App\Filament\Forms\VehicleFields::payloadFromForm([
            'type' => VehicleType::Bus->value,
            'registration_number' => 'GD 7777C',
            'capacity' => 45,
            'crew_seats' => 2,
            'equipment' => [],
            'status' => VehicleStatus::Active->value,
        ]);
        $payload['contractor_id'] = $carrier->id;
        $payload['is_ad_hoc'] = false;

        $vehicle = Vehicle::query()->create($payload);

        $this->assertSame($carrier->id, $vehicle->fresh()->contractor_id);
        $this->assertFalse($vehicle->fresh()->is_ad_hoc);
        $this->assertTrue($carrier->vehicles()->whereKey($vehicle->id)->exists());
    }

    public function test_edit_from_transport_can_claim_orphan_ad_hoc_vehicle(): void
    {
        $carrier = $this->createTransportContractor('Claim Flota');
        $vehicle = Vehicle::factory()->adHoc()->create([
            'registration_number' => 'WA ORPHAN1',
            'capacity' => 40,
        ]);

        $this->assertNull($vehicle->contractor_id);

        $payload = \App\Filament\Forms\VehicleFields::payloadFromForm([
            'type' => VehicleType::Bus->value,
            'registration_number' => 'WA ORPHAN1',
            'capacity' => 42,
            'crew_seats' => 2,
            'equipment' => [],
            'status' => VehicleStatus::Active->value,
            'is_ad_hoc' => true,
        ]);
        $payload['contractor_id'] = $carrier->id;
        $payload['is_ad_hoc'] = false;

        $vehicle->update($payload);
        $vehicle->refresh();

        $this->assertSame($carrier->id, $vehicle->contractor_id);
        $this->assertFalse($vehicle->is_ad_hoc);
        $this->assertSame(42, $vehicle->capacity);
    }

    private function createTransportContractor(string $name): Contractor
    {
        $contractor = Contractor::create([
            'name' => $name,
            'status' => 'active',
        ]);

        $type = ContractorType::query()->firstOrCreate(['name' => 'przewoźnik']);
        $contractor->types()->sync([$type->id]);

        return $contractor;
    }
}
