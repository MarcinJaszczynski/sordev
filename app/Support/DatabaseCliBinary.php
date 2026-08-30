<?php

namespace App\Support;

/**
 * Lokalizuje mysql / mysqldump — PATH, Homebrew, DBngin (Herd).
 */
final class DatabaseCliBinary
{
    public static function find(string $name): ?string
    {
        $which = trim((string) shell_exec('which '.escapeshellarg($name).' 2>/dev/null'));
        if ($which !== '' && is_executable($which)) {
            return $which;
        }

        foreach (self::candidatePaths($name) as $path) {
            if (is_executable($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public static function candidatePaths(string $name): array
    {
        $paths = [
            '/usr/bin/'.$name,
            '/usr/local/bin/'.$name,
            '/usr/local/mysql/bin/'.$name,
            '/opt/homebrew/bin/'.$name,
            '/opt/homebrew/opt/mysql-client/bin/'.$name,
            '/opt/homebrew/opt/mysql/bin/'.$name,
            '/usr/local/opt/mysql-client/bin/'.$name,
            '/usr/local/opt/mysql/bin/'.$name,
        ];

        foreach (self::dbnginBinaryPaths($name) as $path) {
            $paths[] = $path;
        }

        return $paths;
    }

    /**
     * Najnowsza wersja DBngin pierwsza (sortowanie malejące po nazwie katalogu).
     *
     * @return list<string>
     */
    private static function dbnginBinaryPaths(string $name): array
    {
        $pattern = '/Users/Shared/DBngin/mysql/*/bin/'.$name;
        $matches = glob($pattern) ?: [];

        rsort($matches, SORT_NATURAL);

        return array_values(array_filter($matches, 'is_string'));
    }
}
