<?php

namespace Tests\Unit\Support;

use App\Models\PlaceDistance;
use App\Services\PlaceDistanceRouteService;
use App\Support\OpenRouteServiceSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpenRouteServiceSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_saves_encrypted_api_key_and_limits_in_panel(): void
    {
        OpenRouteServiceSettings::save([
            'api_key' => 'test-ors-secret-key-123456',
            'requests_per_minute' => 20,
            'daily_limit' => 500,
            'min_interval_ms' => 3000,
        ]);

        $status = OpenRouteServiceSettings::status();

        $this->assertTrue($status['has_api_key']);
        $this->assertSame('panel', $status['api_key_source']);
        $this->assertSame('test-ors-secret-key-123456', $status['api_key']);
        $this->assertSame(20, $status['requests_per_minute']);
        $this->assertSame(500, $status['daily_limit']);
        $this->assertSame(3000, $status['min_interval_ms']);
        $this->assertStringContainsString('•', (string) $status['api_key_masked']);
    }

    public function test_place_distance_source_labels_distinguish_formula_and_ors(): void
    {
        $this->assertSame(
            'Formuła (szacunek)',
            PlaceDistance::sourceLabel(PlaceDistanceRouteService::SOURCE_HAVERSINE),
        );
        $this->assertSame(
            'OpenRouteService (trasa)',
            PlaceDistance::sourceLabel(PlaceDistanceRouteService::SOURCE_ORS),
        );
        $this->assertSame('warning', PlaceDistance::sourceColor(PlaceDistanceRouteService::SOURCE_HAVERSINE));
        $this->assertSame('success', PlaceDistance::sourceColor(PlaceDistanceRouteService::SOURCE_ORS));
    }

    public function test_clear_api_key_falls_back_to_env(): void
    {
        config(['services.openrouteservice.key' => 'env-key-abcdef']);

        OpenRouteServiceSettings::save([
            'api_key' => 'panel-key-xyz',
        ]);
        $this->assertSame('panel', OpenRouteServiceSettings::status()['api_key_source']);

        OpenRouteServiceSettings::save(['clear_api_key' => true]);
        $status = OpenRouteServiceSettings::status();

        $this->assertSame('env', $status['api_key_source']);
        $this->assertSame('env-key-abcdef', $status['api_key']);
    }
}
