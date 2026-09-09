<?php

namespace App\Support;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;

/**
 * Klucz i limity OpenRouteService — panel admina (AppSetting) z fallbackiem do .env.
 *
 * Darmowy plan ORS (orientacyjnie): ~40 req/min, ~2000/dzień dla Directions.
 */
final class OpenRouteServiceSettings
{
    public const SETTING_KEY = 'openrouteservice';

    public const DEFAULT_REQUESTS_PER_MINUTE = 35;

    public const DEFAULT_DAILY_LIMIT = 2000;

    /** Bezpieczny odstęp przy ~35/min. */
    public const DEFAULT_MIN_INTERVAL_MS = 1700;

    /**
     * @return array{
     *     api_key: ?string,
     *     api_key_source: 'panel'|'env'|'none',
     *     api_key_masked: ?string,
     *     has_api_key: bool,
     *     requests_per_minute: int,
     *     daily_limit: int,
     *     min_interval_ms: int
     * }
     */
    public static function status(): array
    {
        $stored = self::stored();
        $panelKey = self::decryptKey($stored['api_key_encrypted'] ?? null);
        $envKey = filled(config('services.openrouteservice.key'))
            ? (string) config('services.openrouteservice.key')
            : null;

        $apiKey = $panelKey ?: $envKey;
        $source = $panelKey ? 'panel' : ($envKey ? 'env' : 'none');

        return [
            'api_key' => $apiKey,
            'api_key_source' => $source,
            'api_key_masked' => self::maskKey($apiKey),
            'has_api_key' => filled($apiKey),
            'requests_per_minute' => max(1, (int) ($stored['requests_per_minute']
                ?? config('services.openrouteservice.requests_per_minute')
                ?? self::DEFAULT_REQUESTS_PER_MINUTE)),
            'daily_limit' => max(1, (int) ($stored['daily_limit']
                ?? config('services.openrouteservice.daily_limit')
                ?? self::DEFAULT_DAILY_LIMIT)),
            'min_interval_ms' => max(0, (int) ($stored['min_interval_ms']
                ?? config('services.openrouteservice.min_interval_ms')
                ?? self::DEFAULT_MIN_INTERVAL_MS)),
        ];
    }

    public static function apiKey(): ?string
    {
        return self::status()['api_key'];
    }

    /**
     * @return array{requests_per_minute: int, daily_limit: int, min_interval_ms: int}
     */
    public static function limits(): array
    {
        $status = self::status();

        return [
            'requests_per_minute' => $status['requests_per_minute'],
            'daily_limit' => $status['daily_limit'],
            'min_interval_ms' => $status['min_interval_ms'],
        ];
    }

    /**
     * @param  array{api_key?: ?string, clear_api_key?: bool, requests_per_minute?: int, daily_limit?: int, min_interval_ms?: int}  $payload
     */
    public static function save(array $payload): void
    {
        $stored = self::stored();

        if (! empty($payload['clear_api_key'])) {
            unset($stored['api_key_encrypted']);
        } elseif (filled($payload['api_key'] ?? null)) {
            $stored['api_key_encrypted'] = Crypt::encryptString((string) $payload['api_key']);
        }

        if (array_key_exists('requests_per_minute', $payload)) {
            $stored['requests_per_minute'] = max(1, (int) $payload['requests_per_minute']);
        }
        if (array_key_exists('daily_limit', $payload)) {
            $stored['daily_limit'] = max(1, (int) $payload['daily_limit']);
        }
        if (array_key_exists('min_interval_ms', $payload)) {
            $stored['min_interval_ms'] = max(0, (int) $payload['min_interval_ms']);
        }

        AppSetting::setValue(self::SETTING_KEY, $stored);
    }

    /**
     * @return array<string, mixed>
     */
    private static function stored(): array
    {
        if (! Schema::hasTable('app_settings')) {
            return [];
        }

        $value = AppSetting::getValue(self::SETTING_KEY, []);

        return is_array($value) ? $value : [];
    }

    private static function decryptKey(mixed $encrypted): ?string
    {
        if (! is_string($encrypted) || $encrypted === '') {
            return null;
        }

        try {
            $plain = Crypt::decryptString($encrypted);

            return filled($plain) ? $plain : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private static function maskKey(?string $key): ?string
    {
        if (! filled($key)) {
            return null;
        }

        $len = strlen($key);
        if ($len <= 8) {
            return str_repeat('•', $len);
        }

        return substr($key, 0, 4).str_repeat('•', max(4, $len - 8)).substr($key, -4);
    }
}
