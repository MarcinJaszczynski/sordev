<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\EventTemplate;
use App\Models\Place;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PublicCatalogApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_packages_index_returns_only_active_templates(): void
    {
        EventTemplate::factory()->create(['name' => 'Aktywna oferta', 'is_active' => true, 'slug' => 'aktywna-oferta']);
        EventTemplate::factory()->create(['name' => 'Ukryta', 'is_active' => false, 'slug' => 'ukryta']);

        $response = $this->json('GET', '/api/v1/public/packages', [], ['Accept' => 'application/json']);

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.items.0.slug', 'aktywna-oferta');
    }

    public function test_packages_show_by_slug(): void
    {
        EventTemplate::factory()->create([
            'name' => 'Wycieczka Kraków',
            'slug' => 'wycieczka-krakow',
            'is_active' => true,
            'event_description' => 'Opis publiczny',
        ]);

        $response = $this->json('GET', '/api/v1/public/packages/wycieczka-krakow', [], ['Accept' => 'application/json']);

        $response->assertOk()
            ->assertJsonPath('data.slug', 'wycieczka-krakow')
            ->assertJsonPath('data.event_description', 'Opis publiczny')
            ->assertJsonMissingPath('data.office_description');
    }

    public function test_packages_show_unknown_returns_404(): void
    {
        $response = $this->json('GET', '/api/v1/public/packages/brak-takiej', [], ['Accept' => 'application/json']);

        $response->assertStatus(404)
            ->assertJson(['success' => false]);
    }

    public function test_regions_lists_starting_places(): void
    {
        if (! Schema::hasColumn('places', 'starting_place')) {
            $this->markTestSkipped('Brak kolumny starting_place.');
        }

        Place::factory()->create(['name' => 'Warszawa', 'starting_place' => true]);
        Place::factory()->create(['name' => 'Hotel X', 'starting_place' => false]);

        $response = $this->json('GET', '/api/v1/public/regions', [], ['Accept' => 'application/json']);

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name')->all();
        $this->assertContains('Warszawa', $names);
        $this->assertNotContains('Hotel X', $names);
    }
}
