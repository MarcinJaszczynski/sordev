<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\ContractTemplate;
use App\Models\Event;
use App\Models\EventSettlement;
use App\Models\EventSettlementParticipantPayment;
use App\Models\EventTemplate;
use App\Models\User;
use App\Services\ContractPaymentSyncService;
use App\Services\ContractTfgSetupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContractCancelAndAnnexTest extends TestCase
{
    use RefreshDatabase;

    public function test_cancelling_contract_removes_synced_participant_payment_without_error(): void
    {
        $user = User::factory()->create();
        $template = EventTemplate::factory()->create();

        $event = Event::create([
            'event_template_id' => $template->id,
            'name' => 'Impreza anulowanie',
            'client_name' => 'Szkola',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'participant_count' => 10,
            'total_cost' => 1000,
            'status' => 'confirmed',
            'created_by' => $user->id,
        ]);

        $settlement = EventSettlement::findOrCreateActiveForEvent($event);

        $contract = Contract::create([
            'event_id' => $event->id,
            'contract_type' => Contract::TYPE_GROUP,
            'title' => 'Umowa test',
            'contract_date' => now()->toDateString(),
            'total_price' => 1000,
            'amount_paid' => 1000,
            'currency' => 'PLN',
            'status' => 'completed',
            'payment_status' => 'paid',
            'paid_at' => now(),
            'subject_code' => 'IT',
            'payment_method_code' => 'WPLATAPRZED',
            'created_by' => $user->id,
        ]);

        app(ContractPaymentSyncService::class)->sync($contract->fresh());

        $contract->refresh();
        $this->assertNotNull($contract->participant_payment_id);

        $paymentId = $contract->participant_payment_id;

        Contract::withoutEvents(function () use ($contract): void {
            $contract->update(['status' => 'cancelled']);
        });

        app(ContractPaymentSyncService::class)->remove($contract->fresh());

        $this->assertNull(EventSettlementParticipantPayment::find($paymentId));
        $settlement->refresh();
    }

    public function test_create_annex_copies_tfg_structure_and_links_parent(): void
    {
        $user = User::factory()->create();
        $template = EventTemplate::factory()->create();

        $event = Event::create([
            'event_template_id' => $template->id,
            'name' => 'Impreza aneks',
            'client_name' => 'Szkola',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'participant_count' => 5,
            'total_cost' => 500,
            'status' => 'confirmed',
            'created_by' => $user->id,
        ]);

        ContractTemplate::create([
            'name' => 'Szablon',
            'content' => 'Treść {{agreement_number}}',
        ]);

        $parent = Contract::create([
            'event_id' => $event->id,
            'contract_type' => Contract::TYPE_GROUP,
            'title' => 'Umowa bazowa',
            'contract_date' => now()->toDateString(),
            'total_price' => 2000,
            'currency' => 'PLN',
            'status' => 'sent',
            'payment_status' => 'pending',
            'created_by' => $user->id,
        ]);

        app(ContractTfgSetupService::class)->applyToContract(
            $parent,
            app(ContractTfgSetupService::class)->defaultsFromEvent($event),
        );

        $annex = app(ContractTfgSetupService::class)->createAnnex($parent, [
            'title' => 'Aneks zmiana ceny',
            'amount_due' => 1800,
            'agreement_date' => now()->toDateString(),
        ]);

        $this->assertTrue($annex->meta['is_annex'] ?? false);
        $this->assertSame($parent->id, $annex->meta['parent_contract_id']);
        $this->assertSame('IT', $annex->subject_code);
        $this->assertCount(1, $annex->variants);
        $this->assertNotEquals($parent->public_token, $annex->public_token);
    }
}
