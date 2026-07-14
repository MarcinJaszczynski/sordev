<?php

namespace Tests\Feature;

use App\Models\Contractor;
use App\Models\Event;
use App\Models\HotelCorrespondenceLog;
use App\Models\User;
use App\Services\HotelCorrespondenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HotelCorrespondenceLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_correspondence_log_for_event(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create();
        $contractor = Contractor::create(['name' => 'Hotel Testowy', 'status' => 'active']);

        $log = app(HotelCorrespondenceService::class)->create($event, $user, [
            'direction' => HotelCorrespondenceLog::DIRECTION_INBOUND,
            'contractor_id' => $contractor->id,
            'subject' => 'Potwierdzenie pokoi',
            'body' => 'Hotel potwierdził 20 pokoi DBL.',
            'contact_person' => 'Recepcja',
            'contacted_at' => now(),
        ]);

        $this->assertDatabaseHas('hotel_correspondence_logs', [
            'id' => $log->id,
            'event_id' => $event->id,
            'contractor_id' => $contractor->id,
            'subject' => 'Potwierdzenie pokoi',
        ]);

        $logs = app(HotelCorrespondenceService::class)->logsForEvent($event->fresh());
        $this->assertCount(1, $logs);
        $this->assertSame('Recepcja', $logs->first()->contact_person);
    }
}
