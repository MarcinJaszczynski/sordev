<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\ContractPaymentSchedule;
use App\Models\Event;
use App\Models\EventPaymentInstallmentTemplate;
use App\Models\User;
use App\Services\ContractPaymentScheduleService;
use App\Services\EventPaymentInstallmentTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EventPaymentInstallmentTemplateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('event_payment_installment_templates')) {
            $this->markTestSkipped('Brak tabeli event_payment_installment_templates.');
        }
    }

    public function test_sync_template_and_apply_materializes_percent_and_foreign_pilot_row(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create([
            'start_date' => '2026-09-10',
            'participant_count' => 10,
        ]);

        $contract = Contract::create([
            'event_id' => $event->id,
            'contract_type' => Contract::TYPE_GROUP,
            'title' => 'Umowa',
            'contract_date' => now()->toDateString(),
            'participant_count' => 10,
            'unit_price' => 1000,
            'total_price' => 10000,
            'amount_due' => 10000,
            'payment_scheme' => Contract::PAYMENT_SCHEME_LUMP_SUM,
            'currency' => 'PLN',
            'status' => 'sent',
            'payment_status' => 'pending',
            'created_by' => $user->id,
        ]);

        $service = app(EventPaymentInstallmentTemplateService::class);
        $service->syncTemplate($event, [
            [
                'label' => 'Zaliczka',
                'share_type' => EventPaymentInstallmentTemplate::SHARE_PERCENT,
                'percent' => 10,
                'due_offset_days' => -30,
                'paid_by' => EventPaymentInstallmentTemplate::PAID_BY_OFFICE,
            ],
            [
                'label' => 'Reszta',
                'share_type' => EventPaymentInstallmentTemplate::SHARE_PERCENT,
                'percent' => 90,
                'due_offset_days' => -14,
                'paid_by' => EventPaymentInstallmentTemplate::PAID_BY_OFFICE,
            ],
            [
                'label' => 'Dopłata EUR',
                'share_type' => EventPaymentInstallmentTemplate::SHARE_FOREIGN,
                'amount_foreign' => 50,
                'currency_code' => 'eur',
                'due_offset_days' => 0,
                'paid_by' => EventPaymentInstallmentTemplate::PAID_BY_PILOT,
            ],
        ]);

        $this->assertSame(3, $event->paymentInstallmentTemplates()->count());

        $updated = $service->applyToEventContracts($event);
        $this->assertSame(1, $updated);

        $contract->refresh()->load('paymentSchedules');
        $this->assertSame(Contract::PAYMENT_SCHEME_INSTALLMENTS, $contract->payment_scheme);
        $this->assertCount(3, $contract->paymentSchedules);

        $zaliczka = $contract->paymentSchedules->firstWhere('label', 'Zaliczka');
        $this->assertNotNull($zaliczka);
        $this->assertEqualsWithDelta(1000.0, (float) $zaliczka->amount, 0.01);
        $this->assertSame('2026-08-11', optional($zaliczka->due_date)?->toDateString());

        $foreign = $contract->paymentSchedules->firstWhere('label', 'Dopłata EUR');
        $this->assertNotNull($foreign);
        $this->assertEqualsWithDelta(0.0, (float) $foreign->amount, 0.01);
        $this->assertEqualsWithDelta(50.0, (float) $foreign->amount_foreign, 0.01);
        $this->assertSame('EUR', $foreign->currency_code);
        $this->assertSame('pilot', $foreign->paid_by);
        $this->assertSame('2026-09-10', optional($foreign->due_date)?->toDateString());
    }

    public function test_upsert_preserves_paid_amount_on_reapply(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create([
            'start_date' => '2026-10-01',
            'participant_count' => 5,
        ]);

        $contract = Contract::create([
            'event_id' => $event->id,
            'contract_type' => Contract::TYPE_INDIVIDUAL,
            'title' => 'Indywid',
            'contract_date' => now()->toDateString(),
            'participant_count' => 1,
            'unit_price' => 2000,
            'total_price' => 2000,
            'amount_due' => 2000,
            'payment_scheme' => Contract::PAYMENT_SCHEME_INSTALLMENTS,
            'currency' => 'PLN',
            'status' => 'sent',
            'payment_status' => 'pending',
            'created_by' => $user->id,
        ]);

        app(ContractPaymentScheduleService::class)->syncForContract($contract, [
            ['label' => 'R1', 'amount' => 500, 'due_date' => '2026-08-01'],
            ['label' => 'R2', 'amount' => 1500, 'due_date' => '2026-09-01'],
        ], Contract::PAYMENT_SCHEME_INSTALLMENTS);

        $first = $contract->paymentSchedules()->orderBy('sort_order')->first();
        $firstId = $first->id;
        $first->update(['paid_amount' => 500, 'paid_at' => now()]);

        $service = app(EventPaymentInstallmentTemplateService::class);
        $service->syncTemplate($event, [
            [
                'label' => 'R1',
                'share_type' => EventPaymentInstallmentTemplate::SHARE_PERCENT,
                'percent' => 25,
                'due_offset_days' => -60,
            ],
            [
                'label' => 'R2',
                'share_type' => EventPaymentInstallmentTemplate::SHARE_PERCENT,
                'percent' => 75,
                'due_offset_days' => -30,
            ],
        ]);
        $service->applyToEventContracts($event, $contract);

        $first->refresh();
        $this->assertSame($firstId, $first->id);
        $this->assertEqualsWithDelta(500.0, (float) $first->paid_amount, 0.01);
        $this->assertEqualsWithDelta(500.0, (float) $first->amount, 0.01);
    }

    public function test_capture_from_contract_rebuilds_percent_template(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create([
            'start_date' => '2026-12-01',
            'participant_count' => 1,
        ]);

        $contract = Contract::create([
            'event_id' => $event->id,
            'contract_type' => Contract::TYPE_INDIVIDUAL,
            'title' => 'Indywid',
            'contract_date' => now()->toDateString(),
            'unit_price' => 1000,
            'total_price' => 1000,
            'amount_due' => 1000,
            'payment_scheme' => Contract::PAYMENT_SCHEME_INSTALLMENTS,
            'currency' => 'PLN',
            'status' => 'sent',
            'payment_status' => 'pending',
            'created_by' => $user->id,
        ]);

        ContractPaymentSchedule::query()->create([
            'contract_id' => $contract->id,
            'sort_order' => 0,
            'label' => 'Zaliczka',
            'amount' => 200,
            'due_date' => '2026-11-01',
        ]);
        ContractPaymentSchedule::query()->create([
            'contract_id' => $contract->id,
            'sort_order' => 1,
            'label' => 'Reszta',
            'amount' => 800,
            'due_date' => '2026-11-17',
        ]);

        app(EventPaymentInstallmentTemplateService::class)->captureFromContract($event, $contract->fresh());

        $rows = $event->paymentInstallmentTemplates()->orderBy('sort_order')->get();
        $this->assertCount(2, $rows);
        $this->assertEqualsWithDelta(20.0, (float) $rows[0]->percent, 0.01);
        $this->assertSame(-30, (int) $rows[0]->due_offset_days);
        $this->assertEqualsWithDelta(80.0, (float) $rows[1]->percent, 0.01);
        $this->assertSame(-14, (int) $rows[1]->due_offset_days);
    }

    public function test_default_template_rows_include_percent_split(): void
    {
        $event = Event::factory()->create(['start_date' => '2026-09-01']);
        $rows = app(EventPaymentInstallmentTemplateService::class)->defaultTemplateRows($event);

        $this->assertGreaterThanOrEqual(2, count($rows));
        $this->assertSame('Zaliczka', $rows[0]['label']);
        $this->assertEqualsWithDelta(10.0, (float) $rows[0]['percent'], 0.01);
        $this->assertSame(-30, (int) $rows[0]['due_offset_days']);
        $this->assertSame('Dopłata', $rows[1]['label']);
        $this->assertEqualsWithDelta(90.0, (float) $rows[1]['percent'], 0.01);
    }

    public function test_sync_rejects_rows_without_values_without_wiping_existing(): void
    {
        $event = Event::factory()->create(['start_date' => '2026-09-01']);
        $service = app(EventPaymentInstallmentTemplateService::class);
        $service->syncTemplate($event, [
            [
                'label' => 'Zaliczka',
                'share_type' => EventPaymentInstallmentTemplate::SHARE_PERCENT,
                'percent' => 10,
                'due_offset_days' => -30,
            ],
        ]);

        $this->expectException(\InvalidArgumentException::class);

        try {
            $service->syncTemplate($event, [
                [
                    'label' => 'Puste',
                    'share_type' => EventPaymentInstallmentTemplate::SHARE_PERCENT,
                    'percent' => null,
                    'due_offset_days' => -10,
                ],
            ]);
        } finally {
            $this->assertSame(1, $event->paymentInstallmentTemplates()->count());
        }
    }
}
