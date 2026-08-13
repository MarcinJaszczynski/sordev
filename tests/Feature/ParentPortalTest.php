<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\ContractPaymentSchedule;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\EventSettlement;
use App\Models\EventSettlementParticipantPayment;
use App\Models\User;
use App\Services\ParentParticipantAccessService;
use App\Support\EventParticipantConsents;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ParentPortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_parent_can_view_portal_and_save_consents(): void
    {
        if (! Schema::hasColumn('event_participants', 'parent_access_token')) {
            $this->markTestSkipped('Brak parent_access_token.');
        }

        $event = Event::factory()->create(['name' => 'Wycieczka szkolna']);
        $participant = EventParticipant::query()->create([
            'event_id' => $event->id,
            'first_name' => 'Ania',
            'last_name' => 'Kowalska',
            'email' => 'rodzic@example.com',
            'source' => EventParticipant::SOURCE_MANUAL,
            'status' => EventParticipant::STATUS_ACTIVE,
        ]);

        $url = app(ParentParticipantAccessService::class)->urlFor($participant);
        $token = $participant->fresh()->parent_access_token;

        $this->get(route('parent.portal.show', ['token' => $token]))
            ->assertOk()
            ->assertSee('Ania Kowalska')
            ->assertSee('Wycieczka szkolna');

        $this->post(route('parent.portal.consents', ['token' => $token]), [
            'consent_terms' => '1',
            'consent_insurance' => '1',
            'consent_rodo' => '1',
        ])->assertRedirect(route('parent.portal.show', ['token' => $token]));

        $participant->refresh();
        $this->assertTrue(EventParticipantConsents::hasRequired($participant->consents));
        $this->assertTrue($participant->hasParentConsent());
        $this->assertStringContainsString('/rodzic/', $url);
    }

    public function test_parent_pay_redirects_to_checkout_for_open_installment(): void
    {
        if (! Schema::hasColumn('event_participants', 'parent_access_token')
            || ! Schema::hasTable('online_payment_sessions')
        ) {
            $this->markTestSkipped('Brak tokenów / sesji płatności.');
        }

        $user = User::factory()->create();
        $event = Event::factory()->create();
        $settlement = EventSettlement::findOrCreateActiveForEvent($event);
        $payment = EventSettlementParticipantPayment::query()->create([
            'settlement_id' => $settlement->id,
            'participant_name' => 'Ania Kowalska',
            'due_amount_pln' => 500,
            'paid_amount_pln' => 0,
            'payment_status' => 'pending',
        ]);

        $contract = Contract::create([
            'event_id' => $event->id,
            'contract_type' => Contract::TYPE_INDIVIDUAL,
            'title' => 'Umowa',
            'contract_date' => now()->toDateString(),
            'participant_count' => 1,
            'total_price' => 500,
            'amount_paid' => 0,
            'currency' => 'PLN',
            'status' => 'sent',
            'payment_status' => 'pending',
            'participant_payment_id' => $payment->id,
            'created_by' => $user->id,
        ]);

        ContractPaymentSchedule::create([
            'contract_id' => $contract->id,
            'sort_order' => 1,
            'label' => 'Zaliczka',
            'amount' => 200,
            'paid_amount' => 0,
            'due_date' => now()->addDays(5)->toDateString(),
        ]);

        $participant = EventParticipant::query()->create([
            'event_id' => $event->id,
            'first_name' => 'Ania',
            'last_name' => 'Kowalska',
            'email' => 'rodzic@example.com',
            'source' => EventParticipant::SOURCE_MANUAL,
            'status' => EventParticipant::STATUS_ACTIVE,
            'contract_id' => $contract->id,
            'participant_payment_id' => $payment->id,
        ]);

        $token = app(ParentParticipantAccessService::class)->urlFor($participant);
        $token = $participant->fresh()->parent_access_token;

        $response = $this->post(route('parent.portal.pay', ['token' => $token]));
        $response->assertRedirect();
        $this->assertStringContainsString('/payments/online/', $response->headers->get('Location'));
    }
}
