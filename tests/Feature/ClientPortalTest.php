<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\Event;
use App\Models\EventPortalAccess;
use App\Models\EventSettlement;
use App\Models\EventSettlementParticipantPayment;
use App\Models\User;
use App\Services\ClientGroupPaymentsService;
use App\Services\ClientPortalProvisioningService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ClientPortalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'client_participant']);
        Role::firstOrCreate(['name' => 'client_guardian']);
        Role::firstOrCreate(['name' => 'admin']);
    }

    public function test_client_can_access_portal_panel(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('client_participant');

        $this->actingAs($user)
            ->get('/portal')
            ->assertOk();

        $this->actingAs($user)
            ->get('/admin')
            ->assertForbidden();
    }

    public function test_provisioning_from_group_agreement_creates_participant_access(): void
    {
        if (! Schema::hasTable('contracts') || ! Schema::hasTable('event_portal_accesses')) {
            $this->markTestSkipped('Brak tabel contracts lub event_portal_accesses.');
        }

        $event = Event::factory()->create([
            'client_name' => 'Szkoła Test',
            'client_email' => 'opiekun@test.local',
        ]);

        $contract = Contract::create([
            'event_id' => $event->id,
            'contract_type' => Contract::TYPE_GROUP,
            'title' => 'Umowa grupowa',
            'contract_date' => now()->toDateString(),
            'participant_count' => 10,
            'unit_price' => 100,
            'total_price' => 1000,
            'amount_due' => 1000,
            'currency' => 'PLN',
            'status' => 'signed',
            'payment_status' => 'pending',
            'signer_email' => 'opiekun@test.local',
            'signer_name' => 'Opiekun Test',
            'customer_email' => 'opiekun@test.local',
            'customer_name' => 'Szkoła Test',
        ]);

        $access = app(ClientPortalProvisioningService::class)->provisionFromAgreement($contract);

        $this->assertNotNull($access);
        $this->assertSame(EventPortalAccess::ROLE_PARTICIPANT, $access->role);
        $this->assertTrue(User::where('email', 'opiekun@test.local')->first()->hasRole('client_participant'));
    }

    public function test_guardian_sees_group_payments_participant_does_not(): void
    {
        if (! Schema::hasTable('event_settlement_participant_payments')) {
            $this->markTestSkipped('Brak tabeli event_settlement_participant_payments.');
        }

        $event = Event::factory()->create();
        $settlement = EventSettlement::findOrCreateActiveForEvent($event);

        EventSettlementParticipantPayment::create([
            'settlement_id' => $settlement->id,
            'participant_name' => 'Uczestnik 1',
            'booking_reference' => 'REF1',
            'due_amount_pln' => 1000,
            'paid_amount_pln' => 500,
        ]);
        EventSettlementParticipantPayment::create([
            'settlement_id' => $settlement->id,
            'participant_name' => 'Uczestnik 2',
            'booking_reference' => 'REF2',
            'due_amount_pln' => 1000,
            'paid_amount_pln' => 1000,
        ]);

        $guardian = User::factory()->create(['status' => 'active', 'email' => 'guardian@test.local']);
        $guardian->assignRole('client_guardian');
        $participant = User::factory()->create(['status' => 'active', 'email' => 'part@test.local']);
        $participant->assignRole('client_participant');

        EventPortalAccess::create([
            'event_id' => $event->id,
            'user_id' => $guardian->id,
            'role' => EventPortalAccess::ROLE_GUARDIAN,
            'shared_at' => now(),
            'source' => EventPortalAccess::SOURCE_ADMIN,
        ]);
        EventPortalAccess::create([
            'event_id' => $event->id,
            'user_id' => $participant->id,
            'role' => EventPortalAccess::ROLE_PARTICIPANT,
            'shared_at' => now(),
            'source' => EventPortalAccess::SOURCE_ADMIN,
        ]);

        $service = app(ClientGroupPaymentsService::class);

        $this->assertCount(2, $service->rowsFor($guardian, $event));
        $this->assertCount(0, $service->rowsFor($participant, $event));
    }

    public function test_participant_portal_pages_require_access(): void
    {
        if (! Schema::hasTable('event_portal_accesses')) {
            $this->markTestSkipped('Brak tabeli event_portal_accesses.');
        }

        $event = Event::factory()->create(['status' => Event::STATUS_CONFIRMED]);
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('client_participant');

        $this->actingAs($user)
            ->get('/portal/program/'.$event->id)
            ->assertForbidden();

        EventPortalAccess::create([
            'event_id' => $event->id,
            'user_id' => $user->id,
            'role' => EventPortalAccess::ROLE_PARTICIPANT,
            'shared_at' => now(),
            'source' => EventPortalAccess::SOURCE_ADMIN,
        ]);

        Filament::setServingStatus(true);
        Filament::setCurrentPanel(Filament::getPanel('portal'));

        $this->actingAs($user)
            ->get('/portal/program/'.$event->id)
            ->assertOk();

        Filament::setServingStatus(false);
        Filament::setCurrentPanel(null);
    }

    public function test_guardian_cannot_access_group_payments_without_guardian_role(): void
    {
        if (! Schema::hasTable('event_portal_accesses')) {
            $this->markTestSkipped('Brak tabeli event_portal_accesses.');
        }

        $event = Event::factory()->create(['status' => Event::STATUS_CONFIRMED]);
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('client_participant');

        EventPortalAccess::create([
            'event_id' => $event->id,
            'user_id' => $user->id,
            'role' => EventPortalAccess::ROLE_PARTICIPANT,
            'shared_at' => now(),
            'source' => EventPortalAccess::SOURCE_ADMIN,
        ]);

        $this->actingAs($user)
            ->get('/portal/group-payments/'.$event->id)
            ->assertForbidden();
    }

    public function test_visible_trips_require_portal_access_even_for_admin(): void
    {
        if (! Schema::hasTable('event_portal_accesses')) {
            $this->markTestSkipped('Brak tabeli event_portal_accesses.');
        }

        Role::firstOrCreate(['name' => 'pilot']);

        $mine = Event::factory()->create(['status' => Event::STATUS_CONFIRMED, 'name' => 'Moja wycieczka']);
        $other = Event::factory()->create(['status' => Event::STATUS_CONFIRMED, 'name' => 'Obca wycieczka']);

        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole(['client_participant', 'admin', 'pilot']);

        EventPortalAccess::create([
            'event_id' => $mine->id,
            'user_id' => $user->id,
            'role' => EventPortalAccess::ROLE_PARTICIPANT,
            'shared_at' => now(),
            'source' => EventPortalAccess::SOURCE_ADMIN,
        ]);

        $ids = app(\App\Services\ClientAccessService::class)
            ->visibleTripsQuery($user)
            ->pluck('id')
            ->all();

        $this->assertContains($mine->id, $ids);
        $this->assertNotContains($other->id, $ids);
        $this->assertFalse(app(\App\Services\ClientAccessService::class)->canViewTrip($user, $other));
        $this->assertTrue(app(\App\Services\ClientAccessService::class)->canViewTrip($user, $mine));
    }

    public function test_client_role_keeps_own_trips_even_with_preview_session(): void
    {
        if (! Schema::hasTable('event_portal_accesses')) {
            $this->markTestSkipped('Brak tabeli event_portal_accesses.');
        }

        $mine = Event::factory()->create(['status' => Event::STATUS_CONFIRMED]);
        $other = Event::factory()->create(['status' => Event::STATUS_CONFIRMED]);
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole(['client_participant', 'admin']);

        EventPortalAccess::create([
            'event_id' => $mine->id,
            'user_id' => $user->id,
            'role' => EventPortalAccess::ROLE_PARTICIPANT,
            'shared_at' => now(),
            'source' => EventPortalAccess::SOURCE_ADMIN,
        ]);

        session(['client_preview_mode' => true]);

        $ids = app(\App\Services\ClientAccessService::class)
            ->visibleTripsQuery($user)
            ->pluck('id')
            ->all();

        $this->assertSame([$mine->id], $ids);
        session()->forget('client_preview_mode');
    }

    public function test_trip_list_renders_portal_cards(): void
    {
        if (! Schema::hasTable('event_portal_accesses')) {
            $this->markTestSkipped('Brak tabeli event_portal_accesses.');
        }

        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('client_participant');
        $event = Event::factory()->create(['name' => 'Wycieczka Portal Cards']);

        EventPortalAccess::create([
            'event_id' => $event->id,
            'user_id' => $user->id,
            'role' => EventPortalAccess::ROLE_PARTICIPANT,
            'shared_at' => now(),
            'source' => EventPortalAccess::SOURCE_ADMIN,
        ]);

        \Filament\Facades\Filament::setServingStatus(true);
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('portal'));

        $this->actingAs($user)
            ->get('/portal/client-events')
            ->assertOk()
            ->assertSee('Wycieczka Portal Cards', false)
            ->assertSee('client-portal-card', false);

        \Filament\Facades\Filament::setServingStatus(false);
        \Filament\Facades\Filament::setCurrentPanel(null);
    }
}
