<?php

namespace Tests\Feature\Tfg;

use App\Jobs\Tfg\PollTfgFeedStatusJob;
use App\Jobs\Tfg\SubmitTfgFeedJob;
use App\Models\Contract;
use App\Models\ContractVariant;
use App\Models\TfgFeedLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TfgMockFeedFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\TfgDictionarySeeder::class);
        config(['tfg.enabled' => true, 'tfg.driver' => 'mock']);
    }

    public function test_submit_and_poll_marks_contract_as_zawarta(): void
    {
        $contract = $this->createContractReadyForTfg();
        $contract->queueTfgOperation(Contract::OP_NOWEDANE);

        SubmitTfgFeedJob::dispatchSync([$contract->id]);

        $log = TfgFeedLog::query()->first();
        $this->assertNotNull($log);
        $this->assertSame('ACCEPTED', $log->sync_status);
        $this->assertNotNull($log->feed_identifier);

        PollTfgFeedStatusJob::dispatchSync($log->id);
        PollTfgFeedStatusJob::dispatchSync($log->id);

        $contract->refresh();
        $this->assertSame('Zawarta', $contract->tfg_status);
        $this->assertNull($contract->pending_operation);
    }

    public function test_correction_blocked_without_zawarta_status(): void
    {
        $contract = $this->createContractReadyForTfg();

        $this->assertFalse($contract->canCorrect());
    }

    public function test_correction_allowed_when_zawarta(): void
    {
        $contract = $this->createContractReadyForTfg();
        $contract->forceFill(['tfg_status' => 'Zawarta'])->save();

        $this->assertTrue($contract->canCorrect());
    }

    protected function createContractReadyForTfg(): Contract
    {
        $contract = Contract::create([
            'contract_number' => 'UM/2026/'.uniqid(),
            'contract_date' => now()->toDateString(),
            'subject_code' => 'IT',
            'payment_method_code' => 'WPLATAPRZED',
            'total_price' => 500,
            'public_token' => 'token-'.uniqid(),
            'agreement_body' => '<p>test</p>',
        ]);

        $variant = ContractVariant::create([
            'contract_id' => $contract->id,
            'travelers_count' => 5,
            'starts_at' => now()->toDateString(),
            'ends_at' => now()->addDays(2)->toDateString(),
        ]);

        $variant->locations()->create([
            'scope_type' => 'PLISAS',
            'country_code' => 'CZ',
            'locality' => 'Kraków',
        ]);

        $variant->transports()->create(['transport_code' => 'NLOT']);

        return $contract->fresh(['variants.locations', 'variants.transports']);
    }
}
