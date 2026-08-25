<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\Installer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ResolveRegionSlugMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        $marker = storage_path(Installer::INSTALLED_MARKER);
        if (File::exists($marker)) {
            File::delete($marker);
        }

        parent::tearDown();
    }

    public function test_install_route_does_not_query_missing_places_table(): void
    {
        if (Schema::hasTable('places')) {
            Schema::drop('places');
        }

        Installer::markInstalled();

        $response = $this
            ->withUnencryptedCookie('start_place_id', '36')
            ->get('/install');

        $response->assertRedirect('/admin');
    }
}
