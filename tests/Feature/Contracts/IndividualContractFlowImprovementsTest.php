<?php

declare(strict_types=1);

namespace Tests\Feature\Contracts;

use App\Actions\Contracts\CreateContractAnnexesAction;
use App\Actions\Contracts\GenerateEventContractAction;
use App\Models\Contract;
use App\Models\ContractTemplate;
use App\Models\ContractVariant;
use App\Models\Event;
use App\Models\User;
use App\Services\IndividualPaymentSchedulePolicy;
use App\Services\PublicAgreementResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class IndividualContractFlowImprovementsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('contracts')) {
            $this->markTestSkipped('Brak tabeli contracts.');
        }
    }

    public function test_individual_ignores_stale_group_total_without_override(): void
    {
        $event = Event::factory()->create([
            'participant_count' => 10,
            'start_date' => now()->addDays(60)->toDateString(),
            'end_date' => now()->addDays(65)->toDateString(),
        ]);

        $result = app(GenerateEventContractAction::class)($event, [
            'generation_mode' => GenerateEventContractAction::MODE_INDIVIDUAL,
            'title' => 'Umowa uczestnika',
            'paying_participants_count' => 10,
            'participants_on_contract' => 1,
            'unit_price' => 3050,
            // Stara suma grupy — bez override nie może przejść.
            'amount_due' => 30500,
            'override_amount_due' => false,
            'payment_scheme' => Contract::PAYMENT_SCHEME_LUMP_SUM,
            'payment_schedules' => [],
            'use_event_payment_template' => false,
        ], null);

        $this->assertSame(3050.0, (float) $result['primary']->amount_due);
        $this->assertSame(Contract::PAYMENT_SCHEME_INSTALLMENTS, $result['primary']->payment_scheme);
        $this->assertGreaterThanOrEqual(2, $result['primary']->paymentSchedules()->count());
    }

    public function test_policy_auto_splits_full_amount_when_more_than_30_days_before_start(): void
    {
        $event = Event::factory()->create([
            'start_date' => now()->addDays(90)->toDateString(),
        ]);

        $this->assertTrue(app(IndividualPaymentSchedulePolicy::class)->requiresInstallments($event));

        $enforced = app(IndividualPaymentSchedulePolicy::class)->enforceForIndividual(
            $event,
            1000,
            Contract::PAYMENT_SCHEME_LUMP_SUM,
            [],
        );

        $this->assertSame(Contract::PAYMENT_SCHEME_INSTALLMENTS, $enforced['payment_scheme']);
        $this->assertGreaterThanOrEqual(2, count($enforced['payment_schedules']));
        $this->assertNotNull($enforced['policy_message']);

        $fixed = app(IndividualPaymentSchedulePolicy::class)->enforceForIndividual(
            $event,
            1000,
            Contract::PAYMENT_SCHEME_INSTALLMENTS,
            [
                [
                    'label' => 'Całość za wcześnie',
                    'amount' => 1000,
                    'due_date' => now()->toDateString(),
                    'paid_by' => 'office',
                ],
                [
                    'label' => 'EUR w autokarze',
                    'amount' => 0,
                    'amount_foreign' => 50,
                    'currency_code' => 'EUR',
                    'paid_by' => 'pilot',
                    'due_date' => $event->start_date?->toDateString(),
                ],
            ],
        );

        $this->assertSame(Contract::PAYMENT_SCHEME_INSTALLMENTS, $fixed['payment_scheme']);
        $this->assertTrue(collect($fixed['payment_schedules'])->contains(
            fn (array $row): bool => (float) ($row['amount_foreign'] ?? 0) > 0
        ));
        $plnSum = collect($fixed['payment_schedules'])->sum(fn (array $row): float => (float) ($row['amount'] ?? 0));
        $this->assertEqualsWithDelta(1000.0, $plnSum, 0.05);
    }

    public function test_create_from_template_copies_tfg_structure(): void
    {
        if (! Schema::hasTable('contract_variants')) {
            $this->markTestSkipped('Brak tabeli contract_variants.');
        }

        $event = Event::factory()->create(['participant_count' => 5]);
        $template = Contract::create([
            'event_id' => $event->id,
            'contract_type' => Contract::TYPE_INDIVIDUAL,
            'title' => 'Szablon',
            'status' => 'template',
            'payment_status' => 'pending',
            'participant_count' => 1,
            'amount_due' => 1000,
            'currency' => 'PLN',
            'subject_code' => 'IT',
            'payment_method_code' => 'WPLATAPRZED',
        ]);

        ContractVariant::query()->create([
            'contract_id' => $template->id,
            'travelers_count' => 1,
            'starts_at' => $event->start_date,
            'ends_at' => $event->end_date,
            'sort_order' => 0,
        ]);

        $clone = app(PublicAgreementResolver::class)->createFromTemplate($template->fresh(['variants']));

        $this->assertInstanceOf(Contract::class, $clone);
        $this->assertGreaterThanOrEqual(1, $clone->variants()->count());
        $this->assertSame('IT', $clone->subject_code);
    }

    public function test_bulk_annex_copies_participant_data(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $event = Event::factory()->create(['participant_count' => 3]);
        $parents = collect([
            Contract::create([
                'event_id' => $event->id,
                'contract_type' => Contract::TYPE_INDIVIDUAL,
                'title' => 'Umowa A',
                'status' => 'signed',
                'payment_status' => 'pending',
                'payment_scheme' => Contract::PAYMENT_SCHEME_LUMP_SUM,
                'participant_count' => 1,
                'amount_due' => 1100,
                'currency' => 'PLN',
                'customer_name' => 'Rodzic A',
                'participant_name' => 'Dziecko A',
                'signer_email' => 'a@test.local',
            ]),
            Contract::create([
                'event_id' => $event->id,
                'contract_type' => Contract::TYPE_INDIVIDUAL,
                'title' => 'Umowa B',
                'status' => 'sent',
                'payment_status' => 'pending',
                'payment_scheme' => Contract::PAYMENT_SCHEME_LUMP_SUM,
                'participant_count' => 1,
                'amount_due' => 1200,
                'currency' => 'PLN',
                'customer_name' => 'Rodzic B',
                'participant_name' => 'Dziecko B',
                'signer_email' => 'b@test.local',
            ]),
        ]);

        $result = app(CreateContractAnnexesAction::class)($parents->all(), [
            'agreement_date' => now()->toDateString(),
            'annex_change_types' => [Contract::ANNEX_CHANGE_PROGRAM],
            'annex_program_change_notes' => 'Zmiana hotelu',
            'body_edit_mode' => Contract::BODY_EDIT_TEMPLATE,
        ]);

        $this->assertCount(2, $result['created']);
        $this->assertTrue((bool) data_get($result['created'][0]->meta, 'is_annex'));
        $this->assertSame('Dziecko A', $result['created'][0]->participant_name);
        $this->assertSame('Dziecko B', $result['created'][1]->participant_name);
        $this->assertSame(1100.0, (float) $result['created'][0]->amount_due);
        $this->assertSame(1200.0, (float) $result['created'][1]->amount_due);
    }

    public function test_event_tfg_defaults_override_heuristics(): void
    {
        if (! Schema::hasColumn('events', 'tfg_defaults')) {
            $this->markTestSkipped('Brak kolumny tfg_defaults.');
        }

        $event = Event::factory()->create([
            'participant_count' => 12,
            'tfg_defaults' => [
                'subject_code' => 'WYC',
                'tfg_locality' => 'Paryż',
                'payment_method_code' => 'WPLATAPRZED',
            ],
        ]);

        $defaults = app(\App\Services\ContractTfgSetupService::class)->defaultsFromEvent($event);

        $this->assertSame('WYC', $defaults['subject_code']);
        $this->assertSame('Paryż', $defaults['tfg_locality']);
        $this->assertSame(12, (int) $defaults['tfg_travelers_count']);
    }
}
