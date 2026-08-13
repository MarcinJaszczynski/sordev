<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Events\AssignEventPilotAction;
use App\Actions\Events\ChangeEventStatusAction;
use App\Actions\Events\UpsertEventParticipantAction;
use App\Actions\Finance\CompleteOnlinePaymentAction;
use App\Actions\Finance\InitiateOnlinePaymentAction;
use App\Actions\Finance\RecordParticipantPaymentAction;
use App\Actions\Finance\RecordSettlementCostPaymentAction;
use App\Actions\Finance\UpdateSettlementCostPlanAction;
use App\Actions\Reservations\UpsertReservationAction;
use App\Data\AssignEventPilotData;
use App\Data\ChangeEventStatusData;
use App\Data\RecordParticipantPaymentData;
use App\Data\RecordSettlementCostPaymentData;
use App\Data\UpdateSettlementCostPlanData;
use App\Data\UpsertEventParticipantData;
use App\Data\UpsertReservationData;
use App\Filament\Resources\EventResource\Pages\CreateEvent;
use App\Models\Contract;
use App\Models\Contractor;
use App\Models\ContractPaymentSchedule;
use App\Models\Event;
use App\Models\EventProgramPoint;
use App\Models\EventSettlement;
use App\Models\EventSettlementParticipantPayment;
use App\Models\EventSettlementParticipantPaymentEntry;
use App\Models\OnlinePaymentSession;
use App\Models\Place;
use App\Models\Task;
use App\Models\User;
use App\Services\ClientLookupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Rozszerzone ścieżki operacyjne biura:
 * uczestnicy/wpłaty, koszty, rezerwacje, pilot, notify, płatność online.
 */
class UserJourneyExtendedPathsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\TaskStatusSeeder::class);

        foreach (['admin', 'super_admin', 'biuro', 'pilot'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        $this->admin = User::factory()->create(['status' => 'active']);
        $this->admin->assignRole('admin');
        $this->actingAs($this->admin);

        config(['payments.default' => 'fake']);
    }

    public function test_create_event_with_notify_office_creates_inquiry_tasks(): void
    {
        $biuro = User::factory()->create(['status' => 'active']);
        $biuro->assignRole('biuro');

        $place = Place::factory()->starting()->create();
        $payload = app(ClientLookupService::class)->quickCreate([
            'first_name' => 'Kasia',
            'last_name' => 'Nowak',
            'phone' => '501111222',
        ]);

        Livewire::test(CreateEvent::class)
            ->call(
                'applyClientLookup',
                $payload['ordering_parties'],
                $payload['client_name'],
                $payload['client_email'],
                $payload['client_phone'],
            )
            ->fillForm([
                'event_template_id' => null,
                'name' => 'Zapytanie z powiadomieniem',
                'start_date' => '2026-12-01',
                'duration_days' => 1,
                'participant_count' => 10,
                'start_place_id' => $place->id,
                'notify_office_about_inquiry' => true,
            ])
            ->set('data.notify_office_about_inquiry', true)
            ->call('create')
            ->assertHasNoFormErrors();

        $event = Event::query()->where('name', 'Zapytanie z powiadomieniem')->firstOrFail();
        $this->assertSame(Event::STATUS_INQUIRY, $event->status);

        $tasks = Task::query()
            ->where('taskable_type', Event::class)
            ->where('taskable_id', $event->id)
            ->get();

        $this->assertCount(1, $tasks);
        $this->assertTrue(
            $tasks->contains(fn (Task $task): bool => str_contains((string) $task->title, 'Nowe zapytanie:')),
        );
        $this->assertTrue(
            $tasks->pluck('assignee_id')->contains($biuro->id)
            || $tasks->pluck('assignee_id')->contains($this->admin->id),
        );
    }

    public function test_assign_pilot_and_share_flag(): void
    {
        $pilot = User::factory()->create(['status' => 'active']);
        $pilot->assignRole('pilot');

        $event = Event::factory()->create([
            'status' => Event::STATUS_CONFIRMED,
            'created_by' => $this->admin->id,
            'assigned_to' => null,
        ]);

        $updated = app(AssignEventPilotAction::class)(new AssignEventPilotData(
            event: $event,
            assignedTo: $pilot->id,
            sharedWithPilot: true,
        ));

        $this->assertSame($pilot->id, (int) $updated->assigned_to);

        if (Schema::hasColumn('events', 'shared_with_pilot')) {
            $this->assertTrue((bool) $updated->shared_with_pilot);
        }

        $cleared = app(AssignEventPilotAction::class)(new AssignEventPilotData(
            event: $updated,
            assignedTo: null,
            sharedWithPilot: false,
        ));

        $this->assertNull($cleared->assigned_to);
    }

    public function test_participant_payment_and_cost_plan_journey(): void
    {
        if (! Schema::hasTable('event_participants')
            || ! Schema::hasTable('event_settlement_participant_payments')
            || ! Schema::hasTable('event_settlement_costs')
        ) {
            $this->markTestSkipped('Brak tabel uczestników/kosztów.');
        }

        $event = Event::factory()->create([
            'status' => Event::STATUS_CONFIRMED,
            'created_by' => $this->admin->id,
            'participant_count' => 2,
        ]);

        $settlement = EventSettlement::findOrCreateActiveForEvent($event);
        $this->assertSame('draft', $settlement->status);

        $participant = app(UpsertEventParticipantAction::class)(new UpsertEventParticipantData(
            event: $event,
            firstName: 'Ola',
            lastName: 'Kwiatkowska',
            email: 'ola@example.com',
            ensurePayment: true,
            dueAmountPln: 1500,
        ));

        $this->assertNotNull($participant->participant_payment_id);

        $payment = EventSettlementParticipantPayment::query()->findOrFail($participant->participant_payment_id);

        app(RecordParticipantPaymentAction::class)(new RecordParticipantPaymentData(
            payment: $payment,
            amount: 500,
            paymentMethod: 'transfer',
            notes: 'Zaliczka uczestnika',
        ));

        $payment->refresh();
        $this->assertEqualsWithDelta(500.0, (float) $payment->paid_amount_pln, 0.01);
        $this->assertDatabaseHas('event_settlement_participant_payment_entries', [
            'participant_payment_id' => $payment->id,
        ]);

        $plan = $settlement->costs()->create([
            'source_type' => 'manual',
            'name' => 'Hotel',
            'planned_amount' => 2000,
            'planned_amount_pln' => 2000,
            'planned_convert_to_pln' => true,
            'paid_by' => 'office',
            'payment_status' => 'planned',
            'order' => 1,
        ]);

        app(UpdateSettlementCostPlanAction::class)(new UpdateSettlementCostPlanData(
            planCost: $plan->fresh(),
            plannedAmountPln: 2200,
            paidBy: 'office',
            notes: 'Korekta po ofercie hotelu',
        ));

        $plan->refresh();
        $this->assertEqualsWithDelta(2200.0, (float) $plan->planned_amount_pln, 0.01);

        app(RecordSettlementCostPaymentAction::class)(new RecordSettlementCostPaymentData(
            planCost: $plan->fresh(),
            amountPln: 800,
            paymentMethod: 'transfer',
            paidBy: 'office',
            advanceType: 'advance',
        ));

        $paymentRow = $settlement->costs()
            ->where('source_type', 'manual_payment')
            ->where('source_id', $plan->id)
            ->first();

        $this->assertNotNull($paymentRow, 'Wpłata powinna utworzyć wiersz manual_payment powiązany z planem.');
        $this->assertEqualsWithDelta(800.0, (float) $paymentRow->actual_amount_pln, 0.01);
        $this->assertContains($plan->fresh()->payment_status, ['partially_paid', 'advance_paid', 'paid']);
    }

    public function test_reservation_from_program_point_links_settlement_cost(): void
    {
        \App\Models\Currency::factory()->pln()->create();

        $contractor = Contractor::create(['name' => 'Muzeum Test', 'status' => 'active']);
        $event = Event::factory()->create([
            'status' => Event::STATUS_CONFIRMED,
            'created_by' => $this->admin->id,
            'assigned_to' => $this->admin->id,
        ]);

        $point = EventProgramPoint::create([
            'event_id' => $event->id,
            'name' => 'Wstęp do muzeum',
            'day' => 1,
            'order' => 1,
            'total_price' => 600,
            'contractor_id' => $contractor->id,
        ]);

        $reservation = app(UpsertReservationAction::class)(new UpsertReservationData(
            attributes: [
                'booking_reference' => 'RES-JOURNEY-1',
                'status' => 'pending',
                'participant_count' => 20,
                'reserved_amount' => 600,
                'amount_basis' => 'lump_sum',
                'participant_scope' => 'all',
                'convert_to_pln' => true,
            ],
            programPoint: $point,
            createdBy: $this->admin->id,
        ));

        $this->assertSame($event->id, $reservation->event_id);
        $this->assertSame($point->id, $reservation->program_point_id);
        $this->assertSame($contractor->id, $reservation->contractor_id);
        $this->assertNotNull($reservation->settlement_cost_id);

        $settlement = EventSettlement::findOrCreateActiveForEvent($event->fresh());
        $this->assertTrue(
            $settlement->costs()
                ->where('id', $reservation->settlement_cost_id)
                ->exists(),
        );
    }

    public function test_initiate_and_complete_online_payment_for_contract_installment(): void
    {
        if (! Schema::hasTable('online_payment_sessions')
            || ! Schema::hasTable('contract_payment_schedules')
            || ! Schema::hasTable('event_settlement_participant_payment_entries')
        ) {
            $this->markTestSkipped('Brak tabel płatności online / ledger.');
        }

        $event = Event::factory()->create([
            'participant_count' => 1,
            'created_by' => $this->admin->id,
        ]);

        $contract = Contract::create([
            'event_id' => $event->id,
            'contract_type' => Contract::TYPE_INDIVIDUAL,
            'payment_scheme' => Contract::PAYMENT_SCHEME_INSTALLMENTS,
            'title' => 'Umowa journey online',
            'contract_date' => now()->toDateString(),
            'participant_count' => 1,
            'total_price' => 1000,
            'amount_paid' => 0,
            'currency' => 'PLN',
            'status' => 'sent',
            'payment_status' => 'pending',
            'customer_name' => 'Jan Journey',
            'created_by' => $this->admin->id,
        ]);

        $schedule = ContractPaymentSchedule::create([
            'contract_id' => $contract->id,
            'sort_order' => 1,
            'label' => 'Zaliczka',
            'amount' => 350,
            'paid_amount' => 0,
            'due_date' => now()->addDays(7)->toDateString(),
        ]);

        $initiated = app(InitiateOnlinePaymentAction::class)(
            $schedule,
            'jan.journey@example.com',
            'Zaliczka journey',
        );

        $this->assertArrayHasKey('session', $initiated);
        $this->assertArrayHasKey('checkout_url', $initiated);
        $this->assertNotSame('', (string) $initiated['checkout_url']);

        /** @var OnlinePaymentSession $session */
        $session = $initiated['session'];
        $this->assertSame(OnlinePaymentSession::STATUS_PENDING, $session->status);
        $this->assertEqualsWithDelta(350.0, (float) $session->amount, 0.01);

        $completed = app(CompleteOnlinePaymentAction::class)($session);
        $this->assertSame(OnlinePaymentSession::STATUS_PAID, $completed->status);

        $schedule->refresh();
        $this->assertEqualsWithDelta(350.0, (float) $schedule->paid_amount, 0.01);

        $contract->refresh();
        $this->assertNotNull($contract->participant_payment_id);

        $this->assertTrue(
            EventSettlementParticipantPaymentEntry::query()
                ->where('participant_payment_id', $contract->participant_payment_id)
                ->where('source', EventSettlementParticipantPaymentEntry::SOURCE_ONLINE)
                ->exists(),
        );
    }

    public function test_end_to_end_operations_after_confirmation(): void
    {
        if (! Schema::hasTable('event_participants') || ! Schema::hasTable('event_settlement_costs')) {
            $this->markTestSkipped('Brak tabel operacyjnych.');
        }

        $pilot = User::factory()->create(['status' => 'active']);
        $pilot->assignRole('pilot');
        User::factory()->create(['status' => 'active'])->assignRole('biuro');

        $event = Event::factory()->create([
            'status' => Event::STATUS_OFFER,
            'created_by' => $this->admin->id,
            'name' => 'Operacje E2E',
        ]);

        app(ChangeEventStatusAction::class)(new ChangeEventStatusData(
            event: $event,
            status: Event::STATUS_CONFIRMED,
            reason: 'E2E',
        ));

        app(AssignEventPilotAction::class)(new AssignEventPilotData(
            event: $event->fresh(),
            assignedTo: $pilot->id,
            sharedWithPilot: true,
        ));

        $participant = app(UpsertEventParticipantAction::class)(new UpsertEventParticipantData(
            event: $event->fresh(),
            firstName: 'Bartek',
            lastName: 'E2E',
            ensurePayment: true,
            dueAmountPln: 900,
            firstPaymentAmountPln: 200,
        ));

        $this->assertNotNull($participant->participant_payment_id);

        $settlement = EventSettlement::findOrCreateActiveForEvent($event->fresh());
        $plan = $settlement->costs()->create([
            'source_type' => 'manual',
            'name' => 'Autokar E2E',
            'planned_amount_pln' => 3000,
            'planned_amount' => 3000,
            'paid_by' => 'office',
            'payment_status' => 'planned',
            'order' => 1,
        ]);

        app(RecordSettlementCostPaymentAction::class)(new RecordSettlementCostPaymentData(
            planCost: $plan->fresh(),
            amountPln: 1000,
            paymentMethod: 'transfer',
            paidBy: 'office',
        ));

        app(ChangeEventStatusAction::class)(new ChangeEventStatusData(
            event: $event->fresh(),
            status: Event::STATUS_TO_SETTLE,
            reason: 'Powrót E2E',
        ));

        $event = $event->fresh();
        $this->assertSame(Event::STATUS_TO_SETTLE, $event->status);
        $this->assertSame($pilot->id, (int) $event->assigned_to);
        $this->assertTrue(
            Task::query()
                ->where('taskable_id', $event->id)
                ->where('description', 'like', '%event-status:'.$event->id.':to_settle%')
                ->exists(),
        );
        $this->assertDatabaseHas('event_participants', [
            'id' => $participant->id,
            'event_id' => $event->id,
        ]);
    }
}
