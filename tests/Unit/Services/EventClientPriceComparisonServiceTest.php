<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Contract;
use App\Models\ContractPaymentSchedule;
use App\Models\Currency;
use App\Models\Event;
use App\Models\EventPricePerPerson;
use App\Models\User;
use App\Services\EventClientPriceComparisonService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EventClientPriceComparisonServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_without_contract_shows_dash_over_calculation_placeholder_when_no_calc(): void
    {
        $event = Event::factory()->create(['participant_count' => 10]);

        $result = app(EventClientPriceComparisonService::class)->forEvent($event);

        $this->assertSame('none', $result['source']);
        $this->assertFalse($result['has_contract']);
        $this->assertSame('—', $result['label']);
    }

    public function test_prefers_latest_annex_with_unit_price_over_parent_contract(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create(['participant_count' => 10]);

        Contract::create([
            'event_id' => $event->id,
            'contract_type' => Contract::TYPE_GROUP,
            'title' => 'Umowa',
            'contract_date' => now()->toDateString(),
            'participant_count' => 10,
            'unit_price' => 1000,
            'total_price' => 10000,
            'amount_due' => 10000,
            'payment_scheme' => Contract::PAYMENT_SCHEME_LUMP_SUM,
            'currency' => 'PLN',
            'status' => 'sent',
            'payment_status' => 'pending',
            'created_by' => $user->id,
        ]);

        $annex = Contract::create([
            'event_id' => $event->id,
            'contract_type' => Contract::TYPE_GROUP,
            'title' => 'Aneks',
            'contract_date' => now()->toDateString(),
            'participant_count' => 10,
            'unit_price' => 1200,
            'total_price' => 12000,
            'amount_due' => 12000,
            'payment_scheme' => Contract::PAYMENT_SCHEME_LUMP_SUM,
            'currency' => 'PLN',
            'status' => 'sent',
            'payment_status' => 'pending',
            'created_by' => $user->id,
            'meta' => ['is_annex' => true],
        ]);

        $service = app(EventClientPriceComparisonService::class);
        $resolved = $service->resolveEffectiveGroupContract($event);
        $result = $service->forEvent($event);

        $this->assertNotNull($resolved);
        $this->assertSame((int) $annex->id, (int) $resolved->id);
        $this->assertSame('contract', $result['source']);
        $this->assertTrue($result['has_contract']);
        $this->assertStringContainsString('1 200,00 PLN', $result['label']);
        $this->assertStringContainsString(' / ', $result['label']);
    }

    public function test_contract_foreign_schedule_is_compared_per_currency(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create(['participant_count' => 10]);

        $contract = Contract::create([
            'event_id' => $event->id,
            'contract_type' => Contract::TYPE_GROUP,
            'title' => 'Umowa',
            'contract_date' => now()->toDateString(),
            'participant_count' => 10,
            'unit_price' => 900,
            'total_price' => 9000,
            'amount_due' => 9000,
            'payment_scheme' => Contract::PAYMENT_SCHEME_INSTALLMENTS,
            'currency' => 'PLN',
            'status' => 'sent',
            'payment_status' => 'pending',
            'created_by' => $user->id,
        ]);

        ContractPaymentSchedule::create([
            'contract_id' => $contract->id,
            'sort_order' => 1,
            'label' => 'Waluta u pilota',
            'amount' => 0,
            'amount_foreign' => 50,
            'currency_code' => 'EUR',
        ]);

        $result = app(EventClientPriceComparisonService::class)->forEvent($event);

        $byCode = collect($result['currencies'])->keyBy('currency');
        $this->assertSame(900.0, $byCode['PLN']['effective']);
        $this->assertSame(50.0, $byCode['EUR']['effective']);
        $this->assertStringContainsString('900,00 PLN', $result['label']);
        $this->assertStringContainsString('50,00 EUR', $result['label']);
    }

    public function test_individual_contract_with_foreign_schedule_is_used_as_effective_price(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create(['participant_count' => 33]);

        $contract = Contract::create([
            'event_id' => $event->id,
            'contract_type' => Contract::TYPE_INDIVIDUAL,
            'title' => 'Umowa indywidualna',
            'contract_date' => now()->toDateString(),
            'participant_count' => 1,
            'unit_price' => 3005,
            'total_price' => 3005,
            'amount_due' => 3005,
            'payment_scheme' => Contract::PAYMENT_SCHEME_INSTALLMENTS,
            'currency' => 'PLN',
            'status' => 'completed',
            'payment_status' => 'pending',
            'created_by' => $user->id,
        ]);

        ContractPaymentSchedule::create([
            'contract_id' => $contract->id,
            'sort_order' => 1,
            'label' => 'Waluta u pilota',
            'amount' => 0,
            'amount_foreign' => 128.47,
            'currency_code' => 'EUR',
        ]);

        // Szablon nie powinien wygrywać z umową.
        Contract::create([
            'event_id' => $event->id,
            'contract_type' => Contract::TYPE_INDIVIDUAL,
            'title' => 'Szablon',
            'contract_date' => now()->toDateString(),
            'participant_count' => 1,
            'unit_price' => 1,
            'total_price' => 1,
            'currency' => 'PLN',
            'status' => 'template',
            'payment_status' => 'pending',
            'created_by' => $user->id,
        ]);

        $result = app(EventClientPriceComparisonService::class)->forEvent($event);

        $this->assertSame('contract', $result['source']);
        $this->assertTrue($result['has_contract']);
        $byCode = collect($result['currencies'])->keyBy('currency');
        $this->assertSame(3005.0, $byCode['PLN']['effective']);
        $this->assertSame(128.47, $byCode['EUR']['effective']);
        $this->assertStringContainsString('3 005,00 PLN', $result['label']);
        $this->assertStringContainsString('128,47 EUR', $result['label']);
        $this->assertStringNotContainsString('— / 3 005', $result['label']);
    }

    public function test_individual_generator_template_is_used_as_contract_price(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create(['participant_count' => 20]);

        $template = Contract::create([
            'event_id' => $event->id,
            'contract_type' => Contract::TYPE_INDIVIDUAL,
            'title' => 'Umowa uczestnika (szablon)',
            'contract_date' => now()->toDateString(),
            'participant_count' => 1,
            'unit_price' => 3565,
            'total_price' => 3565,
            'amount_due' => 3565,
            'payment_scheme' => Contract::PAYMENT_SCHEME_INSTALLMENTS,
            'currency' => 'PLN',
            'status' => 'template',
            'payment_status' => 'pending',
            'created_by' => $user->id,
            'meta' => [
                'is_individual_template' => true,
                'unit_price_pln' => 3565,
                'foreign_prices_per_person' => [
                    ['currency' => 'EUR', 'price_per_person' => 140.2, 'label' => '140,20 EUR'],
                ],
            ],
        ]);

        ContractPaymentSchedule::create([
            'contract_id' => $template->id,
            'sort_order' => 1,
            'label' => 'Wpłata PLN',
            'amount' => 3565,
            'amount_foreign' => null,
            'currency_code' => null,
        ]);

        ContractPaymentSchedule::create([
            'contract_id' => $template->id,
            'sort_order' => 2,
            'label' => 'Waluta — płatne u pilota',
            'amount' => 0,
            'amount_foreign' => 140.2,
            'currency_code' => 'EUR',
        ]);

        $result = app(EventClientPriceComparisonService::class)->forEvent($event);

        $this->assertSame('contract', $result['source']);
        $this->assertTrue($result['has_contract']);
        $byCode = collect($result['currencies'])->keyBy('currency');
        $this->assertSame(3565.0, $byCode['PLN']['effective']);
        $this->assertSame(140.2, $byCode['EUR']['effective']);
        $this->assertStringContainsString('3 565,00 PLN', $result['label']);
        $this->assertStringContainsString('140,20 EUR', $result['label']);
        $this->assertStringNotContainsString('— / 3 565', $result['label']);
    }

    public function test_manual_price_used_when_no_contract(): void
    {
        if (! Schema::hasTable('event_price_per_person') || ! Schema::hasTable('currencies')) {
            $this->markTestSkipped('Brak tabel ceny/walut.');
        }

        $event = Event::factory()->create(['participant_count' => 10]);
        $plnId = Currency::defaultPlnId();
        if (! $plnId) {
            $plnId = (int) Currency::query()->create([
                'name' => 'Polski złoty',
                'symbol' => 'PLN',
                'exchange_rate' => 1,
            ])->id;
            Currency::clearPlnIdsCache();
        }

        EventPricePerPerson::query()->create([
            'event_id' => $event->id,
            'currency_id' => $plnId,
            'price_per_person' => 777,
            'is_manual' => true,
        ]);

        $result = app(EventClientPriceComparisonService::class)->forEvent($event);

        $this->assertSame('manual', $result['source']);
        $this->assertStringContainsString('777,00', $result['label']);
        $this->assertStringContainsString(' / ', $result['label']);
        $this->assertNotEmpty($result['currencies']);
        $this->assertSame(777.0, $result['currencies'][0]['effective']);
    }
}
