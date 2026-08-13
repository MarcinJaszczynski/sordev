<?php

namespace Tests\Feature;

use App\Actions\Finance\RecordSettlementCostPaymentAction;
use App\Actions\Finance\UpdateSettlementCostPlanAction;
use App\Data\RecordSettlementCostPaymentData;
use App\Data\UpdateSettlementCostPlanData;
use App\Models\Event;
use App\Models\EventDayInsurance;
use App\Models\EventSettlement;
use App\Models\EventSettlementCost;
use App\Models\EventSettlementDocument;
use App\Models\EventTemplate;
use App\Models\Insurance;
use App\Models\User;
use App\Services\ProgramPointSettlementDocumentSync;
use App\Services\SettlementPaymentHealthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventDayInsuranceSettlementSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_day_insurance_creates_settlement_cost_on_import(): void
    {
        $user = User::factory()->create();
        $template = EventTemplate::factory()->create();

        $event = Event::create([
            'event_template_id' => $template->id,
            'name' => 'Impreza ubezpieczenia',
            'client_name' => 'Szkola',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'participant_count' => 20,
            'total_cost' => 2000,
            'status' => 'confirmed',
            'created_by' => $user->id,
        ]);

        $settlement = EventSettlement::findOrCreateActiveForEvent($event);

        $insurance = Insurance::create([
            'name' => 'Polisa podstawowa',
            'price_per_person' => 5.50,
            'active' => true,
            'insurance_enabled' => true,
        ]);

        EventDayInsurance::create([
            'event_id' => $event->id,
            'day' => 1,
            'insurance_id' => $insurance->id,
        ]);

        $settlement->importFromEvent();

        $cost = EventSettlementCost::query()
            ->where('settlement_id', $settlement->id)
            ->where('source_type', 'insurance_day')
            ->first();

        $this->assertNotNull($cost);
        $this->assertSame('110.00', $cost->planned_amount_pln);
        $this->assertSame('planned', $cost->payment_status);
        $this->assertStringContainsString('Polisa podstawowa', (string) $cost->name);
    }

    public function test_day_insurances_relation_manager_opens_finance_drawer(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('admin');

        $template = EventTemplate::factory()->create();
        $event = Event::create([
            'event_template_id' => $template->id,
            'name' => 'Impreza drawer ubezpieczeń',
            'client_name' => 'Szkola',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'participant_count' => 10,
            'total_cost' => 1000,
            'status' => 'confirmed',
            'created_by' => $user->id,
        ]);

        $settlement = EventSettlement::findOrCreateActiveForEvent($event);
        $insurance = Insurance::create([
            'name' => 'NNW test',
            'price_per_person' => 4,
            'active' => true,
            'insurance_enabled' => true,
        ]);
        $day = EventDayInsurance::create([
            'event_id' => $event->id,
            'day' => 1,
            'insurance_id' => $insurance->id,
        ]);
        $settlement->importFromEvent();

        $cost = EventSettlementCost::query()
            ->where('settlement_id', $settlement->id)
            ->where('source_type', 'insurance_day')
            ->where('source_id', $day->id)
            ->firstOrFail();

        Livewire::actingAs($user)
            ->test(
                \App\Filament\Resources\EventResource\RelationManagers\DayInsurancesRelationManager::class,
                [
                    'ownerRecord' => $event,
                    'pageClass' => \App\Filament\Resources\EventResource\Pages\ManageEventDayInsurances::class,
                ]
            )
            ->call('openCost', (int) $cost->id)
            ->assertSet('selectedCostId', (int) $cost->id)
            ->assertSet('showPaymentForm', false)
            ->call('startAddAdvance', 'office')
            ->assertSet('showPaymentForm', true)
            ->assertSet('paymentForm.advance_type', 'advance')
            ->assertSet('paymentForm.paid_by', 'office');
    }

    public function test_day_insurance_settlement_cost_includes_gratis(): void
    {
        $user = User::factory()->create();
        $template = EventTemplate::factory()->create();

        $event = Event::create([
            'event_template_id' => $template->id,
            'name' => 'Impreza z gratisami',
            'client_name' => 'Szkola',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'participant_count' => 20,
            'total_cost' => 2000,
            'status' => 'confirmed',
            'created_by' => $user->id,
        ]);

        \App\Models\EventQty::create([
            'event_id' => $event->id,
            'qty' => 20,
            'gratis' => 2,
            'staff' => 1,
            'driver' => 1,
        ]);

        $settlement = EventSettlement::findOrCreateActiveForEvent($event);

        $insurance = Insurance::create([
            'name' => 'Polisa z gratisami',
            'price_per_person' => 5.50,
            'active' => true,
            'insurance_enabled' => true,
        ]);

        EventDayInsurance::create([
            'event_id' => $event->id,
            'day' => 1,
            'insurance_id' => $insurance->id,
        ]);

        $settlement->importFromEvent();

        $cost = EventSettlementCost::query()
            ->where('settlement_id', $settlement->id)
            ->where('source_type', 'insurance_day')
            ->first();

        $this->assertNotNull($cost);
        // 22 × 5.50 = 121.00
        $this->assertSame('121.00', $cost->planned_amount_pln);
    }

    public function test_day_insurance_payment_and_document_follow_settlement_stack(): void
    {
        Role::findOrCreate('admin');
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('admin');
        $this->actingAs($user);

        Storage::fake('public');

        $template = EventTemplate::factory()->create();
        $event = Event::create([
            'event_template_id' => $template->id,
            'name' => 'Impreza ubez. finance',
            'client_name' => 'Szkola',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'participant_count' => 10,
            'total_cost' => 1000,
            'status' => 'confirmed',
            'created_by' => $user->id,
        ]);

        $settlement = EventSettlement::findOrCreateActiveForEvent($event);
        $insurance = Insurance::create([
            'name' => 'NNW test',
            'price_per_person' => 10,
            'active' => true,
            'insurance_enabled' => true,
        ]);

        $day = EventDayInsurance::create([
            'event_id' => $event->id,
            'day' => 1,
            'insurance_id' => $insurance->id,
        ]);

        $settlement->importFromEvent();

        $plan = EventSettlementCost::query()
            ->where('settlement_id', $settlement->id)
            ->where('source_type', 'insurance_day')
            ->where('source_id', $day->id)
            ->first();

        $this->assertNotNull($plan);
        $this->assertSame('100.00', $plan->planned_amount_pln);

        app(UpdateSettlementCostPlanAction::class)(new UpdateSettlementCostPlanData(
            planCost: $plan,
            plannedAmountPln: 120.0,
            paidBy: 'office',
            notes: 'Korekta planu',
            plannedAmount: 120.0,
        ));

        $plan->refresh();
        $this->assertSame('120.00', $plan->planned_amount_pln);

        app(RecordSettlementCostPaymentAction::class)(new RecordSettlementCostPaymentData(
            planCost: $plan->fresh(),
            amountPln: 120.0,
            paymentMethod: 'transfer',
            paidBy: 'office',
            advanceType: 'final',
            paidAt: now(),
            notes: 'Polisa zapłacona',
        ));

        $payment = EventSettlementCost::query()
            ->where('settlement_id', $settlement->id)
            ->where('source_type', 'insurance_day_payment')
            ->where('source_id', $day->id)
            ->first();

        $this->assertNotNull($payment);
        $this->assertSame('120.00', $payment->actual_amount_pln);

        $health = app(SettlementPaymentHealthService::class);
        $all = $settlement->fresh(['costs'])->costs;
        $this->assertSame(120.0, $health->paidPlnForPlanCost($plan->fresh(), $all));
        $this->assertSame('paid', $plan->fresh()->payment_status);

        $path = 'event-settlement-documents/test-polisa.pdf';
        Storage::disk('public')->put($path, 'fake-pdf');

        $document = app(ProgramPointSettlementDocumentSync::class)->syncForCost(
            $settlement->fresh(),
            $plan->fresh(),
            [
                'document_type' => 'invoice',
                'document_number' => 'POL/1/2026',
                'document_files' => [$path],
            ],
        );

        $this->assertInstanceOf(EventSettlementDocument::class, $document);
        $this->assertContains((int) $plan->id, collect($document->linked_cost_ids)->map(fn ($id) => (int) $id)->all());
        $this->assertSame('POL/1/2026', $document->document_number);
    }

    public function test_event_insurance_readiness_persists_completed_status(): void
    {
        $user = User::factory()->create();
        $template = EventTemplate::factory()->create();
        $event = Event::create([
            'event_template_id' => $template->id,
            'name' => 'Gotowość ubez.',
            'client_name' => 'Szkola',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'participant_count' => 5,
            'total_cost' => 500,
            'status' => 'confirmed',
            'created_by' => $user->id,
        ]);

        $event->updateInsuranceFromFormData([
            'insurance_policy_number' => 'ABC-123',
            'insurance_status' => 'completed',
            'insurance_payment_status' => 'paid',
            'insurance_amount' => 250,
            'insurance_paid_at' => now()->toDateTimeString(),
            'insurance_document_path' => null,
            'insurance_terms' => 'Warunki test',
        ]);

        $event->refresh();
        $this->assertTrue($event->isInsuranceCompleted());
        $this->assertSame('ABC-123', $event->insurance_policy_number);
        $this->assertSame('completed', $event->insurance_status);
        $this->assertSame('paid', $event->insurance_payment_status);
    }
}
