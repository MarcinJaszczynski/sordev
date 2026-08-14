<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\EventTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PublicInquiryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_inquiry_creates_lead(): void
    {
        Mail::fake();

        EventTemplate::factory()->create([
            'name' => 'Oferta API',
            'slug' => 'oferta-api',
            'is_active' => true,
        ]);

        $response = $this->json('POST', '/api/v1/public/inquiries', [
            'name' => 'Jan Test',
            'email' => 'jan@example.com',
            'telephone' => '+48123123123',
            'message' => 'Proszę o ofertę',
            'package_slug' => 'oferta-api',
        ], ['Accept' => 'application/json']);

        $response->assertCreated()
            ->assertJsonPath('success', true);
    }

    public function test_honeypot_is_silently_accepted(): void
    {
        $response = $this->json('POST', '/api/v1/public/inquiries', [
            'email' => 'bot@example.com',
            'telephone' => '123',
            'website' => 'http://spam.test',
        ], ['Accept' => 'application/json']);

        $response->assertOk()->assertJsonPath('data.accepted', true);
    }
}
