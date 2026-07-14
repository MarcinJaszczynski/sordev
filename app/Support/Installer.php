<?php

namespace App\Support;

use Illuminate\Support\Facades\File;

final class Installer
{
    public const INSTALLED_MARKER = 'app/.installed';

    public static function isInstalled(): bool
    {
        if (File::exists(storage_path(self::INSTALLED_MARKER))) {
            return true;
        }

        if (! File::exists(base_path('.env'))) {
            return false;
        }

        try {
            return \Illuminate\Support\Facades\Schema::hasTable('migrations');
        } catch (\Throwable) {
            return false;
        }
    }

    public static function markInstalled(): void
    {
        $path = storage_path(self::INSTALLED_MARKER);
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode([
            'installed_at' => now()->toIso8601String(),
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    /**
     * @return array<string, array{ok: bool, message: string}>
     */
    public static function requirements(): array
    {
        $checks = [];

        $checks['php'] = [
            'ok' => version_compare(PHP_VERSION, '8.2.0', '>='),
            'message' => 'PHP '.PHP_VERSION.' (wymagane ≥ 8.2)',
        ];

        foreach (['pdo', 'mbstring', 'openssl', 'tokenizer', 'xml', 'ctype', 'json', 'fileinfo', 'zip'] as $ext) {
            $checks['ext_'.$ext] = [
                'ok' => extension_loaded($ext),
                'message' => "Rozszerzenie PHP: {$ext}",
            ];
        }

        $checks['storage_writable'] = [
            'ok' => is_writable(storage_path()),
            'message' => 'storage/ — zapis',
        ];

        $checks['bootstrap_cache_writable'] = [
            'ok' => is_writable(base_path('bootstrap/cache')),
            'message' => 'bootstrap/cache/ — zapis',
        ];

        $checks['env_writable'] = [
            'ok' => ! file_exists(base_path('.env')) || is_writable(base_path('.env')),
            'message' => '.env — możliwość zapisu',
        ];

        return $checks;
    }

    public static function requirementsMet(): bool
    {
        foreach (self::requirements() as $check) {
            if (! $check['ok']) {
                return false;
            }
        }

        return true;
    }

    public static function envTemplate(): string
    {
        $example = base_path('.env.example');
        if (file_exists($example)) {
            return (string) file_get_contents($example);
        }

        return <<<'ENV'
APP_NAME="SOR"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=http://localhost

LOG_CHANNEL=stack
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=
DB_USERNAME=
DB_PASSWORD=

SESSION_DRIVER=file
QUEUE_CONNECTION=sync
CACHE_STORE=file

ENV;
    }

    public static function writeEnv(array $values): void
    {
        $content = self::envTemplate();

        foreach ($values as $key => $value) {
            $escaped = str_replace('"', '\\"', (string) $value);
            $pattern = '/^'.preg_quote($key, '/').'=.*/m';
            $replacement = $key.'="'.$escaped.'"';

            if (preg_match($pattern, $content)) {
                $content = preg_replace($pattern, $replacement, $content) ?? $content;
            } else {
                $content .= "\n{$replacement}";
            }
        }

        if (! str_contains($content, 'APP_KEY=') || preg_match('/^APP_KEY=\s*$/m', $content)) {
            $content = preg_replace('/^APP_KEY=.*/m', 'APP_KEY='.base64_encode(random_bytes(32)), $content) ?? $content;
        }

        file_put_contents(base_path('.env'), $content);
    }
}
