<?php

declare(strict_types=1);

namespace Tests\Feature\Contracts;

use App\Actions\Contracts\GenerateEventContractAction;
use App\Models\Contract;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GenerateEventContractActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('contracts')) {
            $this->markTestSkipped('Brak tabeli contracts.');
        }
    }

    public function test_generates_group_ordering_contract_with_public_link(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create([
            'participant_count' => 10,
            'client_name' => 'Szkoła',
            'client_email' => 'szkola@test.local',
        ]);

        $result = app(GenerateEventContractAction::class)($event, [
            'generation_mode' => GenerateEventContractAction::MODE_GROUP_ORDERING,
            'title' => 'Umowa test',
            'participant_count' => 10,
            'unit_price' => 100,
            'amount_due' => 1000,
            'payment_scheme' => Contract::PAYMENT_SCHEME_LUMP_SUM,
            'payment_schedules' => [],
            'use_event_payment_template' => false,
            'participants_fill_separately' => false,
        ], $user->id);

        $contract = $result['primary'];
        $this->assertSame('group_ordering', $result['mode']);
        $this->assertNull($result['companion']);
        $this->assertSame(Contract::TYPE_GROUP, $contract->contract_type);
        $this->assertSame('sent', $contract->status);
        $this->assertNotEmpty($contract->public_token);
        $this->assertFalse((bool) data_get($contract->meta, 'participants_fill_separately'));
    }

    public function test_group_ordering_with_participants_fill_creates_companion_template(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create(['participant_count' => 8]);

        $result = app(GenerateEventContractAction::class)($event, [
            'generation_mode' => GenerateEventContractAction::MODE_GROUP_ORDERING,
            'title' => 'Umowa grupowa',
            'participant_count' => 8,
            'unit_price' => 200,
            'amount_due' => 1600,
            'payment_scheme' => Contract::PAYMENT_SCHEME_LUMP_SUM,
            'payment_schedules' => [],
            'use_event_payment_template' => false,
            'participants_fill_separately' => true,
        ], $user->id);

        $this->assertNotNull($result['companion']);
        $this->assertSame('template', $result['companion']->status);
        $this->assertTrue((bool) data_get($result['companion']->meta, 'skip_payment'));
        $this->assertTrue((bool) data_get($result['companion']->meta, 'data_collection_for_group'));
        $this->assertSame(
            (int) $result['primary']->id,
            (int) data_get($result['companion']->meta, 'linked_group_contract_id')
        );
    }

    public function test_individual_mode_uses_per_person_amount_not_group_total(): void
    {
        $event = Event::factory()->create([
            'participant_count' => 10,
            'client_name' => 'Szkoła XYZ',
            'client_email' => 'szkola@test.local',
        ]);

        $result = app(GenerateEventContractAction::class)($event, [
            'generation_mode' => GenerateEventContractAction::MODE_INDIVIDUAL,
            'title' => 'Umowa uczestnika',
            'paying_participants_count' => 10,
            'participants_on_contract' => 1,
            'unit_price' => 3050,
            'amount_due' => 3050,
            'include_foreign_currency' => true,
            'foreign_paid_by' => 'pilot',
            'payment_scheme' => Contract::PAYMENT_SCHEME_INSTALLMENTS,
            'payment_schedules' => [
                [
                    'label' => 'Wpłata PLN',
                    'amount' => 3050,
                    'amount_foreign' => null,
                    'currency_code' => null,
                    'paid_by' => 'office',
                ],
                [
                    'label' => 'Waluta — płatne w autokarze',
                    'amount' => 0,
                    'amount_foreign' => 200,
                    'currency_code' => 'EUR',
                    'paid_by' => 'pilot',
                ],
            ],
        ], null);

        $this->assertSame(3050.0, (float) $result['primary']->amount_due);
        $this->assertSame(1, (int) $result['primary']->participant_count);
        $this->assertGreaterThanOrEqual(2, $result['primary']->paymentSchedules()->count());
        $this->assertNull($result['primary']->customer_name);
        $this->assertSame(0, $result['primary']->orderingParties()->count());
        $this->assertTrue((bool) data_get($result['primary']->meta, 'awaiting_participant_details'));
    }

    public function test_siblings_on_one_contract_multiplies_pln(): void
    {
        $event = Event::factory()->create(['participant_count' => 10]);

        $result = app(GenerateEventContractAction::class)($event, [
            'generation_mode' => GenerateEventContractAction::MODE_GROUP_PARTICIPANTS,
            'title' => 'Umowa',
            'paying_participants_count' => 10,
            'participants_on_contract' => 2,
            'unit_price' => 1000,
            'amount_due' => 2000,
            'include_foreign_currency' => false,
        ], null);

        $this->assertSame(2000.0, (float) $result['primary']->amount_due);
        $this->assertSame(2, (int) $result['primary']->participant_count);
    }

    public function test_individual_fx_off_strips_foreign_schedules(): void
    {
        $event = Event::factory()->create(['participant_count' => 5, 'client_name' => 'Szkoła']);

        $result = app(GenerateEventContractAction::class)($event, [
            'generation_mode' => GenerateEventContractAction::MODE_INDIVIDUAL,
            'unit_price' => 1000,
            'amount_due' => 1000,
            'participants_on_contract' => 1,
            'include_foreign_currency' => false,
            'payment_scheme' => Contract::PAYMENT_SCHEME_INSTALLMENTS,
            'payment_schedules' => [
                [
                    'label' => 'PLN',
                    'amount' => 1000,
                    'paid_by' => 'office',
                ],
                [
                    'label' => 'EUR',
                    'amount' => 0,
                    'amount_foreign' => 50,
                    'currency_code' => 'EUR',
                    'paid_by' => 'pilot',
                ],
            ],
        ], null);

        $contract = $result['primary'];
        $this->assertFalse((bool) data_get($contract->meta, 'include_foreign'));
        $this->assertTrue((bool) data_get($contract->meta, 'foreign_excluded'));
        $this->assertSame(0, (int) $contract->paymentSchedules()->where('amount_foreign', '>', 0)->count());
        $this->assertNull($contract->customer_name);
    }

    public function test_individual_fx_on_office_sets_paid_by(): void
    {
        $event = Event::factory()->create(['participant_count' => 5]);

        $result = app(GenerateEventContractAction::class)($event, [
            'generation_mode' => GenerateEventContractAction::MODE_INDIVIDUAL,
            'unit_price' => 1000,
            'amount_due' => 1000,
            'participants_on_contract' => 1,
            'include_foreign_currency' => true,
            'foreign_paid_by' => 'office',
            'payment_scheme' => Contract::PAYMENT_SCHEME_INSTALLMENTS,
            'payment_schedules' => [
                [
                    'label' => 'PLN',
                    'amount' => 1000,
                    'paid_by' => 'office',
                ],
                [
                    'label' => 'EUR',
                    'amount' => 0,
                    'amount_foreign' => 40,
                    'currency_code' => 'EUR',
                    'paid_by' => 'pilot',
                ],
            ],
        ], null);

        $fx = $result['primary']->paymentSchedules()->where('amount_foreign', '>', 0)->first();
        $this->assertNotNull($fx);
        $this->assertSame('office', $fx->paid_by);
        $this->assertSame(40.0, (float) $fx->amount_foreign);
        $this->assertTrue((bool) data_get($result['primary']->meta, 'include_foreign'));
        $this->assertSame('office', data_get($result['primary']->meta, 'foreign_paid_by'));
    }
}
