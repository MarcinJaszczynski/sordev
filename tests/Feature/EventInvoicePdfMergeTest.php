<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventDocument;
use App\Models\EventSettlement;
use App\Models\EventSettlementDocument;
use App\Models\User;
use App\Models\VendorInvoice;
use App\Services\EventInvoicePdfMergeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Fpdi;
use Tests\TestCase;

class EventInvoicePdfMergeTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_document_can_be_marked_as_invoice(): void
    {
        if (! Schema::hasTable('event_documents') || ! Schema::hasColumn('event_documents', 'is_invoice')) {
            $this->markTestSkipped('Kolumna is_invoice nie istnieje w event_documents.');
        }

        $event = Event::factory()->create();

        $document = EventDocument::create([
            'event_id' => $event->id,
            'name' => 'FV hotel',
            'file_path' => 'event-documents/test.pdf',
            'is_invoice' => true,
        ]);

        $this->assertTrue($document->fresh()->is_invoice);
    }

    public function test_merge_service_collects_ksef_vendor_invoices_settlement_invoices_and_event_documents(): void
    {
        if (! Schema::hasTable('vendor_invoices') || ! Schema::hasTable('event_settlement_documents')) {
            $this->markTestSkipped('Brak tabel faktur w tym środowisku testowym.');
        }

        Storage::fake('public');
        $event = Event::factory()->create(['code' => 'EVT-001']);

        $vendorPdf = $this->createTestPdf(Storage::disk('public')->path('vendor-invoices/ksef-1.pdf'), 'KSeF');
        Storage::disk('public')->put('vendor-invoices/ksef-1.pdf', file_get_contents($vendorPdf));

        VendorInvoice::create([
            'event_id' => $event->id,
            'invoice_number' => 'FV/1/2026',
            'issue_date' => now()->subDay()->toDateString(),
            'gross_amount' => 100,
            'pdf_path' => 'vendor-invoices/ksef-1.pdf',
        ]);

        $settlement = EventSettlement::create([
            'event_id' => $event->id,
            'status' => 'draft',
        ]);

        $settlementPdf = $this->createTestPdf(Storage::disk('public')->path('event-settlement-documents/fv-2.pdf'), 'Settlement');
        Storage::disk('public')->put('event-settlement-documents/fv-2.pdf', file_get_contents($settlementPdf));

        EventSettlementDocument::create([
            'settlement_id' => $settlement->id,
            'document_type' => 'invoice',
            'document_number' => 'FV/2/2026',
            'issue_date' => now()->toDateString(),
            'files' => ['event-settlement-documents/fv-2.pdf'],
        ]);

        if (Schema::hasColumn('event_documents', 'is_invoice')) {
            $eventDocPdf = $this->createTestPdf(Storage::disk('public')->path('event-documents/fv-3.pdf'), 'Event doc');
            Storage::disk('public')->put('event-documents/fv-3.pdf', file_get_contents($eventDocPdf));

            EventDocument::create([
                'event_id' => $event->id,
                'name' => 'Skan faktury',
                'file_path' => 'event-documents/fv-3.pdf',
                'is_invoice' => true,
            ]);
        }

        $entries = app(EventInvoicePdfMergeService::class)->collectInvoicePdfFiles($event->fresh());

        $expectedCount = Schema::hasColumn('event_documents', 'is_invoice') ? 3 : 2;
        $this->assertCount($expectedCount, $entries);

        $result = app(EventInvoicePdfMergeService::class)->mergeToTempFile($event->fresh());
        $this->assertFileExists($result['path']);
        $this->assertStringEndsWith('.pdf', $result['filename']);
        $this->assertGreaterThan(0, filesize($result['path']));
        $this->assertStringStartsWith('%PDF', (string) file_get_contents($result['path']));

        @unlink($result['path']);
    }

    public function test_invoices_pdf_route_downloads_merged_file(): void
    {
        if (! Schema::hasTable('vendor_invoices')) {
            $this->markTestSkipped('Brak tabeli vendor_invoices.');
        }

        Storage::fake('public');
        $user = User::factory()->create();
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user->assignRole('admin');
        $this->actingAs($user);

        $event = Event::factory()->create();
        $pdfPath = $this->createTestPdf(Storage::disk('public')->path('vendor-invoices/route-test.pdf'), 'Route');
        Storage::disk('public')->put('vendor-invoices/route-test.pdf', file_get_contents($pdfPath));

        VendorInvoice::create([
            'event_id' => $event->id,
            'invoice_number' => 'FV/ROUTE/1',
            'issue_date' => now()->toDateString(),
            'gross_amount' => 50,
            'pdf_path' => 'vendor-invoices/route-test.pdf',
        ]);

        $response = $this->get(route('admin.events.invoices.pdf', ['event' => $event->id]));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
    }

    private function createTestPdf(string $absolutePath, string $label): string
    {
        $directory = dirname($absolutePath);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $pdf = new Fpdi;
        $pdf->AddPage();
        $pdf->SetFont('Helvetica', '', 12);
        $pdf->Cell(0, 10, $label);
        $pdf->Output('F', $absolutePath);

        return $absolutePath;
    }
}
