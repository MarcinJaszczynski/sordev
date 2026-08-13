<?php

namespace Tests\Feature;

use App\Models\Contractor;
use App\Models\Event;
use App\Models\EventAgreement;
use App\Models\EventProgramPoint;
use App\Models\VendorInvoice;
use App\Services\Invoices\BulkPdfSplitter;
use App\Services\Invoices\ContractorResolver;
use App\Services\Invoices\KsefCsvImporter;
use App\Services\Invoices\KsefNumberNormalizer;
use App\Services\Invoices\KsefXmlImporter;
use App\Services\Invoices\VendorInvoiceBatchImportService;
use App\Services\Invoices\VendorInvoiceImportService;
use App\Services\Invoices\VendorInvoiceMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class VendorInvoiceModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('vendor_invoices')) {
            $this->artisan('migrate', ['--path' => 'database/migrations/2026_06_12_100000_create_vendor_invoices_tables.php']);
        }
    }

    private function requireKsefFixture(string $relativePath): string
    {
        $path = base_path('pliki/ksef/'.$relativePath);
        if (! is_file($path)) {
            $this->markTestSkipped('Brak lokalnych fixture KSeF (pliki/ksef/'.$relativePath.').');
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            $this->markTestSkipped('Nie można odczytać fixture KSeF: '.$relativePath);
        }

        return $contents;
    }

    public function test_csv_importer_groups_multiline_invoice_rows(): void
    {
        $csv = $this->requireKsefFixture('CSV.csv');
        $parsed = app(KsefCsvImporter::class)->parse($csv);

        $this->assertGreaterThanOrEqual(17, count($parsed));

        $multiLine = collect($parsed)->first(fn ($row) => ($row['invoice_number'] ?? '') === 'FV/22/06/2026/OL');
        $this->assertNotNull($multiLine);
        $this->assertNotEmpty($multiLine['lines']);
        $this->assertStringContainsString('Ostrów Lednicki', $multiLine['notes'] ?? '');
    }

    public function test_xml_importer_uses_buyer_as_cost_vendor(): void
    {
        $xml = $this->requireKsefFixture('xml.xml');
        $parsed = app(KsefXmlImporter::class)->parse($xml);

        $first = $parsed[0];
        $this->assertSame('7840006416', $first['seller_nip']);
        $this->assertStringContainsString('MUZEUM', strtoupper($first['seller_name']));
        $this->assertSame('7840006416-20260612-405F68C00002-0A', $first['ksef_number']);
        $this->assertNotEmpty($first['lines']);
    }

    public function test_import_is_idempotent_by_ksef_number(): void
    {
        $csv = $this->requireKsefFixture('CSV.csv');
        $service = app(VendorInvoiceImportService::class);

        $first = $service->importFromContent($csv, 'csv', 'CSV.csv');
        $second = $service->importFromContent($csv, 'csv', 'CSV.csv');

        $this->assertGreaterThan(0, $first['imported']);
        $this->assertSame($first['imported'], $second['imported']);
        $totalAfterSecond = VendorInvoice::count();
        $service->importFromContent($csv, 'csv', 'CSV.csv');
        $this->assertSame($totalAfterSecond, VendorInvoice::count());
    }

    public function test_contractor_resolver_normalizes_nip_and_finds_contractor(): void
    {
        $contractor = Contractor::create(['name' => 'Test Vendor', 'nip' => '716-250-87-61', 'status' => 'active']);

        $resolved = app(ContractorResolver::class)->resolveFromInvoice(new VendorInvoice([
            'seller_nip' => '7162508761',
        ]));

        $this->assertNotNull($resolved);
        $this->assertSame($contractor->id, $resolved->id);
    }

    public function test_matcher_matches_event_by_agreement_number_in_line_description(): void
    {
        $event = Event::factory()->create([
            'code' => '26-TEST01',
            'start_date' => '2026-06-10',
            'end_date' => '2026-06-12',
        ]);

        EventAgreement::create([
            'event_id' => $event->id,
            'agreement_number' => '20260222/2059',
            'title' => 'Umowa testowa',
            'status' => 'draft',
        ]);

        $invoice = VendorInvoice::create([
            'invoice_number' => '104/2026',
            'seller_nip' => '9591518347',
            'seller_name' => 'Dominik Kowalski',
            'sale_date' => '2026-06-11',
            'gross_amount' => 700,
            'payment_status' => 'due',
            'approval_status' => 'pending',
            'matching_status' => 'unmatched',
        ]);

        $invoice->lines()->create([
            'line_order' => 0,
            'name' => 'Usługa przewodnicka 20260222/2059',
            'quantity' => 1,
            'gross_amount' => 700,
        ]);

        $matched = app(VendorInvoiceMatcher::class)->match($invoice->fresh('lines'));

        $this->assertSame($event->id, $matched->event_id);
        $this->assertSame('auto_matched', $matched->matching_status);
    }

    public function test_matcher_matches_event_by_code_in_description(): void
    {
        $event = Event::factory()->create(['code' => '26ABCD12']);

        $invoice = VendorInvoice::create([
            'invoice_number' => 'TEST/1',
            'notes' => 'Wycieczka 26ABCD12 grupa szkolna',
            'gross_amount' => 100,
            'payment_status' => 'due',
            'approval_status' => 'pending',
            'matching_status' => 'unmatched',
        ]);

        $matched = app(VendorInvoiceMatcher::class)->match($invoice);

        $this->assertSame($event->id, $matched->event_id);
    }

    public function test_matcher_matches_legacy_event_code_with_hyphen(): void
    {
        $event = Event::factory()->create(['code' => '26-ABCD12']);

        $invoice = VendorInvoice::create([
            'invoice_number' => 'TEST/2',
            'notes' => 'Wycieczka 26-ABCD12 grupa szkolna',
            'gross_amount' => 100,
            'payment_status' => 'due',
            'approval_status' => 'pending',
            'matching_status' => 'unmatched',
        ]);

        $matched = app(VendorInvoiceMatcher::class)->match($invoice);

        $this->assertSame($event->id, $matched->event_id);
    }

    public function test_matcher_matches_by_contractor_nip_and_program_point_dates(): void
    {
        $contractor = Contractor::create(['name' => 'Muzeum', 'nip' => '7841016977', 'status' => 'active']);
        $event = Event::factory()->create([
            'start_date' => '2026-06-10',
            'end_date' => '2026-06-12',
        ]);

        EventProgramPoint::factory()->create([
            'event_id' => $event->id,
            'contractor_id' => $contractor->id,
            'day' => 1,
        ]);

        $invoice = VendorInvoice::create([
            'invoice_number' => 'FV/22/06/2026/OL',
            'seller_nip' => '7841016977',
            'seller_name' => 'Muzeum Pierwszych Piastów',
            'sale_date' => '2026-06-11',
            'gross_amount' => 1105,
            'payment_status' => 'paid',
            'approval_status' => 'pending',
            'matching_status' => 'unmatched',
        ]);

        $matched = app(VendorInvoiceMatcher::class)->match($invoice);

        $this->assertSame($event->id, $matched->event_id);
        $this->assertSame($contractor->id, $matched->contractor_id);
        $this->assertNotNull($matched->event_program_point_id);
        $this->assertSame(
            EventProgramPoint::query()->where('event_id', $event->id)->value('id'),
            $matched->event_program_point_id,
        );
    }

    public function test_ksef_normalizer_fixes_pdf_format(): void
    {
        $this->assertSame(
            '7840006416-20260612-405F68C00002-0A',
            KsefNumberNormalizer::normalize('7840006416-20260612405F68C00002-0A')
        );
    }

    public function test_bulk_pdf_splitter_attaches_individual_files(): void
    {
        $pdf = $this->requireKsefFixture('Faktury pdf w jednym pliku.pdf');
        $csv = $this->requireKsefFixture('CSV.csv');

        Storage::disk('public')->put('vendor-invoices/test-bulk.pdf', $pdf);

        app(VendorInvoiceImportService::class)->importFromContent($csv, 'csv', 'CSV.csv');

        $result = app(BulkPdfSplitter::class)->splitAndAttach('vendor-invoices/test-bulk.pdf');

        $this->assertGreaterThan(0, $result['attached']);
        $this->assertTrue(
            VendorInvoice::query()->whereNotNull('pdf_path')->count() >= $result['attached']
        );
    }

    public function test_batch_import_processes_csv_and_xml_together(): void
    {
        $this->requireKsefFixture('CSV.csv');
        $this->requireKsefFixture('xml.xml');

        $csv = base_path('pliki/ksef/CSV.csv');
        $xml = base_path('pliki/ksef/xml.xml');

        $result = app(VendorInvoiceBatchImportService::class)->import(
            csvPath: $csv,
            xmlPath: $xml,
            csvName: 'CSV.csv',
            xmlName: 'xml.xml',
        );

        $this->assertGreaterThan(0, $result['imported']);
        $this->assertNotEmpty($result['batch_ids'] ?? []);
        $this->assertSame(
            VendorInvoice::query()->whereNotNull('ksef_number')->count(),
            VendorInvoice::count(),
        );
        $this->assertSame(
            VendorInvoice::query()->whereIn('import_batch_id', $result['batch_ids'])->count(),
            VendorInvoice::query()->whereNotNull('ksef_number')->count(),
        );
    }

    public function test_demo_ksef_xml_import_returns_batch_and_lines(): void
    {
        $path = base_path('pliki/ksef/demo-ksef.xml');
        if (! is_file($path)) {
            $this->markTestSkipped('Brak lokalnego demo KSeF (pliki/ksef/demo-ksef.xml).');
        }

        $event = Event::factory()->create([
            'code' => '26JGYZY7',
            'name' => 'Paryż demo',
            'start_date' => '2026-08-13',
            'end_date' => '2026-08-16',
        ]);

        $this->assertSame('26JGYZY7', $event->fresh()->code);

        $result = app(VendorInvoiceBatchImportService::class)->import(
            xmlPath: $path,
            xmlName: 'demo-ksef.xml',
        );

        $this->assertSame(5, $result['imported']);
        $this->assertNotEmpty($result['batch_ids']);

        $invoices = VendorInvoice::query()
            ->whereIn('import_batch_id', $result['batch_ids'])
            ->with('lines')
            ->get();

        $this->assertCount(5, $invoices);

        $demo001 = $invoices->firstWhere('invoice_number', 'FV/DEMO/001');
        $this->assertNotNull($demo001);
        $this->assertCount(2, $demo001->lines);
        $this->assertSame('auto_matched', $demo001->matching_status);
        $this->assertSame($event->id, $demo001->event_id);
        $this->assertTrue($invoices->contains(fn (VendorInvoice $i) => $i->matching_status === 'unmatched'));
    }

    public function test_invoice_permissions_exist_for_ksiegowosc_role(): void
    {
        foreach ([
            'view_vendor_invoice',
            'import_vendor_invoice',
            'approve_vendor_invoice',
            'manage_vendor_invoice_assignment',
        ] as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        $role = Role::firstOrCreate(['name' => 'ksiegowosc']);
        $role->givePermissionTo('view_vendor_invoice');

        $this->assertTrue($role->hasPermissionTo('view_vendor_invoice'));
    }
}
