<?php

namespace Tests\Feature\Tfg;

use App\Models\Contract;
use App\Services\Tfg\TfgWykazCsvExporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TfgWykazCsvExporterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\TfgDictionarySeeder::class);
    }

    /**
     * @return array<int, string>
     */
    private function officialLines(string $file): array
    {
        $raw = file_get_contents(base_path('tests/Fixtures/tfg2/'.$file));

        if (str_starts_with($raw, "\xEF\xBB\xBF")) {
            $raw = substr($raw, 3);
        }

        $raw = str_replace("\r\n", "\n", $raw);

        return array_values(array_filter(explode("\n", $raw), fn ($line) => $line !== ''));
    }

    public function test_output_has_bom_crlf_and_correct_filename(): void
    {
        $contract = $this->bareContract('2026/0183');

        $result = app(TfgWykazCsvExporter::class)->export(collect([$contract]), Contract::OP_USUNIECIE);

        $this->assertStringStartsWith("\xEF\xBB\xBF", $result['content']);
        $this->assertStringContainsString("\r\n", $result['content']);
        $this->assertSame('usuniecie.csv', $result['filename']);
    }

    public function test_headers_match_official_samples(): void
    {
        $exporter = app(TfgWykazCsvExporter::class);

        $this->assertSame(
            $this->officialLines('nowe_dane.csv')[0],
            implode(';', $exporter->header(Contract::OP_NOWEDANE)),
        );

        $this->assertSame(
            $this->officialLines('korekta_zmiana.csv')[0],
            implode(';', $exporter->header(Contract::OP_KOREKTA)),
        );

        $this->assertSame(
            $this->officialLines('rozwiazanie.csv')[0],
            implode(';', $exporter->header(Contract::OP_ROZWIAZANIE)),
        );

        $this->assertSame(
            $this->officialLines('usuniecie.csv')[0],
            implode(';', $exporter->header(Contract::OP_USUNIECIE)),
        );
    }

    public function test_usuniecie_full_bytes_match_official_sample(): void
    {
        $a = $this->bareContract('2026/0183');
        $b = $this->bareContract('2026/0184');

        $result = app(TfgWykazCsvExporter::class)->export(collect([$a, $b]), Contract::OP_USUNIECIE);

        $expected = "\xEF\xBB\xBF"."NrUmowyRezerwacji\r\n2026/0183\r\n2026/0184\r\n";

        $this->assertSame($expected, $result['content']);
    }

    public function test_nowedane_row_matches_official_sample_row(): void
    {
        $contract = $this->bareContract('2026/0181');
        $contract->update([
            'subject_code' => 'IT',
            'contract_date' => '2026-07-10',
            'payment_method_code' => 'WPLATAPRZED',
            'total_price' => 50000,
            'currency' => 'PLN',
        ]);

        $variant = $contract->variants()->create([
            'travelers_count' => 5,
            'starts_at' => '2026-07-11',
            'ends_at' => '2026-07-17',
        ]);
        $variant->locations()->create(['scope_type' => 'PLISAS', 'country_code' => 'CZ', 'locality' => 'Praga', 'sort_order' => 0]);
        $variant->locations()->create(['scope_type' => 'EUR', 'country_code' => 'SI', 'locality' => 'Lublana', 'sort_order' => 1]);
        $variant->transports()->create(['transport_code' => 'LOTCZART', 'icao_codes' => ['LKPR']]);
        $contract->payments()->create(['amount' => 12000, 'currency' => 'PLN', 'paid_at' => '2026-07-10', 'sort_order' => 0]);

        $contract = $contract->fresh(['variants.locations', 'variants.transports', 'payments', 'refunds']);

        $result = app(TfgWykazCsvExporter::class)->export(collect([$contract]), Contract::OP_NOWEDANE);

        $generated = str_replace("\r\n", "\n", substr($result['content'], 3));
        $generatedRows = array_values(array_filter(explode("\n", $generated), fn ($l) => $l !== ''));

        $officialRow = collect($this->officialLines('nowe_dane.csv'))
            ->first(fn ($line) => str_starts_with($line, '2026/0181;'));

        $this->assertNotNull($officialRow);
        $this->assertContains($officialRow, $generatedRows);
    }

    public function test_payments_and_refunds_expand_to_rows(): void
    {
        $contract = $this->bareContract('2026/0190');

        $variant = $contract->variants()->create([
            'travelers_count' => 5,
            'starts_at' => '2026-07-11',
            'ends_at' => '2026-07-17',
        ]);
        $variant->locations()->create(['scope_type' => 'PLISAS', 'country_code' => 'CZ', 'locality' => 'Praga']);
        $contract->payments()->create(['amount' => 1000, 'currency' => 'PLN', 'paid_at' => '2026-07-10']);
        $contract->payments()->create(['amount' => 2000, 'currency' => 'PLN', 'paid_at' => '2026-07-11']);
        $contract->refunds()->create(['amount' => 500, 'currency' => 'EUR', 'refunded_at' => '2026-07-12']);

        $contract = $contract->fresh(['variants.locations', 'variants.transports', 'payments', 'refunds']);

        $rows = app(TfgWykazCsvExporter::class)->rowsForContract($contract, Contract::OP_NOWEDANE);

        $this->assertCount(3, $rows);
        $this->assertContains('wpłata', $rows[0]);
        $this->assertContains('zwrot', $rows[2]);
    }

    private function bareContract(string $number): Contract
    {
        return Contract::create([
            'contract_number' => $number,
            'contract_date' => '2026-07-10',
            'subject_code' => 'IT',
            'payment_method_code' => 'WPLATAPRZED',
            'total_price' => 50000,
            'currency' => 'PLN',
            'public_token' => 'tok-'.uniqid(),
        ]);
    }
}
