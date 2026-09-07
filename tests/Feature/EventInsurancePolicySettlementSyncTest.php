<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventDayInsurance;
use App\Models\EventInsurancePolicy;
use App\Models\EventSettlement;
use App\Models\EventSettlementCost;
use App\Models\EventSettlementDocument;
use App\Models\EventTemplate;
use App\Models\Insurance;
use App\Models\User;
use App\Services\EventFinanceOverviewService;
use App\Services\EventInsurancePolicySettlementSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EventInsurancePolicySettlementSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_saving_policy_file_mirrors_to_settlement_and_finance_costs(): void
    {
        Storage::fake('public');

        [$event, $day] = $this->makeEventWithDayInsurance();

        $source = 'event-insurance/polisa-test.pdf';
        Storage::disk('public')->put($source, '%PDF-1.4 fake policy');

        $event->updateInsuranceFromFormData([
            'insurance_policy_number' => 'POL/2026/1',
            'insurance_status' => 'completed',
            'insurance_payment_status' => 'paid',
            'insurance_amount' => 150,
            'insurance_paid_at' => now()->toDateTimeString(),
            'insurance_document_path' => $source,
            'insurance_terms' => null,
        ]);

        $event->refresh();
        $this->assertSame($source, $event->insurance_document_path);

        $policy = $event->insurancePolicies()->orderBy('id')->firstOrFail();
        $day->update(['event_insurance_policy_id' => $policy->id]);
        app(EventInsurancePolicySettlementSync::class)->syncPolicy($policy->fresh());

        $settlement = EventSettlement::findOrCreateActiveForEvent($event);
        $plan = EventSettlementCost::query()
            ->where('settlement_id', $settlement->id)
            ->where('source_type', 'insurance_day')
            ->where('source_id', $day->id)
            ->first();

        $this->assertNotNull($plan);

        $document = app(EventInsurancePolicySettlementSync::class)->findSyncedDocument($settlement->fresh());
        $this->assertInstanceOf(EventSettlementDocument::class, $document);
        $this->assertSame(EventInsurancePolicySettlementSync::DOCUMENT_TYPE, $document->document_type);
        $this->assertSame('POL/2026/1', $document->document_number);
        $this->assertContains((int) $plan->id, collect($document->linked_cost_ids)->map(fn ($id) => (int) $id)->all());
        $this->assertStringContainsString(EventInsurancePolicySettlementSync::SOURCE_MARKER, (string) $document->notes);

        $mirrored = collect($document->files ?? [])->first();
        $this->assertIsString($mirrored);
        $this->assertStringStartsWith('event-settlement-documents/polisa-imprezy-', $mirrored);
        Storage::disk('public')->assertExists($mirrored);
        Storage::disk('public')->assertExists($source);

        $overview = app(EventFinanceOverviewService::class)->forEvent($event->fresh(), hideZero: false);
        $insuranceRow = collect($overview['rows'] ?? [])
            ->first(fn (array $row): bool => (int) ($row['cost_id'] ?? 0) === (int) $plan->id);

        if ($insuranceRow === null) {
            $insuranceRow = collect($overview['groups'] ?? [])
                ->flatMap(fn (array $group) => $group['rows'] ?? [])
                ->first(fn (array $row): bool => (int) ($row['cost_id'] ?? 0) === (int) $plan->id);
        }

        $this->assertNotNull($insuranceRow, 'Brak wiersza ubezpieczenia w overview Finansów');
        $this->assertTrue((bool) ($insuranceRow['has_uploaded_file'] ?? false));
        $this->assertGreaterThan(0, (int) ($insuranceRow['files_count'] ?? $insuranceRow['documents_count'] ?? 0));
    }

    public function test_clearing_policy_file_removes_mirrored_settlement_document_only(): void
    {
        Storage::fake('public');

        [$event] = $this->makeEventWithDayInsurance();

        $source = 'event-insurance/do-usuniecia.pdf';
        Storage::disk('public')->put($source, 'policy-bytes');

        $event->updateInsuranceFromFormData([
            'insurance_policy_number' => 'X-1',
            'insurance_status' => 'in_progress',
            'insurance_payment_status' => 'pending',
            'insurance_amount' => null,
            'insurance_paid_at' => null,
            'insurance_document_path' => $source,
            'insurance_terms' => null,
        ]);

        $settlement = EventSettlement::findOrCreateActiveForEvent($event->fresh());
        $document = app(EventInsurancePolicySettlementSync::class)->findSyncedDocument($settlement);
        $this->assertNotNull($document);
        $mirrored = collect($document->files ?? [])->first();
        $this->assertIsString($mirrored);

        $event->updateInsuranceFromFormData([
            'insurance_policy_number' => 'X-1',
            'insurance_status' => 'in_progress',
            'insurance_payment_status' => 'pending',
            'insurance_amount' => null,
            'insurance_paid_at' => null,
            'insurance_document_path' => null,
            'insurance_terms' => null,
        ]);

        $this->assertNull(
            app(EventInsurancePolicySettlementSync::class)->findSyncedDocument($settlement->fresh())
        );
        $this->assertDatabaseMissing('event_settlement_documents', ['id' => $document->id]);
        Storage::disk('public')->assertMissing($mirrored);
        // Oryginał z Operacji zostaje (FileUpload/Filament czyści osobno).
        Storage::disk('public')->assertExists($source);
    }

    public function test_policy_without_day_linkage_does_not_auto_attach_all_costs(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $template = EventTemplate::factory()->create();
        $event = Event::create([
            'event_template_id' => $template->id,
            'name' => 'Polisa przed kosztem',
            'client_name' => 'Szkola',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'participant_count' => 10,
            'total_cost' => 1000,
            'status' => 'confirmed',
            'created_by' => $user->id,
        ]);

        $source = 'event-insurance/early.pdf';
        Storage::disk('public')->put($source, 'early-policy');

        $event->updateInsuranceFromFormData([
            'insurance_policy_number' => 'EARLY-1',
            'insurance_status' => 'in_progress',
            'insurance_payment_status' => 'pending',
            'insurance_amount' => 80,
            'insurance_paid_at' => null,
            'insurance_document_path' => $source,
            'insurance_terms' => null,
        ]);

        $settlement = EventSettlement::findOrCreateActiveForEvent($event->fresh());
        $document = app(EventInsurancePolicySettlementSync::class)->findSyncedDocument($settlement);
        $this->assertNotNull($document);
        $this->assertSame([], collect($document->linked_cost_ids ?? [])->all());

        $insurance = Insurance::create([
            'name' => 'NNW późne',
            'price_per_person' => 4,
            'active' => true,
            'insurance_enabled' => true,
        ]);
        $day = EventDayInsurance::create([
            'event_id' => $event->id,
            'day' => 1,
            'insurance_id' => $insurance->id,
        ]);

        $event->refreshActiveSettlementCosts();

        $plan = EventSettlementCost::query()
            ->where('settlement_id', $settlement->id)
            ->where('source_type', 'insurance_day')
            ->where('source_id', $day->id)
            ->firstOrFail();

        // Bez jawnego policy_id pozycja nie dostaje dokumentu polisy.
        $document = app(EventInsurancePolicySettlementSync::class)->findSyncedDocument($settlement->fresh());
        $this->assertNotNull($document);
        $this->assertNotContains((int) $plan->id, collect($document->linked_cost_ids)->map(fn ($id) => (int) $id)->all());

        $policy = $event->insurancePolicies()->orderBy('id')->firstOrFail();
        $day->update(['event_insurance_policy_id' => $policy->id]);
        app(EventInsurancePolicySettlementSync::class)->syncPolicy($policy->fresh());

        $document = app(EventInsurancePolicySettlementSync::class)->findSyncedDocument($settlement->fresh());
        $this->assertContains((int) $plan->id, collect($document->linked_cost_ids)->map(fn ($id) => (int) $id)->all());
    }

    public function test_manual_invoice_document_is_not_removed_when_clearing_policy(): void
    {
        Storage::fake('public');

        [$event] = $this->makeEventWithDayInsurance();
        $settlement = EventSettlement::findOrCreateActiveForEvent($event);
        $settlement->importFromEvent();
        $plan = EventSettlementCost::query()
            ->where('settlement_id', $settlement->id)
            ->where('source_type', 'insurance_day')
            ->firstOrFail();

        $manualPath = 'event-settlement-documents/fv-reczna.pdf';
        Storage::disk('public')->put($manualPath, 'invoice');
        $manual = $settlement->documents()->create([
            'document_type' => 'invoice',
            'document_number' => 'FV/9',
            'linked_cost_ids' => [(int) $plan->id],
            'files' => [$manualPath],
            'notes' => 'Ręczna faktura',
            'approval_status' => 'pending',
        ]);

        $source = 'event-insurance/polisa.pdf';
        Storage::disk('public')->put($source, 'policy');
        $event->updateInsuranceFromFormData([
            'insurance_policy_number' => 'P-1',
            'insurance_status' => 'completed',
            'insurance_payment_status' => 'paid',
            'insurance_amount' => 10,
            'insurance_paid_at' => now()->toDateTimeString(),
            'insurance_document_path' => $source,
            'insurance_terms' => null,
        ]);

        $event->updateInsuranceFromFormData([
            'insurance_policy_number' => 'P-1',
            'insurance_status' => 'completed',
            'insurance_payment_status' => 'paid',
            'insurance_amount' => 10,
            'insurance_paid_at' => now()->toDateTimeString(),
            'insurance_document_path' => null,
            'insurance_terms' => null,
        ]);

        $this->assertDatabaseHas('event_settlement_documents', ['id' => $manual->id, 'document_number' => 'FV/9']);
        Storage::disk('public')->assertExists($manualPath);
    }

    public function test_deleting_synced_policy_document_from_finance_clears_source(): void
    {
        Storage::fake('public');

        [$event, $day] = $this->makeEventWithDayInsurance();
        $source = 'event-insurance/polisa-do-usuniecia.pdf';
        Storage::disk('public')->put($source, 'policy-bytes');

        $policy = EventInsurancePolicy::query()->create([
            'event_id' => $event->id,
            'policy_number' => 'DEL-1',
            'document_path' => $source,
            'status' => 'in_progress',
            'payment_status' => 'pending',
        ]);
        $day->update(['event_insurance_policy_id' => $policy->id]);

        app(EventInsurancePolicySettlementSync::class)->syncPolicy($policy->fresh());

        $settlement = EventSettlement::findOrCreateActiveForEvent($event->fresh());
        $plan = EventSettlementCost::query()
            ->where('settlement_id', $settlement->id)
            ->where('source_type', 'insurance_day')
            ->where('source_id', $day->id)
            ->firstOrFail();

        $document = app(EventInsurancePolicySettlementSync::class)
            ->findSyncedDocumentForPolicy($settlement->fresh(), (int) $policy->id);
        $this->assertNotNull($document);
        $mirrored = collect($document->files ?? [])->first();
        $this->assertIsString($mirrored);

        // Zasiej cache overview — bez forget „Usuń” wyglądałoby jakby nic się nie stało.
        app(EventFinanceOverviewService::class)->forEvent($event->fresh(), hideZero: false);

        app(\App\Actions\Finance\AttachSettlementCostDocumentAction::class)
            ->delete($document, $plan);

        $this->assertDatabaseMissing('event_settlement_documents', ['id' => $document->id]);
        Storage::disk('public')->assertMissing($mirrored);
        Storage::disk('public')->assertMissing($source);
        $this->assertNull($policy->fresh()->document_path);

        $overview = app(EventFinanceOverviewService::class)->forEvent($event->fresh(), hideZero: false);
        $row = collect($overview['rows'] ?? [])
            ->first(fn (array $r): bool => (int) ($r['cost_id'] ?? 0) === (int) $plan->id);
        if ($row === null) {
            $row = collect($overview['groups'] ?? [])
                ->flatMap(fn (array $g) => $g['rows'] ?? [])
                ->first(fn (array $r): bool => (int) ($r['cost_id'] ?? 0) === (int) $plan->id);
        }
        $this->assertNotNull($row);
        $this->assertSame(0, (int) ($row['files_count'] ?? $row['documents_count'] ?? 0));
        $this->assertEmpty($row['documents'] ?? []);
    }

    public function test_creating_kl_policy_does_not_attach_unrelated_day_products(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $template = EventTemplate::factory()->create();
        $event = Event::create([
            'event_template_id' => $template->id,
            'name' => 'Polisa KL izolacja',
            'client_name' => 'Szkola',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'participant_count' => 20,
            'total_cost' => 2000,
            'status' => 'confirmed',
            'created_by' => $user->id,
        ]);

        $kl = Insurance::create([
            'name' => 'KL',
            'price_per_person' => 8,
            'active' => true,
            'insurance_enabled' => true,
            'coverage_type' => 'kl',
        ]);
        $tfg = Insurance::create([
            'name' => 'TFG 2',
            'price_per_person' => 3,
            'active' => true,
            'insurance_enabled' => true,
        ]);
        $tfp = Insurance::create([
            'name' => 'TFP 2',
            'price_per_person' => 4,
            'active' => true,
            'insurance_enabled' => true,
        ]);

        foreach ([$kl, $tfg, $tfp] as $product) {
            EventDayInsurance::create([
                'event_id' => $event->id,
                'day' => 1,
                'insurance_id' => $product->id,
            ]);
        }

        EventSettlement::findOrCreateActiveForEvent($event)->importFromEvent();

        $source = 'event-insurance/kl-only.pdf';
        Storage::disk('public')->put($source, 'kl-policy');

        $policy = app(\App\Actions\Events\CreateEventInsurancePolicyAction::class)(
            $event,
            [
                'insurance_policy_number' => 'KL-ONLY',
                'insurance_document_path' => $source,
            ],
            1,
            [$kl->id],
        );

        $linked = EventDayInsurance::query()
            ->where('event_id', $event->id)
            ->where('event_insurance_policy_id', $policy->id)
            ->pluck('insurance_id')
            ->map(fn ($id): int => (int) $id)
            ->sort()
            ->values()
            ->all();

        $this->assertSame([(int) $kl->id], $linked);

        $settlement = EventSettlement::findOrCreateActiveForEvent($event->fresh());
        $document = app(EventInsurancePolicySettlementSync::class)
            ->findSyncedDocumentForPolicy($settlement, (int) $policy->id);
        $this->assertNotNull($document);

        $klDayId = (int) EventDayInsurance::query()
            ->where('event_id', $event->id)
            ->where('insurance_id', $kl->id)
            ->value('id');
        $tfgDayId = (int) EventDayInsurance::query()
            ->where('event_id', $event->id)
            ->where('insurance_id', $tfg->id)
            ->value('id');
        $tfpDayId = (int) EventDayInsurance::query()
            ->where('event_id', $event->id)
            ->where('insurance_id', $tfp->id)
            ->value('id');

        $klCostId = (int) EventSettlementCost::query()
            ->where('settlement_id', $settlement->id)
            ->where('source_type', 'insurance_day')
            ->where('source_id', $klDayId)
            ->value('id');
        $tfgCostId = (int) EventSettlementCost::query()
            ->where('settlement_id', $settlement->id)
            ->where('source_type', 'insurance_day')
            ->where('source_id', $tfgDayId)
            ->value('id');
        $tfpCostId = (int) EventSettlementCost::query()
            ->where('settlement_id', $settlement->id)
            ->where('source_type', 'insurance_day')
            ->where('source_id', $tfpDayId)
            ->value('id');

        $linkedCostIds = collect($document->linked_cost_ids)->map(fn ($id) => (int) $id)->all();
        $this->assertContains($klCostId, $linkedCostIds);
        $this->assertNotContains($tfgCostId, $linkedCostIds);
        $this->assertNotContains($tfpCostId, $linkedCostIds);
    }

    /**
     * @return array{0: Event, 1: EventDayInsurance}
     */
    private function makeEventWithDayInsurance(): array
    {
        $user = User::factory()->create();
        $template = EventTemplate::factory()->create();
        $event = Event::create([
            'event_template_id' => $template->id,
            'name' => 'Sync polisy',
            'client_name' => 'Szkola',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'participant_count' => 20,
            'total_cost' => 2000,
            'status' => 'confirmed',
            'created_by' => $user->id,
        ]);

        $insurance = Insurance::create([
            'name' => 'NNW sync',
            'price_per_person' => 5,
            'active' => true,
            'insurance_enabled' => true,
        ]);

        $day = EventDayInsurance::create([
            'event_id' => $event->id,
            'day' => 1,
            'insurance_id' => $insurance->id,
        ]);

        EventSettlement::findOrCreateActiveForEvent($event)->importFromEvent();

        return [$event->fresh(), $day];
    }
}
