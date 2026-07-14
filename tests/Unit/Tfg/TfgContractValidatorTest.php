<?php

namespace Tests\Unit\Tfg;

use App\Models\Contract;
use App\Models\ContractVariant;
use App\Models\ContractVariantTransport;
use App\Services\Tfg\TfgContractPayloadBuilder;
use App\Services\Tfg\TfgContractValidator;
use App\Services\Tfg\TfgFeedBundler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TfgContractValidatorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\TfgDictionarySeeder::class);
    }

    public function test_valid_contract_passes_validation(): void
    {
        $contract = $this->makeValidContract();

        app(TfgContractValidator::class)->validate($contract);

        $this->assertTrue(true);
    }

    public function test_flight_transport_requires_icao_codes(): void
    {
        $contract = $this->makeValidContract();
        $variant = $contract->variants()->first();
        ContractVariantTransport::query()->where('contract_variant_id', $variant->id)->delete();
        ContractVariantTransport::create([
            'contract_variant_id' => $variant->id,
            'transport_code' => 'LOTCZART',
            'icao_codes' => null,
        ]);

        $errors = app(TfgContractValidator::class)->collectErrors($contract->fresh(['variants.transports']));

        $this->assertNotEmpty($errors);
    }

    public function test_invalid_icao_code_is_rejected(): void
    {
        $contract = $this->makeValidContract();
        $transport = $contract->variants()->first()->transports()->first();
        $transport->update(['transport_code' => 'LOTCZART', 'icao_codes' => ['bad']]);

        $errors = app(TfgContractValidator::class)->collectErrors($contract->fresh(['variants.transports']));

        $this->assertNotEmpty($errors);
    }

    public function test_payload_builder_creates_json_structure(): void
    {
        $contract = $this->makeValidContract();
        $payload = app(TfgContractPayloadBuilder::class)->buildForContracts(collect([$contract]), Contract::OP_NOWEDANE);

        $this->assertSame(Contract::OP_NOWEDANE, $payload['operation']);
        $this->assertCount(1, $payload['contracts']);
        $this->assertSame($contract->contract_number, $payload['contracts'][0]['contract_number']);
    }

    public function test_feed_bundler_respects_max_contracts(): void
    {
        $bundler = new TfgFeedBundler(2);
        $contracts = collect([
            new Contract(['id' => 1]),
            new Contract(['id' => 2]),
            new Contract(['id' => 3]),
        ]);

        $bundles = $bundler->bundle($contracts);

        $this->assertCount(2, $bundles);
        $this->assertCount(2, $bundles->first());
        $this->assertCount(1, $bundles->last());
    }

    protected function makeValidContract(): Contract
    {
        $contract = Contract::create([
            'contract_number' => 'UM/2026/00001',
            'contract_date' => now()->toDateString(),
            'subject_code' => 'IT',
            'payment_method_code' => 'WPLATAPRZED',
            'total_price' => 1000,
            'public_token' => 'test-token-'.uniqid(),
            'agreement_body' => '<p>test</p>',
        ]);

        $variant = ContractVariant::create([
            'contract_id' => $contract->id,
            'travelers_count' => 10,
            'starts_at' => now()->toDateString(),
            'ends_at' => now()->addDays(3)->toDateString(),
        ]);

        $variant->locations()->create([
            'scope_type' => 'PLISAS',
            'country_code' => 'CZ',
            'locality' => 'Warszawa',
        ]);

        $variant->transports()->create([
            'transport_code' => 'NLOT',
        ]);

        return $contract->fresh(['variants.locations', 'variants.transports', 'payments', 'refunds']);
    }
}
