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
        $this->assertStringContainsString('Zaliczka do zapłaty', $html);
        $this->assertStringContainsString($dueDate->format('d.m.Y'), $html);
        $this->assertStringContainsString('EUR', $html);

        $task = Task::query()
            ->where('description', 'like', '%[payment-reminder:settlement_cost:'.$cost->id.':advance]%')
            ->first();

        // Auto-przypomnienia płatności wyłączone (SystemTaskPolicy).
        $this->assertNull($task);
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

        // Tworzenie auto-reminderów wyłączone — seedujemy legacy task, żeby sprawdzić retire przy paid.
        $fingerprint = '[payment-reminder:settlement_cost:'.$cost->id.':advance]';
        $openStatusId = Task::getDefaultStatusId();
        Task::create([
            'title' => 'Termin zaliczki: Hotel',
            'description' => "Kwota.\n\n{$fingerprint}",
            'due_date' => now()->addDays(3),
            'status_id' => $openStatusId,
            'priority' => 'urgent',
            'source' => 'system',
            'author_id' => $user->id,
            'assignee_id' => $user->id,
            'taskable_type' => Event::class,
            'taskable_id' => $event->id,
            'order' => 1,
        ]);

        $this->assertSame(1, Task::query()->where('description', 'like', '%'.$fingerprint.'%')->count());

        $cost->update([
            'payment_status' => 'paid',
            'paid_at' => now(),
        ]);

        $completedStatusId = TaskStatus::query()->where('name', 'Zakończone')->value('id');

        $this->assertSame(
            1,
            Task::query()
                ->where('description', 'like', '%'.$fingerprint.'%')
                ->where('status_id', $completedStatusId)
                ->count(),
        );

        $this->assertSame(
            0,
            Task::query()
                ->where('description', 'like', '%'.$fingerprint.'%')
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

        // Auto-przypomnienia płatności wyłączone (SystemTaskPolicy) — nie zaśmiecamy skrzynki.
        $this->assertSame(0, Task::query()->where('description', 'like', '%[payment-reminder:settlement_cost:'.$cost->id.':advance]%')->count());
    }

    public function test_contract_installment_reminders_are_aggregated_per_event_and_label(): void
    {
        if (! Schema::hasTable('contract_payment_schedules')) {
            $this->markTestSkipped('contract_payment_schedules table not available.');
        }

        $user = $this->createOfficeUser();
        $event = Event::factory()->create([
            'name' => 'Paryż agregacja',
            'assigned_to' => $user->id,
        ]);

        $labels = [
            ['sort_order' => 0, 'label' => 'Zaliczka (10%)', 'amount' => 300.50, 'due' => '2026-08-11'],
            ['sort_order' => 1, 'label' => 'Dopłata (90%)', 'amount' => 2704.50, 'due' => '2026-08-27'],
            ['sort_order' => 2, 'label' => 'Waluta u pilota', 'amount' => 0, 'due' => '2026-09-10'],
        ];

        for ($i = 1; $i <= 3; $i++) {
            $contract = Contract::create([
                'event_id' => $event->id,
                'contract_type' => Contract::TYPE_INDIVIDUAL,
                'title' => 'Umowa uczestnika '.$i,
                'contract_date' => now()->toDateString(),
                'total_price' => 3005,
                'amount_due' => 3005,
                'amount_paid' => 0,
                'currency' => 'PLN',
                'status' => 'sent',
                'payment_status' => 'pending',
                'created_by' => $user->id,
            ]);

            foreach ($labels as $row) {
                ContractPaymentSchedule::create([
                    'contract_id' => $contract->id,
                    'sort_order' => $row['sort_order'],
                    'label' => $row['label'],
                    'amount' => $row['amount'],
                    'due_date' => $row['due'],
                ]);
            }
        }

        app(EventPaymentReminderSyncService::class)->syncEventContractInstallmentReminders($event->fresh());

        $completedStatusId = TaskStatus::query()->where('name', 'Zakończone')->value('id');

        $this->assertSame(
            0,
            Task::query()
                ->where('description', 'like', '%[payment-reminder:contract_schedule:%')
                ->where('status_id', '!=', $completedStatusId)
                ->count(),
        );

        $grouped = Task::query()
            ->where('description', 'like', '%[payment-reminder:event_contract_installment:'.$event->id.':%')
            ->where('status_id', '!=', $completedStatusId)
            ->get();

        $this->assertCount(0, $grouped);
    }

    public function test_aggregated_contract_reminder_retires_when_all_paid(): void
    {
        if (! Schema::hasTable('contract_payment_schedules')) {
            $this->markTestSkipped('contract_payment_schedules table not available.');
        }

        $user = $this->createOfficeUser();
        $event = Event::factory()->create([
            'name' => 'Paryż paid',
            'assigned_to' => $user->id,
        ]);

        $contracts = collect();

        for ($i = 1; $i <= 2; $i++) {
            $contract = Contract::create([
                'event_id' => $event->id,
                'contract_type' => Contract::TYPE_INDIVIDUAL,
                'title' => 'Umowa '.$i,
                'contract_date' => now()->toDateString(),
                'total_price' => 1000,
                'amount_due' => 1000,
                'amount_paid' => 0,
                'currency' => 'PLN',
                'status' => 'sent',
                'payment_status' => 'pending',
                'created_by' => $user->id,
            ]);
            $contracts->push($contract);

            ContractPaymentSchedule::create([
                'contract_id' => $contract->id,
                'sort_order' => 0,
                'label' => 'Zaliczka (10%)',
                'amount' => 1000,
                'due_date' => now()->addDays(5),
            ]);
        }

        app(EventPaymentReminderSyncService::class)->syncEventContractInstallmentReminders($event->fresh());

        $activeBefore = Task::query()
            ->where('description', 'like', '%[payment-reminder:event_contract_installment:'.$event->id.':%')
            ->whereHas('status', fn ($q) => $q->where('name', '!=', 'Zakończone'))
            ->count();
        $this->assertSame(0, $activeBefore);

        foreach ($contracts as $contract) {
            $contract->update([
                'payment_status' => 'paid',
                'amount_paid' => 1000,
            ]);
        }

        app(EventPaymentReminderSyncService::class)->syncEventContractInstallmentReminders($event->fresh());

        $this->assertSame(
            0,
            Task::query()
                ->where('description', 'like', '%[payment-reminder:event_contract_installment:'.$event->id.':%')
                ->whereHas('status', fn ($q) => $q->where('name', '!=', 'Zakończone'))
                ->count(),
        );
    }

    public function test_retire_legacy_payment_reminders_command_closes_per_schedule_tasks(): void
    {
        if (! Schema::hasTable('contract_payment_schedules')) {
            $this->markTestSkipped('contract_payment_schedules table not available.');
        }

        $user = $this->createOfficeUser();
        $event = Event::factory()->create(['assigned_to' => $user->id, 'name' => 'Legacy cleanup']);
        $statusId = Task::getDefaultStatusId();

        $legacy = Task::create([
            'title' => 'Termin raty kontraktu: Zaliczka (legacy)',
            'description' => "Kwota: 100 PLN.\n\n[payment-reminder:contract_schedule:99999:schedule]",
            'due_date' => now()->addDays(2),
            'status_id' => $statusId,
            'priority' => 'urgent',
            'source' => 'system',
            'author_id' => $user->id,
            'assignee_id' => $user->id,
            'taskable_type' => Event::class,
            'taskable_id' => $event->id,
            'order' => 1,
        ]);

        $this->artisan('tasks:retire-legacy-payment-reminders', ['--no-resync' => true])
            ->assertSuccessful();

        $legacy->refresh();
        $completedStatusId = TaskStatus::query()->where('name', 'Zakończone')->value('id');
        $this->assertSame($completedStatusId, (int) $legacy->status_id);
    }

    public function test_reservation_deposit_on_program_point_shows_readable_advance_line(): void
    {
        $user = $this->createOfficeUser();
        $event = Event::factory()->create(['assigned_to' => $user->id]);
        $point = EventProgramPoint::create([
            'event_id' => $event->id,
            'day' => 1,
            'order' => 1,
            'name' => 'Hotel',
            'planned_price' => 4000,
            'include_in_program' => true,
            'active' => true,
        ]);

        $settlement = EventSettlement::create([
            'event_id' => $event->id,
            'status' => 'active',
            'created_by' => $user->id,
        ]);

        $dueDate = now()->addDays(5);
        EventSettlementCost::create([
            'settlement_id' => $settlement->id,
            'source_type' => 'program_point',
            'source_id' => $point->id,
            'name' => 'Hotel',
            'payment_status' => 'reserved',
            'advance_type' => 'deposit',
            'advance_due_date' => $dueDate,
            'advance_amount' => 1200,
            'planned_amount' => 4000,
            'planned_amount_pln' => 4000,
            'paid_by' => 'office',
        ]);

        $rows = app(EventPaymentScheduleService::class)->collectForProgramPoint($point->fresh(), $event->fresh());
        $html = EventProgramPointPaymentDueColumn::html($point, $rows);

        $this->assertStringContainsString('Zaliczka do zapłaty', $html);
        $this->assertStringContainsString($dueDate->format('d.m.Y'), $html);
        $this->assertStringContainsString('1', $html);
        $this->assertStringNotContainsString(' · Plan · ', $html);
        $this->assertFalse((bool) ($rows->first()['is_overdue'] ?? true));
    }

    public function test_paid_advance_shows_paid_phrase_remaining_and_today_is_not_overdue(): void
    {
        $user = $this->createOfficeUser();
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
            'source_type' => 'program_point',
            'source_id' => $point->id,
            'name' => 'Muzeum',
            'payment_status' => 'advance_paid',
            'advance_type' => 'deposit',
            'advance_due_date' => now(),
            'paid_at' => now(),
            'advance_amount' => 500,
            'actual_amount' => 500,
            'actual_amount_pln' => 500,
            'planned_amount' => 2000,
            'planned_amount_pln' => 2000,
            'paid_by' => 'office',
        ]);

        $rows = app(EventPaymentScheduleService::class)->collectForProgramPoint($point->fresh(), $event->fresh());
        $row = $rows->first();
        $html = EventProgramPointPaymentDueColumn::html($point, $rows);

        $this->assertNotNull($row);
        $this->assertSame('advance_paid', $row['kind']);
        $this->assertFalse((bool) $row['is_overdue']);
        $this->assertStringContainsString('Zaliczka zapłacona', $html);
        $this->assertStringContainsString('pozostało', $html);
        $this->assertStringNotContainsString('#dc2626', $html);
    }

    public function test_advance_paid_flag_without_booked_money_is_not_shown_as_paid(): void
    {
        $user = $this->createOfficeUser();
        $event = Event::factory()->create(['assigned_to' => $user->id]);
        $point = EventProgramPoint::create([
            'event_id' => $event->id,
            'day' => 1,
            'order' => 1,
            'name' => 'Luwr bilety dorośli',
            'include_in_program' => false,
            'active' => true,
        ]);

        $settlement = EventSettlement::create([
            'event_id' => $event->id,
            'status' => 'active',
            'created_by' => $user->id,
        ]);

        EventSettlementCost::create([
            'settlement_id' => $settlement->id,
            'source_type' => 'program_point',
            'source_id' => $point->id,
            'name' => 'Luwr bilety dorośli',
            'payment_status' => 'advance_paid',
            'advance_type' => 'full',
            'advance_due_date' => '2026-08-06',
            'paid_at' => '2026-08-06',
            'advance_amount' => null,
            'actual_amount' => null,
            'planned_amount' => 66,
            'planned_amount_pln' => null,
            'paid_by' => 'pilot',
        ]);

        $rows = app(EventPaymentScheduleService::class)->collectForProgramPoint($point->fresh(), $event->fresh());
        $html = EventProgramPointPaymentDueColumn::html($point, $rows);

        $this->assertTrue($rows->isNotEmpty());
        $this->assertNotSame('advance_paid', $rows->first()['kind'] ?? null);
        $this->assertStringNotContainsString('Zaliczka zapłacona', $html);
        $this->assertStringContainsString('do zapłaty', mb_strtolower($html));
    }

    public function test_calendar_includes_unpaid_reservation_deposit_due_date(): void
    {
        $user = $this->createOfficeUser();
        $this->actingAs($user);
        $dueDate = now()->addDays(5)->toDateString();
        $event = Event::factory()->create([
            'name' => 'Wycieczka zaliczka',
            'start_date' => now()->addMonth()->toDateString(),
        ]);
        $point = EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'name' => 'Bilety Bałtów',
        ]);

        $reservation = \App\Models\Reservation::query()->create([
            'event_id' => $event->id,
            'program_point_id' => $point->id,
            'status' => 'confirmed',
            'deposit_due_at' => $dueDate,
            'participant_count' => 20,
            'reserved_at' => now(),
            'confirmed_at' => now(),
        ]);

        $items = app(CalendarEventAggregator::class)->events([
            'from' => now()->toDateString(),
            'to' => now()->addMonth()->toDateString(),
            'types' => ['payments'],
        ]);

        $this->assertTrue($items->contains(fn (array $row): bool => $row['id'] === 'res-deposit-'.$reservation->id));
        $this->assertTrue($items->contains(fn (array $row): bool => str_contains((string) ($row['title'] ?? ''), 'Zaliczka rezerwacji')));
        $this->assertTrue($items->contains(fn (array $row): bool => ($row['start'] ?? null) === $dueDate));
    }
}
