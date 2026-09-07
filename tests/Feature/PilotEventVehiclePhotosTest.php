<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Events\AttachPilotEventVehiclePhotosAction;
use App\Enums\EventVehicleRole;
use App\Livewire\PilotEventVehiclePhotos;
use App\Models\Contractor;
use App\Models\ContractorType;
use App\Models\Event;
use App\Models\EventVehicle;
use App\Models\User;
use App\Models\Vehicle;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PilotEventVehiclePhotosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'pilot', 'guard_name' => 'web']);
        Filament::setCurrentPanel(Filament::getPanel('pilot'));
    }

    public function test_pilot_can_upload_and_delete_bus_photos_for_assigned_event(): void
    {
        if (! Schema::hasTable('event_vehicles') || ! Schema::hasColumn('event_vehicles', 'pilot_photos')) {
            $this->markTestSkipped('Brak kolumny pilot_photos.');
        }

        Storage::fake('public');

        $pilot = User::factory()->create(['status' => 'active']);
        $pilot->assignRole('pilot');

        $carrier = $this->createTransportContractor('Foto Flota');
        $vehicle = Vehicle::factory()->forContractor($carrier)->create([
            'registration_number' => 'KR FOTO1',
            'manufacture_year' => 2018,
        ]);

        $event = Event::factory()->create([
            'assigned_to' => $pilot->id,
            'shared_with_pilot' => true,
            'status' => Event::STATUS_CONFIRMED,
            'transport_contractor_id' => $carrier->id,
        ]);

        EventVehicle::query()->create([
            'event_id' => $event->id,
            'vehicle_id' => $vehicle->id,
            'role' => EventVehicleRole::Main,
            'sort_order' => 0,
        ]);

        $this->actingAs($pilot);

        Livewire::test(PilotEventVehiclePhotos::class, ['event' => $event->fresh(['eventVehicles.vehicle'])])
            ->assertSee('Autokar — zdjęcia')
            ->assertSee('2018')
            ->assertSee('KR FOTO1')
            ->set('photos', UploadedFile::fake()->image('bus.jpg', 800, 600))
            ->assertHasNoErrors();

        $assignment = EventVehicle::query()->where('event_id', $event->id)->first();
        $this->assertNotNull($assignment);
        $photos = $assignment->pilotPhotoPaths();
        $this->assertCount(1, $photos);
        Storage::disk('public')->assertExists($photos[0]);

        Livewire::test(PilotEventVehiclePhotos::class, ['event' => $event->fresh(['eventVehicles.vehicle'])])
            ->call('deletePhoto', $photos[0])
            ->assertHasNoErrors();

        $assignment->refresh();
        $this->assertSame([], $assignment->pilotPhotoPaths());
        Storage::disk('public')->assertMissing($photos[0]);
    }

    public function test_upload_fails_without_assigned_fleet_vehicle(): void
    {
        if (! Schema::hasTable('event_vehicles') || ! Schema::hasColumn('event_vehicles', 'pilot_photos')) {
            $this->markTestSkipped('Brak kolumny pilot_photos.');
        }

        Storage::fake('public');

        $pilot = User::factory()->create(['status' => 'active']);
        $pilot->assignRole('pilot');

        $event = Event::factory()->create([
            'assigned_to' => $pilot->id,
            'shared_with_pilot' => true,
            'status' => Event::STATUS_CONFIRMED,
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(AttachPilotEventVehiclePhotosAction::class)(
            $event,
            $pilot,
            [UploadedFile::fake()->image('x.jpg')],
        );
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
