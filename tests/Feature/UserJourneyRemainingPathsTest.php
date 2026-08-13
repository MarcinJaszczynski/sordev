<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Crm\CreateInquiryFromWebAction;
use App\Actions\Events\MarkAttendanceAction;
use App\Actions\Events\RecalculateEventTotalsAction;
use App\Actions\EventTemplates\CloneEventTemplateAction;
use App\Actions\Finance\AttachSettlementCostDocumentAction;
use App\Actions\Finance\CompletePendingPaymentAction;
use App\Actions\Finance\CreateVatMarginInvoiceDraftAction;
use App\Actions\Finance\GenerateInstallmentPaymentLinkAction;
use App\Actions\Finance\RecalculateSettlementTotalsAction;
use App\Data\CompletePendingPaymentData;
use App\Data\CreateInquiryFromWebData;
use App\Data\CreateVatMarginInvoiceDraftData;
use App\Data\GenerateInstallmentPaymentLinkData;
use App\Data\MarkAttendanceData;
use App\Data\RecalculateEventTotalsData;
use App\Data\RecalculateSettlementTotalsData;
use App\Events\SettlementTotalsRecalculated;
use App\Models\Contact;
use App\Models\Contract;
use App\Models\ContractPaymentSchedule;
use App\Models\Event;
use App\Models\EventAttendance;
use App\Models\EventParticipant;
use App\Models\EventSettlement;
use App\Models\EventSettlementDocument;
use App\Models\EventSettlementParticipantPayment;
use App\Models\EventTemplate;
use App\Models\EventTemplateProgramPoint;
use App\Models\Place;
use App\Models\SalesInvoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event as EventFacade;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Brakujące ścieżki Actionów: WWW lead, obecność, dokumenty, linki rat, FV, pending, recalc, clone.
 */
class UserJourneyRemainingPathsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\TaskStatusSeeder::class);

        foreach (['admin', 'super_admin', 'biuro'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        $this->admin = User::factory()->create(['status' => 'active']);
        $this->admin->assignRole('admin');
        $this->actingAs($this->admin);
    }

    public function test_create_inquiry_from_web_creates_contact_event_and_task(): void
    {
        $result = app(CreateInquiryFromWebAction::class)(new CreateInquiryFromWebData(
            email: 'lead@example.com',
            telephone: '501600700',
            name: 'Jan Lead',
            message: 'Proszę o ofertę',
            eventName: 'Wycieczka Kraków',
            startPlaceName: 'Warszawa',
        ));

        $this->assertInstanceOf(Contact::class, $result['contact']);
        $this->assertSame('lead@example.com', $result['contact']->email);
        $this->assertSame(Event::STATUS_INQUIRY, $result['event']->status);
        $this->assertStringContainsString('Wycieczka Kraków', $result['event']->name);
        $this->assertNotNull($result['event']->start_date);
        $this->assertNotNull($result['task']);
        $this->assertStringContainsString('Nowe zapytanie:', (string) $result['task']->title);
        $this->assertSame(Event::class, $result['task']->taskable_type);
        $this->assertSame($result['event']->id, (int) $result['task']->taskable_id);
    }

    public function test_mark_attendance_for_participants(): void
    {
        if (! Schema::hasTable('event_attendances') || ! Schema::hasTable('event_participants')) {
            $this->markTestSkipped('Brak tabel obecności/uczestników.');
        }

        $event = Event::factory()->create([
            'created_by' => $this->admin->id,
            'duration_days' => 2,
        ]);

        $present = EventParticipant::query()->create([
            'event_id' => $event->id,
            'first_name' => 'Ala',
            'last_name' => 'Obecna',
            'source' => EventParticipant::SOURCE_MANUAL,
            'status' => EventParticipant::STATUS_ACTIVE,
        ]);
        $absent = EventParticipant::query()->create([
            'event_id' => $event->id,
            'first_name' => 'Bartek',
            'last_name' => 'Nieobecny',
            'source' => EventParticipant::SOURCE_MANUAL,
            'status' => EventParticipant::STATUS_ACTIVE,
        ]);

        app(MarkAttendanceAction::class)(new MarkAttendanceData(
            event: $event,
            day: 1,
            statuses: [
                $present->id => EventAttendance::STATUS_PRESENT,
                $absent->id => EventAttendance::STATUS_ABSENT,
            ],
            markedBy: $this->admin->id,
        ));

        $this->assertDatabaseHas('event_attendances', [
            'event_id' => $event->id,
            'event_participant_id' => $present->id,
            'day' => 1,
            'status' => EventAttendance::STATUS_PRESENT,
        ]);
        $this->assertDatabaseHas('event_attendances', [
            'event_id' => $event->id,
            'event_participant_id' => $absent->id,
            'day' => 1,
            'status' => EventAttendance::STATUS_ABSENT,
        ]);
    }

    public function test_attach_and_delete_settlement_cost_document(): void
    {
        if (! Schema::hasTable('event_settlement_documents')) {
            $this->markTestSkipped('Brak tabeli dokumentów settlement.');
        }

        Storage::fake('public');

        $event = Event::factory()->create(['created_by' => $this->admin->id]);
        $settlement = EventSettlement::findOrCreateActiveForEvent($event);
        $plan = $settlement->costs()->create([
            'source_type' => 'manual',
            'name' => 'Hotel dok',
            'planned_amount_pln' => 1200,
            'planned_amount' => 1200,
            'paid_by' => 'office',
            'payment_status' => 'planned',
            'order' => 1,
        ]);

        $file = UploadedFile::fake()->image('fv.jpg', 200, 200);

        $document = app(AttachSettlementCostDocumentAction::class)(
            $plan,
            [$file],
            'invoice',
            'FV/1/2026',
            'Test attach',
        );

        $this->assertInstanceOf(EventSettlementDocument::class, $document);
        $this->assertSame('invoice', $document->document_type);
        $this->assertSame('FV/1/2026', $document->document_number);
        $this->assertContains($plan->id, collect($document->linked_cost_ids)->map(fn ($id) => (int) $id)->all());
        $this->assertNotEmpty($document->files);

        app(AttachSettlementCostDocumentAction::class)->delete($document, $plan);

        $this->assertDatabaseMissing('event_settlement_documents', ['id' => $document->id]);
    }

    public function test_generate_installment_payment_link_for_contract_schedule(): void
    {
        if (! Schema::hasTable('contract_payment_schedules')) {
            $this->markTestSkipped('Brak tabeli harmonogramu umów.');
        }

        $event = Event::factory()->create(['created_by' => $this->admin->id]);
        $contract = Contract::create([
            'event_id' => $event->id,
            'contract_type' => Contract::TYPE_INDIVIDUAL,
            'payment_scheme' => Contract::PAYMENT_SCHEME_INSTALLMENTS,
            'title' => 'Umowa link',
            'contract_date' => now()->toDateString(),
            'participant_count' => 1,
            'total_price' => 800,
            'amount_paid' => 0,
            'currency' => 'PLN',
            'status' => 'sent',
            'payment_status' => 'pending',
            'customer_name' => 'Klient Link',
            'created_by' => $this->admin->id,
        ]);

        $schedule = ContractPaymentSchedule::create([
            'contract_id' => $contract->id,
            'sort_order' => 1,
            'label' => 'Rata 1',
            'amount' => 400,
            'paid_amount' => 0,
            'due_date' => now()->addDays(14)->toDateString(),
        ]);

        $result = app(GenerateInstallmentPaymentLinkAction::class)(
            new GenerateInstallmentPaymentLinkData(schedule: $schedule, ttlDays: 7)
        );

        $this->assertSame('contract', $result['type']);
        $this->assertSame($schedule->id, $result['schedule_id']);
        $this->assertStringContainsString('/payments/installment/', $result['url']);
        $this->assertNotSame('', $result['expires_at']);
    }

    public function test_complete_pending_payment_action_marks_cost_paid(): void
    {
        if (! Schema::hasTable('event_settlement_costs')) {
            $this->markTestSkipped('Brak tabeli kosztów.');
        }

        $event = Event::factory()->create(['created_by' => $this->admin->id]);
        $settlement = EventSettlement::findOrCreateActiveForEvent($event);
        $cost = $settlement->costs()->create([
            'source_type' => 'manual',
            'name' => 'Pending hotel',
            'payment_status' => 'advance_required',
            'advance_due_date' => now()->addDay(),
            'planned_amount_pln' => 900,
            'planned_amount' => 900,
            'paid_by' => 'office',
            'advance_type' => 'advance',
            'order' => 1,
        ]);

        app(CompletePendingPaymentAction::class)(new CompletePendingPaymentData(
            rowId: 'cost-'.$cost->id,
        ));

        $cost->refresh();
        $this->assertSame('paid', $cost->payment_status);
        $this->assertNotNull($cost->paid_at);
    }

    public function test_create_vat_margin_invoice_draft(): void
    {
        if (! Schema::hasTable('sales_invoices')) {
            $this->markTestSkipped('Brak tabeli sales_invoices.');
        }

        $event = Event::factory()->create([
            'created_by' => $this->admin->id,
            'client_name' => 'Firma Testowa Sp. z o.o.',
            'name' => 'Impreza FV',
        ]);

        $invoice = app(CreateVatMarginInvoiceDraftAction::class)(new CreateVatMarginInvoiceDraftData(
            event: $event,
            type: SalesInvoice::TYPE_FINAL,
            buyerName: 'Firma Testowa Sp. z o.o.',
            buyerNip: '5250000000',
            createdBy: $this->admin->id,
        ));

        $this->assertSame(SalesInvoice::STATUS_DRAFT, $invoice->status);
        $this->assertSame(SalesInvoice::PROCEDURE_VAT_MARGIN, $invoice->procedure);
        $this->assertSame($event->id, $invoice->event_id);
        $this->assertSame('Firma Testowa Sp. z o.o.', $invoice->buyer_name);
        $this->assertCount(1, $invoice->lines);
    }

    public function test_recalculate_event_totals_persists_total_cost(): void
    {
        $place = Place::factory()->starting()->create();
        $event = Event::factory()->create([
            'created_by' => $this->admin->id,
            'start_place_id' => $place->id,
            'participant_count' => 10,
            'total_cost' => 0,
        ]);

        $total = app(RecalculateEventTotalsAction::class)(new RecalculateEventTotalsData(
            event: $event,
            participantCount: 10,
            persist: true,
        ));

        $this->assertIsFloat($total);
        $this->assertEqualsWithDelta($total, (float) $event->fresh()->total_cost, 0.01);
    }

    public function test_clone_event_template_action_copies_program_points(): void
    {
        $place = Place::factory()->starting()->create();
        $template = EventTemplate::factory()->create([
            'name' => 'Szablon clone action',
            'slug' => 'szablon-clone-action',
            'start_place_id' => $place->id,
            'duration_days' => 2,
            'is_active' => true,
        ]);

        $point = EventTemplateProgramPoint::factory()->create(['name' => 'Punkt clone']);
        $template->programPoints()->attach($point->id, [
            'day' => 1,
            'order' => 1,
            'notes' => null,
            'include_in_program' => true,
            'include_in_calculation' => true,
            'active' => true,
        ]);

        $clone = app(CloneEventTemplateAction::class)($template);

        $this->assertNotSame($template->id, $clone->id);
        $this->assertSame('Szablon clone action (Kopia)', $clone->name);
        $this->assertCount(1, $clone->programPoints);
        $this->assertSame('Punkt clone', $clone->programPoints->first()->name);
    }

    public function test_recalculate_settlement_totals_action_updates_aggregates_and_dispatches_event(): void
    {
        if (! Schema::hasTable('event_settlement_costs')
            || ! Schema::hasTable('event_settlement_participant_payments')
        ) {
            $this->markTestSkipped('Brak tabel settlement.');
        }

        EventFacade::fake([SettlementTotalsRecalculated::class]);

        $event = Event::factory()->create(['created_by' => $this->admin->id]);
        $settlement = EventSettlement::findOrCreateActiveForEvent($event);

        $settlement->costs()->create([
            'source_type' => 'manual',
            'name' => 'Hotel plan',
            'planned_amount_pln' => 2000,
            'planned_amount' => 2000,
            'paid_by' => 'office',
            'payment_status' => 'partially_paid',
            'order' => 1,
        ]);

        $settlement->costs()->create([
            'source_type' => 'manual_payment',
            'name' => 'Hotel • zaliczka #1',
            'planned_amount_pln' => 0,
            'actual_amount_pln' => 750,
            'actual_amount' => 750,
            'paid_by' => 'office',
            'payment_status' => 'advance_paid',
            'order' => 2,
        ]);

        EventSettlementParticipantPayment::query()->create([
            'settlement_id' => $settlement->id,
            'participant_name' => 'Uczestnik Test',
            'due_amount_pln' => 1500,
            'paid_amount_pln' => 400,
            'payment_status' => 'partial',
            'attended' => true,
        ]);

        // Celowo zepsute agregaty — Action ma je przeliczyć.
        $settlement->forceFill([
            'planned_cost_pln' => 1,
            'actual_cost_pln' => 1,
            'participant_due_pln' => 1,
            'participant_paid_pln' => 1,
        ])->saveQuietly();

        $updated = app(RecalculateSettlementTotalsAction::class)(new RecalculateSettlementTotalsData(
            settlement: $settlement,
            fullRefresh: false,
        ));

        $this->assertEqualsWithDelta(2000.0, (float) $updated->planned_cost_pln, 0.01);
        $this->assertEqualsWithDelta(750.0, (float) $updated->actual_cost_pln, 0.01);
        $this->assertEqualsWithDelta(1500.0, (float) $updated->participant_due_pln, 0.01);
        $this->assertEqualsWithDelta(400.0, (float) $updated->participant_paid_pln, 0.01);

        EventFacade::assertDispatched(SettlementTotalsRecalculated::class);

        $refreshed = app(RecalculateSettlementTotalsAction::class)(new RecalculateSettlementTotalsData(
            settlement: $updated,
            fullRefresh: true,
        ));

        $this->assertEqualsWithDelta(2000.0, (float) $refreshed->planned_cost_pln, 0.01);
        $this->assertEqualsWithDelta(750.0, (float) $refreshed->actual_cost_pln, 0.01);
        EventFacade::assertDispatchedTimes(SettlementTotalsRecalculated::class, 2);
    }
}
