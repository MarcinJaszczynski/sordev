<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\ContractPaymentSchedule;
use App\Models\Currency;
use App\Models\Event;
use App\Models\EventProgramPoint;
use App\Models\EventSettlement;
use App\Models\EventSettlementCost;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use App\Models\VendorInvoice;
use App\Services\CalendarEventAggregator;
use App\Services\EventPaymentReminderSyncService;
use App\Services\EventPaymentScheduleService;
use App\Support\EventProgramPointPaymentDueColumn;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventPaymentScheduleTest extends TestCase
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

    public function test_advance_in_eur_with_convert_shows_pln_equivalent_in_schedule_and_task(): void
    {
        $user = $this->createOfficeUser();
        $eur = Currency::create([
            'name' => 'Euro',
            'symbol' => 'EUR',
            'code' => 'EUR',
            'exchange_rate' => 4.3,
        ]);

        $event = Event::factory()->create([
            'name' => 'Włochy 2026',
            'assigned_to' => $user->id,
        ]);

        $point = EventProgramPoint::create([
            'event_id' => $event->id,
            'day' => 1,
            'order' => 1,
            'name' => 'Hotel Rzym',
            'include_in_program' => true,
            'active' => true,
        ]);

        $settlement = EventSettlement::create([
            'event_id' => $event->id,
            'status' => 'active',
            'created_by' => $user->id,
        ]);

        $dueDate = now()->addDays(8);

        $cost = EventSettlementCost::create([
            'settlement_id' => $settlement->id,
            'source_type' => 'program_point_payment',
            'source_id' => $point->id,
            'name' => 'Hotel Rzym',
            'payment_status' => 'advance_required',
            'advance_due_date' => $dueDate,
            'advance_amount' => 500,
            'planned_amount' => 500,
            'planned_currency_id' => $eur->id,
            'planned_convert_to_pln' => true,
            'planned_rate' => 4.3,
            'planned_amount_pln' => 2150,
            'paid_by' => 'office',
            'advance_type' => 'advance',
        ]);

        $rows = app(EventPaymentScheduleService::class)->collectForProgramPoint($point->fresh(), $event->fresh());

        $this->assertTrue($rows->contains(fn (array $row): bool => $row['kind'] === 'advance'));
        $advance = $rows->first(fn (array $row): bool => $row['kind'] === 'advance');
        $this->assertStringContainsString('EUR', (string) $advance['amount_label']);
        $this->assertStringContainsString('≈', (string) $advance['amount_label']);
        $this->assertStringContainsString('PLN', (string) $advance['amount_label']);

        $html = EventProgramPointPaymentDueColumn::html($point, $rows);
        $this->assertStringContainsString('Zaliczka', $html);
        $this->assertStringContainsString('Hotel Rzym', $html);
        $this->assertStringContainsString($dueDate->format('d.m.Y'), $html);

        $task = Task::query()
            ->where('description', 'like', '%[payment-reminder:settlement_cost:'.$cost->id.':advance]%')
            ->first();

        $this->assertNotNull($task);
        $this->assertSame($dueDate->toDateString(), $task->due_date?->toDateString());
        $this->assertStringContainsString('500,00 EUR', (string) $task->description);
        $this->assertStringContainsString('≈', (string) $task->description);
        $this->assertSame($user->id, (int) $task->assignee_id);
    }

    public function test_advance_in_eur_without_convert_omits_pln_equivalent(): void
    {
        $user = $this->createOfficeUser();
        $eur = Currency::create([
            'name' => 'Euro',
            'symbol' => 'EUR',
            'code' => 'EUR',
            'exchange_rate' => 4.3,
        ]);

        $event = Event::factory()->create(['assigned_to' => $user->id]);

        $point = EventProgramPoint::create([
            'event_id' => $event->id,
            'day' => 1,
            'order' => 1,
            'name' => 'Muzeum',
            'include_in_program' => true,
            'active' => true,
        ]);

        $settlement = EventSettlement::create([
            'event_id' => $event->id,
            'status' => 'active',
            'created_by' => $user->id,
        ]);

        EventSettlementCost::create([
            'settlement_id' => $settlement->id,
            'source_type' => 'program_point_payment',
            'source_id' => $point->id,
            'name' => 'Muzeum',
            'payment_status' => 'advance_required',
            'advance_due_date' => now()->addDays(5),
            'advance_amount' => 120,
            'planned_amount' => 120,
            'planned_currency_id' => $eur->id,
            'planned_convert_to_pln' => false,
            'planned_rate' => 4.3,
            'paid_by' => 'office',
            'advance_type' => 'advance',
        ]);

        $rows = app(EventPaymentScheduleService::class)->collectForProgramPoint($point->fresh(), $event->fresh());
        $advance = $rows->first(fn (array $row): bool => $row['kind'] === 'advance');

        $this->assertNotNull($advance);
        $this->assertStringContainsString('EUR', (string) $advance['amount_label']);
        $this->assertStringNotContainsString('≈', (string) $advance['amount_label']);
    }

    public function test_program_point_column_shows_vendor_invoice_with_pdf(): void
    {
        if (! Schema::hasTable('vendor_invoices')) {
            $this->markTestSkipped('vendor_invoices table not available.');
        }

        $user = $this->createOfficeUser();
        $event = Event::factory()->create(['assigned_to' => $user->id]);

        $point = EventProgramPoint::create([
            'event_id' => $event->id,
            'day' => 2,
            'order' => 1,
            'name' => 'Restauracja',
            'include_in_program' => true,
            'active' => true,
        ]);

        VendorInvoice::create([
            'event_id' => $event->id,
            'event_program_point_id' => $point->id,
            'invoice_number' => 'FV/2026/77',
            'gross_amount' => 640,
            'paid_amount' => 0,
            'currency' => 'PLN',
            'due_date' => now()->addDays(6),
            'payment_status' => 'due',
            'approval_status' => 'approved',
            'matching_status' => 'manual',
            'pdf_path' => 'vendor-invoices/test.pdf',
        ]);

        $rows = app(EventPaymentScheduleService::class)->collectForProgramPoint($point->fresh(), $event->fresh());
        $html = EventProgramPointPaymentDueColumn::html($point, $rows);

        $this->assertStringContainsString('FV/2026/77', $html);
        $this->assertStringContainsString('PDF', $html);
    }

    public function test_program_point_column_shows_vendor_invoice_without_pdf_label(): void
    {
        if (! Schema::hasTable('vendor_invoices')) {
            $this->markTestSkipped('vendor_invoices table not available.');
        }

        $event = Event::factory()->create();

        $point = EventProgramPoint::create([
            'event_id' => $event->id,
            'day' => 3,
            'order' => 1,
            'name' => 'Bilet',
            'include_in_program' => true,
            'active' => true,
        ]);

        VendorInvoice::create([
            'event_id' => $event->id,
            'event_program_point_id' => $point->id,
            'invoice_number' => 'FV/2026/78',
            'gross_amount' => 200,
            'paid_amount' => 0,
            'currency' => 'PLN',
            'due_date' => now()->addDays(9),
            'payment_status' => 'due',
            'approval_status' => 'approved',
            'matching_status' => 'manual',
            'pdf_path' => null,
        ]);

        $rows = app(EventPaymentScheduleService::class)->collectForProgramPoint($point->fresh(), $event->fresh());
        $html = EventProgramPointPaymentDueColumn::html($point, $rows);

        $this->assertStringContainsString('FV/2026/78', $html);
        $this->assertStringContainsString('brak pliku', $html);
    }

    public function test_paid_status_retires_active_payment_reminder_task(): void
    {
        $user = $this->createOfficeUser();
        $event = Event::factory()->create(['assigned_to' => $user->id]);
        $settlement = EventSettlement::create([
            'event_id' => $event->id,
            'status' => 'active',
            'created_by' => $user->id,
        ]);

        $cost = EventSettlementCost::create([
            'settlement_id' => $settlement->id,
            'name' => 'Hotel',
            'source_type' => 'manual',
            'payment_status' => 'advance_required',
            'advance_due_date' => now()->addDays(3),
            'planned_amount_pln' => 900,
            'paid_by' => 'office',
            'advance_type' => 'advance',
            'advance_amount' => 900,
        ]);

        $this->assertSame(1, Task::query()->where('description', 'like', '%[payment-reminder:settlement_cost:'.$cost->id.':advance]%')->count());

        $cost->update([
            'payment_status' => 'paid',
            'paid_at' => now(),
        ]);

        $completedStatusId = TaskStatus::query()->where('name', 'Zakończone')->value('id');

        $this->assertSame(
            1,
            Task::query()
                ->where('description', 'like', '%[payment-reminder:settlement_cost:'.$cost->id.':advance]%')
                ->where('status_id', $completedStatusId)
                ->count(),
        );

        $this->assertSame(
            0,
            Task::query()
                ->where('description', 'like', '%[payment-reminder:settlement_cost:'.$cost->id.':advance]%')
                ->where('status_id', '!=', $completedStatusId)
                ->count(),
        );
    }

    public function test_calendar_payments_include_contract_schedule_without_vendor_invoice_duplicate(): void
    {
        if (! Schema::hasTable('contract_payment_schedules')) {
            $this->markTestSkipped('contract_payment_schedules table not available.');
        }

        $user = $this->createOfficeUser();
        $event = Event::factory()->create(['code' => 'CAL-01']);
        $dueDate = now()->addDays(12);

        $contract = Contract::create([
            'event_id' => $event->id,
            'contract_type' => Contract::TYPE_GROUP,
            'title' => 'Umowa kalendarzowa',
            'contract_date' => now()->toDateString(),
            'total_price' => 800,
            'currency' => 'PLN',
            'status' => 'sent',
            'payment_status' => 'pending',
            'created_by' => $user->id,
        ]);

        $schedule = ContractPaymentSchedule::create([
            'contract_id' => $contract->id,
            'sort_order' => 1,
            'label' => 'Rata końcowa',
            'amount' => 800,
            'due_date' => $dueDate,
        ]);

        $items = app(CalendarEventAggregator::class)->events([
            'from' => now()->subDay()->toDateString(),
            'to' => now()->addMonth()->toDateString(),
            'types' => ['payments'],
        ]);

        $this->assertTrue($items->contains(fn (array $row): bool => $row['id'] === 'contract-'.$schedule->id));
        $this->assertTrue($items->contains(fn (array $row): bool => str_contains((string) ($row['title'] ?? ''), 'Rata końcowa')));
        $this->assertFalse($items->contains(fn (array $row): bool => str_starts_with((string) ($row['id'] ?? ''), 'vendor-')));
    }

    public function test_reminder_sync_service_upserts_without_duplicates(): void
    {
        $user = $this->createOfficeUser();
        $event = Event::factory()->create(['assigned_to' => $user->id, 'name' => 'Test impreza']);
        $settlement = EventSettlement::create([
            'event_id' => $event->id,
            'status' => 'active',
            'created_by' => $user->id,
        ]);

        $cost = EventSettlementCost::create([
            'settlement_id' => $settlement->id,
            'name' => 'Przewodnik',
            'source_type' => 'manual',
            'payment_status' => 'advance_required',
            'advance_due_date' => now()->addDays(4),
            'planned_amount_pln' => 300,
            'paid_by' => 'office',
            'advance_type' => 'advance',
            'advance_amount' => 300,
        ]);

        app(EventPaymentReminderSyncService::class)->syncSettlementCost($cost);
        app(EventPaymentReminderSyncService::class)->syncSettlementCost($cost->fresh());

        $this->assertSame(1, Task::query()->where('description', 'like', '%[payment-reminder:settlement_cost:'.$cost->id.':advance]%')->count());
    }
}
