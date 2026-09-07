<?php

namespace Tests\Feature;

use App\Livewire\EventBusCollections;
use App\Models\Currency;
use App\Models\Event;
use App\Models\EventBusCollection;
use App\Models\EventTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventBusCollectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        Currency::query()->firstOrCreate(
            ['code' => 'PLN'],
            ['name' => 'Polski złoty', 'symbol' => 'PLN', 'exchange_rate' => 1],
        );
    }

    public function test_bus_collection_stores_unit_amount_times_participant_count(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $template = EventTemplate::factory()->create();
        $plnId = Currency::defaultPlnId();

        $event = Event::create([
            'event_template_id' => $template->id,
            'name' => 'Impreza zbiórka',
            'client_name' => 'Szkola',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'participant_count' => 40,
            'total_cost' => 4000,
            'status' => 'confirmed',
            'created_by' => $user->id,
        ]);

        $this->actingAs($user);

        Livewire::test(EventBusCollections::class, ['event' => $event])
            ->set('unitAmount', '25')
            ->set('participantCount', '40')
            ->set('currencyId', $plnId)
            ->call('addCollection')
            ->assertHasNoErrors();

        $collection = EventBusCollection::query()->where('event_id', $event->id)->first();

        $this->assertNotNull($collection);
        $this->assertSame('25.00', $collection->amount_per_person);
        $this->assertSame('1000.00', $collection->amount);
        $this->assertSame(40, $collection->participant_count);
    }

    public function test_bus_collection_prefills_participant_count_from_event(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');
        $this->actingAs($user);

        $template = EventTemplate::factory()->create();
        $event = Event::create([
            'event_template_id' => $template->id,
            'name' => 'Impreza zbiórka defaults',
            'client_name' => 'Szkola',
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-05',
            'participant_count' => 33,
            'total_cost' => 4000,
            'status' => 'confirmed',
            'created_by' => $user->id,
        ]);

        Livewire::test(EventBusCollections::class, ['event' => $event])
            ->assertSet('participantCount', '33')
            ->assertSet('collectedAt', '2026-09-01T08:00');
    }

    public function test_bus_collection_prefills_pilot_foreign_from_installment_template(): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('event_payment_installment_templates')) {
            $this->markTestSkipped('Brak tabeli event_payment_installment_templates.');
        }

        $user = User::factory()->create();
        $user->assignRole('admin');
        $this->actingAs($user);

        $eurId = Currency::query()->firstOrCreate(
            ['code' => 'EUR'],
            ['name' => 'Euro', 'symbol' => 'EUR', 'exchange_rate' => 4.3],
        )->id;

        $template = EventTemplate::factory()->create();
        $event = Event::create([
            'event_template_id' => $template->id,
            'name' => 'Impreza EUR',
            'client_name' => 'Szkola',
            'start_date' => '2026-08-12',
            'end_date' => '2026-08-16',
            'participant_count' => 33,
            'total_cost' => 4000,
            'status' => 'confirmed',
            'created_by' => $user->id,
        ]);

        \App\Models\EventPaymentInstallmentTemplate::query()->create([
            'event_id' => $event->id,
            'sort_order' => 0,
            'label' => 'Waluta u pilota',
            'share_type' => \App\Models\EventPaymentInstallmentTemplate::SHARE_FOREIGN,
            'amount_foreign' => 128.47,
            'currency_code' => 'EUR',
            'paid_by' => \App\Models\EventPaymentInstallmentTemplate::PAID_BY_PILOT,
            'due_offset_days' => 0,
        ]);

        Livewire::test(EventBusCollections::class, ['event' => $event])
            ->assertSet('participantCount', '33')
            ->assertSet('unitAmount', '128.47')
            ->assertSet('currencyId', $eurId)
            ->assertSet('title', 'Waluta u pilota');
    }

    public function test_bus_collection_keeps_typed_amount_after_save(): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('event_payment_installment_templates')) {
            $this->markTestSkipped('Brak tabeli event_payment_installment_templates.');
        }

        $user = User::factory()->create();
        $user->assignRole('admin');
        $this->actingAs($user);

        $eurId = Currency::query()->firstOrCreate(
            ['code' => 'EUR'],
            ['name' => 'Euro', 'symbol' => 'EUR', 'exchange_rate' => 4.3],
        )->id;

        $template = EventTemplate::factory()->create();
        $event = Event::create([
            'event_template_id' => $template->id,
            'name' => 'Impreza kwota zbiórki',
            'client_name' => 'Szkola',
            'start_date' => '2026-08-12',
            'end_date' => '2026-08-16',
            'participant_count' => 10,
            'total_cost' => 4000,
            'status' => 'confirmed',
            'created_by' => $user->id,
        ]);

        \App\Models\EventPaymentInstallmentTemplate::query()->create([
            'event_id' => $event->id,
            'sort_order' => 0,
            'label' => 'Waluta u pilota',
            'share_type' => \App\Models\EventPaymentInstallmentTemplate::SHARE_FOREIGN,
            'amount_foreign' => 128.47,
            'currency_code' => 'EUR',
            'paid_by' => \App\Models\EventPaymentInstallmentTemplate::PAID_BY_PILOT,
            'due_offset_days' => 0,
        ]);

        Livewire::test(EventBusCollections::class, ['event' => $event])
            ->assertSet('unitAmount', '128.47')
            ->set('unitAmount', '50')
            ->set('participantCount', '10')
            ->set('currencyId', $eurId)
            ->call('addPlan')
            ->assertHasNoErrors()
            ->assertSet('unitAmount', '50')
            ->assertSet('participantCount', '10')
            ->assertSee('Podpowiedź z imprezy:')
            ->assertSee('128,47 EUR/os. (cennik — tylko podpowiedź)');

        $collection = EventBusCollection::query()->where('event_id', $event->id)->first();
        $this->assertNotNull($collection);
        $this->assertSame('50.00', $collection->amount_per_person);
        $this->assertSame('500.00', $collection->amount);
    }

    public function test_bus_collection_can_save_plan_without_funding_balance(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');
        $this->actingAs($user);

        $template = EventTemplate::factory()->create();
        $plnId = Currency::defaultPlnId();

        $event = Event::create([
            'event_template_id' => $template->id,
            'name' => 'Impreza plan zbiórki',
            'client_name' => 'Szkola',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'participant_count' => 20,
            'total_cost' => 4000,
            'status' => 'confirmed',
            'created_by' => $user->id,
        ]);

        Livewire::test(EventBusCollections::class, ['event' => $event])
            ->set('unitAmount', '50')
            ->set('participantCount', '20')
            ->set('currencyId', $plnId)
            ->call('addPlan')
            ->assertHasNoErrors();

        $collection = EventBusCollection::query()->where('event_id', $event->id)->first();
        $this->assertNotNull($collection);
        $this->assertSame(EventBusCollection::STATUS_PLANNED, $collection->status);
        $this->assertSame('1000.00', $collection->amount);
        $this->assertSame('1000.00', $collection->planned_amount);

        $settlement = \App\Models\EventSettlement::findOrCreateActiveForEvent($event);
        $row = app(\App\Services\PilotSettlementService::class)
            ->getCashReconciliation($settlement->fresh())
            ->firstWhere('currency_id', $plnId);

        $this->assertNotNull($row);
        $this->assertSame(0.0, (float) $row->from_bus);
        $this->assertSame(1000.0, (float) $row->bus_planned);
    }

    public function test_mark_collected_funds_pilot_cash_balance(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');
        $this->actingAs($user);

        $template = EventTemplate::factory()->create();
        $plnId = Currency::defaultPlnId();

        $event = Event::create([
            'event_template_id' => $template->id,
            'name' => 'Impreza zbiórka saldo',
            'client_name' => 'Szkola',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'participant_count' => 10,
            'total_cost' => 4000,
            'status' => 'confirmed',
            'created_by' => $user->id,
            'assigned_to' => $user->id,
        ]);

        $collection = EventBusCollection::create([
            'event_id' => $event->id,
            'title' => 'EUR autokar',
            'amount' => 500,
            'planned_amount' => 500,
            'currency_id' => $plnId,
            'participant_count' => 10,
            'amount_per_person' => 50,
            'status' => EventBusCollection::STATUS_PLANNED,
        ]);

        Livewire::test(EventBusCollections::class, ['event' => $event])
            ->call('markCollected', $collection->id)
            ->assertHasNoErrors();

        $this->assertSame(EventBusCollection::STATUS_COLLECTED, $collection->fresh()->status);

        $settlement = \App\Models\EventSettlement::findOrCreateActiveForEvent($event);
        $settlement->costs()->create([
            'source_type' => 'manual',
            'name' => 'Wydatek z zbiórki',
            'planned_amount' => 400,
            'planned_amount_pln' => 400,
            'actual_amount' => 400,
            'actual_amount_pln' => 400,
            'planned_currency_id' => $plnId,
            'actual_currency_id' => $plnId,
            'paid_by' => 'pilot',
            'payment_method' => 'cash',
            'payment_status' => 'paid',
        ]);

        $row = app(\App\Services\PilotSettlementService::class)
            ->getCashReconciliation($settlement->fresh())
            ->firstWhere('currency_id', $plnId);

        $this->assertNotNull($row);
        $this->assertSame(0.0, (float) $row->from_office);
        $this->assertSame(500.0, (float) $row->from_bus);
        $this->assertSame(400.0, (float) $row->actual_spent);
        $this->assertSame(100.0, (float) $row->to_return);
        $this->assertSame(0.0, (float) $row->to_pay_pilot);
        $this->assertSame('bus', $row->origin);
    }
}
