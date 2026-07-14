<?php

namespace Tests\Unit\Services;

use App\Models\Event;
use App\Services\EventFolderPdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EventFolderPdfServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_build_travel_legends_uses_return_time_when_available(): void
    {
        if (! Schema::hasColumn('events', 'return_time')) {
            $this->markTestSkipped('Brak kolumny return_time.');
        }

        $event = Event::factory()->create([
            'start_date' => '2026-08-10',
            'end_date' => '2026-08-12',
            'departure_time' => '07:30',
            'return_time' => '18:45',
        ]);

        $legends = app(EventFolderPdfService::class)->buildTravelLegends($event);

        $this->assertStringContainsString('07:30', $legends['departure']);
        $this->assertStringContainsString('18:45', $legends['return']);
        $this->assertSame('#1d4ed8', $legends['colors']['departure']);
        $this->assertArrayHasKey('destination', $legends['colors']);
    }
}
