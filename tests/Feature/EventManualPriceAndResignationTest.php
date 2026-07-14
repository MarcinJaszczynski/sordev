<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventParticipantResignation;
use App\Models\EventParticipantResignationLine;
use App\Models\EventPricePerPerson;
use App\Models\EventSettlement;
use App\Models\EventSettlementParticipantPayment;
use App\Models\EventTemplate;
use App\Models\User;
use App\Services\EventManualPricePerPersonService;
use App\Services\EventPriceCalculator;
use App\Services\ParticipantResignationSettlementSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventManualPriceAndResignationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'biuro', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        \App\Models\Currency::query()->firstOrCreate(
            ['code' => 'PLN'],
            ['name' => 'Polski złoty', 'symbol' => 'PLN', 'exchange_rate' => 1],
        );
    }

    public function test_manual_price_is_preserved_on_recalculate_and_used_in_resolved_price(): void
    {
        $user = User::factory()->create();
        $template = EventTemplate::factory()->create();

        $event = Event::create([
            'event_template_id' => $template->id,
            'name' => 'Impreza cena ręczna',
            'client_name' => 'Szkola',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'participant_count' => 20,
            'total_cost' => 2000,
            'status' => 'confirmed',
            'created_by' => $user->id,
        ]);

        $price = EventPricePerPerson::create([
            'event_id' => $event->id,
            'price_per_person' => 499.99,
            'price_with_tax' => 499.99,
            'is_manual' => true,
        ]);

        app(EventPriceCalculator::class)->calculateForEvent($event->fresh());

        $price->refresh();
        $this->assertTrue($price->is_manual);
        $this->assertSame('499.99', $price->price_per_person);

        $this->assertSame(499.99, $event->fresh()->resolvedPricePerPerson(20));
    }

    public function test_manual_price_service_sync_and_clear(): void
    {
        $user = User::factory()->create();
        $template = EventTemplate::factory()->create();

        $event = Event::create([
            'event_template_id' => $template->id,
            'name' => 'Sync cena ręczna',
            'client_name' => 'Szkola',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'participant_count' => 15,
            'total_cost' => 1500,
            'status' => 'confirmed',
            'created_by' => $user->id,
        ]);

        $service = app(EventManualPricePerPersonService::class);

        $service->sync($event, true, [], 389.5);

        $this->assertSame(389.5, $event->fresh()->resolvedPricePerPerson(15));
        $this->assertTrue(
            EventPricePerPerson::query()
                ->where('event_id', $event->id)
                ->where('is_manual', true)
                ->exists()
        );

        $service->sync($event->fresh(), false, []);

        $this->assertFalse(
            EventPricePerPerson::query()
                ->where('event_id', $event->id)
                ->where('is_manual', true)
                ->exists()
        );
    }

    public function test_resignation_sync_updates_settlement_participant_payment(): void
    {
        $user = User::factory()->create();
        $template = EventTemplate::factory()->create();

        $event = Event::create([
            'event_template_id' => $template->id,
            'name' => 'Impreza rezygnacja',
            'client_name' => 'Szkola',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'participant_count' => 10,
            'total_cost' => 1000,
            'status' => 'confirmed',
            'created_by' => $user->id,
        ]);

        $settlement = EventSettlement::findOrCreateActiveForEvent($event);

        $resignation = EventParticipantResignation::create([
            'event_id' => $event->id,
            'participant_name' => 'Jan Kowalski',
            'resignation_type' => 'contractual',
            'status' => 'confirmed',
            'resigned_at' => now()->toDateString(),
            'amount_due_pln' => 500,
            'amount_paid_pln' => 500,
            'created_by' => $user->id,
        ]);

        EventParticipantResignationLine::create([
            'resignation_id' => $resignation->id,
            'description' => 'Bilet wstępu',
            'refunded_amount_pln' => 200,
            'retained_amount_pln' => 100,
        ]);

        EventParticipantResignationLine::create([
            'resignation_id' => $resignation->id,
            'description' => 'Transport',
            'refunded_amount_pln' => 100,
            'retained_amount_pln' => 100,
        ]);

        $payment = app(ParticipantResignationSettlementSync::class)->sync($resignation->fresh(['lines']));

        $this->assertInstanceOf(EventSettlementParticipantPayment::class, $payment);
        $this->assertFalse($payment->attended);
        $this->assertSame('200.00', $payment->due_amount_pln);
        $this->assertSame('500.00', $payment->paid_amount_pln);
        $this->assertSame('300.00', $payment->discount_amount_pln);
        $this->assertStringContainsString('Rezygnacja', (string) $payment->notes);

        $resignation->refresh();
        $this->assertSame('settled', $resignation->status);
        $this->assertSame($settlement->id, $resignation->settlement_id);
        $this->assertSame('300.00', $resignation->refund_amount_pln);
        $this->assertSame('200.00', $resignation->retention_amount_pln);
    }

    public function test_insurance_resignation_sets_full_refund_and_zero_due(): void
    {
        $user = User::factory()->create();
        $template = EventTemplate::factory()->create();

        $event = Event::create([
            'event_template_id' => $template->id,
            'name' => 'Impreza ubezpieczenie',
            'client_name' => 'Szkola',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'participant_count' => 1,
            'total_cost' => 800,
            'status' => 'confirmed',
            'created_by' => $user->id,
        ]);

        $resignation = EventParticipantResignation::create([
            'event_id' => $event->id,
            'participant_name' => 'Anna Nowak',
            'resignation_type' => 'insurance',
            'status' => 'confirmed',
            'resigned_at' => now()->toDateString(),
            'amount_due_pln' => 800,
            'amount_paid_pln' => 800,
            'insurance_policy_number' => 'POL-123',
            'created_by' => $user->id,
        ]);

        $payment = app(ParticipantResignationSettlementSync::class)->sync($resignation->fresh());

        $this->assertSame('0.00', $payment->due_amount_pln);
        $this->assertSame('800.00', $payment->paid_amount_pln);
        $this->assertStringContainsString('POL-123', (string) $payment->notes);
    }
}
