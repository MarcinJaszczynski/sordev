<?php

namespace Tests\Unit;

use App\Models\EventTemplate;
use App\Models\TransportType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventTemplateTransportTypeFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_exact_transport_filter_excludes_mixed_transport_templates(): void
    {
        $bus = TransportType::create(['name' => 'Autokar']);
        $plane = TransportType::create(['name' => 'Samolot']);

        $busOnly = EventTemplate::factory()->create(['name' => 'Tylko autokar', 'is_active' => true]);
        $busOnly->transportTypes()->attach($bus->id);

        $mixed = EventTemplate::factory()->create(['name' => 'Autokar i samolot', 'is_active' => true]);
        $mixed->transportTypes()->attach([$bus->id, $plane->id]);

        $ids = EventTemplate::query()
            ->withExactTransportTypes([$bus->id])
            ->pluck('id')
            ->all();

        $this->assertSame([$busOnly->id], $ids);
    }

    public function test_exact_transport_filter_matches_selected_combination(): void
    {
        $bus = TransportType::create(['name' => 'Autokar']);
        $train = TransportType::create(['name' => 'Pociąg']);
        $plane = TransportType::create(['name' => 'Samolot']);

        $busOnly = EventTemplate::factory()->create(['name' => 'Tylko autokar', 'is_active' => true]);
        $busOnly->transportTypes()->attach($bus->id);

        $busAndTrain = EventTemplate::factory()->create(['name' => 'Autokar i pociąg', 'is_active' => true]);
        $busAndTrain->transportTypes()->attach([$bus->id, $train->id]);

        $busAndPlane = EventTemplate::factory()->create(['name' => 'Autokar i samolot', 'is_active' => true]);
        $busAndPlane->transportTypes()->attach([$bus->id, $plane->id]);

        $ids = EventTemplate::query()
            ->withExactTransportTypes([$bus->id, $train->id])
            ->pluck('id')
            ->all();

        $this->assertSame([$busAndTrain->id], $ids);
    }
}
