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

    public function test_assign_action_keeps_existing_assigned_to_when_contractor_has_no_portal_user(): void
    {
        Role::findOrCreate('pilot');

        $existingUser = User::factory()->create([
            'email' => 'existing.pilot@example.test',
            'name' => 'Existing Pilot',
        ]);
        $existingUser->assignRole('pilot');

        $pilotType = ContractorType::query()->firstOrCreate(['name' => 'pilot']);
        ContractorType::clearIdsForNamesCache();

        $contractor = Contractor::create([
            'name' => 'Contractor Only',
            'email' => 'contractor.only@example.test',
            'status' => 'active',
        ]);
        $contractor->types()->sync([$pilotType->getKey()]);

        $event = Event::factory()->create([
            'assigned_to' => $existingUser->getKey(),
            'pilot_contractor_id' => null,
        ]);

        app(AssignEventPilotAction::class)(new AssignEventPilotData(
            event: $event,
            assignedTo: null,
            pilotContractorId: $contractor->getKey(),
        ));

        $event->refresh();

        $this->assertSame($contractor->getKey(), $event->pilot_contractor_id);
        $this->assertSame($existingUser->getKey(), $event->assigned_to);
    }

    public function test_contractor_only_pilot_counts_as_assigned_for_readiness_and_advance(): void
    {
        $pilotType = ContractorType::query()->firstOrCreate(['name' => 'pilot']);
        ContractorType::clearIdsForNamesCache();

        $contractor = Contractor::create([
            'name' => 'Only Contractor',
            'email' => 'only.contractor@example.test',
            'status' => 'active',
        ]);
        $contractor->types()->sync([$pilotType->getKey()]);

        $event = Event::factory()->create([
            'assigned_to' => null,
            'pilot_contractor_id' => $contractor->getKey(),
            'pilot_funds_paid' => false,
            'pilot_advance_planned_amount' => 500,
        ]);

        $assignment = app(PilotContractorAssignmentService::class);

        $this->assertTrue($assignment->eventHasAssignedPilot($event));
        $this->assertFalse($assignment->eventHasPortalAccount($event));

        $item = collect(\App\Support\EventReadinessIndicators::forEvent($event->fresh()))
            ->firstWhere('key', 'pilot_funds');

        $this->assertNotNull($item);
        $this->assertNotSame('Brak przypisanego pilota', $item['title']);
        $this->assertSame('warn', $item['tone']);

        $pln = \App\Models\Currency::query()->create([
            'name' => 'PLN',
            'code' => 'PLN',
            'symbol' => 'PLN',
            'exchange_rate' => 1,
        ]);

        $approved = app(\App\Services\PilotAdvanceService::class)->approvePayment(
            $event,
            paidLines: [['amount' => 500, 'currency_id' => $pln->id]],
        );

        $this->assertTrue((bool) $approved->pilot_funds_paid);
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

    public function test_persist_pilot_demographics_writes_contractor_then_user(): void
    {
        Role::findOrCreate('pilot');

        $pilotType = ContractorType::query()->firstOrCreate(['name' => 'pilot']);
        ContractorType::clearIdsForNamesCache();

        $user = User::factory()->create([
            'email' => 'pesel.pilot@example.test',
            'name' => 'Pesel Pilot',
            'birth_date' => null,
            'pesel' => null,
        ]);
        $user->assignRole('pilot');

        $contractor = Contractor::create([
            'name' => 'Pesel Pilot',
            'email' => 'pesel.pilot@example.test',
            'status' => 'active',
            'birth_date' => null,
            'pesel' => null,
        ]);
        $contractor->types()->sync([$pilotType->getKey()]);

        app(PilotContractorAssignmentService::class)->persistPilotDemographicsFromPortalUser(
            $user,
            '1990-05-10',
            '90051012345',
            '+48111222333',
        );

        $contractor->refresh();
        $user->refresh();

        $this->assertSame('90051012345', $contractor->pesel);
        $this->assertSame('1990-05-10', $contractor->birth_date?->format('Y-m-d'));
        $this->assertSame('90051012345', $user->pesel);
        $this->assertSame('1990-05-10', $user->birth_date?->format('Y-m-d'));
    }

    public function test_contractor_edit_syncs_demographics_to_portal_user(): void
    {
        Role::findOrCreate('pilot');

        $pilotType = ContractorType::query()->firstOrCreate(['name' => 'pilot']);
        ContractorType::clearIdsForNamesCache();

        $user = User::factory()->create([
            'email' => 'sync.pilot@example.test',
            'pesel' => null,
            'birth_date' => null,
        ]);
        $user->assignRole('pilot');

        $contractor = Contractor::create([
            'name' => 'Sync Pilot',
            'email' => 'sync.pilot@example.test',
            'status' => 'active',
            'birth_date' => '1988-01-02',
            'pesel' => '88010212345',
        ]);
        $contractor->types()->sync([$pilotType->getKey()]);

        app(PilotContractorAssignmentService::class)
            ->syncPortalUserDemographicsFromContractorRecord($contractor->fresh());

        $user->refresh();

        $this->assertSame('88010212345', $user->pesel);
        $this->assertSame('1988-01-02', $user->birth_date?->format('Y-m-d'));
    }
}
