<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class ImageCompressionService
{
    public const JPEG_QUALITY = 85;

    public const WEBP_QUALITY = 85;

    public const MAX_WIDTH = 1920;

    public const MAX_HEIGHT = 1080;

    /** Kwadrat listingowy — 720 px wystarcza na Retinę przy slocie ~35% karty oferty. */
    public const THUMBNAIL_SIZE = 720;

    /**
     * Kompresuje i optymalizuje uploadowany obraz
     */
    public static function compressAndStore(UploadedFile $file, string $disk = 'public', string $directory = 'images'): array
    {
        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $extension = strtolower($file->getClientOriginalExtension());
        $safeName = self::transliterateFilename($originalName);
        $filename = uniqid($safeName.'_').'.'.$extension;

        // Załaduj obraz
        $manager = new ImageManager(new Driver);
        $image = $manager->read($file->getRealPath());

        // Optymalizuj rozmiar
        $image = self::resizeIfNeeded($image);

        // Zapisz oryginalny format (skompresowany)
        $originalPath = $directory.'/'.$filename;
        $compressedData = self::compressImage($image, $extension);
        Storage::disk($disk)->put($originalPath, $compressedData);

        // Utwórz WebP wersję (jeśli nie jest już WebP)
        $webpPath = null;
        if ($extension !== 'webp') {
            $webpFilename = pathinfo($filename, PATHINFO_FILENAME).'.webp';
            $webpPath = $directory.'/'.$webpFilename;
            $webpData = $image->toWebp(self::WEBP_QUALITY);
            Storage::disk($disk)->put($webpPath, $webpData);
        }

        // Utwórz miniaturę (cover = zachowane proporcje + kadr do centrum)
        $thumbnailPath = $directory.'/thumbs/'.$filename;
        $thumbnailData = self::compressImage(self::makeCenteredThumbnail($image), $extension);
        Storage::disk($disk)->put($thumbnailPath, $thumbnailData);

        $originalSize = $file->getSize();
        $compressedSize = Storage::disk($disk)->size($originalPath);
        $compressionRatio = round((1 - $compressedSize / $originalSize) * 100, 1);

        return [
            'original' => $originalPath,
            'webp' => $webpPath,
            'thumbnail' => $thumbnailPath,
            'filename' => $filename,
            'size_original' => $originalSize,
            'size_compressed' => $compressedSize,
            'compression_ratio' => $compressionRatio,
            'dimensions' => [
                'width' => $image->width(),
                'height' => $image->height(),
            ],
        ];
    }

    /**
     * Kompresuje istniejące obrazy w storage
     */
    public static function compressExistingImages(string $disk = 'public', string $directory = 'images', bool $force = false): array
    {
        $files = self::preferPrimarySources(
            array_values(array_filter(
                Storage::disk($disk)->allFiles($directory),
                fn (string $filePath): bool => self::isProcessableSourceImage($filePath)
            ))
        );
        $results = [];

        foreach ($files as $filePath) {
            try {
                $results[] = self::compressExistingImage($disk, $filePath, $force);
            } catch (\Exception $e) {
                $results[] = [
                    'file' => $filePath,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Usuwa wszystkie pliki w podkatalogach thumbs/ pod wskazanym katalogiem.
     */
    public static function purgeThumbnails(string $disk, string $directory): int
    {
        $storage = Storage::disk($disk);
        $deleted = 0;

        foreach ($storage->allFiles($directory) as $filePath) {
            $normalized = str_replace('\\', '/', $filePath);

            if (! str_contains($normalized, '/thumbs/')) {
                continue;
            }

            $storage->delete($filePath);
            $deleted++;
        }

        return $deleted;
    }

    /**
     * Kompresuje pojedynczy istniejący obraz
     */
    public static function compressExistingImage(string $disk, string $filePath, bool $force = false): array
    {
        $storage = Storage::disk($disk);
        $originalSize = $storage->size($filePath);

        // Załaduj obraz z storage
        $imageData = $storage->get($filePath);
        $manager = new ImageManager(new Driver);
        $image = $manager->read($imageData);

        $widthBefore = $image->width();
        $heightBefore = $image->height();

        // Optymalizuj wymiary (bez rozciągania)
        $image = self::resizeIfNeeded($image);
        $extension = strtolower((string) pathinfo($filePath, PATHINFO_EXTENSION));

        // Nadpisuj pełny plik tylko gdy wymiary faktycznie się zmieniły
        // (unikamy ponownej kompresji JPEG i utraty jakości przy regeneracji miniaturek).
        if ($image->width() !== $widthBefore || $image->height() !== $heightBefore) {
            $storage->put($filePath, self::compressImage($image, $extension));
        }

        $thumbnailPath = self::thumbnailPathFor($filePath);
        if ($force || ! $storage->exists($thumbnailPath)) {
            $storage->put(
                $thumbnailPath,
                self::compressImage(self::makeCenteredThumbnail($image), $extension)
            );
        }

        $webpPath = null;
        if ($extension !== 'webp') {
            $webpPath = self::webpPathFor($filePath);
            if ($force || ! $storage->exists($webpPath)) {
                $storage->put($webpPath, $image->toWebp(self::WEBP_QUALITY));
            }
        }

        $newSize = $storage->size($filePath);
        $compressionRatio = round((1 - $newSize / max($originalSize, 1)) * 100, 1);

        return [
            'file' => $filePath,
            'size_before' => $originalSize,
            'size_after' => $newSize,
            'compression_ratio' => $compressionRatio,
            'saved_bytes' => $originalSize - $newSize,
            'thumbnail' => $thumbnailPath,
            'webp' => $webpPath,
        ];
    }

    /**
     * Zmniejsza obraz tylko gdy przekracza limity — bez rozciągania proporcji.
     * Intervention v3: scaleDown (nie resize z callbackiem z v2).
     */
    private static function resizeIfNeeded($image)
    {
        $image->scaleDown(self::MAX_WIDTH, self::MAX_HEIGHT);

        return $image;
    }

    /**
     * Kwadratowa miniatura: wypełnia ramkę, kadruje do centrum, zachowuje proporcje.
     */
    private static function makeCenteredThumbnail($image)
    {
        $thumbnail = clone $image;
        $thumbnail->cover(self::THUMBNAIL_SIZE, self::THUMBNAIL_SIZE, 'center');

        return $thumbnail;
    }

    /**
     * Kompresuje obraz do odpowiedniego formatu
     */
    private static function compressImage($image, string $extension): string
    {
        return match (strtolower($extension)) {
            'jpg', 'jpeg' => $image->toJpeg(self::JPEG_QUALITY),
            'png' => $image->toPng(),
            'gif' => $image->toGif(),
            'webp' => $image->toWebp(self::WEBP_QUALITY),
            default => $image->toJpeg(self::JPEG_QUALITY),
        };
    }

    /**
     * Sprawdza czy plik jest obrazem
     */
    private static function isImageFile(string $filePath): bool
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        return in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
    }

    private static function isProcessableSourceImage(string $filePath): bool
    {
        if (! self::isImageFile($filePath)) {
            return false;
        }

        $normalized = str_replace('\\', '/', $filePath);

        return ! str_contains($normalized, '/thumbs/');
    }

    /**
     * Pomija WebP-sibling gdy istnieje JPG/PNG/GIF o tej samej nazwie —
     * prewki budujemy z oryginału, nie z już stratnej kopii WebP.
     *
     * @param  list<string>  $filePaths
     * @return list<string>
     */
    private static function preferPrimarySources(array $filePaths): array
    {
        $grouped = [];

        foreach ($filePaths as $filePath) {
            $normalized = str_replace('\\', '/', $filePath);
            $directory = pathinfo($normalized, PATHINFO_DIRNAME);
            $basename = pathinfo($normalized, PATHINFO_FILENAME);
            $extension = strtolower((string) pathinfo($normalized, PATHINFO_EXTENSION));
            $key = ($directory === '.' ? '' : $directory).'/'.$basename;

            $grouped[$key][$extension] = $normalized;
        }

        $preferred = [];

        foreach ($grouped as $variants) {
            $primary = array_filter(
                $variants,
                fn (string $path, string $extension): bool => $extension !== 'webp',
                ARRAY_FILTER_USE_BOTH
            );

            if ($primary === []) {
                $preferred[] = $variants['webp'];

                continue;
            }

            foreach ($primary as $path) {
                $preferred[] = $path;
            }
        }

        return $preferred;
    }

    private static function thumbnailPathFor(string $filePath): string
    {
        $directory = pathinfo($filePath, PATHINFO_DIRNAME);
        $filename = pathinfo($filePath, PATHINFO_BASENAME);

        if (! $directory || $directory === '.') {
            return 'thumbs/'.$filename;
        }

        return trim($directory, '/').'/thumbs/'.$filename;
    }

    private static function webpPathFor(string $filePath): string
    {
        $directory = pathinfo($filePath, PATHINFO_DIRNAME);
        $filename = pathinfo($filePath, PATHINFO_FILENAME).'.webp';

        if (! $directory || $directory === '.') {
            return $filename;
        }

        return trim($directory, '/').'/'.$filename;
    }

    /**
     * Transliterate filename to ASCII (remove diacritics)
     */
    private static function transliterateFilename(string $filename): string
    {
        $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $filename);
        // Zamień spacje i niebezpieczne znaki na podkreślenia
        $transliterated = preg_replace('/[^A-Za-z0-9_.-]/', '_', $transliterated);

        return $transliterated;
    }

    /**
     * Formatuje bajty do czytelnej postaci
     */
    public static function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision).' '.$units[$i];
    }
}
