<?php

namespace Tests\Feature\Api\V1;

use App\Models\Event;
use App\Models\EventProgramPoint;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventApiTest extends TestCase
{
    use RefreshDatabase;

    private function apiAs(User $user, string $method, string $uri, array $data = [])
    {
        return $this->actingAs($user, 'sanctum')
            ->json($method, '/api/v1'.$uri, $data, ['Accept' => 'application/json']);
    }

    private function makeEvent(array $overrides = []): Event
    {
        return Event::factory()->create($overrides);
    }

    // ── index ─────────────────────────────────────────────────────────────

    public function test_events_index_returns_paginated_list(): void
    {
        $user = User::factory()->create();
        Event::factory()->count(3)->create();

        $response = $this->apiAs($user, 'GET', '/events');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonPath('data.total', 3)
            ->assertJsonStructure(['data' => ['data', 'total', 'per_page', 'current_page']]);
    }

    public function test_events_index_without_auth_returns_401(): void
    {
        $response = $this->json('GET', '/api/v1/events', [], ['Accept' => 'application/json']);

        $response->assertStatus(401);
    }

    public function test_events_index_filters_by_status(): void
    {
        $user = User::factory()->create();
        Event::factory()->create(['status' => Event::STATUS_CONFIRMED]);
        Event::factory()->create(['status' => Event::STATUS_INQUIRY]);

        $response = $this->apiAs($user, 'GET', '/events?status='.Event::STATUS_CONFIRMED);

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data.data'));
        $this->assertSame(Event::STATUS_CONFIRMED, $response->json('data.data.0.status'));
    }

    public function test_events_index_filters_by_search_name(): void
    {
        $user = User::factory()->create();
        Event::factory()->create(['name' => 'Wycieczka do Krakowa']);
        Event::factory()->create(['name' => 'Wakacje w górach']);

        $response = $this->apiAs($user, 'GET', '/events?search=Krakow');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data.data'));
    }

    public function test_events_index_respects_per_page_param(): void
    {
        $user = User::factory()->create();
        Event::factory()->count(10)->create();

        $response = $this->apiAs($user, 'GET', '/events?per_page=3');

        $response->assertStatus(200);
        $this->assertCount(3, $response->json('data.data'));
        $this->assertSame(3, $response->json('data.per_page'));
    }

    public function test_events_index_rejects_invalid_per_page(): void
    {
        $user = User::factory()->create();

        $response = $this->apiAs($user, 'GET', '/events?per_page=999');

        $response->assertStatus(422);
    }

    // ── show ──────────────────────────────────────────────────────────────

    public function test_events_show_returns_event_detail(): void
    {
        $user  = User::factory()->create();
        $event = $this->makeEvent();

        $response = $this->apiAs($user, 'GET', '/events/'.$event->id);

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonPath('data.id', $event->id)
            ->assertJsonPath('data.name', $event->name);
    }

    public function test_events_show_returns_404_for_missing_event(): void
    {
        $user = User::factory()->create();

        $response = $this->apiAs($user, 'GET', '/events/99999');

        $response->assertStatus(404);
    }

    public function test_events_show_without_auth_returns_401(): void
    {
        $event = $this->makeEvent();

        $response = $this->json('GET', '/api/v1/events/'.$event->id, [], ['Accept' => 'application/json']);

        $response->assertStatus(401);
    }

    // ── recalculate-price ─────────────────────────────────────────────────

    public function test_recalculate_price_returns_price_per_person(): void
    {
        $user  = User::factory()->create();
        $event = $this->makeEvent(['participant_count' => 10]);

        $response = $this->apiAs($user, 'POST', '/events/'.$event->id.'/recalculate-price');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonPath('data.event_id', $event->id)
            ->assertJsonStructure(['data' => ['event_id', 'price_per_person']]);
    }

    public function test_recalculate_price_without_auth_returns_401(): void
    {
        $event = $this->makeEvent();

        $response = $this->json('POST', '/api/v1/events/'.$event->id.'/recalculate-price', [], ['Accept' => 'application/json']);

        $response->assertStatus(401);
    }

    // ── reorder program points ─────────────────────────────────────────────

    public function test_reorder_program_points_updates_order(): void
    {
        $user  = User::factory()->create();
        $event = $this->makeEvent();

        $p1 = EventProgramPoint::factory()->create(['event_id' => $event->id, 'day' => 1, 'order' => 1]);
        $p2 = EventProgramPoint::factory()->create(['event_id' => $event->id, 'day' => 1, 'order' => 2]);
        $p3 = EventProgramPoint::factory()->create(['event_id' => $event->id, 'day' => 1, 'order' => 3]);

        $response = $this->apiAs($user, 'POST', '/events/'.$event->id.'/program-points/reorder', [
            'day'       => 1,
            'point_ids' => [$p3->id, $p1->id, $p2->id],
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonPath('data.day', 1);

        $this->assertDatabaseHas('event_program_points', ['id' => $p3->id, 'order' => 1]);
        $this->assertDatabaseHas('event_program_points', ['id' => $p1->id, 'order' => 2]);
        $this->assertDatabaseHas('event_program_points', ['id' => $p2->id, 'order' => 3]);
    }

    public function test_reorder_rejects_missing_day(): void
    {
        $user  = User::factory()->create();
        $event = $this->makeEvent();

        $response = $this->apiAs($user, 'POST', '/events/'.$event->id.'/program-points/reorder', [
            'point_ids' => [1, 2],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['day']);
    }

    public function test_reorder_rejects_point_ids_from_different_day(): void
    {
        $user  = User::factory()->create();
        $event = $this->makeEvent();

        $p1 = EventProgramPoint::factory()->create(['event_id' => $event->id, 'day' => 1]);
        $p2 = EventProgramPoint::factory()->create(['event_id' => $event->id, 'day' => 2]);

        $response = $this->apiAs($user, 'POST', '/events/'.$event->id.'/program-points/reorder', [
            'day'       => 1,
            'point_ids' => [$p1->id, $p2->id],   // p2 jest dnia 2 – powinno odrzucić
        ]);

        $response->assertStatus(422)
            ->assertJson(['success' => false]);
    }

    public function test_reorder_rejects_empty_point_ids(): void
    {
        $user  = User::factory()->create();
        $event = $this->makeEvent();

        $response = $this->apiAs($user, 'POST', '/events/'.$event->id.'/program-points/reorder', [
            'day'       => 1,
            'point_ids' => [],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['point_ids']);
    }
}
