<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventTemplate;
use App\Models\Place;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationServiceTopbarTest extends TestCase
{
    use RefreshDatabase;

    private function createEventForUser(User $user, string $status, string $name): Event
    {
        $place = Place::factory()->starting()->create();
        $template = EventTemplate::factory()->create([
            'start_place_id' => $place->id,
        ]);

        return Event::create([
            'event_template_id' => $template->id,
            'start_place_id' => $place->id,
            'created_by' => $user->id,
            'assigned_to' => $user->id,
            'name' => $name,
            'client_name' => 'Klient testowy',
            'start_date' => '2026-05-10',
            'end_date' => '2026-05-12',
            'duration_days' => 3,
            'participant_count' => 20,
            'total_cost' => 3000,
            'status' => $status,
        ]);
    }

    public function test_topbar_counts_include_new_and_pending_cancellation_events(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->createEventForUser($user, Event::STATUS_INQUIRY, 'Nowa impreza');
        $this->createEventForUser($user, Event::STATUS_PENDING_CANCELLATION, 'Impreza do anulacji');
        $this->createEventForUser($user, Event::STATUS_CONFIRMED, 'Potwierdzona impreza');

        $otherUser = User::factory()->create();
        $this->createEventForUser($otherUser, Event::STATUS_INQUIRY, 'Nieprzypisana dla użytkownika');

        NotificationService::clearCacheForUser($user->id);
        $data = NotificationService::getTopbarDataForUser($user->id);

        $this->assertSame(1, $data['counts']['new_events']);
        $this->assertSame(1, $data['counts']['pending_cancellation_events']);
        $this->assertSame(1, $data['counts']['confirmed_events']);
        $this->assertArrayHasKey('new_event', $data['items_by_type']);
        $this->assertArrayHasKey('pending_cancellation_event', $data['items_by_type']);
        $this->assertCount(1, $data['items_by_type']['new_event']);
        $this->assertCount(1, $data['items_by_type']['pending_cancellation_event']);
    }

    public function test_notification_counts_endpoint_returns_new_event_counters(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->createEventForUser($user, Event::STATUS_INQUIRY, 'Nowe zapytanie');
        $this->createEventForUser($user, Event::STATUS_PENDING_CANCELLATION, 'Do anulacji');

        NotificationService::clearCacheForUser($user->id);

        $response = $this->getJson(route('admin.notifications.counts'));

        $response->assertOk()->assertJson([
            'new_events' => 1,
            'pending_cancellation_events' => 1,
        ]);
    }
}
