<?php

namespace Tests\Feature;

use App\Livewire\EventBusCollections;
use App\Models\Currency;
use App\Models\Event;
use App\Models\EventBusCollection;
use App\Models\EventTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventBusCollectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        Currency::query()->firstOrCreate(
            ['code' => 'PLN'],
            ['name' => 'Polski złoty', 'symbol' => 'PLN', 'exchange_rate' => 1],
        );
    }

    public function test_bus_collection_stores_unit_amount_times_participant_count(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $template = EventTemplate::factory()->create();
        $plnId = Currency::defaultPlnId();

        $event = Event::create([
            'event_template_id' => $template->id,
            'name' => 'Impreza zbiórka',
            'client_name' => 'Szkola',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'participant_count' => 40,
            'total_cost' => 4000,
            'status' => 'confirmed',
            'created_by' => $user->id,
        ]);

        $this->actingAs($user);

        Livewire::test(EventBusCollections::class, ['event' => $event])
            ->set('unitAmount', '25')
            ->set('participantCount', '40')
            ->set('currencyId', $plnId)
            ->call('addCollection')
            ->assertHasNoErrors();

        $collection = EventBusCollection::query()->where('event_id', $event->id)->first();

        $this->assertNotNull($collection);
        $this->assertSame('25.00', $collection->amount_per_person);
        $this->assertSame('1000.00', $collection->amount);
        $this->assertSame(40, $collection->participant_count);
    }
}
