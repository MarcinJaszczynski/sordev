<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Crm\AnswerClientTripInquiryAction;
use App\Actions\Crm\CreateClientTripInquiryAction;
use App\Actions\Contracts\GenerateEventContractAction;
use App\Data\CreateClientTripInquiryData;
use App\Http\Middleware\ClientPreviewMiddleware;
use App\Models\Contract;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\EventPortalAccess;
use App\Models\User;
use App\Services\ClientAccessService;
use App\Services\ContractExtrasSurchargeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ClientPortalPreviewAndInquiriesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('event_portal_accesses')) {
            $this->markTestSkipped('Brak event_portal_accesses.');
        }

        Role::firstOrCreate(['name' => 'client_participant', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'client_guardian', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'biuro', 'guard_name' => 'web']);
    }

    public function test_preview_as_guardian_exposes_guardian_role_and_scopes_trips(): void
    {
        $staff = User::factory()->create(['status' => 'active']);
        $staff->assignRole('biuro');

        $client = User::factory()->create(['status' => 'active', 'name' => 'Opiekun Test']);
        $client->assignRole('client_guardian');

        $event = Event::factory()->create(['name' => 'Impreza Preview']);
        $other = Event::factory()->create(['name' => 'Inna']);

        $access = EventPortalAccess::create([
            'event_id' => $event->id,
            'user_id' => $client->id,
            'role' => EventPortalAccess::ROLE_GUARDIAN,
            'source' => EventPortalAccess::SOURCE_ADMIN,
            'shared_at' => now(),
        ]);

        $this->actingAs($staff);
        session([
            ClientPreviewMiddleware::SESSION_MODE => true,
            ClientPreviewMiddleware::SESSION_ACCESS_ID => $access->id,
        ]);

        $service = app(ClientAccessService::class);
        $this->assertTrue($service->isGuardian($staff, $event));
        $this->assertFalse($service->isParticipant($staff, $event));
        $this->assertTrue($service->isPreviewReadOnly($staff));

        $ids = $service->visibleTripsQuery($staff)->pluck('id')->all();
        $this->assertSame([$event->id], $ids);
        $this->assertNotContains($other->id, $ids);
    }

    public function test_preview_blocks_mutations(): void
    {
        $staff = User::factory()->create(['status' => 'active']);
        $staff->assignRole('admin');

        $this->actingAs($staff);
        session([ClientPreviewMiddleware::SESSION_MODE => true]);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(ClientAccessService::class)->assertPortalMutationsAllowed();
    }

    public function test_trip_inquiry_create_and_answer(): void
    {
        if (! Schema::hasTable('client_trip_inquiries')) {
            $this->markTestSkipped('Brak client_trip_inquiries.');
        }

        $client = User::factory()->create(['status' => 'active', 'email' => 'klient@test.local']);
        $staff = User::factory()->create(['status' => 'active']);
        $staff->assignRole('biuro');
        $event = Event::factory()->create();

        $inquiry = app(CreateClientTripInquiryAction::class)(new CreateClientTripInquiryData(
            event: $event,
            user: $client,
            subject: 'Pytanie o wyjazd',
            body: 'Czy autobus ma klimatyzację?',
        ));

        $this->assertSame('open', $inquiry->status);

        $answered = app(AnswerClientTripInquiryAction::class)(
            $inquiry,
            'Tak, klimatyzacja jest.',
            $staff,
        );

        $this->assertSame('answered', $answered->status);
        $this->assertSame('Tak, klimatyzacja jest.', $answered->office_reply);
        $this->assertNotNull($answered->answered_at);
    }

    public function test_extra_surcharge_from_wizard_catalog(): void
    {
        if (! Schema::hasTable('contracts')) {
            $this->markTestSkipped('Brak contracts.');
        }

        $user = User::factory()->create();
        $event = Event::factory()->create([
            'participant_count' => 2,
            'duration_days' => 3,
        ]);

        $result = app(GenerateEventContractAction::class)($event, [
            'generation_mode' => GenerateEventContractAction::MODE_GROUP_ORDERING,
            'title' => 'Umowa extras',
            'participant_count' => 2,
            'unit_price' => 100,
            'amount_due' => 200,
            'payment_scheme' => Contract::PAYMENT_SCHEME_LUMP_SUM,
            'payment_schedules' => [],
            'use_event_payment_template' => false,
            'requires_diet' => false,
            'contract_extras' => [
                [
                    'label' => 'Pokój 1-os.',
                    'key' => 'single_room',
                    'pricing' => 'per_person',
                    'amount_pln' => 150,
                    'options' => ['Tak'],
                    'applies' => 'participant',
                ],
            ],
        ], $user->id);

        $contract = $result['primary']->fresh();
        $this->assertNotEmpty(data_get($contract->meta, 'extras'));
        $this->assertSame(200.0, (float) data_get($contract->meta, 'pricing.base_amount_due'));

        $participant = EventParticipant::query()->create([
            'event_id' => $event->id,
            'first_name' => 'Anna',
            'last_name' => 'Nowak',
            'status' => EventParticipant::STATUS_ACTIVE,
            'source' => EventParticipant::SOURCE_MANUAL,
            'contract_id' => $contract->id,
            'selected_extras' => ['single_room' => 'Tak'],
        ]);

        app(ContractExtrasSurchargeService::class)->applyForContract($contract->fresh());
        $contract->refresh();

        $this->assertSame(150.0, (float) data_get($contract->meta, 'pricing.extras_surcharge_pln'));
        $this->assertSame(350.0, (float) $contract->amount_due);
    }
}
