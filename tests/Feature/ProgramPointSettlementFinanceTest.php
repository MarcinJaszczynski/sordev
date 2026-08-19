<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\Event;
use App\Models\EventProgramPoint;
use App\Models\EventSettlement;
use App\Models\EventSettlementCost;
use App\Models\EventSettlementDocument;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use App\Services\EventPaymentReminderSyncService;
use App\Services\ProgramPointSettlementDocumentSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProgramPointSettlementFinanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\TaskStatusSeeder::class);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    }

    protected function createOfficeUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    public function test_upsert_cost_from_eur_point_without_convert_keeps_null_pln(): void
    {
        $user = User::factory()->create();
        $eur = $this->createEurCurrency();

        $event = Event::factory()->create([
            'participant_count' => 44,
            'assigned_to' => $user->id,
        ]);

        $point = EventProgramPoint::create([
            'event_id' => $event->id,
            'day' => 1,
            'order' => 1,
            'name' => 'Luwr przewodnik',
            'unit_price' => 280,
            'quantity' => 2,
            'group_size' => 25,
            'currency_id' => $eur->id,
            'convert_to_pln' => false,
            'include_in_program' => true,
            'active' => true,
        ]);

        $settlement = EventSettlement::create([
            'event_id' => $event->id,
            'status' => 'active',
            'created_by' => $user->id,
        ]);

        $cost = $settlement->upsertCostFromProgramPoint($point->fresh(['currency', 'event']));

        $this->assertSame(560.0, (float) $cost->planned_amount);
        $this->assertSame($eur->id, (int) $cost->planned_currency_id);
        $this->assertFalse((bool) $cost->planned_convert_to_pln);
        $this->assertNull($cost->planned_amount_pln);
    }

    public function test_upsert_cost_from_eur_point_with_convert_calculates_pln(): void
    {
        $user = User::factory()->create();
        $eur = $this->createEurCurrency(4.35);

        $event = Event::factory()->create([
            'participant_count' => 44,
            'assigned_to' => $user->id,
        ]);

        $point = EventProgramPoint::create([
            'event_id' => $event->id,
            'day' => 1,
            'order' => 1,
            'name' => 'Luwr przewodnik',
            'unit_price' => 280,
            'quantity' => 2,
            'group_size' => 25,
            'currency_id' => $eur->id,
            'convert_to_pln' => true,
            'include_in_program' => true,
            'active' => true,
        ]);

        $settlement = EventSettlement::create([
            'event_id' => $event->id,
            'status' => 'active',
            'created_by' => $user->id,
        ]);

        $cost = $settlement->upsertCostFromProgramPoint($point->fresh(['currency', 'event']));

        $this->assertTrue((bool) $cost->planned_convert_to_pln);
        $this->assertSame(2436.0, (float) $cost->planned_amount_pln);
    }

    public function test_plan_payable_until_creates_task_reminder(): void
    {
        $user = $this->createOfficeUser();
        $eur = $this->createEurCurrency();

        $event = Event::factory()->create(['assigned_to' => $user->id]);

        $point = EventProgramPoint::create([
            'event_id' => $event->id,
            'day' => 1,
            'order' => 1,
            'name' => 'Luwr przewodnik',
            'currency_id' => $eur->id,
            'convert_to_pln' => false,
            'include_in_program' => true,
            'active' => true,
        ]);

        $settlement = EventSettlement::create([
            'event_id' => $event->id,
            'status' => 'active',
            'created_by' => $user->id,
        ]);

        $dueDate = now()->addDays(18);

        $cost = EventSettlementCost::create([
            'settlement_id' => $settlement->id,
            'source_type' => 'program_point',
            'source_id' => $point->id,
            'name' => 'Luwr przewodnik',
            'planned_amount' => 560,
            'planned_currency_id' => $eur->id,
            'planned_convert_to_pln' => false,
            'planned_rate' => 4.35,
            'planned_amount_pln' => null,
            'advance_due_date' => $dueDate,
            'payment_status' => 'planned',
            'paid_by' => 'office',
            'advance_type' => 'full',
        ]);

        app(EventPaymentReminderSyncService::class)->syncSettlementCost($cost->fresh(['plannedCurrency', 'settlement.event']));

        $task = Task::query()
            ->where('description', 'like', '%[payment-reminder:settlement_cost:'.$cost->id.':plan]%')
            ->first();

        // Auto-przypomnienia płatności wyłączone (SystemTaskPolicy).
        $this->assertNull($task);
    }

    public function test_document_sync_links_invoice_to_settlement_cost(): void
    {
        $user = User::factory()->create();
        $eur = $this->createEurCurrency();

        $event = Event::factory()->create(['assigned_to' => $user->id]);

        $point = EventProgramPoint::create([
            'event_id' => $event->id,
            'day' => 1,
            'order' => 1,
            'name' => 'Luwr przewodnik',
            'currency_id' => $eur->id,
            'include_in_program' => true,
            'active' => true,
        ]);

        $settlement = EventSettlement::create([
            'event_id' => $event->id,
            'status' => 'active',
            'created_by' => $user->id,
        ]);

        $cost = EventSettlementCost::create([
            'settlement_id' => $settlement->id,
            'source_type' => 'program_point_payment',
            'source_id' => $point->id,
            'name' => 'Luwr przewodnik • wpłata #1',
            'planned_amount' => 0,
            'planned_currency_id' => $eur->id,
            'planned_convert_to_pln' => false,
            'planned_rate' => 4.35,
            'actual_amount' => 560,
            'actual_currency_id' => $eur->id,
            'actual_rate' => 4.35,
            'payment_status' => 'paid',
            'paid_by' => 'office',
            'advance_type' => 'full',
        ]);

        $document = app(ProgramPointSettlementDocumentSync::class)->syncForCost($settlement, $cost, [
            'document_type' => 'wz',
            'document_number' => 'WZ/2026/12',
            'document_files' => ['event-settlement-documents/test-wz.pdf'],
        ]);

        $this->assertInstanceOf(EventSettlementDocument::class, $document);
        $this->assertSame('wz', $document->document_type);
        $this->assertSame('WZ/2026/12', $document->document_number);
        $this->assertContains($cost->id, $document->linked_cost_ids ?? []);

        $loaded = app(ProgramPointSettlementDocumentSync::class)->loadDocumentDataForCost($cost);
        $this->assertSame($document->id, $loaded['document_id']);
        $this->assertSame('wz', $loaded['document_type']);
        $this->assertSame('WZ/2026/12', $loaded['document_number']);
    }

    public function test_build_settle_point_form_data_resolves_event_without_recursion(): void
    {
        $event = Event::factory()->create(['participant_count' => 20]);
        $point = EventProgramPoint::create([
            'event_id' => $event->id,
            'day' => 1,
            'order' => 1,
            'name' => 'Muzeum',
            'unit_price' => 100,
            'quantity' => 1,
            'include_in_program' => true,
            'active' => true,
        ]);

        $helper = new class($event)
        {
            use \App\Filament\Resources\EventResource\Concerns\ManagesProgramPointSettlementFinance;

            public function __construct(private Event $event) {}

            public function getOwnerRecord(): Event
            {
                return $this->event;
            }

            public function formData(EventProgramPoint $point): array
            {
                return $this->buildSettlePointFormData($point);
            }
        };

        $data = $helper->formData($point->fresh());

        $this->assertArrayHasKey('settlement_planned_amount', $data);
        $this->assertArrayHasKey('payment_entries', $data);
        $this->assertArrayHasKey('advance_entries', $data);
        $this->assertSame($event->id, $helper->getOwnerRecord()->id);
    }

    public function test_multiple_advance_entries_persist_as_separate_payment_rows(): void
    {
        $user = $this->createOfficeUser();
        $this->actingAs($user);
        $pln = Currency::factory()->pln()->create();

        $event = Event::factory()->create(['assigned_to' => $user->id]);
        $point = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'currency_id' => $pln->id,
            'convert_to_pln' => true,
            'include_in_program' => true,
            'active' => true,
        ]);

        $helper = $this->makeSettlementFinanceHelper($event);

        $dueDateOne = now()->addDays(10);
        $dueDateTwo = now()->addDays(20);

        $helper->saveAdvances($point->fresh(['currency', 'templatePoint', 'event']), [
            'settlement_planned_amount' => 1000,
            'settlement_planned_currency_id' => $pln->id,
            'settlement_planned_convert_to_pln' => true,
            'settlement_planned_rate' => 1,
            'settlement_planned_amount_pln' => 1000,
            'settlement_paid_by' => 'office',
            'advance_entries' => [
                [
                    'due_date' => $dueDateOne,
                    'advance_amount' => 300,
                    'currency_id' => $pln->id,
                    'paid_at' => null,
                    'notes' => 'Pierwsza zaliczka',
                ],
                [
                    'due_date' => $dueDateTwo,
                    'advance_amount' => 200,
                    'currency_id' => $pln->id,
                    'paid_at' => now(),
                    'notes' => 'Druga zapłacona',
                ],
            ],
        ]);

        $advanceRows = EventSettlementCost::query()
            ->where('source_type', 'program_point_payment')
            ->where('source_id', $point->id)
            ->where('advance_type', 'advance')
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $advanceRows);
        $this->assertSame(300.0, (float) $advanceRows[0]->advance_amount);
        $this->assertSame('advance_required', $advanceRows[0]->payment_status);
        $this->assertSame(200.0, (float) $advanceRows[1]->advance_amount);
        $this->assertSame('advance_paid', $advanceRows[1]->payment_status);
        $this->assertSame(200.0, (float) $advanceRows[1]->actual_amount);

        $baseCost = EventSettlementCost::query()
            ->where('source_type', 'program_point')
            ->where('source_id', $point->id)
            ->first();

        $this->assertNotNull($baseCost);
        $this->assertSame(500.0, (float) $baseCost->advance_amount);
    }

    public function test_unpaid_advance_with_due_date_creates_task_reminder(): void
    {
        $this->seed(\Database\Seeders\TaskStatusSeeder::class);

        $user = $this->createOfficeUser();
        $this->actingAs($user);
        $pln = Currency::factory()->pln()->create();

        $event = Event::factory()->create(['assigned_to' => $user->id]);
        $point = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'currency_id' => $pln->id,
            'convert_to_pln' => true,
            'include_in_program' => true,
            'active' => true,
        ]);

        $helper = $this->makeSettlementFinanceHelper($event);
        $dueDate = now()->addDays(7);

        $helper->saveAdvances($point->fresh(['currency', 'templatePoint', 'event']), [
            'settlement_planned_amount' => 800,
            'settlement_planned_currency_id' => $pln->id,
            'settlement_planned_convert_to_pln' => true,
            'settlement_planned_rate' => 1,
            'settlement_planned_amount_pln' => 800,
            'settlement_paid_by' => 'office',
            'advance_entries' => [[
                'due_date' => $dueDate,
                'advance_amount' => 250,
                'currency_id' => $pln->id,
                'paid_at' => null,
                'notes' => null,
            ]],
        ]);

        $advanceCost = EventSettlementCost::query()
            ->where('source_type', 'program_point_payment')
            ->where('source_id', $point->id)
            ->where('advance_type', 'advance')
            ->first();

        $this->assertNotNull($advanceCost);

        $task = Task::query()
            ->where('description', 'like', '%[payment-reminder:settlement_cost:'.$advanceCost->id.':advance]%')
            ->first();

        $this->assertNull($task);
    }

    public function test_paid_advance_retires_task_reminder(): void
    {
        $this->seed(\Database\Seeders\TaskStatusSeeder::class);

        $user = $this->createOfficeUser();
        $this->actingAs($user);
        $pln = Currency::factory()->pln()->create();

        $event = Event::factory()->create(['assigned_to' => $user->id]);
        $point = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'currency_id' => $pln->id,
            'convert_to_pln' => true,
            'include_in_program' => true,
            'active' => true,
        ]);

        $helper = $this->makeSettlementFinanceHelper($event);
        $dueDate = now()->addDays(7);

        $helper->saveAdvances($point->fresh(['currency', 'templatePoint', 'event']), [
            'settlement_planned_amount' => 800,
            'settlement_planned_currency_id' => $pln->id,
            'settlement_planned_convert_to_pln' => true,
            'settlement_planned_rate' => 1,
            'settlement_planned_amount_pln' => 800,
            'settlement_paid_by' => 'office',
            'advance_entries' => [[
                'due_date' => $dueDate,
                'advance_amount' => 250,
                'currency_id' => $pln->id,
                'paid_at' => null,
                'notes' => null,
            ]],
        ]);

        $advanceCost = EventSettlementCost::query()
            ->where('source_type', 'program_point_payment')
            ->where('source_id', $point->id)
            ->where('advance_type', 'advance')
            ->firstOrFail();

        $fingerprint = '[payment-reminder:settlement_cost:'.$advanceCost->id.':advance]';
        Task::create([
            'title' => 'Termin zaliczki',
            'description' => "Kwota.\n\n{$fingerprint}",
            'due_date' => $dueDate,
            'status_id' => Task::getDefaultStatusId(),
            'priority' => 'urgent',
            'source' => 'system',
            'author_id' => $user->id,
            'assignee_id' => $user->id,
            'taskable_type' => Event::class,
            'taskable_id' => $event->id,
            'order' => 1,
        ]);

        $this->assertSame(1, Task::query()
            ->where('description', 'like', '%'.$fingerprint.'%')
            ->count());

        $helper->saveAdvances($point->fresh(['currency', 'templatePoint', 'event']), [
            'settlement_planned_amount' => 800,
            'settlement_planned_currency_id' => $pln->id,
            'settlement_planned_convert_to_pln' => true,
            'settlement_planned_rate' => 1,
            'settlement_planned_amount_pln' => 800,
            'settlement_paid_by' => 'office',
            'advance_entries' => [[
                'id' => $advanceCost->id,
                'due_date' => $dueDate,
                'advance_amount' => 250,
                'currency_id' => $pln->id,
                'paid_at' => now(),
                'notes' => null,
            ]],
        ]);

        $completedStatusId = TaskStatus::query()->where('name', 'Zakończone')->value('id');

        $this->assertSame(
            1,
            Task::query()
                ->where('description', 'like', '%'.$fingerprint.'%')
                ->where('status_id', $completedStatusId)
                ->count(),
        );
    }

    public function test_plan_save_does_not_delete_existing_advances(): void
    {
        $user = $this->createOfficeUser();
        $this->actingAs($user);
        $pln = Currency::factory()->pln()->create();

        $event = Event::factory()->create(['assigned_to' => $user->id]);
        $point = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'currency_id' => $pln->id,
            'convert_to_pln' => true,
            'include_in_program' => true,
            'active' => true,
        ]);

        $settlement = EventSettlement::findOrCreateActiveForEvent($event);
        $settlement->upsertCostFromProgramPoint($point->fresh(['currency', 'templatePoint']));

        $helper = $this->makeSettlementFinanceHelper($event);

        $helper->saveAdvances($point->fresh(['currency', 'templatePoint', 'event']), [
            'settlement_planned_amount' => 1000,
            'settlement_planned_currency_id' => $pln->id,
            'settlement_planned_convert_to_pln' => true,
            'settlement_planned_rate' => 1,
            'settlement_planned_amount_pln' => 1000,
            'settlement_paid_by' => 'office',
            'advance_entries' => [[
                'advance_amount' => 400,
                'currency_id' => $pln->id,
                'due_date' => now()->addDays(5),
            ]],
        ]);

        $helper->savePlan($point->fresh(['currency', 'templatePoint', 'event']), [
            'settlement_planned_amount' => 1200,
            'settlement_planned_currency_id' => $pln->id,
            'settlement_planned_convert_to_pln' => true,
            'settlement_planned_rate' => 1,
            'settlement_planned_amount_pln' => 1200,
            'settlement_paid_by' => 'office',
        ]);

        $this->assertSame(
            1,
            EventSettlementCost::query()
                ->where('source_type', 'program_point_payment')
                ->where('source_id', $point->id)
                ->where('advance_type', 'advance')
                ->count(),
        );

        $advance = EventSettlementCost::query()
            ->where('source_type', 'program_point_payment')
            ->where('source_id', $point->id)
            ->where('advance_type', 'advance')
            ->first();

        $this->assertSame(400.0, (float) $advance->advance_amount);
    }

    /**
     * @return object{
     *     savePlan: callable,
     *     saveAdvances: callable,
     * }
     */
    private function makeSettlementFinanceHelper(Event $event): object
    {
        return new class($event)
        {
            use \App\Filament\Resources\EventResource\Concerns\ManagesProgramPointSettlementFinance;

            public function __construct(private Event $event) {}

            public function getOwnerRecord(): Event
            {
                return $this->event;
            }

            protected function settlementOwnerEvent(): Event
            {
                return $this->event;
            }

            protected function afterSettlePointFinanceSaved(): void {}

            public function savePlan(EventProgramPoint $point, array $data): array
            {
                return $this->persistSettlePointFinance($point, $data);
            }

            public function saveAdvances(EventProgramPoint $point, array $data): array
            {
                return $this->persistSettlePointFinance($point, $data, syncAdvanceEntries: true);
            }
        };
    }

    private function createEurCurrency(float $rate = 4.35): Currency
    {
        return Currency::create([
            'name' => 'Euro',
            'symbol' => 'EUR',
            'code' => 'EUR',
            'exchange_rate' => $rate,
        ]);
    }
}
