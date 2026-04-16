<?php

namespace App\Support;

final class StoragePath
{
    public static function normalize(?string $path): ?string
    {
        if ($path === null) {
            return null;
        }

        $path = trim($path);

        if ($path === '') {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            $parsedPath = parse_url($path, PHP_URL_PATH);

            if (!is_string($parsedPath) || $parsedPath === '') {
                return null;
            }

            $path = $parsedPath;
        }

        $path = str_replace('\\', '/', $path);
        $path = preg_replace('/[?#].*$/', '', $path) ?? $path;
        $path = ltrim($path, '/');

        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, strlen('storage/'));
        }

        if (str_starts_with($path, 'public/')) {
            $path = substr($path, strlen('public/'));
        }

        $path = ltrim($path, '/');

        return $path !== '' ? $path : null;
    }

    public static function publicUrl(?string $path): ?string
    {
        $normalized = self::normalize($path);

        return $normalized ? '/storage/' . $normalized : null;
    }
}