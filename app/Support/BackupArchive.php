<?php

namespace App\Support;

use ZipArchive;

final class BackupArchive
{
    public const COMPONENT_DB = 'db';

    public const COMPONENT_STORAGE = 'storage';

    public const COMPONENT_CODE = 'code';

    public const COMPONENT_ENV = 'env';

    /** @var list<string> */
    public const ALL_COMPONENTS = [
        self::COMPONENT_DB,
        self::COMPONENT_STORAGE,
        self::COMPONENT_CODE,
        self::COMPONENT_ENV,
    ];

    /** @var list<string> */
    public const FULL_APPLICATION_COMPONENTS = [
        self::COMPONENT_DB,
        self::COMPONENT_STORAGE,
        self::COMPONENT_CODE,
    ];

    /**
     * @return list<string>
     */
    public static function parseComponents(?string $raw, ?array $limitTo = null): array
    {
        if ($raw === null || trim($raw) === '') {
            return $limitTo ?? [];
        }

        $parts = array_map('trim', explode(',', $raw));
        $components = array_values(array_intersect($parts, self::ALL_COMPONENTS));

        if ($limitTo !== null) {
            $components = array_values(array_intersect($components, $limitTo));
        }

        return $components;
    }

    public static function addDirectoryToZip(
        ZipArchive $zip,
        string $sourceDir,
        string $zipPrefix,
        ?callable $shouldSkip = null,
    ): int {
        $sourceDir = rtrim($sourceDir, DIRECTORY_SEPARATOR);
        $zipPrefix = trim($zipPrefix, '/');
        $count = 0;

        if (! is_dir($sourceDir)) {
            return 0;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourceDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $absolute = $file->getRealPath();
            $relative = substr($absolute, strlen($sourceDir) + 1);

            if ($shouldSkip !== null && $shouldSkip($relative, $absolute)) {
                continue;
            }

            $entry = $zipPrefix === '' ? $relative : $zipPrefix.'/'.$relative;
            $zip->addFile($absolute, str_replace('\\', '/', $entry));
            $count++;
        }

        return $count;
    }

    public static function shouldSkipStoragePath(string $relative): bool
    {
        $relative = str_replace('\\', '/', $relative);

        if (str_starts_with($relative, 'backups/')) {
            return true;
        }

        if (str_starts_with($relative, 'framework/cache/')) {
            return true;
        }

        if (str_starts_with($relative, 'framework/sessions/')) {
            return true;
        }

        if (str_starts_with($relative, 'framework/views/')) {
            return true;
        }

        if (str_starts_with($relative, 'logs/')) {
            return true;
        }

        return false;
    }

    public static function shouldSkipCodePath(string $relative): bool
    {
        $relative = str_replace('\\', '/', $relative);

        $blockedPrefixes = [
            'vendor/',
            'node_modules/',
            '.git/',
            'storage/',
            'bootstrap/cache/',
        ];

        foreach ($blockedPrefixes as $prefix) {
            if (str_starts_with($relative, $prefix)) {
                return true;
            }
        }

        if ($relative === '.env' || $relative === '.env.backup') {
            return true;
        }

        return false;
    }

    /**
     * @return list<string>
     */
    public static function codeRootPaths(): array
    {
        $paths = [
            'app',
            'bootstrap',
            'config',
            'database',
            'public',
            'resources',
            'routes',
            'artisan',
            'composer.json',
            'composer.lock',
            'package.json',
            'package-lock.json',
            'vite.config.js',
            'tailwind.config.js',
            'postcss.config.js',
            'phpunit.xml',
            'pint.json',
        ];

        return array_values(array_filter($paths, fn (string $path) => file_exists(base_path($path))));
    }

    public static function restoreTree(string $sourceDir, string $targetDir, bool $wipeTargetFirst = false): void
    {
        $sourceDir = rtrim($sourceDir, DIRECTORY_SEPARATOR);
        $targetDir = rtrim($targetDir, DIRECTORY_SEPARATOR);

        if (! is_dir($sourceDir)) {
            throw new \RuntimeException("Katalog źródłowy nie istnieje: {$sourceDir}");
        }

        if ($wipeTargetFirst && is_dir($targetDir)) {
            self::deleteDirectory($targetDir);
        }

        if (! is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourceDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $relative = substr($item->getRealPath(), strlen($sourceDir) + 1);
            $target = $targetDir.DIRECTORY_SEPARATOR.$relative;

            if ($item->isDir()) {
                if (! is_dir($target)) {
                    mkdir($target, 0755, true);
                }
            } else {
                $parent = dirname($target);
                if (! is_dir($parent)) {
                    mkdir($parent, 0755, true);
                }
                copy($item->getRealPath(), $target);
            }
        }
    }

    public static function deleteDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            if ($item->isDir()) {
                rmdir($item->getRealPath());
            } else {
                unlink($item->getRealPath());
            }
        }

        rmdir($dir);
    }

    public static function isLegacyPublicOnlyStorageManifest(array $manifest): bool
    {
        return ($manifest['storage_scope'] ?? 'public') === 'public';
    }
}
