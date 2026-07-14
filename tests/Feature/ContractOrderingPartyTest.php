<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\Contractor;
use App\Models\Event;
use App\Models\User;
use App\Services\ContractOrderingPartyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ContractOrderingPartyTest extends TestCase
{
    use RefreshDatabase;

    public function test_contract_can_store_multiple_ordering_parties_and_sync_primary_customer(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create([
            'client_name' => 'Fallback Client',
            'participant_count' => 10,
        ]);

        $contract = Contract::create([
            'event_id' => $event->id,
            'contract_type' => Contract::TYPE_GROUP,
            'title' => 'Umowa testowa',
            'contract_date' => now()->toDateString(),
            'event_name' => $event->name,
            'participant_count' => 10,
            'total_price' => 5000,
            'currency' => 'PLN',
            'status' => 'sent',
            'payment_status' => 'pending',
            'created_by' => $user->id,
        ]);

        app(ContractOrderingPartyService::class)->syncForContract($contract, [
            [
                'name' => 'Szkoła Podstawowa nr 1',
                'email' => 'sp1@example.com',
                'phone' => '111222333',
                'nip' => '1234567890',
                'city' => 'Warszawa',
            ],
            [
                'name' => 'Gmina Partnerska',
                'email' => 'gmina@example.com',
                'phone' => '444555666',
            ],
        ], 'Wspólna uwaga do zamawiających');

        $contract->refresh()->load('orderingParties');

        $this->assertCount(2, $contract->orderingParties);
        $this->assertSame('Szkoła Podstawowa nr 1', $contract->customer_name);
        $this->assertSame('sp1@example.com', $contract->customer_email);
        $this->assertSame('Wspólna uwaga do zamawiających', $contract->ordering_party_notes);
        $this->assertSame(
            'Szkoła Podstawowa nr 1, Gmina Partnerska',
            app(ContractOrderingPartyService::class)->formattedPartyNames($contract),
        );
    }

    public function test_parties_from_event_use_ordering_contractors(): void
    {
        if (! Schema::hasTable('event_contractor')) {
            $this->markTestSkipped('Tabela event_contractor nie istnieje w tym środowisku testowym.');
        }

        $event = Event::factory()->create();
        $first = Contractor::create(['name' => 'Kontrahent A', 'email' => 'a@example.com', 'status' => 'active']);
        $second = Contractor::create(['name' => 'Kontrahent B', 'email' => 'b@example.com', 'status' => 'active']);

        $event->syncOrderingContractors([$first->id, $second->id]);

        $parties = app(ContractOrderingPartyService::class)->partiesFromEvent($event->fresh());

        $this->assertCount(2, $parties);
        $this->assertSame('Kontrahent A', $parties[0]['name']);
        $this->assertSame('Kontrahent B', $parties[1]['name']);
        $this->assertSame($first->id, $parties[0]['contractor_id']);
    }

    public function test_template_payload_includes_all_ordering_parties(): void
    {
        $event = Event::factory()->create();
        $contract = Contract::create([
            'event_id' => $event->id,
            'contract_type' => Contract::TYPE_GROUP,
            'title' => 'Umowa grupowa',
            'contract_date' => now()->toDateString(),
            'total_price' => 3000,
            'currency' => 'PLN',
            'status' => 'sent',
            'payment_status' => 'pending',
        ]);

        app(ContractOrderingPartyService::class)->syncForContract($contract, [
            ['name' => 'Zamawiający 1', 'email' => 'z1@example.com'],
            ['name' => 'Zamawiający 2', 'email' => 'z2@example.com'],
        ]);

        $payload = $contract->fresh()->buildTemplatePayload();

        $this->assertSame('Zamawiający 1, Zamawiający 2', $payload['ordering_parties_names']);
        $this->assertStringContainsString('Zamawiający 1', $payload['ordering_parties_list']);
        $this->assertStringContainsString('Zamawiający 2', $payload['ordering_parties_list']);
    }
}
