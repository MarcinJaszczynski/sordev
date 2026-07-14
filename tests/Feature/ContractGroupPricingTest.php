<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\Contractor;
use App\Models\ContractOrderingParty;
use App\Models\Event;
use App\Models\User;
use App\Services\AgreementDocumentService;
use App\Services\ContractGroupPricingService;
use App\Services\ContractOrderingPartyService;
use App\Services\ContractPaymentScheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ContractGroupPricingTest extends TestCase
{
    use RefreshDatabase;

    public function test_group_pricing_calculates_total_from_unit_price_and_participants(): void
    {
        $service = app(ContractGroupPricingService::class);

        $data = $service->applyGroupPricingToFormData([
            'agreement_type' => Contract::TYPE_GROUP,
            'participant_count' => 20,
            'unit_price' => 150,
        ]);

        $this->assertSame(20, $data['participant_count']);
        $this->assertSame(150.0, (float) $data['unit_price']);
        $this->assertSame(3000.0, (float) $data['amount_due']);
        $this->assertSame(Contract::PAYMENT_SCHEME_LUMP_SUM, $data['payment_scheme']);
    }

    public function test_payment_schedule_sync_persists_installments(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create(['client_name' => 'Szkoła']);

        $contract = Contract::create([
            'event_id' => $event->id,
            'contract_type' => Contract::TYPE_GROUP,
            'title' => 'Umowa grupowa',
            'contract_date' => now()->toDateString(),
            'participant_count' => 10,
            'unit_price' => 200,
            'total_price' => 2000,
            'payment_scheme' => Contract::PAYMENT_SCHEME_INSTALLMENTS,
            'currency' => 'PLN',
            'status' => 'sent',
            'payment_status' => 'pending',
            'created_by' => $user->id,
        ]);

        app(ContractPaymentScheduleService::class)->syncForContract($contract, [
            ['label' => 'Zaliczka', 'amount' => 1000, 'due_date' => now()->addWeek()->toDateString()],
            ['label' => 'Reszta', 'amount' => 1000, 'due_date' => now()->addMonth()->toDateString()],
        ], Contract::PAYMENT_SCHEME_INSTALLMENTS);

        $contract->refresh();

        $this->assertCount(2, $contract->paymentSchedules);
        $this->assertSame('Zaliczka', $contract->paymentSchedules->first()->label);
    }

    public function test_ordering_party_name_is_resolved_from_contractor_when_snapshot_name_missing(): void
    {
        if (! Schema::hasTable('contractors')) {
            $this->markTestSkipped('Brak tabeli contractors.');
        }

        $user = User::factory()->create();
        $event = Event::factory()->create(['client_name' => 'Impreza testowa']);
        $contractor = Contractor::create([
            'name' => 'Szkoła Podstawowa nr 7',
            'email' => 'sp7@example.com',
            'status' => 'active',
        ]);

        $contract = Contract::create([
            'event_id' => $event->id,
            'contract_type' => Contract::TYPE_GROUP,
            'title' => 'Umowa',
            'contract_date' => now()->toDateString(),
            'total_price' => 1000,
            'currency' => 'PLN',
            'status' => 'sent',
            'payment_status' => 'pending',
            'created_by' => $user->id,
        ]);

        ContractOrderingParty::create([
            'contract_id' => $contract->id,
            'contractor_id' => $contractor->id,
            'sort_order' => 0,
            'name' => '',
        ]);

        $service = app(ContractOrderingPartyService::class);
        $contract->refresh()->load('orderingParties.contractor');

        $this->assertSame('Szkoła Podstawowa nr 7', $service->formattedPartyNames($contract));
        $this->assertSame('Szkoła Podstawowa nr 7', $service->partiesForTemplatePayload($contract)[0]['name']);
    }

    public function test_group_contract_pdf_view_includes_ordering_party_name(): void
    {
        if (! Schema::hasTable('contractors')) {
            $this->markTestSkipped('Brak tabeli contractors.');
        }

        $user = User::factory()->create();
        $event = Event::factory()->create(['client_name' => 'Impreza testowa']);
        $contractor = Contractor::create([
            'name' => 'Gmina Testowa',
            'nip' => '1234567890',
            'status' => 'active',
        ]);

        $contract = Contract::create([
            'event_id' => $event->id,
            'contract_type' => Contract::TYPE_GROUP,
            'title' => 'Umowa grupowa',
            'contract_date' => now()->toDateString(),
            'participant_count' => 15,
            'unit_price' => 100,
            'total_price' => 1500,
            'payment_scheme' => Contract::PAYMENT_SCHEME_LUMP_SUM,
            'agreement_body' => 'Treść umowy grupowej',
            'currency' => 'PLN',
            'status' => 'sent',
            'payment_status' => 'pending',
            'created_by' => $user->id,
        ]);

        ContractOrderingParty::create([
            'contract_id' => $contract->id,
            'contractor_id' => $contractor->id,
            'sort_order' => 0,
            'name' => '',
        ]);

        $contract->loadMissing(['event', 'orderingParties.contractor', 'paymentSchedules']);

        $html = view('pdf.agreement', [
            'agreement' => $contract,
            'agreementBody' => (string) $contract->agreement_body,
            'groupPricing' => app(ContractGroupPricingService::class)->presentationFor($contract),
        ])->render();

        $this->assertStringContainsString('Gmina Testowa', $html);

        $pdf = app(AgreementDocumentService::class)->renderPdfBytes($contract);

        $this->assertNotNull($pdf);
        $this->assertStringStartsWith('%PDF', $pdf);
    }
}
