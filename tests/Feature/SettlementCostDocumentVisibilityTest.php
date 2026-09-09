<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Finance\AttachSettlementCostDocumentAction;
use App\Models\Event;
use App\Models\EventSettlement;
use App\Models\EventSettlementDocument;
use App\Models\User;
use App\Services\EventFinanceOverviewService;
use App\Services\ProgramPointSettlementDocumentSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SettlementCostDocumentVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin']);
        $this->admin = User::factory()->create(['status' => 'active']);
        $this->admin->assignRole('admin');
        $this->actingAs($this->admin);
    }

    public function test_attached_document_is_visible_in_finance_overview_without_manual_cache_forget(): void
    {
        if (! Schema::hasTable('event_settlement_documents')) {
            $this->markTestSkipped('Brak tabeli dokumentów settlement.');
        }

        Storage::fake('public');

        $event = Event::factory()->create(['created_by' => $this->admin->id]);
        $settlement = EventSettlement::findOrCreateActiveForEvent($event);
        $plan = $settlement->costs()->create([
            'source_type' => 'manual',
            'name' => 'Przewodnik',
            'planned_amount_pln' => 500,
            'planned_amount' => 500,
            'paid_by' => 'office',
            'payment_status' => 'planned',
            'order' => 1,
        ]);

        // Zagrzej cache overview (stan bez dokumentu).
        $before = app(EventFinanceOverviewService::class)->forEvent($event->fresh(), hideZero: false);
        $beforeRow = collect($before['rows'])->firstWhere('cost_id', $plan->id);
        $this->assertNotNull($beforeRow);
        $this->assertFalse((bool) ($beforeRow['has_uploaded_file'] ?? false));

        app(AttachSettlementCostDocumentAction::class)(
            $plan,
            [UploadedFile::fake()->image('fv.jpg', 120, 120)],
            'invoice',
            'FV/99/2026',
        );

        // Bez ręcznego forget — klucz cache musi uwzględniać dokumenty.
        $after = app(EventFinanceOverviewService::class)->forEvent($event->fresh(), hideZero: false);
        $afterRow = collect($after['rows'])->firstWhere('cost_id', $plan->id);

        $this->assertNotNull($afterRow);
        $this->assertTrue((bool) ($afterRow['has_uploaded_file'] ?? false));
        $this->assertGreaterThan(0, (int) ($afterRow['files_count'] ?? 0));
        $this->assertNotEmpty($afterRow['documents'] ?? []);
    }

    public function test_forget_overview_cache_bumps_active_settlement_not_newer_closed(): void
    {
        if (! Schema::hasTable('event_settlements')) {
            $this->markTestSkipped('Brak tabeli settlement.');
        }

        $event = Event::factory()->create(['created_by' => $this->admin->id]);
        $active = EventSettlement::create([
            'event_id' => $event->id,
            'status' => 'active',
            'created_by' => $this->admin->id,
        ]);
        $closed = EventSettlement::create([
            'event_id' => $event->id,
            'status' => 'closed',
            'created_by' => $this->admin->id,
        ]);

        $activeBefore = $active->fresh()->updated_at?->timestamp;
        $closedBefore = $closed->fresh()->updated_at?->timestamp;

        EventFinanceOverviewService::forgetOverviewCacheForEvent((int) $event->id);

        $this->assertNotSame($activeBefore, $active->fresh()->updated_at?->timestamp);
        // Zamknięte nie musi być bumpowane — ważne że aktywne zostało.
        $this->assertTrue(
            $active->fresh()->updated_at?->timestamp > ($closed->fresh()->updated_at?->timestamp ?? 0)
            || $closed->fresh()->updated_at?->timestamp === $closedBefore
        );
    }

    public function test_load_document_data_finds_document_when_linked_cost_ids_are_strings(): void
    {
        if (! Schema::hasTable('event_settlement_documents')) {
            $this->markTestSkipped('Brak tabeli dokumentów settlement.');
        }

        $event = Event::factory()->create(['created_by' => $this->admin->id]);
        $settlement = EventSettlement::findOrCreateActiveForEvent($event);
        $cost = $settlement->costs()->create([
            'source_type' => 'program_point_payment',
            'source_id' => 1,
            'name' => 'Wpłata',
            'planned_amount' => 0,
            'payment_status' => 'paid',
            'paid_by' => 'office',
        ]);

        EventSettlementDocument::create([
            'settlement_id' => $settlement->id,
            'document_type' => 'invoice',
            'document_number' => 'STR/1',
            'linked_cost_ids' => [(string) $cost->id],
            'files' => ['event-settlement-documents/string-id.pdf'],
            'approval_status' => 'pending',
            'created_by' => $this->admin->id,
        ]);

        $loaded = app(ProgramPointSettlementDocumentSync::class)->loadDocumentDataForCost($cost->fresh());

        $this->assertNotNull($loaded['document_id']);
        $this->assertSame('invoice', $loaded['document_type']);
        $this->assertSame('STR/1', $loaded['document_number']);
        $this->assertSame(['event-settlement-documents/string-id.pdf'], $loaded['document_files']);
    }
}
