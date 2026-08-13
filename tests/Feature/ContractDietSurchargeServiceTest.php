<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Contracts\GenerateEventContractAction;
use App\Actions\Events\UpsertEventParticipantAction;
use App\Data\UpsertEventParticipantData;
use App\Models\Contract;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\User;
use App\Services\ContractDietSurchargeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ContractDietSurchargeServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('contracts') || ! Schema::hasColumn('contracts', 'requires_diet')) {
            $this->markTestSkipped('Brak kolumn diety na umowach.');
        }
        if (! Schema::hasTable('event_participants')) {
            $this->markTestSkipped('Brak tabeli event_participants.');
        }
    }

    public function test_generation_stores_diet_catalog_and_base_amount(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create([
            'participant_count' => 5,
            'duration_days' => 3,
        ]);

        $result = app(GenerateEventContractAction::class)($event, [
            'generation_mode' => GenerateEventContractAction::MODE_GROUP_ORDERING,
            'title' => 'Umowa z dietą',
            'participant_count' => 5,
            'unit_price' => 100,
            'amount_due' => 500,
            'payment_scheme' => Contract::PAYMENT_SCHEME_LUMP_SUM,
            'payment_schedules' => [],
            'use_event_payment_template' => false,
            'requires_diet' => true,
            'diet_daily_pln' => 20,
            'diet_options' => ['Wegetariańska', 'Bezglutenowa'],
        ], $user->id);

        $contract = $result['primary']->fresh();
        $this->assertTrue((bool) $contract->requires_diet);
        $this->assertSame(20.0, (float) $contract->diet_daily_pln);
        $this->assertSame(500.0, (float) data_get($contract->meta, 'pricing.base_amount_due'));
        $this->assertSame(0.0, (float) data_get($contract->meta, 'pricing.diet_surcharge_pln'));
        $this->assertSame(['Wegetariańska', 'Bezglutenowa'], data_get($contract->meta, 'diet_options'));
        $this->assertSame(500.0, (float) $contract->amount_due);
    }

    public function test_choosing_diet_increases_amount_due_and_clearing_resets(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create([
            'participant_count' => 2,
            'duration_days' => 4,
        ]);

        $result = app(GenerateEventContractAction::class)($event, [
            'generation_mode' => GenerateEventContractAction::MODE_GROUP_ORDERING,
            'title' => 'Umowa grupowa',
            'participant_count' => 2,
            'unit_price' => 200,
            'amount_due' => 400,
            'payment_scheme' => Contract::PAYMENT_SCHEME_LUMP_SUM,
            'payment_schedules' => [],
            'use_event_payment_template' => false,
            'requires_diet' => true,
            'diet_daily_pln' => 25,
            'diet_options' => ['Wege'],
        ], $user->id);

        $contract = $result['primary']->fresh();

        $participant = app(UpsertEventParticipantAction::class)(new UpsertEventParticipantData(
            event: $event,
            firstName: 'Jan',
            lastName: 'Kowalski',
            diet: 'Wege',
            ensurePayment: false,
            source: EventParticipant::SOURCE_MANUAL,
        ));
        $participant->forceFill(['contract_id' => $contract->id])->save();

        app(ContractDietSurchargeService::class)->applyForContract($contract->fresh());
        $contract->refresh();

        // 1 osoba × 25 PLN × 4 dni = 100
        $this->assertSame(100.0, (float) data_get($contract->meta, 'pricing.diet_surcharge_pln'));
        $this->assertSame(500.0, (float) $contract->amount_due);
        $this->assertSame(400.0, (float) data_get($contract->meta, 'pricing.base_amount_due'));

        $participant->update(['diet' => null]);
        app(ContractDietSurchargeService::class)->applyForContract($contract->fresh());
        $contract->refresh();

        $this->assertSame(0.0, (float) data_get($contract->meta, 'pricing.diet_surcharge_pln'));
        $this->assertSame(400.0, (float) $contract->amount_due);
    }
}
