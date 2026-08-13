<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Finance\ApplyPaymentScheduleTemplateAction;
use App\Models\Contract;
use App\Models\Event;
use App\Models\PaymentScheduleTemplate;
use App\Models\PaymentScheduleTemplateInstallment;
use App\Models\User;
use App\Services\ContractParticipantPaymentLinkService;
use App\Services\PaymentScheduleTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentScheduleTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_global_template_materializes_percent_and_due_window(): void
    {
        $event = Event::factory()->create(['start_date' => now()->addDays(60)]);
        $template = PaymentScheduleTemplate::query()->create(['name' => 'Standard 10/90']);

        PaymentScheduleTemplateInstallment::query()->create([
            'payment_schedule_template_id' => $template->id,
            'sort_order' => 0,
            'label' => 'Zaliczka',
            'share_type' => PaymentScheduleTemplateInstallment::SHARE_PERCENT,
            'percent' => 10,
            'due_offset_from_days' => -30,
            'due_offset_to_days' => -30,
            'paid_by' => PaymentScheduleTemplateInstallment::PAID_BY_OFFICE,
        ]);
        PaymentScheduleTemplateInstallment::query()->create([
            'payment_schedule_template_id' => $template->id,
            'sort_order' => 1,
            'label' => 'Dopłata',
            'share_type' => PaymentScheduleTemplateInstallment::SHARE_PERCENT,
            'percent' => 90,
            'due_offset_from_days' => -14,
            'due_offset_to_days' => -14,
            'paid_by' => PaymentScheduleTemplateInstallment::PAID_BY_OFFICE,
        ]);

        $rows = app(PaymentScheduleTemplateService::class)->materializeForEvent($template, $event, 1000.0);

        $this->assertCount(2, $rows);
        $this->assertSame(100.0, $rows[0]['amount']);
        $this->assertSame(900.0, $rows[1]['amount']);
        $this->assertSame(
            \Illuminate\Support\Carbon::parse($event->start_date)->addDays(-30)->toDateString(),
            $rows[0]['due_to'],
        );
    }

    public function test_apply_to_contract_preserves_paid_amount(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create(['start_date' => now()->addDays(45)]);
        $contract = Contract::create([
            'event_id' => $event->id,
            'contract_type' => Contract::TYPE_GROUP,
            'title' => 'Umowa',
            'contract_date' => now()->toDateString(),
            'amount_due' => 2000,
            'participant_count' => 2,
            'unit_price' => 1000,
            'payment_scheme' => Contract::PAYMENT_SCHEME_INSTALLMENTS,
            'currency' => 'PLN',
            'status' => 'sent',
            'payment_status' => 'pending',
            'created_by' => $user->id,
        ]);

        $contract->paymentSchedules()->create([
            'sort_order' => 0,
            'label' => 'Stara rata',
            'amount' => 2000,
            'paid_amount' => 500,
            'due_date' => now()->addDays(10),
        ]);

        $template = PaymentScheduleTemplate::query()->create(['name' => 'Nowy']);
        PaymentScheduleTemplateInstallment::query()->create([
            'payment_schedule_template_id' => $template->id,
            'sort_order' => 0,
            'share_type' => PaymentScheduleTemplateInstallment::SHARE_PERCENT,
            'percent' => 100,
            'due_offset_to_days' => -7,
            'paid_by' => PaymentScheduleTemplateInstallment::PAID_BY_OFFICE,
        ]);

        app(ApplyPaymentScheduleTemplateAction::class)($template, $contract->fresh(), $event);

        $contract->refresh();
        $schedule = $contract->paymentSchedules()->first();
        $this->assertSame(2000.0, (float) $schedule->amount);
        $this->assertSame(500.0, (float) $schedule->paid_amount);
        $this->assertSame($template->id, $contract->payment_schedule_template_id);
    }

    public function test_contract_participant_payment_pivot_links_many_payments(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create();
        $contract = Contract::create([
            'event_id' => $event->id,
            'contract_type' => Contract::TYPE_GROUP,
            'title' => 'Grupa indywidualna',
            'contract_date' => now()->toDateString(),
            'participant_payment_id' => null,
            'currency' => 'PLN',
            'status' => 'sent',
            'payment_status' => 'pending',
            'created_by' => $user->id,
        ]);

        $settlement = \App\Models\EventSettlement::findOrCreateActiveForEvent($event);
        $paymentA = $settlement->participantPayments()->create([
            'participant_name' => 'Anna',
            'booking_reference' => 'REF-A',
            'due_amount_pln' => 1000,
            'paid_amount_pln' => 0,
            'payment_status' => 'pending',
        ]);
        $paymentB = $settlement->participantPayments()->create([
            'participant_name' => 'Bartek',
            'booking_reference' => 'REF-B',
            'due_amount_pln' => 1000,
            'paid_amount_pln' => 0,
            'payment_status' => 'pending',
        ]);

        app(ContractParticipantPaymentLinkService::class)->syncForContract(
            $contract,
            [(int) $paymentA->id, (int) $paymentB->id],
        );

        $contract->refresh();
        $this->assertNull($contract->participant_payment_id);
        $this->assertCount(2, $contract->linkedParticipantPaymentIds());
        $this->assertTrue($paymentA->hasLinkedAgreementsBesides(0));
    }

    public function test_apply_library_template_to_event_copies_and_updates_contracts(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create([
            'start_date' => '2026-10-01',
            'participant_count' => 10,
        ]);
        $contract = Contract::create([
            'event_id' => $event->id,
            'contract_type' => Contract::TYPE_GROUP,
            'title' => 'Umowa',
            'contract_date' => now()->toDateString(),
            'participant_count' => 10,
            'unit_price' => 1000,
            'amount_due' => 10000,
            'payment_scheme' => Contract::PAYMENT_SCHEME_LUMP_SUM,
            'currency' => 'PLN',
            'status' => 'sent',
            'payment_status' => 'pending',
            'created_by' => $user->id,
        ]);

        $template = PaymentScheduleTemplate::query()->create(['name' => 'Lib 10/90']);
        PaymentScheduleTemplateInstallment::query()->create([
            'payment_schedule_template_id' => $template->id,
            'sort_order' => 0,
            'label' => 'Zaliczka',
            'share_type' => PaymentScheduleTemplateInstallment::SHARE_PERCENT,
            'percent' => 10,
            'due_offset_to_days' => -30,
            'paid_by' => PaymentScheduleTemplateInstallment::PAID_BY_OFFICE,
        ]);
        PaymentScheduleTemplateInstallment::query()->create([
            'payment_schedule_template_id' => $template->id,
            'sort_order' => 1,
            'label' => 'Dopłata',
            'share_type' => PaymentScheduleTemplateInstallment::SHARE_PERCENT,
            'percent' => 90,
            'due_offset_to_days' => -14,
            'paid_by' => PaymentScheduleTemplateInstallment::PAID_BY_OFFICE,
        ]);

        $count = app(\App\Actions\Finance\ApplyPaymentScheduleTemplateToEventAction::class)(
            $template,
            $event,
            true,
        );

        $this->assertSame(1, $count);
        $this->assertSame(2, $event->paymentInstallmentTemplates()->count());
        $contract->refresh();
        $this->assertSame(Contract::PAYMENT_SCHEME_INSTALLMENTS, $contract->payment_scheme);
        $this->assertCount(2, $contract->paymentSchedules);
        $this->assertSame(1000.0, (float) $contract->paymentSchedules->sortBy('sort_order')->first()->amount);
    }

    public function test_capture_contract_schedule_to_library(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create(['start_date' => '2026-11-01']);
        $contract = Contract::create([
            'event_id' => $event->id,
            'contract_type' => Contract::TYPE_GROUP,
            'title' => 'Umowa',
            'contract_date' => now()->toDateString(),
            'participant_count' => 1,
            'unit_price' => 1000,
            'amount_due' => 1000,
            'payment_scheme' => Contract::PAYMENT_SCHEME_INSTALLMENTS,
            'currency' => 'PLN',
            'status' => 'sent',
            'payment_status' => 'pending',
            'created_by' => $user->id,
        ]);

        $contract->paymentSchedules()->create([
            'sort_order' => 0,
            'label' => 'Zaliczka',
            'amount' => 200,
            'due_date' => '2026-10-01',
            'due_from' => '2026-10-01',
            'due_to' => '2026-10-01',
            'paid_by' => 'office',
        ]);
        $contract->paymentSchedules()->create([
            'sort_order' => 1,
            'label' => 'Dopłata',
            'amount' => 800,
            'due_date' => '2026-10-18',
            'due_from' => '2026-10-18',
            'due_to' => '2026-10-18',
            'paid_by' => 'office',
        ]);

        $template = app(\App\Actions\Finance\CaptureContractScheduleAsLibraryTemplateAction::class)(
            $contract->fresh(),
            'Z umowy testowej',
            [PaymentScheduleTemplate::APPLIES_GROUP],
        );

        $this->assertSame('Z umowy testowej', $template->name);
        $this->assertCount(2, $template->installments);
        $this->assertSame(20.0, (float) $template->installments->first()->percent);
        $this->assertSame(80.0, (float) $template->installments->skip(1)->first()->percent);
    }

    public function test_seeder_creates_presets(): void
    {
        $this->seed(\Database\Seeders\PaymentScheduleTemplateSeeder::class);

        $this->assertTrue(
            PaymentScheduleTemplate::query()->where('name', 'Standard szkolny 10/90')->exists()
        );
        $withEur = PaymentScheduleTemplate::query()
            ->where('name', 'Standard szkolny 10/90 + EUR u pilota')
            ->with('installments')
            ->first();
        $this->assertNotNull($withEur);
        $this->assertCount(3, $withEur->installments);
        $this->assertTrue(
            $withEur->installments->contains(
                fn ($row) => $row->share_type === PaymentScheduleTemplateInstallment::SHARE_FOREIGN
            )
        );
    }
}
