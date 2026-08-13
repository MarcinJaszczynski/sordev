<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use App\Services\PilotAccessService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PilotAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'pilot']);
        Role::firstOrCreate(['name' => 'ksiegowosc']);
    }

    public function test_pilot_loses_full_access_after_grace_period(): void
    {
        $pilot = $this->createPilotUser();
        $event = Event::factory()->create([
            'assigned_to' => $pilot->id,
            'shared_with_pilot' => true,
            'status' => Event::STATUS_TO_SETTLE,
            'start_date' => now()->subDays(20),
            'end_date' => now()->subDays(16),
        ]);

        $service = app(PilotAccessService::class);

        $this->assertTrue($service->isArchived($event));
        $this->assertFalse($service->hasFullAccess($event, $pilot));
        $this->assertTrue($pilot->can('view', $event));
        $this->assertFalse($pilot->can('viewPilotDetails', $event));
    }

    public function test_pilot_keeps_full_access_within_grace_period(): void
    {
        $pilot = $this->createPilotUser();
        $event = Event::factory()->create([
            'assigned_to' => $pilot->id,
            'shared_with_pilot' => true,
            'status' => Event::STATUS_CONFIRMED,
            'start_date' => now()->subDays(5),
            'end_date' => now()->subDays(2),
        ]);

        $service = app(PilotAccessService::class);

        $this->assertFalse($service->isArchived($event));
        $this->assertTrue($service->hasFullAccess($event, $pilot));
        $this->assertTrue($pilot->can('viewPilotDetails', $event));
    }

    public function test_access_denial_reason_when_not_shared(): void
    {
        $pilot = $this->createPilotUser();
        $event = Event::factory()->create([
            'assigned_to' => $pilot->id,
            'shared_with_pilot' => false,
        ]);

        $reason = app(PilotAccessService::class)->accessDenialReason($pilot, $event);

        $this->assertStringContainsString('Udostępnij pilotowi', (string) $reason);
    }

    public function test_access_denial_reason_when_not_assigned(): void
    {
        $pilot = $this->createPilotUser();
        $otherPilot = User::factory()->create(['status' => 'active']);
        $event = Event::factory()->create([
            'assigned_to' => $otherPilot->id,
            'shared_with_pilot' => true,
        ]);

        $reason = app(PilotAccessService::class)->accessDenialReason($pilot, $event);

        $this->assertStringContainsString('nie jest przypisana', (string) $reason);
    }

    public function test_admin_with_pilot_role_can_download_office_exports_outside_filament_panel(): void
    {
        Role::firstOrCreate(['name' => 'admin']);

        $pilotAdmin = User::factory()->create(['status' => 'active']);
        $pilotAdmin->assignRole(['pilot', 'admin']);

        $event = Event::factory()->create([
            'assigned_to' => User::factory()->create()->id,
            'shared_with_pilot' => false,
        ]);

        $this->assertFalse($pilotAdmin->can('view', $event));

        $this->actingAs($pilotAdmin)
            ->get(route('admin.events.participants.import-template', [
                'event' => $event,
                'format' => 'csv',
            ]))
            ->assertOk();

        $this->actingAs($pilotAdmin)
            ->get(route('admin.events.offer.word', $event))
            ->assertOk();

        Filament::setServingStatus(false);
        Filament::setCurrentPanel(null);
    }

    public function test_ksiegowosc_can_view_any_event_in_admin_panel(): void
    {
        $accountant = User::factory()->create(['status' => 'active']);
        $accountant->assignRole('ksiegowosc');

        $event = Event::factory()->create([
            'assigned_to' => User::factory()->create()->id,
            'shared_with_pilot' => false,
        ]);

        Filament::setServingStatus(true);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $service = app(PilotAccessService::class);

        $this->assertTrue($service->officeCanViewEvent($accountant, $event));
        $this->assertTrue($accountant->can('view', $event));
        $this->assertTrue($accountant->can('update', $event));

        Filament::setServingStatus(false);
        Filament::setCurrentPanel(null);
    }

    public function test_archived_trip_settlement_is_forbidden(): void
    {
        $pilot = $this->createPilotUser();
        $event = Event::factory()->create([
            'assigned_to' => $pilot->id,
            'shared_with_pilot' => true,
            'status' => Event::STATUS_TO_SETTLE,
            'start_date' => now()->subDays(30),
            'end_date' => now()->subDays(20),
        ]);

        $this->actingAs($pilot)
            ->get(route('pilot.trip.settle', $event))
            ->assertForbidden();
    }

    protected function createPilotUser(): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('pilot');

        return $user;
    }
}
