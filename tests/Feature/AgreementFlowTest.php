<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\ContractTemplate;
use App\Models\Currency;
use App\Models\Event;
use App\Models\EventAgreement;
use App\Models\EventDocument;
use App\Models\EventSettlement;
use App\Models\EventSettlementCost;
use App\Models\EventSettlementParticipantPayment;
use App\Models\EventTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AgreementFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_complete_plan_consents_and_personal_steps_before_payment(): void
    {
        $this->withoutMiddleware();

        $agreement = $this->createAgreement();

        $planResponse = $this->post(route('agreement.flow.plan', ['token' => $agreement->public_token]), [
            'travel_insurance' => 'yes',
        ]);

        $planResponse->assertRedirect(route('agreement.flow.consents', ['token' => $agreement->public_token]));

        $consentsResponse = $this->post(route('agreement.flow.consents.store', ['token' => $agreement->public_token]), [
            'consent_terms' => '1',
            'consent_insurance' => '1',
            'consent_data' => '1',
            'consent_comm' => '1',
        ]);

        $consentsResponse->assertRedirect(route('agreement.flow.personal', ['token' => $agreement->public_token]));

        $personalResponse = $this->post(route('agreement.flow.personal.store', ['token' => $agreement->public_token]), [
            'signer_name' => 'Jan Kowalski',
            'signer_email' => 'jan@example.com',
            'signer_phone' => '500600700',
            'signer_address_street' => 'Polna',
            'signer_address_number' => '1/10',
            'signer_postal_code' => '00-001',
            'signer_city' => 'Warszawa',
            'signer_province' => 'Mazowieckie',
            'participant_name' => 'Jan Kowalski',
            'participant_birth_date' => '2012-01-01',
        ]);

        $personalResponse->assertRedirect(route('agreement.flow.payment', ['token' => $agreement->public_token]));

        $agreement->refresh();

        $this->assertSame('signed', $agreement->status);
        $this->assertSame('pending', $agreement->payment_status);
        $this->assertSame('Jan Kowalski', $agreement->signer_name);
        $this->assertSame('jan@example.com', $agreement->signer_email);
        $this->assertNotNull($agreement->signed_at);
        $this->assertSame('yes', data_get($agreement->meta, 'flow.travel_insurance'));
        $this->assertTrue((bool) data_get($agreement->meta, 'flow.consents.accepted'));
        $this->assertSame('Warszawa', data_get($agreement->meta, 'flow.signer_address.city'));
    }

    public function test_personal_data_regenerates_agreement_body_with_flow_values(): void
    {
        $this->withoutMiddleware();

        $agreement = $this->createAgreement();

        $agreement->contractTemplate->update([
            'content' => implode("\n", [
                'Umowa [NUMER_UMOWY]',
                'Podpisujacy: [PODPISUJACY_IMIE_NAZWISKO]',
                'Adres: [ZAMAWIAJACY_ADRES]',
                'Ubezpieczenie: [DODATKOWE_UBEZPIECZENIE]',
                'Uczestnik: [UCZESTNIK]',
                'Telefon uczestnika: [UCZESTNIK_TELEFON]',
            ]),
        ]);

        $agreement->regenerateAgreementBody();

        $this->post(route('agreement.flow.plan', ['token' => $agreement->public_token]), [
            'travel_insurance' => 'yes',
        ])->assertRedirect(route('agreement.flow.consents', ['token' => $agreement->public_token]));

        $this->post(route('agreement.flow.consents.store', ['token' => $agreement->public_token]), [
            'consent_terms' => '1',
            'consent_insurance' => '1',
            'consent_data' => '1',
            'consent_comm' => '1',
        ])->assertRedirect(route('agreement.flow.personal', ['token' => $agreement->public_token]));

        $this->post(route('agreement.flow.personal.store', ['token' => $agreement->public_token]), [
            'signer_name' => 'Agnieszka Nowak',
            'signer_email' => 'agnieszka@example.com',
            'signer_phone' => '600700800',
            'signer_address_street' => 'Lesna',
            'signer_address_number' => '15A/3',
            'signer_postal_code' => '00-123',
            'signer_city' => 'Warszawa',
            'signer_province' => 'Mazowieckie',
            'participant_name' => 'Agnieszka Nowak',
            'participant_birth_date' => '2010-02-10',
            'participant_phone' => '500500500',
        ])->assertRedirect(route('agreement.flow.payment', ['token' => $agreement->public_token]));

        $agreement->refresh();

        $this->assertStringContainsString('Podpisujacy: Agnieszka Nowak', $agreement->agreement_body);
        $this->assertStringContainsString('Adres: Lesna 15A/3, 00-123 Warszawa, Mazowieckie', $agreement->agreement_body);
        $this->assertStringContainsString('Ubezpieczenie: Tak', $agreement->agreement_body);
        $this->assertStringContainsString('Telefon uczestnika: 500500500', $agreement->agreement_body);
    }

    public function test_demo_payment_marks_agreement_paid_and_syncs_settlement(): void
    {
        $this->withoutMiddleware();

        $agreement = $this->createAgreement([
            'status' => 'signed',
            'signer_name' => 'Jan Kowalski',
            'signer_email' => 'jan@example.com',
            'signed_at' => now(),
        ]);

        $response = $this->post(route('agreement.flow.pay', ['token' => $agreement->public_token]), [
            'payment_method' => 'demo_transfer',
            'accept_demo' => '1',
        ]);

        $response->assertRedirect(route('agreement.flow.success', ['token' => $agreement->public_token]));

        $agreement->refresh();

        $this->assertSame('completed', $agreement->status);
        $this->assertSame('paid', $agreement->payment_status);
        $this->assertSame('demo_transfer', $agreement->payment_method);
        $this->assertSame((float) $agreement->amount_due, (float) $agreement->amount_paid);
        $this->assertNotNull($agreement->paid_at);

        $settlement = EventSettlement::query()
            ->where('event_id', $agreement->event_id)
            ->first();

        $this->assertNotNull($settlement);
        $this->assertSame((float) $agreement->amount_due, (float) $settlement->participant_due_pln);
        $this->assertSame((float) $agreement->amount_paid, (float) $settlement->participant_paid_pln);

        $participantPayment = EventSettlementParticipantPayment::query()
            ->where('settlement_id', $settlement->id)
            ->where('booking_reference', $agreement->agreement_number)
            ->first();

        $this->assertNotNull($participantPayment);
        $this->assertSame('paid', $participantPayment->payment_status);
        $this->assertSame('Jan Kowalski', $participantPayment->participant_name);
    }

    public function test_demo_payment_for_individual_agreement_updates_linked_participant_payment(): void
    {
        $this->withoutMiddleware();

        $agreement = $this->createAgreement([
            'agreement_type' => EventAgreement::TYPE_INDIVIDUAL,
            'participant_count' => 1,
            'status' => 'signed',
            'signer_name' => 'Anna Nowak',
            'signer_email' => 'anna@example.com',
            'participant_name' => 'Anna Nowak',
            'signed_at' => now(),
            'amount_due' => 1300.00,
        ]);

        $settlement = EventSettlement::findOrCreateActiveForEvent($agreement->event);

        $participantPayment = EventSettlementParticipantPayment::create([
            'settlement_id' => $settlement->id,
            'participant_name' => 'Anna Nowak',
            'booking_reference' => 'TMP-123',
            'due_amount_pln' => 1300,
            'paid_amount_pln' => 0,
            'payment_status' => 'pending',
        ]);

        $agreement->update([
            'participant_payment_id' => $participantPayment->id,
        ]);

        $response = $this->post(route('agreement.flow.pay', ['token' => $agreement->public_token]), [
            'payment_method' => 'demo_transfer',
            'accept_demo' => '1',
        ]);

        $response->assertRedirect(route('agreement.flow.success', ['token' => $agreement->public_token]));

        $participantPayment->refresh();

        $this->assertSame('paid', $participantPayment->payment_status);
        $this->assertSame(1300.0, (float) $participantPayment->paid_amount_pln);
        $this->assertSame('Anna Nowak', $participantPayment->participant_name);
        $this->assertSame($agreement->agreement_number, $participantPayment->booking_reference);
    }

    public function test_partial_group_agreement_payment_syncs_settlement_totals(): void
    {
        $this->withoutMiddleware();

        $agreement = $this->createAgreement([
            'agreement_type' => EventAgreement::TYPE_GROUP,
            'status' => 'sent',
            'amount_due' => 2400.00,
            'amount_paid' => 600.00,
            'payment_status' => 'pending',
            'payment_method' => 'other',
            'paid_at' => now(),
        ]);

        $agreement->refresh();

        $settlement = EventSettlement::query()
            ->where('event_id', $agreement->event_id)
            ->first();

        $this->assertNotNull($settlement);
        $this->assertSame(2400.0, (float) $settlement->participant_due_pln);
        $this->assertSame(600.0, (float) $settlement->participant_paid_pln);

        $participantPayment = EventSettlementParticipantPayment::query()
            ->where('settlement_id', $settlement->id)
            ->where('booking_reference', $agreement->agreement_number)
            ->first();

        $this->assertNotNull($participantPayment);
        $this->assertSame('partial', $participantPayment->payment_status);
        $this->assertSame(600.0, (float) $participantPayment->paid_amount_pln);

        $agreement->refresh();
        $this->assertSame($participantPayment->id, $agreement->participant_payment_id);
    }

    public function test_paid_advance_amount_is_included_in_actual_settlement_cost(): void
    {
        $this->withoutMiddleware();

        $agreement = $this->createAgreement();
        $settlement = EventSettlement::findOrCreateActiveForEvent($agreement->event);

        EventSettlementCost::create([
            'settlement_id' => $settlement->id,
            'source_type' => 'manual',
            'name' => 'Hotel - zaliczka',
            'planned_amount' => 1000.00,
            'planned_rate' => 1,
            'planned_amount_pln' => 1000.00,
            'advance_type' => 'advance',
            'advance_amount' => 300.00,
            'payment_status' => 'advance_paid',
            'paid_by' => 'office',
        ]);

        $settlement->refresh();

        $this->assertSame(300.0, (float) $settlement->actual_cost_pln);
    }

    public function test_refresh_derived_data_syncs_existing_paid_agreement_into_settlement_payments(): void
    {
        $this->withoutMiddleware();

        $agreement = $this->createAgreement([
            'agreement_type' => EventAgreement::TYPE_INDIVIDUAL,
            'participant_count' => 1,
            'status' => 'sent',
            'payment_status' => 'paid',
            'amount_due' => 707.88,
            'amount_paid' => 707.88,
            'paid_at' => now(),
            'participant_name' => 'Opłacony Uczestnik',
        ]);

        $settlement = EventSettlement::findOrCreateActiveForEvent($agreement->event);
        $settlement->refreshDerivedData();

        $participantPayment = EventSettlementParticipantPayment::query()
            ->where('settlement_id', $settlement->id)
            ->where('booking_reference', $agreement->agreement_number)
            ->first();

        $this->assertNotNull($participantPayment);
        $this->assertSame('paid', $participantPayment->payment_status);
        $this->assertSame(707.88, (float) $participantPayment->paid_amount_pln);

        $settlement->refresh();
        $this->assertSame(707.88, (float) $settlement->participant_due_pln);
        $this->assertSame(707.88, (float) $settlement->participant_paid_pln);
    }

    public function test_pilot_cash_uses_actual_paid_amount_for_advance_paid_costs(): void
    {
        $this->withoutMiddleware();

        $agreement = $this->createAgreement();
        $settlement = EventSettlement::findOrCreateActiveForEvent($agreement->event);
        $pln = Currency::create([
            'name' => 'Polski złoty',
            'symbol' => 'PLN',
            'exchange_rate' => 1,
        ]);

        EventSettlementCost::create([
            'settlement_id' => $settlement->id,
            'source_type' => 'manual',
            'name' => 'Pilot przewodnik',
            'planned_amount' => 300.00,
            'planned_currency_id' => $pln->id,
            'planned_rate' => 1,
            'planned_amount_pln' => 300.00,
            'paid_by' => 'pilot',
            'advance_type' => 'full',
            'payment_status' => 'planned',
        ]);

        EventSettlementCost::create([
            'settlement_id' => $settlement->id,
            'source_type' => 'manual',
            'name' => 'Pilot bilety zaliczka',
            'planned_amount' => 627.00,
            'planned_currency_id' => $pln->id,
            'planned_rate' => 1,
            'planned_amount_pln' => 627.00,
            'actual_amount' => 400.00,
            'actual_currency_id' => $pln->id,
            'actual_rate' => 1,
            'actual_amount_pln' => 400.00,
            'advance_amount' => 200.00,
            'paid_by' => 'pilot',
            'advance_type' => 'advance',
            'payment_status' => 'advance_paid',
        ]);

        $settlement->recalculatePilotCash();

        $pilotCash = $settlement->pilotCashPreparations()->first();

        $this->assertNotNull($pilotCash);
        $this->assertSame(700.0, (float) $pilotCash->calculated_amount);
        $this->assertSame(700.0, (float) $pilotCash->pln_equivalent);
    }

    public function test_individual_agreement_without_settlement_uses_event_share_amount(): void
    {
        $this->withoutMiddleware();

        $agreement = $this->createAgreement([
            'agreement_type' => EventAgreement::TYPE_INDIVIDUAL,
            'participant_count' => 1,
            'amount_due' => 0,
            'status' => 'sent',
        ]);

        $agreement->event->update([
            'participant_count' => 10,
            'total_cost' => 10000,
        ]);

        $this->post(route('agreement.flow.plan', ['token' => $agreement->public_token]), [
            'travel_insurance' => 'no',
        ])->assertRedirect(route('agreement.flow.consents', ['token' => $agreement->public_token]));

        $this->post(route('agreement.flow.consents.store', ['token' => $agreement->public_token]), [
            'consent_terms' => '1',
            'consent_insurance' => '1',
            'consent_data' => '1',
            'consent_comm' => '1',
        ])->assertRedirect(route('agreement.flow.personal', ['token' => $agreement->public_token]));

        $this->post(route('agreement.flow.personal.store', ['token' => $agreement->public_token]), [
            'signer_name' => 'Piotr Testowy',
            'signer_email' => 'piotr@example.com',
            'signer_phone' => '500600700',
            'signer_address_street' => 'Polna',
            'signer_address_number' => '2A/5',
            'signer_postal_code' => '00-002',
            'signer_city' => 'Warszawa',
            'participant_name' => 'Piotr Testowy',
            'participant_birth_date' => '2010-01-01',
        ])->assertRedirect(route('agreement.flow.payment', ['token' => $agreement->public_token]));

        $agreement->refresh();

        $this->assertSame('signed', $agreement->status);
        $this->assertSame(1000.0, (float) $agreement->amount_due);

        $this->post(route('agreement.flow.pay', ['token' => $agreement->public_token]), [
            'payment_method' => 'demo_transfer',
            'accept_demo' => '1',
        ])->assertRedirect(route('agreement.flow.success', ['token' => $agreement->public_token]));

        $agreement->refresh();

        $this->assertSame('paid', $agreement->payment_status);
        $this->assertSame(1000.0, (float) $agreement->amount_paid);
    }

    private function createAgreement(array $overrides = []): EventAgreement|Contract
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $template = EventTemplate::factory()->create([
            'name' => 'Wycieczka testowa',
            'slug' => 'wycieczka-testowa-'.uniqid(),
            'duration_days' => 2,
        ]);

        $event = Event::create([
            'event_template_id' => $template->id,
            'name' => 'Wyjazd testowy',
            'client_name' => 'Jan Kowalski',
            'client_email' => 'jan@example.com',
            'client_phone' => '500600700',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'participant_count' => 20,
            'total_cost' => 1000,
            'status' => 'inquiry', // allowed value
        ]);

        $contractTemplate = ContractTemplate::create([
            'name' => 'Szablon podstawowy',
            'content' => "Umowa nr {{agreement_number}}\nKlient: {{customer_name}}\nKwota: {{amount_due}} {{currency}}",
        ]);

        return $this->createAgreementRecord(array_merge([
            'event_id' => $event->id,
            'contract_template_id' => $contractTemplate->id,
            'title' => 'Umowa imprezy',
            'customer_name' => 'Jan Kowalski',
            'customer_email' => 'jan@example.com',
            'customer_phone' => '500600700',
            'event_name' => $event->name,
            'event_start_date' => $event->start_date,
            'event_end_date' => $event->end_date,
            'participant_count' => 20,
            'amount_due' => 2500.00,
            'currency' => 'PLN',
            'created_by' => $user->id,
        ], $overrides))->fresh();
    }

    private function createAgreementRecord(array $attributes): EventAgreement|Contract
    {
        $model = Schema::hasTable('contracts') ? Contract::class : EventAgreement::class;

        return $model::create($attributes);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Contract>|\Illuminate\Database\Eloquent\Builder<EventAgreement>
     */
    private function agreementQueryForEvent(int $eventId)
    {
        $model = Schema::hasTable('contracts') ? Contract::class : EventAgreement::class;

        return $model::query()->where('event_id', $eventId);
    }

    public function test_public_contract_link_is_accessible_when_only_in_contracts_table(): void
    {
        $this->withoutMiddleware();

        if (! Schema::hasTable('contracts')) {
            $this->markTestSkipped('Tabela contracts nie istnieje w tym środowisku testowym.');
        }

        $agreement = $this->createAgreement([
            'status' => 'sent',
            'payment_status' => 'pending',
        ]);

        $this->assertInstanceOf(Contract::class, $agreement);
        $this->assertSame(0, EventAgreement::where('public_token', $agreement->public_token)->count());

        $response = $this->get(route('agreement.flow.show', ['token' => $agreement->public_token]));

        $response->assertSuccessful();
    }

    public function test_individual_agreement_template_clones_for_each_participant(): void
    {
        $this->withoutMiddleware();

        $user = User::factory()->create();
        $this->actingAs($user);

        $template = EventTemplate::factory()->create();

        $event = Event::create([
            'event_template_id' => $template->id,
            'name' => 'Impreza testowa',
            'client_name' => 'Klient Test',
            'client_email' => 'klient@test.com',
            'client_phone' => '500600700',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'participant_count' => 3,
            'total_cost' => 1500.00,
            'status' => 'confirmed', // allowed value
        ]);

        $contractTemplate = ContractTemplate::create([
            'name' => 'Umowa testowa',
            'content' => 'Test agreement body',
        ]);

        // Utwórz szablonową umowę indywidualną
        $templateAgreement = $this->createAgreementRecord([
            'event_id' => $event->id,
            'contract_template_id' => $contractTemplate->id,
            'agreement_type' => EventAgreement::TYPE_INDIVIDUAL,
            'title' => 'Umowa uczestnika (szablon)',
            'agreement_date' => now()->toDateString(),
            'event_name' => $event->name,
            'event_start_date' => $event->start_date,
            'event_end_date' => $event->end_date,
            'customer_name' => $event->client_name,
            'customer_email' => $event->client_email,
            'customer_phone' => $event->client_phone,
            'participant_count' => 1,
            'amount_due' => 500.00,
            'currency' => 'PLN',
            'status' => 'template', // allowed value
            'payment_status' => 'pending',
            'created_by' => $user->id,
            'meta' => ['is_individual_template' => true, 'expected_participants' => 3],
        ]);

        $templateToken = $templateAgreement->public_token;

        // Pierwszy uczestnik wchodzi w szablon
        $response1 = $this->get(route('agreement.flow.show', ['token' => $templateToken]));
        $this->assertEquals(302, $response1->getStatusCode()); // Redirect

        // W bazie są teraz dwie umowy: szablon + klon dla uczestnika 1
        $this->assertEquals(2, $this->agreementQueryForEvent($event->id)->count());
        $clone1 = $this->agreementQueryForEvent($event->id)
            ->where('status', 'draft')
            ->first();

        $this->assertNotNull($clone1);
        $this->assertEquals('draft', $clone1->status);
        $this->assertNotSame($templateToken, $clone1->public_token);
        $this->assertEquals($templateAgreement->id, $clone1->meta['parent_template_id']);
        $this->assertEquals(500.00, $clone1->amount_due);

        // Drugi uczestnik wchodzi w ten sam szablon
        $response2 = $this->get(route('agreement.flow.show', ['token' => $templateToken]));
        $this->assertEquals(302, $response2->getStatusCode()); // Redirect

        // Teraz są trzy umowy: szablon + dwa klony
        $this->assertEquals(3, $this->agreementQueryForEvent($event->id)->count());

        $clone2 = $this->agreementQueryForEvent($event->id)
            ->where('status', 'draft')
            ->where('id', '!=', $clone1->id)
            ->first();

        $this->assertNotNull($clone2);
        $this->assertNotSame($templateToken, $clone2->public_token);
        $this->assertNotSame($clone1->public_token, $clone2->public_token);
        $this->assertEquals($templateAgreement->id, $clone2->meta['parent_template_id']);
    }

    public function test_individual_template_clone_copies_attachments(): void
    {
        $this->withoutMiddleware();

        $user = User::factory()->create();
        $this->actingAs($user);

        $template = EventTemplate::factory()->create();

        $event = Event::create([
            'event_template_id' => $template->id,
            'name' => 'Impreza z zalacznikami',
            'client_name' => 'Szkola Testowa',
            'client_email' => 'szkola@test.com',
            'client_phone' => '500111222',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'participant_count' => 2,
            'total_cost' => 2000.00,
            'status' => 'confirmed', // allowed value
        ]);

        $contractTemplate = ContractTemplate::create([
            'name' => 'Umowa test zalaczniki',
            'content' => 'Test agreement body',
        ]);

        $templateAgreement = $this->createAgreementRecord([
            'event_id' => $event->id,
            'contract_template_id' => $contractTemplate->id,
            'agreement_type' => EventAgreement::TYPE_INDIVIDUAL,
            'title' => 'Umowa uczestnika (szablon)',
            'agreement_date' => now()->toDateString(),
            'event_name' => $event->name,
            'event_start_date' => $event->start_date,
            'event_end_date' => $event->end_date,
            'customer_name' => $event->client_name,
            'customer_email' => $event->client_email,
            'customer_phone' => $event->client_phone,
            'participant_count' => 1,
            'amount_due' => 1000.00,
            'currency' => 'PLN',
            'status' => 'template', // allowed value
            'payment_status' => 'pending',
            'created_by' => $user->id,
            'attachments' => [
                'event-agreements/warunki-uczestnictwa.pdf',
                'event-agreements/program.pdf',
            ],
            'meta' => ['is_individual_template' => true, 'expected_participants' => 2],
        ]);

        $this->get(route('agreement.flow.show', ['token' => $templateAgreement->public_token]))
            ->assertRedirect();

        $clone = $this->agreementQueryForEvent($event->id)
            ->where('status', 'draft')
            ->where('id', '!=', $templateAgreement->id)
            ->first();

        $this->assertNotNull($clone);
        $this->assertSame($templateAgreement->attachments, $clone->attachments);
    }

    public function test_event_builds_individual_agreement_payment_report_from_concluded_contracts(): void
    {
        $this->withoutMiddleware();

        $agreement = $this->createAgreement([
            'agreement_type' => EventAgreement::TYPE_INDIVIDUAL,
            'status' => 'signed',
            'payment_status' => 'pending',
            'participant_count' => 1,
            'participant_name' => 'Adam Pierwszy',
            'signer_name' => 'Rodzic Pierwszy',
            'signer_email' => 'rodzic1@example.com',
            'amount_due' => 1000.00,
            'amount_paid' => 250.00,
        ]);

        $this->createAgreementRecord([
            'event_id' => $agreement->event_id,
            'contract_template_id' => $agreement->contract_template_id,
            'agreement_type' => EventAgreement::TYPE_INDIVIDUAL,
            'title' => 'Umowa 2',
            'agreement_date' => now()->toDateString(),
            'event_name' => $agreement->event_name,
            'event_start_date' => $agreement->event_start_date,
            'event_end_date' => $agreement->event_end_date,
            'customer_name' => $agreement->customer_name,
            'customer_email' => $agreement->customer_email,
            'customer_phone' => $agreement->customer_phone,
            'participant_name' => 'Beata Druga',
            'signer_name' => 'Rodzic Drugi',
            'signer_email' => 'rodzic2@example.com',
            'participant_count' => 1,
            'amount_due' => 1200.00,
            'amount_paid' => 1200.00,
            'currency' => 'PLN',
            'status' => 'completed',
            'payment_status' => 'paid',
            'created_by' => $agreement->created_by,
        ]);

        $this->createAgreementRecord([
            'event_id' => $agreement->event_id,
            'contract_template_id' => $agreement->contract_template_id,
            'agreement_type' => EventAgreement::TYPE_GROUP,
            'title' => 'Umowa grupowa',
            'agreement_date' => now()->toDateString(),
            'event_name' => $agreement->event_name,
            'event_start_date' => $agreement->event_start_date,
            'event_end_date' => $agreement->event_end_date,
            'customer_name' => $agreement->customer_name,
            'customer_email' => $agreement->customer_email,
            'customer_phone' => $agreement->customer_phone,
            'participant_count' => 20,
            'amount_due' => 3000.00,
            'amount_paid' => 0.00,
            'currency' => 'PLN',
            'status' => 'sent',
            'payment_status' => 'pending',
            'created_by' => $agreement->created_by,
        ]);

        $this->createAgreementRecord([
            'event_id' => $agreement->event_id,
            'contract_template_id' => $agreement->contract_template_id,
            'agreement_type' => EventAgreement::TYPE_INDIVIDUAL,
            'title' => 'Szablon indywidualny',
            'agreement_date' => now()->toDateString(),
            'event_name' => $agreement->event_name,
            'event_start_date' => $agreement->event_start_date,
            'event_end_date' => $agreement->event_end_date,
            'customer_name' => $agreement->customer_name,
            'customer_email' => $agreement->customer_email,
            'customer_phone' => $agreement->customer_phone,
            'participant_count' => 1,
            'amount_due' => 900.00,
            'amount_paid' => 0.00,
            'currency' => 'PLN',
            'status' => 'template', // allowed value
            'payment_status' => 'pending',
            'created_by' => $agreement->created_by,
        ]);

        $report = $agreement->event->fresh()->buildIndividualAgreementReport();

        $this->assertSame(2, $report['summary']['total']);
        $this->assertSame(20, $report['summary']['target_participants']);
        $this->assertSame(1, $report['summary']['paid']);
        $this->assertSame(19, $report['summary']['unpaid']);
        $this->assertSame(2200.0, (float) $report['summary']['amount_due']);
        $this->assertSame(1450.0, (float) $report['summary']['amount_paid']);
        $this->assertSame(750.0, (float) $report['summary']['amount_remaining']);
        $this->assertSame('1/20', $report['summary']['payment_progress_label']);
        $this->assertCount(2, $report['rows']);
        $this->assertSame('Adam Pierwszy', $report['rows'][0]['participant_name']);
        $this->assertSame('Rodzic Pierwszy', $report['rows'][0]['payer_name']);
    }

    public function test_individual_agreement_report_can_be_exported_to_csv(): void
    {
        $agreement = $this->createAgreement([
            'agreement_type' => EventAgreement::TYPE_INDIVIDUAL,
            'status' => 'completed',
            'payment_status' => 'paid',
            'participant_count' => 1,
            'participant_name' => 'Eksportowany Uczestnik',
            'signer_name' => 'Eksportowany Płatnik',
            'signer_email' => 'platnik@example.com',
            'amount_due' => 1500.00,
            'amount_paid' => 1500.00,
        ]);

        $response = $this->get(route('admin.events.individual-agreements.export', [
            'event' => $agreement->event_id,
            'format' => 'csv',
        ]));

        $response->assertOk();
        $response->assertHeader('content-disposition');
        $this->assertStringContainsString('event-'.$agreement->event_id.'-individual-agreements-report.csv', (string) $response->headers->get('content-disposition'));
    }

    public function test_event_offer_word_can_be_generated_from_dashboard(): void
    {
        $agreement = $this->createAgreement([
            'agreement_type' => EventAgreement::TYPE_GROUP,
            'status' => 'sent',
        ]);

        $response = $this->get(route('admin.events.offer.word', [
            'event' => $agreement->event_id,
        ]));

        $response->assertOk();
        $response->assertHeader('content-disposition');
        $this->assertStringContainsString('.docx', (string) $response->headers->get('content-disposition'));

        $offerDocument = EventDocument::query()
            ->where('event_id', $agreement->event_id)
            ->where('is_offer', true)
            ->latest('id')
            ->first();

        $this->assertNotNull($offerDocument);
        $this->assertSame('draft', $offerDocument->offer_status);
        $this->assertStringContainsString('event-offers/', (string) $offerDocument->file_path);
    }
}
