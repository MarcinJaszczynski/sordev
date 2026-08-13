<?php

namespace Tests\Feature;

use App\Actions\Events\AssignEventPilotAction;
use App\Data\AssignEventPilotData;
use App\Models\Contractor;
use App\Models\ContractorType;
use App\Models\Event;
use App\Models\User;
use App\Services\PilotContractorAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PilotContractorAssignmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasColumn('events', 'pilot_contractor_id')) {
            $this->markTestSkipped('pilot_contractor_id column is required.');
        }
    }

    public function test_contractor_contact_fields_are_applied_to_form_state(): void
    {
        $pilotType = ContractorType::query()->firstOrCreate(['name' => 'pilot']);
        ContractorType::clearIdsForNamesCache();

        $contractor = Contractor::create([
            'name' => 'Jan Pilot',
            'email' => 'jan.pilot@example.test',
            'phone' => '+48111222333',
            'birth_date' => '1990-05-10',
            'pesel' => '90051012345',
            'status' => 'active',
        ]);
        $contractor->types()->sync([$pilotType->getKey()]);

        $state = [];
        app(PilotContractorAssignmentService::class)->applyContactFieldsToForm(
            $contractor->getKey(),
            function (string $key, mixed $value) use (&$state): void {
                $state[$key] = $value;
            },
        );

        $this->assertSame('1990-05-10', $state['pilot_birth_date']);
        $this->assertSame('90051012345', $state['pilot_pesel']);
    }

    public function test_assign_action_links_portal_user_by_email(): void
    {
        Role::findOrCreate('pilot');

        $pilotUser = User::factory()->create([
            'email' => 'portal.pilot@example.test',
            'name' => 'Portal Pilot',
        ]);
        $pilotUser->assignRole('pilot');

        $pilotType = ContractorType::query()->firstOrCreate(['name' => 'pilot']);
        ContractorType::clearIdsForNamesCache();

        $contractor = Contractor::create([
            'name' => 'Portal Pilot',
            'email' => 'portal.pilot@example.test',
            'phone' => '+48123456789',
            'status' => 'active',
        ]);
        $contractor->types()->sync([$pilotType->getKey()]);

        $event = Event::factory()->create(['assigned_to' => null, 'pilot_contractor_id' => null]);

        app(AssignEventPilotAction::class)(new AssignEventPilotData(
            event: $event,
            assignedTo: null,
            pilotContractorId: $contractor->getKey(),
        ));

        $event->refresh();

        $this->assertSame($contractor->getKey(), $event->pilot_contractor_id);
        $this->assertSame($pilotUser->getKey(), $event->assigned_to);
    }

    public function test_resolve_contractor_id_from_legacy_assigned_user(): void
    {
        $pilotType = ContractorType::query()->firstOrCreate(['name' => 'pilot']);
        ContractorType::clearIdsForNamesCache();

        $contractor = Contractor::create([
            'name' => 'Legacy Pilot',
            'email' => 'legacy.pilot@example.test',
            'status' => 'active',
        ]);
        $contractor->types()->sync([$pilotType->getKey()]);

        $user = User::factory()->create(['email' => 'legacy.pilot@example.test']);
        $event = Event::factory()->create([
            'assigned_to' => $user->getKey(),
            'pilot_contractor_id' => null,
        ]);

        $resolved = app(PilotContractorAssignmentService::class)->resolveContractorIdForEvent($event);

        $this->assertSame($contractor->getKey(), $resolved);
    }
}
