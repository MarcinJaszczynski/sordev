<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\ContractPaymentSchedule;
use App\Models\ContractTemplate;
use App\Models\Event;
use App\Models\EventPortalAccess;
use App\Models\EventTemplate;
use App\Models\User;
use App\Services\ContractPaymentScheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class IndividualContractInviteFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('contracts') || ! Schema::hasTable('contract_payment_schedules')) {
            $this->markTestSkipped('Brak tabel contracts / contract_payment_schedules.');
        }

        Role::firstOrCreate(['name' => 'client_participant', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'client_guardian', 'guard_name' => 'web']);
    }

    public function test_template_get_does_not_create_contract_personal_submit_does(): void
    {
        $this->withoutMiddleware();
        Mail::fake();

        $template = $this->makeIndividualTemplate();

        $this->get(route('agreement.flow.show', ['token' => $template->public_token]))
            ->assertSuccessful();

        $this->assertSame(1, Contract::query()->where('event_id', $template->event_id)->count());

        $this->completeFlowUntilPersonal($template->public_token, 'a@example.com', 'Uczestnik A');

        $this->assertSame(2, Contract::query()->where('event_id', $template->event_id)->count());

        $signed = Contract::query()
            ->where('event_id', $template->event_id)
            ->where('status', 'signed')
            ->first();

        $this->assertNotNull($signed);
        $this->assertSame($template->id, data_get($signed->meta, 'parent_template_id'));
        $this->assertSame('a@example.com', $signed->signer_email);
        $this->assertNotNull(data_get($signed->meta, 'flow.portal_provisioned_at'));

        if (Schema::hasTable('event_portal_accesses')) {
            $this->assertTrue(
                EventPortalAccess::query()
                    ->where('event_id', $template->event_id)
                    ->where('contract_id', $signed->id)
                    ->exists()
            );
        }
    }

    public function test_group_participants_template_same_deferred_clone_flow(): void
    {
        $this->withoutMiddleware();
        Mail::fake();

        $template = $this->makeIndividualTemplate([
            'meta' => [
                'is_individual_template' => true,
                'generation_mode' => 'group_participants',
            ],
        ]);

        $this->get(route('agreement.flow.show', ['token' => $template->public_token]))
            ->assertSuccessful();
        $this->assertSame(1, Contract::query()->where('event_id', $template->event_id)->count());

        $this->completeFlowUntilPersonal($template->public_token, 'rodzic@example.com', 'Dziecko');

        $this->assertSame(2, Contract::query()->where('event_id', $template->event_id)->count());
        $this->assertSame(
            1,
            Contract::query()->where('event_id', $template->event_id)->where('status', 'signed')->count()
        );
    }

    public function test_first_installment_only_then_remaining_schedule_unpaid(): void
    {
        $this->withoutMiddleware();
        Mail::fake();

        $template = $this->makeIndividualTemplate([
            'amount_due' => 1000,
            'payment_scheme' => Contract::PAYMENT_SCHEME_INSTALLMENTS,
            'start_offset_days' => 60,
        ]);

        $from1 = now()->toDateString();
        $to1 = now()->addDays(3)->toDateString();
        $from2 = now()->addDays(20)->toDateString();
        $to2 = now()->addDays(30)->toDateString();

        app(ContractPaymentScheduleService::class)->syncForContract($template, [
            [
                'label' => 'Zaliczka',
                'amount' => 300,
                'due_from' => $from1,
                'due_to' => $to1,
                'due_date' => $to1,
            ],
            [
                'label' => 'Dopłata',
                'amount' => 700,
                'due_from' => $from2,
                'due_to' => $to2,
                'due_date' => $to2,
            ],
        ], Contract::PAYMENT_SCHEME_INSTALLMENTS);

        $template->refresh();
        $schedules = $template->paymentSchedules()->orderBy('sort_order')->get();
        $this->assertCount(2, $schedules);
        $this->assertSame($to1, optional($schedules[0]->due_to)->toDateString() ?? optional($schedules[0]->due_date)->toDateString());
        $this->assertSame($from1, optional($schedules[0]->due_from)->toDateString());

        $this->completeFlowUntilPersonal($template->public_token, 'platnik@example.com', 'Uczeń');

        $contract = Contract::query()
            ->where('event_id', $template->event_id)
            ->where('status', 'signed')
            ->firstOrFail();

        $this->post(route('agreement.flow.pay', ['token' => $contract->public_token]), [
            'payment_method' => 'demo_transfer',
            'accept_demo' => '1',
        ])->assertRedirect(route('agreement.flow.success', ['token' => $contract->public_token]));

        $contract->refresh();
        $this->assertSame('partial', $contract->payment_status);
        $this->assertEqualsWithDelta(300.0, (float) $contract->amount_paid, 0.01);

        $unpaid = $contract->paymentSchedules()
            ->orderBy('sort_order')
            ->get()
            ->filter(fn (ContractPaymentSchedule $s) => ((float) $s->amount - (float) ($s->paid_amount ?? 0)) > 0.009);

        $this->assertCount(1, $unpaid);
        $this->assertSame('Dopłata', $unpaid->first()->label);
        $this->assertSame(
            $to2,
            optional($unpaid->first()->due_to)->toDateString() ?? optional($unpaid->first()->due_date)->toDateString()
        );
    }

    public function test_due_from_to_syncs_due_date_on_normalize(): void
    {
        $normalized = app(\App\Services\ContractGroupPricingService::class)->normalizedSchedules([
            [
                'label' => 'Rata',
                'amount' => 100,
                'due_from' => '2026-09-01',
                'due_to' => '2026-09-15',
            ],
        ]);

        $this->assertSame('2026-09-15', $normalized[0]['due_date']);
        $this->assertSame('2026-09-01', $normalized[0]['due_from']);
        $this->assertSame('2026-09-15', $normalized[0]['due_to']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeIndividualTemplate(array $overrides = []): Contract
    {
        $user = User::factory()->create();
        $eventTemplate = EventTemplate::factory()->create();
        $startOffset = (int) ($overrides['start_offset_days'] ?? 10);
        unset($overrides['start_offset_days']);

        $event = Event::create([
            'event_template_id' => $eventTemplate->id,
            'name' => 'Impreza invite flow',
            'client_name' => 'Biuro',
            'client_email' => 'biuro@test.com',
            'client_phone' => '500600700',
            'start_date' => now()->addDays($startOffset)->toDateString(),
            'end_date' => now()->addDays($startOffset + 5)->toDateString(),
            'participant_count' => 10,
            'total_cost' => 5000,
            'status' => 'confirmed',
            'created_by' => $user->id,
        ]);

        $contractTemplate = ContractTemplate::create([
            'name' => 'Szablon content',
            'content' => 'Umowa [NUMER_UMOWY] [PODPISUJACY_IMIE_NAZWISKO]',
        ]);

        $meta = array_merge(
            ['is_individual_template' => true, 'generation_mode' => 'individual'],
            is_array($overrides['meta'] ?? null) ? $overrides['meta'] : [],
        );
        unset($overrides['meta']);

        return Contract::query()->create(array_merge([
            'event_id' => $event->id,
            'contract_template_id' => $contractTemplate->id,
            'agreement_type' => Contract::TYPE_INDIVIDUAL,
            'title' => 'Szablon indywidualny',
            'agreement_date' => now()->toDateString(),
            'event_name' => $event->name,
            'event_start_date' => $event->start_date,
            'event_end_date' => $event->end_date,
            'customer_name' => $event->client_name,
            'customer_email' => $event->client_email,
            'customer_phone' => $event->client_phone,
            'participant_count' => 1,
            'amount_due' => 500,
            'currency' => 'PLN',
            'status' => 'template',
            'payment_status' => 'pending',
            'payment_scheme' => Contract::PAYMENT_SCHEME_LUMP_SUM,
            'public_token' => (string) Str::uuid(),
            'created_by' => $user->id,
            'meta' => $meta,
        ], $overrides));
    }

    private function completeFlowUntilPersonal(string $token, string $email, string $participantName): void
    {
        $this->post(route('agreement.flow.plan', ['token' => $token]), [
            'travel_insurance' => 'no',
        ])->assertRedirect(route('agreement.flow.consents', ['token' => $token]));

        $this->post(route('agreement.flow.consents.store', ['token' => $token]), [
            'consent_terms' => '1',
            'consent_insurance' => '1',
            'consent_data' => '1',
            'consent_comm' => '1',
        ])->assertRedirect(route('agreement.flow.personal', ['token' => $token]));

        $response = $this->post(route('agreement.flow.personal.store', ['token' => $token]), [
            'signer_name' => 'Płatnik Test',
            'signer_email' => $email,
            'signer_phone' => '500600700',
            'signer_address_street' => 'Polna',
            'signer_address_number' => '1',
            'signer_postal_code' => '00-001',
            'signer_city' => 'Warszawa',
            'participant_name' => $participantName,
            'participant_birth_date' => '2012-01-01',
        ]);

        $signed = Contract::query()->where('signer_email', $email)->where('status', 'signed')->first();
        $this->assertNotNull($signed);
        $response->assertRedirect(route('agreement.flow.payment', ['token' => $signed->public_token]));
    }
}
