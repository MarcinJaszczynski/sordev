<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventDayInsurance;
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

    public function test_policy_uploaded_before_day_costs_links_after_refresh(): void
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

        $document = app(EventInsurancePolicySettlementSync::class)->findSyncedDocument($settlement->fresh());
        $this->assertNotNull($document);
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
