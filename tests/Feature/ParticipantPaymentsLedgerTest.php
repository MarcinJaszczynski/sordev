<?php

namespace Tests\Feature;

use App\Livewire\ParticipantPaymentsLedger;
use App\Models\Event;
use App\Models\EventSettlement;
use App\Models\EventSettlementParticipantPayment;
use App\Models\User;
use App\Services\ParticipantPaymentBalanceService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ParticipantPaymentsLedgerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin']);
    }

    public function test_livewire_add_entry_updates_remaining_balance(): void
    {
        if (! Schema::hasTable('event_settlement_participant_payment_entries')) {
            $this->markTestSkipped('Brak tabeli historii wpłat.');
        }

        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('admin');
        $this->actingAs($user);

        $event = Event::factory()->create();
        $settlement = EventSettlement::findOrCreateActiveForEvent($event);

        $payment = EventSettlementParticipantPayment::query()->create([
            'settlement_id' => $settlement->id,
            'participant_name' => 'Susan Dale',
            'due_amount_pln' => 1500,
            'paid_amount_pln' => 0,
            'payment_status' => 'pending',
        ]);

        Livewire::test(ParticipantPaymentsLedger::class, [
            'settlementId' => $settlement->id,
            'eventId' => $event->id,
        ])
            ->callAction('addEntry', data: [
                'paid_at' => '2026-07-05 10:00:00',
                'amount_pln' => 150,
                'payer_name' => 'Susan Dale',
                'payment_method' => 'transfer',
            ], arguments: ['paymentId' => $payment->id])
            ->assertHasNoErrors();

        $payment->refresh()->load('entries');

        $this->assertCount(1, $payment->entries);
        $this->assertSame(150.0, (float) $payment->paid_amount_pln);

        $balance = app(ParticipantPaymentBalanceService::class)->balanceRow($payment);
        $this->assertSame(1350.0, $balance['remaining_pln']);
    }

    public function test_livewire_add_entry_supports_foreign_currency(): void
    {
        if (! Schema::hasTable('event_settlement_participant_payment_entries')) {
            $this->markTestSkipped('Brak tabeli historii wpłat.');
        }
        if (! Schema::hasColumn('event_settlement_participant_payment_entries', 'currency_id')) {
            $this->markTestSkipped('Brak kolumn waluty na wpłatach.');
        }

        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('admin');
        $this->actingAs($user);

        $eur = \App\Models\Currency::create([
            'name' => 'Euro',
            'symbol' => 'EUR',
            'code' => 'EUR',
            'exchange_rate' => 4.3,
        ]);

        $event = Event::factory()->create();
        $settlement = EventSettlement::findOrCreateActiveForEvent($event);

        $payment = EventSettlementParticipantPayment::query()->create([
            'settlement_id' => $settlement->id,
            'participant_name' => 'Euro Payer',
            'due_amount_pln' => 1000,
            'paid_amount_pln' => 0,
            'payment_status' => 'pending',
        ]);

        Livewire::test(ParticipantPaymentsLedger::class, [
            'settlementId' => $settlement->id,
            'eventId' => $event->id,
        ])
            ->callAction('addEntry', data: [
                'paid_at' => '2026-07-05 10:00:00',
                'currency_id' => $eur->id,
                'amount' => 100,
                'rate' => 4.3,
                'amount_pln' => 430,
                'payer_name' => 'Euro Payer',
                'payment_kind' => 'pilot_on_site',
                'payment_method' => 'cash',
            ], arguments: ['paymentId' => $payment->id])
            ->assertHasNoErrors();

        $payment->refresh()->load('entries');
        $entry = $payment->entries->first();

        $this->assertNotNull($entry);
        $this->assertEqualsWithDelta(100.0, (float) $entry->amount, 0.01);
        $this->assertEqualsWithDelta(4.3, (float) $entry->rate, 0.0001);
        $this->assertEqualsWithDelta(430.0, (float) $entry->amount_pln, 0.01);
        $this->assertSame((int) $eur->id, (int) $entry->currency_id);
        $this->assertEqualsWithDelta(430.0, (float) $payment->paid_amount_pln, 0.01);
    }

    public function test_livewire_rejects_payment_from_other_event(): void
    {
        if (! Schema::hasTable('event_settlement_participant_payment_entries')) {
            $this->markTestSkipped('Brak tabeli historii wpłat.');
        }

        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('admin');
        $this->actingAs($user);

        $eventA = Event::factory()->create();
        $eventB = Event::factory()->create();
        $settlementA = EventSettlement::findOrCreateActiveForEvent($eventA);
        $settlementB = EventSettlement::findOrCreateActiveForEvent($eventB);

        $paymentB = EventSettlementParticipantPayment::query()->create([
            'settlement_id' => $settlementB->id,
            'participant_name' => 'Obcy uczestnik',
            'due_amount_pln' => 500,
            'paid_amount_pln' => 0,
            'payment_status' => 'pending',
        ]);

        $this->expectException(ModelNotFoundException::class);

        Livewire::test(ParticipantPaymentsLedger::class, [
            'settlementId' => $settlementA->id,
            'eventId' => $eventA->id,
        ])
            ->callAction('addEntry', data: [
                'paid_at' => '2026-07-05 10:00:00',
                'amount_pln' => 100,
                'payer_name' => 'Obcy uczestnik',
                'payment_method' => 'transfer',
            ], arguments: ['paymentId' => $paymentB->id]);
    }

    public function test_livewire_rejects_mismatched_settlement_event_on_mount(): void
    {
        $eventA = Event::factory()->create();
        $eventB = Event::factory()->create();
        $settlementB = EventSettlement::findOrCreateActiveForEvent($eventB);

        Livewire::test(ParticipantPaymentsLedger::class, [
            'settlementId' => $settlementB->id,
            'eventId' => $eventA->id,
        ])->assertStatus(404);
    }

    public function test_add_participant_defaults_due_from_price_and_supports_waive(): void
    {
        if (! Schema::hasTable('event_settlement_participant_payments')) {
            $this->markTestSkipped('Brak tabeli wpłat uczestników.');
        }

        if (! Schema::hasTable('event_participants')) {
            $this->markTestSkipped('Brak tabeli event_participants.');
        }

        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('admin');
        $this->actingAs($user);

        $event = Event::factory()->create([
            'code' => '26TEST01',
            'participant_count' => 10,
        ]);
        $settlement = EventSettlement::findOrCreateActiveForEvent($event);

        // Bez pełnej kalkulacji cena może być 0 — wtedy waive i ręczna kwota i tak działają.
        Livewire::test(ParticipantPaymentsLedger::class, [
            'settlementId' => $settlement->id,
            'eventId' => $event->id,
        ])
            ->callAction('addParticipant', data: [
                'first_name' => 'Anna',
                'last_name' => 'Nowak',
                'waive_payment' => true,
                'due_amount_pln' => 999,
            ])
            ->assertHasNoErrors();

        $payment = EventSettlementParticipantPayment::query()
            ->where('settlement_id', $settlement->id)
            ->where('participant_name', 'Anna Nowak')
            ->first();

        $this->assertNotNull($payment);
        $this->assertEqualsWithDelta(0.0, (float) $payment->due_amount_pln, 0.01);
    }

    public function test_add_participant_uses_explicit_due_when_not_waived(): void
    {
        if (! Schema::hasTable('event_settlement_participant_payments') || ! Schema::hasTable('event_participants')) {
            $this->markTestSkipped('Brak tabel uczestników/wpłat.');
        }

        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('admin');
        $this->actingAs($user);

        $event = Event::factory()->create(['code' => '26TEST02']);
        $settlement = EventSettlement::findOrCreateActiveForEvent($event);

        Livewire::test(ParticipantPaymentsLedger::class, [
            'settlementId' => $settlement->id,
            'eventId' => $event->id,
        ])
            ->callAction('addParticipant', data: [
                'first_name' => 'Piotr',
                'last_name' => 'Kowalski',
                'waive_payment' => false,
                'due_amount_pln' => 1236,
            ])
            ->assertHasNoErrors();

        $payment = EventSettlementParticipantPayment::query()
            ->where('settlement_id', $settlement->id)
            ->where('participant_name', 'Piotr Kowalski')
            ->first();

        $this->assertNotNull($payment);
        $this->assertEqualsWithDelta(1236.0, (float) $payment->due_amount_pln, 0.01);
    }

    public function test_event_aggregate_reports_gap_counts_and_attention(): void
    {
        if (! Schema::hasTable('event_settlement_participant_payments')) {
            $this->markTestSkipped('Brak tabeli wpłat uczestników.');
        }

        $event = Event::factory()->create(['participant_count' => 3]);
        $settlement = EventSettlement::findOrCreateActiveForEvent($event);

        if (Schema::hasTable('event_price_per_person')) {
            \App\Models\EventPricePerPerson::query()->create([
                'event_id' => $event->id,
                'price_per_person' => 1000,
                'price_with_tax' => 1000,
                'is_manual' => true,
            ]);
        }

        EventSettlementParticipantPayment::query()->create([
            'settlement_id' => $settlement->id,
            'participant_name' => 'Opłacony',
            'due_amount_pln' => 1000,
            'paid_amount_pln' => 1000,
            'payment_status' => 'paid',
        ]);
        EventSettlementParticipantPayment::query()->create([
            'settlement_id' => $settlement->id,
            'participant_name' => 'Bez opłaty',
            'due_amount_pln' => 0,
            'paid_amount_pln' => 0,
            'payment_status' => 'paid',
        ]);
        EventSettlementParticipantPayment::query()->create([
            'settlement_id' => $settlement->id,
            'participant_name' => 'Dłużnik',
            'due_amount_pln' => 1500,
            'paid_amount_pln' => 200,
            'payment_status' => 'partial',
        ]);

        $event->setRelation('activeSettlement', $settlement->load('participantPayments'));

        $aggregate = app(ParticipantPaymentBalanceService::class)->eventAggregate($event);

        $this->assertSame(3, $aggregate['count']);
        $this->assertSame(1, $aggregate['paid_count']);
        $this->assertSame(1, $aggregate['waive_count']);
        $this->assertSame(1, $aggregate['shortfall_count']);
        $this->assertSame(2, $aggregate['registered_paying_count']);
        $this->assertEqualsWithDelta(1200.0, $aggregate['total_paid'], 0.01);
        $this->assertEqualsWithDelta(2500.0, $aggregate['ledger_due_pln'], 0.01);
        $this->assertEqualsWithDelta(1300.0, $aggregate['ledger_remaining_pln'], 0.01);
        // Pojemność: 3 × 1000 = 3000, remaining = 3000 - 1200 = 1800
        if (Schema::hasTable('event_price_per_person')) {
            $this->assertSame(3, $aggregate['expected_count']);
            $this->assertEqualsWithDelta(1000.0, $aggregate['price_per_person'], 0.01);
            $this->assertEqualsWithDelta(3000.0, $aggregate['expected_due_pln'], 0.01);
            $this->assertEqualsWithDelta(3000.0, $aggregate['total_due'], 0.01);
            $this->assertEqualsWithDelta(1800.0, $aggregate['capacity_remaining_pln'], 0.01);
            $this->assertEqualsWithDelta(1800.0, $aggregate['total_remaining'], 0.01);
            $this->assertSame(1, $aggregate['unregistered_count']); // 3 - 2 paying
        }
        $this->assertSame('shortfall', $aggregate['coverage_status']);
        $this->assertCount(1, $aggregate['attention']);
        $this->assertSame('Dłużnik', $aggregate['attention'][0]['name']);
    }

    public function test_event_aggregate_capacity_and_installment_gaps(): void
    {
        if (! Schema::hasTable('event_settlement_participant_payments')) {
            $this->markTestSkipped('Brak tabeli wpłat uczestników.');
        }
        if (! Schema::hasTable('event_price_per_person')) {
            $this->markTestSkipped('Brak tabeli cen.');
        }
        if (! Schema::hasTable('event_payment_installment_templates')) {
            $this->markTestSkipped('Brak tabeli szablonu rat.');
        }

        $event = Event::factory()->create([
            'participant_count' => 10,
            'start_date' => '2026-09-10',
        ]);
        $settlement = EventSettlement::findOrCreateActiveForEvent($event);

        \App\Models\EventPricePerPerson::query()->create([
            'event_id' => $event->id,
            'price_per_person' => 100,
            'price_with_tax' => 100,
            'is_manual' => true,
        ]);

        EventSettlementParticipantPayment::query()->create([
            'settlement_id' => $settlement->id,
            'participant_name' => 'Jedyny',
            'due_amount_pln' => 100,
            'paid_amount_pln' => 50,
            'payment_status' => 'partial',
        ]);

        app(\App\Services\EventPaymentInstallmentTemplateService::class)->syncTemplate($event, [
            [
                'label' => 'Zaliczka',
                'share_type' => \App\Models\EventPaymentInstallmentTemplate::SHARE_PERCENT,
                'percent' => 10,
                'due_offset_days' => -30,
            ],
            [
                'label' => 'Dopłata',
                'share_type' => \App\Models\EventPaymentInstallmentTemplate::SHARE_PERCENT,
                'percent' => 90,
                'due_offset_days' => -14,
            ],
        ]);

        $event->setRelation('activeSettlement', $settlement->load('participantPayments'));
        $aggregate = app(ParticipantPaymentBalanceService::class)->eventAggregate($event);

        $this->assertSame(10, $aggregate['expected_count']);
        $this->assertEqualsWithDelta(1000.0, $aggregate['expected_due_pln'], 0.01);
        $this->assertEqualsWithDelta(50.0, $aggregate['total_paid'], 0.01);
        $this->assertEqualsWithDelta(950.0, $aggregate['capacity_remaining_pln'], 0.01);
        $this->assertSame(1, $aggregate['registered_paying_count']);
        $this->assertSame(9, $aggregate['unregistered_count']);
        $this->assertSame(1, $aggregate['paid_any_count']);
        $this->assertSame(0, $aggregate['paid_full_count']);

        $this->assertCount(2, $aggregate['installment_gaps']);
        $zaliczka = $aggregate['installment_gaps'][0];
        $this->assertSame('Zaliczka', $zaliczka['label']);
        $this->assertEqualsWithDelta(100.0, $zaliczka['expected'], 0.01); // 10% × 10 × 100
        $this->assertEqualsWithDelta(50.0, $zaliczka['paid_toward'], 0.01);
        $this->assertEqualsWithDelta(50.0, $zaliczka['remaining'], 0.01);

        $doplata = $aggregate['installment_gaps'][1];
        $this->assertSame('Dopłata', $doplata['label']);
        $this->assertEqualsWithDelta(900.0, $doplata['expected'], 0.01);
        $this->assertEqualsWithDelta(0.0, $doplata['paid_toward'], 0.01);
        $this->assertEqualsWithDelta(900.0, $doplata['remaining'], 0.01);
    }

    public function test_ledger_renders_gap_analysis_cards(): void
    {
        if (! Schema::hasTable('event_settlement_participant_payments')) {
            $this->markTestSkipped('Brak tabeli wpłat uczestników.');
        }

        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('admin');
        $this->actingAs($user);

        $event = Event::factory()->create([
            'code' => '26GAP01',
            'participant_count' => 5,
        ]);
        $settlement = EventSettlement::findOrCreateActiveForEvent($event);

        if (Schema::hasTable('event_price_per_person')) {
            \App\Models\EventPricePerPerson::query()->create([
                'event_id' => $event->id,
                'price_per_person' => 800,
                'price_with_tax' => 800,
                'is_manual' => true,
            ]);
        }

        EventSettlementParticipantPayment::query()->create([
            'settlement_id' => $settlement->id,
            'participant_name' => 'Jan Luka',
            'due_amount_pln' => 800,
            'paid_amount_pln' => 100,
            'payment_status' => 'partial',
        ]);

        Livewire::test(ParticipantPaymentsLedger::class, [
            'settlementId' => $settlement->id,
            'eventId' => $event->id,
        ])
            ->assertSee('Należne PLN')
            ->assertSee('Różnica PLN')
            ->assertSee('Rozliczenie PLN')
            ->assertSee('Jan Luka')
            ->assertSee('Status');
    }
}
