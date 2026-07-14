<?php

namespace App\Support;

use Illuminate\Foundation\ViteManifestNotFoundException;
use Illuminate\Support\Facades\Vite;

final class ViteAssetResolver
{
    public static function manifestExists(): bool
    {
        return is_file(public_path('vite-dist/manifest.json'))
            || is_file(public_path('build/manifest.json'))
            || is_file(public_path('hot'));
    }

    public static function asset(string $resourcePath, ?string $fallbackPublicAsset = null): string
    {
        if (self::manifestExists()) {
            try {
                return Vite::asset($resourcePath);
            } catch (ViteManifestNotFoundException) {
                // fall through
            }
        }

        if ($fallbackPublicAsset !== null && is_file(public_path($fallbackPublicAsset))) {
            return asset($fallbackPublicAsset);
        }

        $hashedAppCss = glob(public_path('dist-front/css/app.*.css')) ?: [];
        if ($hashedAppCss !== []) {
            return asset('dist-front/css/'.basename($hashedAppCss[0]));
        }

        return asset('css/filament/filament/app.css');
    }
}
