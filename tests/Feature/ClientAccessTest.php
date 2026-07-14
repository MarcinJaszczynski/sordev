<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\Event;
use App\Models\EventPortalAccess;
use App\Models\EventSettlement;
use App\Models\EventSettlementParticipantPayment;
use App\Models\User;
use App\Services\ClientAccessService;
use App\Services\ClientPortalProvisioningService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ClientAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'client_participant']);
        Role::firstOrCreate(['name' => 'client_guardian']);
    }

    public function test_participant_cannot_view_other_participants_contract(): void
    {
        if (! Schema::hasTable('contracts') || ! Schema::hasTable('event_portal_accesses')) {
            $this->markTestSkipped('Brak tabel contracts lub event_portal_accesses.');
        }

        $event = Event::factory()->create(['status' => Event::STATUS_CONFIRMED]);
        $participantA = $this->createParticipantUser('a@test.local');
        $participantB = $this->createParticipantUser('b@test.local');

        $contractA = Contract::create([
            'event_id' => $event->id,
            'contract_type' => Contract::TYPE_INDIVIDUAL,
            'title' => 'Umowa A',
            'contract_date' => now()->toDateString(),
            'participant_name' => 'Anna A',
            'signer_email' => 'a@test.local',
            'total_price' => 1000,
            'currency' => 'PLN',
            'status' => 'signed',
            'payment_status' => 'pending',
        ]);

        $contractB = Contract::create([
            'event_id' => $event->id,
            'contract_type' => Contract::TYPE_INDIVIDUAL,
            'title' => 'Umowa B',
            'contract_date' => now()->toDateString(),
            'participant_name' => 'Bogdan B',
            'signer_email' => 'b@test.local',
            'total_price' => 1000,
            'currency' => 'PLN',
            'status' => 'signed',
            'payment_status' => 'pending',
        ]);

        $this->grantParticipantAccess($event, $participantA, $contractA);
        $this->grantParticipantAccess($event, $participantB, $contractB);

        $service = app(ClientAccessService::class);

        $this->assertTrue($service->canViewEvent($participantA, $event));
        $this->assertSame($contractA->id, $service->accessibleContract($participantA, $event, EventPortalAccess::ROLE_PARTICIPANT)?->id);
        $this->assertNotSame($contractB->id, $service->accessibleContract($participantA, $event, EventPortalAccess::ROLE_PARTICIPANT)?->id);
    }

    public function test_client_without_access_cannot_view_event(): void
    {
        $event = Event::factory()->create();
        $user = $this->createParticipantUser('lonely@test.local');

        $this->assertFalse(app(ClientAccessService::class)->canViewEvent($user, $event));
    }

    public function test_archived_event_limits_full_access(): void
    {
        $user = $this->createParticipantUser();
        $event = Event::factory()->create([
            'status' => Event::STATUS_SETTLED,
            'start_date' => now()->subDays(120),
            'end_date' => now()->subDays(100),
        ]);

        EventPortalAccess::create([
            'event_id' => $event->id,
            'user_id' => $user->id,
            'role' => EventPortalAccess::ROLE_PARTICIPANT,
            'shared_at' => now(),
            'source' => EventPortalAccess::SOURCE_ADMIN,
        ]);

        $service = app(ClientAccessService::class);

        $this->assertTrue($service->canViewEvent($user, $event));
        $this->assertFalse($service->hasFullAccess($event, $user));
        $this->assertFalse($user->can('viewClientPortalDetails', $event));
    }

    protected function createParticipantUser(?string $email = null): User
    {
        $user = User::factory()->create([
            'status' => 'active',
            'email' => $email ?: fake()->unique()->safeEmail(),
        ]);
        $user->assignRole('client_participant');

        return $user;
    }

    protected function grantParticipantAccess(Event $event, User $user, Contract $contract): void
    {
        EventPortalAccess::create([
            'event_id' => $event->id,
            'user_id' => $user->id,
            'role' => EventPortalAccess::ROLE_PARTICIPANT,
            'contract_id' => $contract->id,
            'shared_at' => now(),
            'source' => EventPortalAccess::SOURCE_ADMIN,
        ]);
    }
}
