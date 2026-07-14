<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    private function apiJson(string $method, string $uri, array $data = [], array $headers = [])
    {
        return $this->json($method, '/api/v1'.$uri, $data, array_merge([
            'Accept' => 'application/json',
            // Ustawienie Origin z listy SANCTUM_STATEFUL_DOMAINS sprawia, że
            // EnsureFrontendRequestsAreStateful aktywuje session middleware,
            // dzięki czemu $request->session() działa w login/logout.
            'Origin' => 'http://localhost:3000',
            'Referer' => 'http://localhost:3000/',
        ], $headers));
    }

    // ── login ─────────────────────────────────────────────────────────────

    public function test_login_with_valid_credentials_returns_user(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('secret123'),
        ]);

        $response = $this->apiJson('POST', '/auth/login', [
            'email' => $user->email,
            'password' => 'secret123',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => ['user' => ['id' => $user->id]],
            ]);
    }

    public function test_login_with_wrong_password_returns_422(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('correctpassword'),
        ]);

        $response = $this->apiJson('POST', '/auth/login', [
            'email' => $user->email,
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_login_with_missing_email_returns_422(): void
    {
        $response = $this->apiJson('POST', '/auth/login', [
            'password' => 'somepassword',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_login_with_missing_password_returns_422(): void
    {
        $response = $this->apiJson('POST', '/auth/login', [
            'email' => 'user@example.com',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    // ── me ────────────────────────────────────────────────────────────────

    public function test_me_returns_authenticated_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->apiJson('GET', '/auth/me');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => ['user' => ['id' => $user->id, 'email' => $user->email]],
            ]);
    }

    public function test_me_without_auth_returns_401(): void
    {
        $response = $this->apiJson('GET', '/auth/me');

        $response->assertStatus(401);
    }

    // ── logout ────────────────────────────────────────────────────────────

    public function test_logout_invalidates_session(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->apiJson('POST', '/auth/logout');

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    public function test_logout_without_auth_returns_401(): void
    {
        $response = $this->apiJson('POST', '/auth/logout');

        $response->assertStatus(401);
    }

    // ── token (personal access token) ─────────────────────────────────────

    public function test_token_endpoint_creates_bearer_token(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->apiJson('POST', '/auth/token', [
                'name' => 'android-client',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => ['token_type' => 'Bearer'],
            ])
            ->assertJsonPath('data.token', fn ($val) => is_string($val) && strlen($val) > 10);
    }

    public function test_token_with_custom_abilities(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->apiJson('POST', '/auth/token', [
                'name' => 'read-only-client',
                'abilities' => ['events:read', 'notifications:read'],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.abilities', ['events:read', 'notifications:read']);
    }

    public function test_token_requires_name(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->apiJson('POST', '/auth/token', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_token_without_auth_returns_401(): void
    {
        $response = $this->apiJson('POST', '/auth/token', [
            'name' => 'some-client',
        ]);

        $response->assertStatus(401);
    }
}
