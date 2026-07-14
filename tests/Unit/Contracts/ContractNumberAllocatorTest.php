<?php

namespace Tests\Unit\Contracts;

use App\Models\Contract;
use App\Models\Event;
use App\Models\User;
use App\Services\Contracts\ContractNumberAllocator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContractNumberAllocatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_individual_contract_gets_event_code_with_incrementing_suffix(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create(['code' => '25TEST01']);

        $first = Contract::create([
            'event_id' => $event->id,
            'contract_type' => Contract::TYPE_INDIVIDUAL,
            'title' => 'Umowa 1',
            'contract_date' => now()->toDateString(),
            'participant_count' => 1,
            'total_price' => 1000,
            'currency' => 'PLN',
            'status' => 'sent',
            'payment_status' => 'pending',
            'created_by' => $user->id,
        ]);

        $second = Contract::create([
            'event_id' => $event->id,
            'contract_type' => Contract::TYPE_INDIVIDUAL,
            'title' => 'Umowa 2',
            'contract_date' => now()->toDateString(),
            'participant_count' => 1,
            'total_price' => 1000,
            'currency' => 'PLN',
            'status' => 'sent',
            'payment_status' => 'pending',
            'created_by' => $user->id,
        ]);

        $this->assertSame('25TEST01U001', $first->fresh()->operational_number);
        $this->assertSame('25TEST01U002', $second->fresh()->operational_number);
        $this->assertStringStartsWith('UM/', $first->contract_number);
    }

    public function test_group_contract_uses_event_code(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create(['code' => '25GRP001']);

        $contract = Contract::create([
            'event_id' => $event->id,
            'contract_type' => Contract::TYPE_GROUP,
            'title' => 'Umowa grupowa',
            'contract_date' => now()->toDateString(),
            'participant_count' => 20,
            'total_price' => 20000,
            'currency' => 'PLN',
            'status' => 'sent',
            'payment_status' => 'pending',
            'created_by' => $user->id,
        ]);

        $this->assertSame('25GRP001', $contract->fresh()->operational_number);
    }

    public function test_allocator_service_assigns_next_individual_suffix(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create(['code' => '25SEQ001']);

        Contract::create([
            'event_id' => $event->id,
            'contract_type' => Contract::TYPE_INDIVIDUAL,
            'operational_number' => '25SEQ001U003',
            'title' => 'Istniejąca',
            'contract_date' => now()->toDateString(),
            'participant_count' => 1,
            'total_price' => 500,
            'currency' => 'PLN',
            'status' => 'sent',
            'payment_status' => 'pending',
            'created_by' => $user->id,
        ]);

        $draft = new Contract([
            'event_id' => $event->id,
            'contract_type' => Contract::TYPE_INDIVIDUAL,
        ]);
        $draft->setRelation('event', $event);

        $this->assertSame('25SEQ001U004', app(ContractNumberAllocator::class)->allocate($draft));
    }
}
