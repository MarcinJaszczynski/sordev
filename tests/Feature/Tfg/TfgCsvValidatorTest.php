<?php

namespace Tests\Feature\Tfg;

use App\Models\Contract;
use App\Models\ContractVariant;
use App\Services\Tfg\TfgCsvValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TfgCsvValidatorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\TfgDictionarySeeder::class);
    }

    public function test_valid_contract_passes(): void
    {
        $contract = $this->validContract();

        $errors = app(TfgCsvValidator::class)->collectErrors($contract, Contract::OP_NOWEDANE);

        $this->assertSame([], $errors);
    }

    public function test_contract_without_variant_fails(): void
    {
        $contract = $this->validContract();
        $contract->variants()->delete();
        $contract = $contract->fresh(['variants.locations', 'variants.transports']);

        $errors = app(TfgCsvValidator::class)->collectErrors($contract, Contract::OP_NOWEDANE);

        $this->assertNotEmpty($errors);
    }

    public function test_more_than_one_variant_fails_for_csv(): void
    {
        $contract = $this->validContract();
        $contract->variants()->create([
            'travelers_count' => 3,
            'starts_at' => '2026-07-11',
            'ends_at' => '2026-07-17',
        ]);
        $contract = $contract->fresh(['variants.locations', 'variants.transports']);

        $errors = app(TfgCsvValidator::class)->collectErrors($contract, Contract::OP_NOWEDANE);

        $this->assertTrue(collect($errors)->contains(fn ($m) => str_contains($m, 'jeden wariant')));
    }

    public function test_invalid_currency_fails(): void
    {
        $contract = $this->validContract();
        $contract->update(['currency' => 'ABC']);

        $errors = app(TfgCsvValidator::class)->collectErrors($contract->fresh(['variants.locations', 'variants.transports']), Contract::OP_NOWEDANE);

        $this->assertTrue(collect($errors)->contains(fn ($m) => str_contains($m, 'waluta')));
    }

    public function test_air_transport_requires_icao(): void
    {
        $contract = $this->validContract();
        $contract->variants()->first()->transports()->create(['transport_code' => 'LOTCZART', 'icao_codes' => []]);
        $contract = $contract->fresh(['variants.locations', 'variants.transports']);

        $errors = app(TfgCsvValidator::class)->collectErrors($contract, Contract::OP_NOWEDANE);

        $this->assertTrue(collect($errors)->contains(fn ($m) => str_contains($m, 'lotniska')));
    }

    public function test_rozwiazanie_requires_termination_date(): void
    {
        $contract = $this->validContract();

        $errors = app(TfgCsvValidator::class)->collectErrors($contract, Contract::OP_ROZWIAZANIE);

        $this->assertTrue(collect($errors)->contains(fn ($m) => str_contains($m, 'rozwiązania')));
    }

    public function test_collection_enforces_max_contracts(): void
    {
        config(['tfg.csv.max_contracts' => 1]);

        $a = $this->validContract();
        $b = $this->validContract();

        $errors = app(TfgCsvValidator::class)->validateCollection(collect([$a, $b]), Contract::OP_USUNIECIE);

        $this->assertArrayHasKey('_file', $errors);
    }

    private function validContract(): Contract
    {
        $contract = Contract::create([
            'contract_number' => 'UM/'.uniqid(),
            'contract_date' => '2026-07-10',
            'subject_code' => 'IT',
            'payment_method_code' => 'WPLATAPRZED',
            'total_price' => 50000,
            'currency' => 'PLN',
            'public_token' => 'tok-'.uniqid(),
        ]);

        $variant = ContractVariant::create([
            'contract_id' => $contract->id,
            'travelers_count' => 5,
            'starts_at' => '2026-07-11',
            'ends_at' => '2026-07-17',
        ]);

        $variant->locations()->create(['scope_type' => 'PLISAS', 'country_code' => 'CZ', 'locality' => 'Praga']);

        return $contract->fresh(['variants.locations', 'variants.transports', 'payments', 'refunds']);
    }
}
