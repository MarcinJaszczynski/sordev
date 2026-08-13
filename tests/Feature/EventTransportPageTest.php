<?php

namespace Tests\Feature;

use App\Filament\Resources\EventResource\Pages\ManageEventTransport;
use App\Models\Contact;
use App\Models\Contractor;
use App\Models\ContractorType;
use App\Models\Event;
use App\Models\User;
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
