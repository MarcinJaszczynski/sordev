<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_counts_returns_success_for_authenticated_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->json('GET', '/api/v1/notifications/counts', [], ['Accept' => 'application/json']);

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure(['data']);
    }

    public function test_counts_without_auth_returns_401(): void
    {
        $response = $this->json('GET', '/api/v1/notifications/counts', [], ['Accept' => 'application/json']);

        $response->assertStatus(401);
    }
}
