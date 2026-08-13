<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FileSecurityService
{
    // Dozwolone typy MIME dla obrazów
    private const ALLOWED_IMAGE_MIMES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/bmp',
    ];

    // Niebezpieczne rozszerzenia
    private const DANGEROUS_EXTENSIONS = [
        'php', 'phar', 'php3', 'php4', 'php5', 'php7', 'php8',
        'phtml', 'pht', 'phps', 'jsp', 'asp', 'aspx',
        'exe', 'bat', 'cmd', 'com', 'scr', 'vbs', 'js',
        'jar', 'war', 'ear', 'zip', 'rar', '7z',
        'sql', 'db', 'sqlite', 'mdb',
        'htaccess', 'htpasswd', 'ini', 'conf', 'config',
    ];

    // Podejrzane sygnatury plików (magic bytes)
    private const MALICIOUS_SIGNATURES = [
        "\x4D\x5A",                    // PE executable (MZ)
        "\x7F\x45\x4C\x46",          // ELF executable
        "\x50\x4B\x03\x04",          // ZIP/JAR/APK
        "\x52\x61\x72\x21",          // RAR archive
        "\x1F\x8B\x08",              // GZIP
        '<?php',                      // PHP code
        '<script',                    // JavaScript
        '<%',                         // ASP code
        '#!/bin/',                    // Shell script
    ];

    /**
     * Sprawdza bezpieczeństwo uploadowanego pliku
     */
    public static function validateFileUpload(UploadedFile $file): array
    {
        $errors = [];
        $warnings = [];

        // 1. Sprawdź rozmiar pliku
        $maxSize = config('filesystems.max_file_size', 50 * 1024 * 1024); // 50MB
        if ($file->getSize() > $maxSize) {
            $errors[] = 'Plik jest za duży. Maksymalny rozmiar: '.self::formatBytes($maxSize);
        }

        // 2. Sprawdź rozszerzenie
        $extension = strtolower($file->getClientOriginalExtension());
        if (in_array($extension, self::DANGEROUS_EXTENSIONS)) {
            $errors[] = "Niebezpieczne rozszerzenie pliku: .{$extension}";
        }

        // 3. Sprawdź typ MIME
        $mimeType = $file->getMimeType();
        $realMimeType = mime_content_type($file->getRealPath());

        if ($mimeType !== $realMimeType) {
            $warnings[] = "Niezgodność typu MIME: deklarowany {$mimeType}, rzeczywisty {$realMimeType}";
        }

        // 4. Sprawdź czy to rzeczywiście obraz (dla plików obrazowych)
        if (str_starts_with($mimeType, 'image/')) {
            if (! in_array($mimeType, self::ALLOWED_IMAGE_MIMES)) {
                $errors[] = "Nieobsługiwany typ obrazu: {$mimeType}";
            }

            // Sprawdź czy można odczytać obraz
            try {
                $imageInfo = getimagesize($file->getRealPath());
                if ($imageInfo === false) {
                    $errors[] = 'Plik nie jest prawidłowym obrazem';
                }
            } catch (\Exception $e) {
                $errors[] = 'Błąd odczytu obrazu: '.$e->getMessage();
            }
        }

        // 5. Skanuj pod kątem złośliwej zawartości
        $scanResult = self::scanFileContent($file);
        if (! $scanResult['safe']) {
            $errors = array_merge($errors, $scanResult['threats']);
        }

        // 6. Sprawdź nazwę pliku
        $filename = $file->getClientOriginalName();
        if (! self::isValidFilename($filename)) {
            $warnings[] = "Podejrzana nazwa pliku: {$filename}";
        }

        return [
            'safe' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
            'file_info' => [
                'name' => $filename,
                'size' => $file->getSize(),
                'mime_type' => $mimeType,
                'real_mime_type' => $realMimeType,
                'extension' => $extension,
            ],
        ];
    }

    /**
     * Walidacja dokumentów rozliczeniowych (PDF / obrazy) — bez agresywnego skanu ZIP
     * (PDF często zawiera sekwencje PK i dawałby false positive).
     *
     * @return array{safe: bool, errors: list<string>, warnings: list<string>}
     */
    public static function validateDocumentUpload(UploadedFile $file): array
    {
        $errors = [];
        $warnings = [];

        $maxSize = config('filesystems.max_file_size', 50 * 1024 * 1024);
        if ($file->getSize() > $maxSize) {
            $errors[] = 'Plik jest za duży. Maksymalny rozmiar: '.self::formatBytes($maxSize);
        }

        $extension = strtolower($file->getClientOriginalExtension());
        if (in_array($extension, self::DANGEROUS_EXTENSIONS, true)) {
            $errors[] = "Niebezpieczne rozszerzenie pliku: .{$extension}";
        }

        $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (! in_array($extension, $allowedExtensions, true)) {
            $errors[] = "Niedozwolony typ pliku: .{$extension}";
        }

        $allowedMimes = [
            'application/pdf',
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
        ];
        $mimeType = (string) $file->getMimeType();
        if (! in_array($mimeType, $allowedMimes, true)) {
            $errors[] = "Niedozwolony typ MIME: {$mimeType}";
        }

        if (str_starts_with($mimeType, 'image/')) {
            if (! in_array($mimeType, self::ALLOWED_IMAGE_MIMES, true)) {
                $errors[] = "Nieobsługiwany typ obrazu: {$mimeType}";
            }
            try {
                if (@getimagesize($file->getRealPath()) === false) {
                    $errors[] = 'Plik nie jest prawidłowym obrazem';
                }
            } catch (\Throwable $e) {
                $errors[] = 'Błąd odczytu obrazu: '.$e->getMessage();
            }
        }

        $head = (string) file_get_contents($file->getRealPath(), false, null, 0, 512);
        if (str_contains(strtolower($head), '<?php') || str_contains(strtolower($head), '<script')) {
            $errors[] = 'Wykryto podejrzaną zawartość w pliku';
        }

        if (! self::isValidFilename($file->getClientOriginalName())) {
            $warnings[] = 'Podejrzana nazwa pliku: '.$file->getClientOriginalName();
        }

        return [
            'safe' => $errors === [],
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * Skanuje zawartość pliku pod kątem zagrożeń
     */
    private static function scanFileContent(UploadedFile $file): array
    {
        $threats = [];
        $content = file_get_contents($file->getRealPath());

        // Sprawdź sygnatury złośliwych plików
        foreach (self::MALICIOUS_SIGNATURES as $signature) {
            if (str_contains($content, $signature)) {
                $threats[] = 'Wykryto podejrzaną sygnaturę pliku';
                break;
            }
        }

        // Sprawdź kod PHP/JavaScript w obrazach
        if (str_starts_with($file->getMimeType(), 'image/')) {
            $suspiciousPatterns = [
                '/<\?php/i',
                '/<script/i',
                '/eval\s*\(/i',
                '/base64_decode/i',
                '/shell_exec/i',
                '/system\s*\(/i',
                '/exec\s*\(/i',
                '/passthru/i',
                '/file_get_contents/i',
                '/file_put_contents/i',
                '/fopen\s*\(/i',
                '/curl_exec/i',
            ];

            foreach ($suspiciousPatterns as $pattern) {
                if (preg_match($pattern, $content)) {
                    $threats[] = 'Wykryto podejrzany kod w pliku obrazu';
                    break;
                }
            }
        }

        // Sprawdź zbyt długie linie (mogą wskazywać na obfuskację)
        $lines = explode("\n", $content);
        foreach ($lines as $line) {
            if (strlen($line) > 10000) {
                $threats[] = 'Wykryto podejrzanie długie linie w pliku';
                break;
            }
        }

        return [
            'safe' => empty($threats),
            'threats' => $threats,
        ];
    }

    /**
     * Sprawdza czy nazwa pliku jest bezpieczna
     */
    private static function isValidFilename(string $filename): bool
    {
        // Sprawdź długość nazwy
        if (strlen($filename) > 255) {
            return false;
        }

        // Sprawdź niebezpieczne znaki
        $dangerousChars = ['<', '>', ':', '"', '|', '?', '*', '\\', '/', "\0"];
        foreach ($dangerousChars as $char) {
            if (str_contains($filename, $char)) {
                return false;
            }
        }

        // Sprawdź podejrzane wzorce
        $suspiciousPatterns = [
            '/\.\./i',           // Directory traversal
            '/\.(php|phtml|asp|jsp)(\.|$)/i',  // Executable extensions
            '/^(con|prn|aux|nul|com[1-9]|lpt[1-9])(\.|$)/i', // Windows reserved names
        ];

        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $filename)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Generuje bezpieczną nazwę pliku
     */
    public static function sanitizeFilename(string $filename): string
    {
        // Usuń ścieżkę
        $filename = basename($filename);

        // Rozdziel nazwę i rozszerzenie
        $info = pathinfo($filename);
        $name = $info['filename'] ?? '';
        $extension = $info['extension'] ?? '';

        // Oczyść nazwę
        $name = preg_replace('/[^a-zA-Z0-9\-_\.]/', '_', $name);
        $name = preg_replace('/_+/', '_', $name);
        $name = trim($name, '_');

        // Ogranicz długość
        $name = Str::limit($name, 100, '');

        // Jeśli nazwa jest pusta, wygeneruj losową
        if (empty($name)) {
            $name = 'file_'.Str::random(8);
        }

        // Sprawdź rozszerzenie
        if (! empty($extension)) {
            $extension = strtolower($extension);
            if (in_array($extension, self::DANGEROUS_EXTENSIONS)) {
                $extension = 'txt';
            }

            return $name.'.'.$extension;
        }

        return $name;
    }

    /**
     * Usuwa metadane EXIF z obrazu (zabezpieczenie prywatności)
     */
    public static function stripImageMetadata(string $imagePath): bool
    {
        try {
            if (! extension_loaded('exif')) {
                return false;
            }

            $imageType = exif_imagetype($imagePath);

            switch ($imageType) {
                case IMAGETYPE_JPEG:
                    $image = imagecreatefromjpeg($imagePath);
                    if ($image) {
                        imagejpeg($image, $imagePath, 85);
                        imagedestroy($image);

                        return true;
                    }
                    break;

                case IMAGETYPE_PNG:
                    $image = imagecreatefrompng($imagePath);
                    if ($image) {
                        imagepng($image, $imagePath);
                        imagedestroy($image);

                        return true;
                    }
                    break;
            }
        } catch (\Exception $e) {
            Log::warning('Failed to strip image metadata', [
                'file' => $imagePath,
                'error' => $e->getMessage(),
            ]);
        }

        return false;
    }

    /**
     * Loguje próbę przesłania niebezpiecznego pliku
     */
    public static function logSecurityIncident(array $validationResult, ?string $userInfo = null): void
    {
        if (! $validationResult['safe']) {
            Log::warning('Dangerous file upload attempt blocked', [
                'file_info' => $validationResult['file_info'],
                'errors' => $validationResult['errors'],
                'warnings' => $validationResult['warnings'],
                'user_info' => $userInfo,
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'timestamp' => now(),
            ]);
        }
    }

    /**
     * Sprawdza limity uploadów dla użytkownika (rate limiting)
     */
    public static function checkUploadLimits(string $userId): array
    {
        $cacheKey = "upload_limits_{$userId}";
        $hourlyLimit = config('security.hourly_upload_limit', 50);
        $dailyLimit = config('security.daily_upload_limit', 200);

        $hourlyCount = cache()->get("{$cacheKey}_hourly", 0);
        $dailyCount = cache()->get("{$cacheKey}_daily", 0);

        $errors = [];

        if ($hourlyCount >= $hourlyLimit) {
            $errors[] = "Przekroczono limit uploadów na godzinę ({$hourlyLimit})";
        }

        if ($dailyCount >= $dailyLimit) {
            $errors[] = "Przekroczono dzienny limit uploadów ({$dailyLimit})";
        }

        return [
            'allowed' => empty($errors),
            'errors' => $errors,
            'limits' => [
                'hourly' => ['current' => $hourlyCount, 'max' => $hourlyLimit],
                'daily' => ['current' => $dailyCount, 'max' => $dailyLimit],
            ],
        ];
    }

    /**
     * Aktualizuje liczniki uploadów
     */
    public static function updateUploadCounters(string $userId): void
    {
        $cacheKey = "upload_limits_{$userId}";

        // Licznik godzinowy
        cache()->increment("{$cacheKey}_hourly", 1, now()->addHour());

        // Licznik dzienny
        cache()->increment("{$cacheKey}_daily", 1, now()->addDay());
    }

    /**
     * Formatuje bajty do czytelnej postaci
     */
    private static function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2).' '.$units[$i];
    }
}
