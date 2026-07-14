<?php

namespace Tests\Feature;

use App\Filament\Resources\EventResource\Pages\ManageEventTransport;
use App\Models\Contractor;
use App\Models\ContractorType;
use App\Models\Event;
use App\Models\User;
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

        $event = Event::factory()->create([
            'status' => Event::STATUS_CONFIRMED,
            'transport_contractor_id' => null,
            'driver_name' => null,
        ]);

        $this->actingAs($admin);

        Livewire::test(ManageEventTransport::class, ['record' => $event->getKey()])
            ->fillForm([
                'transport_contractor_id' => (string) $carrier->id,
                'driver_name' => 'Jan Kierowca',
                'transfer_km' => 120,
                'program_km' => 450,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $event->refresh();

        $this->assertSame($carrier->id, $event->transport_contractor_id);
        $this->assertSame('Jan Kierowca', $event->driver_name);
        $this->assertSame(120, (int) $event->transfer_km);
        $this->assertSame(450, (int) $event->program_km);
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
