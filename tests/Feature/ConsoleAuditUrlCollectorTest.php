<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use App\Support\ConsoleAuditUrlCollector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConsoleAuditUrlCollectorTest extends TestCase
{
    use RefreshDatabase;

    public function test_pilot_urls_use_demo_pilot_assigned_event(): void
    {
        $legacyPilot = User::factory()->create([
            'email' => 'legacy-pilot@local',
            'type' => 'pilot',
        ]);

        $demoPilot = User::factory()->create([
            'email' => 'pilot@test.local',
            'type' => 'pilot',
        ]);

        $demoEvent = Event::factory()->create([
            'assigned_to' => $demoPilot->id,
            'status' => Event::STATUS_CONFIRMED,
            'shared_with_pilot' => true,
            'end_date' => now()->addDays(7),
        ]);

        $archivedDemoEvent = Event::factory()->create([
            'assigned_to' => $demoPilot->id,
            'status' => Event::STATUS_CONFIRMED,
            'shared_with_pilot' => true,
            'start_date' => now()->subDays(20),
            'end_date' => now()->subDays(16),
        ]);

        $legacyEvent = Event::factory()->create([
            'assigned_to' => $legacyPilot->id,
            'status' => Event::STATUS_CONFIRMED,
            'shared_with_pilot' => true,
            'end_date' => now()->addDays(7),
        ]);

        $result = app(ConsoleAuditUrlCollector::class)->collect('pilot');
        $paths = collect($result['urls'])->pluck('path')->all();

        $this->assertContains("/pilot/settlement/{$demoEvent->id}", $paths);
        $this->assertNotContains("/pilot/settlement/{$archivedDemoEvent->id}", $paths);
        $this->assertNotContains("/pilot/settlement/{$legacyEvent->id}", $paths);
    }
}
