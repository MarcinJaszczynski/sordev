<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AjaxPackagesPartialTest extends TestCase
{
    use RefreshDatabase;
    /** @test */
    public function packages_partial_returns_json_for_ajax_request()
    {
        // Arrange
        // Prevent middleware that queries DB (ResolveRegionSlug etc.) from running in this focused test
        $this->withoutMiddleware();
        $url = '/warszawa/oferty?length_id=1&page=2&partial=1';

        // Act: make GET request with AJAX headers
        $response = $this->getJson($url, [
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ]);

        // Assert: response 200 and JSON contains html and has_more
        $response->assertStatus(200);
        $response->assertJsonStructure(['html', 'has_more']);

        $data = $response->json();
        $this->assertIsString($data['html']);
        $this->assertNotEmpty(trim($data['html']));
    }
}
