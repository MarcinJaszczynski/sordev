<?php

namespace Tests\Feature\Tfg;

use App\Models\Contract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MigrateContractsFromEventAgreementsTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_command_copies_legacy_agreements(): void
    {
        if (! Schema::hasTable('event_agreements')) {
            $this->markTestSkipped('Legacy event_agreements table not present.');
        }

        $this->seed(\Database\Seeders\TfgDictionarySeeder::class);

        $legacyId = DB::table('event_agreements')->insertGetId([
            'event_id' => null,
            'contract_template_id' => null,
            'agreement_type' => 'individual',
            'title' => 'Test umowa',
            'agreement_number' => 'LEG/1',
            'agreement_date' => now()->toDateString(),
            'amount_due' => 100,
            'amount_paid' => 50,
            'currency' => 'PLN',
            'status' => 'signed',
            'payment_status' => 'pending',
            'public_token' => 'legacy-token-'.uniqid(),
            'agreement_body' => '<p>body</p>',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Artisan::call('contracts:migrate-from-event-agreements');

        $contract = Contract::query()->where('legacy_event_agreement_id', $legacyId)->first();

        $this->assertNotNull($contract);
        $this->assertSame('LEG/1', $contract->contract_number);
        $this->assertSame(100.0, (float) $contract->total_price);
        $this->assertCount(1, $contract->variants);
        $this->assertCount(1, $contract->payments);
    }

    public function test_migration_command_can_scope_to_single_event(): void
    {
        if (! Schema::hasTable('event_agreements')) {
            $this->markTestSkipped('Legacy event_agreements table not present.');
        }

        $this->seed(\Database\Seeders\TfgDictionarySeeder::class);

        $eventA = \App\Models\Event::factory()->create();
        $eventB = \App\Models\Event::factory()->create();

        $legacyA = DB::table('event_agreements')->insertGetId([
            'event_id' => $eventA->id,
            'contract_template_id' => null,
            'agreement_type' => 'individual',
            'title' => 'Impreza A',
            'agreement_number' => 'LEG/A',
            'agreement_date' => now()->toDateString(),
            'amount_due' => 100,
            'amount_paid' => 0,
            'currency' => 'PLN',
            'status' => 'signed',
            'payment_status' => 'pending',
            'public_token' => 'legacy-a-'.uniqid(),
            'agreement_body' => '<p>a</p>',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $legacyB = DB::table('event_agreements')->insertGetId([
            'event_id' => $eventB->id,
            'contract_template_id' => null,
            'agreement_type' => 'individual',
            'title' => 'Impreza B',
            'agreement_number' => 'LEG/B',
            'agreement_date' => now()->toDateString(),
            'amount_due' => 200,
            'amount_paid' => 0,
            'currency' => 'PLN',
            'status' => 'signed',
            'payment_status' => 'pending',
            'public_token' => 'legacy-b-'.uniqid(),
            'agreement_body' => '<p>b</p>',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Artisan::call('contracts:migrate-from-event-agreements', [
            '--event' => $eventA->id,
        ]);

        $this->assertNotNull(Contract::query()->where('legacy_event_agreement_id', $legacyA)->first());
        $this->assertNull(Contract::query()->where('legacy_event_agreement_id', $legacyB)->first());
    }
}
