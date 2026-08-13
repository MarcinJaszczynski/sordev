<?php

namespace Tests\Feature;

use App\Filament\Resources\EventResource\Pages\EditEvent;
use App\Models\Bus;
use App\Models\Contractor;
use App\Models\Event;
use App\Models\EventPricePerPerson;
use App\Models\EventQty;
use App\Models\Place;
use App\Models\User;
use App\Services\EventCostCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EditEventStartPlacePriceRecalcTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    }

    public function test_changing_start_place_recalculates_price_from_event_bus(): void
    {
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('admin');

        $contractor = Contractor::create([
            'name' => 'Klient testowy',
            'email' => 'klient@example.com',
            'status' => 'active',
        ]);

        $placeA = Place::factory()->starting()->create(['name' => 'Miejsce A']);
        $placeB = Place::factory()->starting()->create(['name' => 'Miejsce B']);

        $bus = Bus::factory()->create([
            'name' => 'Autokar testowy',
            'package_price_per_day' => 1000,
            'package_km_per_day' => 100,
            'extra_km_price' => 10,
            'currency' => 'PLN',
        ]);

        $event = Event::factory()->create([
            'client_name' => 'Klient testowy',
            'contractor_id' => $contractor->id,
            'status' => Event::STATUS_CONFIRMED,
            'start_place_id' => $placeA->id,
            'bus_id' => $bus->id,
            'transfer_km' => 50,
            'program_km' => 0,
            'participant_count' => 20,
            'duration_days' => 1,
            'start_date' => '2026-06-10',
            'end_date' => '2026-06-10',
        ]);

        EventQty::create([
            'event_id' => $event->id,
            'qty' => 20,
            'gratis' => 0,
            'staff' => 1,
            'driver' => 1,
        ]);

        $costBefore = EventCostCalculator::for($event->fresh(['bus']))->calculate(20);
        $transportBefore = (float) collect($costBefore['lines'] ?? [])
            ->where('category', 'transport')
            ->sum('cost_pln');

        $this->assertGreaterThan(0, $transportBefore);

        $this->actingAs($admin);

        Livewire::test(EditEvent::class, ['record' => $event->getKey()])
            ->fillForm([
                'start_place_id' => $placeB->id,
                'transfer_km' => 200,
                'ordering_parties' => [
                    [
                        'contact_id' => null,
                        'contractor_id' => (string) $contractor->id,
                        'department_label' => null,
                    ],
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $event->refresh();

        $this->assertSame($placeB->id, (int) $event->start_place_id);
        $this->assertEquals(200.0, (float) $event->transfer_km);

        $costAfter = EventCostCalculator::for($event->fresh(['bus']))->calculate(20);
        $transportAfter = (float) collect($costAfter['lines'] ?? [])
            ->where('category', 'transport')
            ->sum('cost_pln');

        $this->assertGreaterThan($transportBefore, $transportAfter);

        $priceRow = EventPricePerPerson::query()
            ->where('event_id', $event->id)
            ->where('is_manual', false)
            ->first();

        $this->assertNotNull($priceRow);
        $this->assertSame($placeB->id, (int) $priceRow->start_place_id);
        $this->assertEqualsWithDelta($transportAfter, (float) $priceRow->transport_cost, 0.02);
    }

    public function test_edit_event_form_exposes_transport_times_with_dates(): void
    {
        if (! Schema::hasColumn('events', 'substitution_time')
            || ! Schema::hasColumn('events', 'departure_time')
            || ! Schema::hasColumn('events', 'return_time')) {
            $this->markTestSkipped('Brak kolumn godzin transportu.');
        }

        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('admin');

        $contractor = Contractor::create([
            'name' => 'Klient godziny',
            'email' => 'godziny@example.com',
            'status' => 'active',
        ]);

        $event = Event::factory()->create([
            'client_name' => 'Klient godziny',
            'contractor_id' => $contractor->id,
            'status' => Event::STATUS_CONFIRMED,
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-03',
            'duration_days' => 3,
            'substitution_time' => '06:30',
            'departure_time' => '07:00',
            'return_time' => '18:45',
        ]);

        $this->actingAs($admin);

        $component = Livewire::test(EditEvent::class, ['record' => $event->getKey()]);

        $state = $component->get('data');
        $this->assertSame('06:30', substr((string) ($state['substitution_time'] ?? ''), 0, 5));
        $this->assertSame('07:00', substr((string) ($state['departure_time'] ?? ''), 0, 5));
        $this->assertSame('18:45', substr((string) ($state['return_time'] ?? ''), 0, 5));

        $component
            ->fillForm([
                'substitution_time' => '05:45',
                'departure_time' => '06:15',
                'return_time' => '19:30',
                'ordering_parties' => [
                    [
                        'contact_id' => null,
                        'contractor_id' => (string) $contractor->id,
                        'department_label' => null,
                    ],
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $event->refresh();

        $this->assertSame('05:45', substr((string) $event->substitution_time, 0, 5));
        $this->assertSame('06:15', substr((string) $event->departure_time, 0, 5));
        $this->assertSame('19:30', substr((string) $event->return_time, 0, 5));
    }
}