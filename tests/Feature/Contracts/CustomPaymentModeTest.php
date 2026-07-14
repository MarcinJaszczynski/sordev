<?php

namespace Tests\Feature\Contracts;

use App\Models\Contract;
use App\Models\Event;
use App\Models\EventSettlementParticipantPayment;
use App\Models\User;
use App\Services\ContractPaymentSyncService;
use App\Services\Contracts\ContractPaymentProfileResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomPaymentModeTest extends TestCase
{
    use RefreshDatabase;

    public function test_custom_per_participant_uses_group_individual_profile(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create(['participant_count' => 2, 'code' => '25CUS001']);

        $contract = Contract::create([
            'event_id' => $event->id,
            'contract_type' => Contract::TYPE_CUSTOM,
            'payment_scheme' => Contract::PAYMENT_SCHEME_LUMP_SUM,
            'title' => 'Umowa własna per uczestnik',
            'contract_date' => now()->toDateString(),
            'participant_count' => 2,
            'unit_price' => 800,
            'total_price' => 1600,
            'currency' => 'PLN',
            'status' => 'sent',
            'payment_status' => 'pending',
            'meta' => ['payment_mode' => Contract::CUSTOM_PAYMENT_PER_PARTICIPANT],
            'created_by' => $user->id,
        ]);

        $profile = app(ContractPaymentProfileResolver::class)->resolve($contract->fresh());

        $this->assertSame(ContractPaymentProfileResolver::PROFILE_CUSTOM, $profile);

        app(ContractPaymentSyncService::class)->sync($contract->fresh());

        $this->assertGreaterThanOrEqual(2, EventSettlementParticipantPayment::query()->count());
    }

    public function test_custom_manual_skips_sync_without_linked_payment(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create();

        $contract = Contract::create([
            'event_id' => $event->id,
            'contract_type' => Contract::TYPE_CUSTOM,
            'title' => 'Umowa własna manual',
            'contract_date' => now()->toDateString(),
            'participant_count' => 1,
            'total_price' => 900,
            'currency' => 'PLN',
            'status' => 'sent',
            'payment_status' => 'pending',
            'meta' => ['payment_mode' => Contract::CUSTOM_PAYMENT_MANUAL],
            'created_by' => $user->id,
        ]);

        app(ContractPaymentSyncService::class)->sync($contract->fresh());

        $this->assertSame(0, EventSettlementParticipantPayment::query()->count());
    }
}
