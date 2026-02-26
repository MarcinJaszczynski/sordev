<?php

namespace App\Services;

use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;

class ImageCompressionService
{
    public const JPEG_QUALITY = 85;
    public const WEBP_QUALITY = 85;
    public const MAX_WIDTH = 1920;
    public const MAX_HEIGHT = 1080;
    public const THUMBNAIL_SIZE = 300;
    
    /**
     * Kompresuje i optymalizuje uploadowany obraz
     */
    public static function compressAndStore(UploadedFile $file, string $disk = 'public', string $directory = 'images'): array
    {
        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $extension = strtolower($file->getClientOriginalExtension());
        $filename = uniqid($originalName . '_') . '.' . $extension;
        
        // Załaduj obraz
        $manager = new ImageManager(new Driver());
        $image = $manager->read($file->getRealPath());
        
        // Optymalizuj rozmiar
        $image = self::resizeIfNeeded($image);
        
        // Zapisz oryginalny format (skompresowany)
        $originalPath = $directory . '/' . $filename;
        $compressedData = self::compressImage($image, $extension);
        Storage::disk($disk)->put($originalPath, $compressedData);
        
        // Utwórz WebP wersję (jeśli nie jest już WebP)
        $webpPath = null;
        if ($extension !== 'webp') {
            $webpFilename = pathinfo($filename, PATHINFO_FILENAME) . '.webp';
            $webpPath = $directory . '/' . $webpFilename;
            $webpData = $image->toWebp(self::WEBP_QUALITY);
            Storage::disk($disk)->put($webpPath, $webpData);
        }
        
        // Utwórz miniaturę
        $thumbnailPath = $directory . '/thumbs/' . $filename;
        $thumbnail = clone $image;
        $thumbnail->resize(self::THUMBNAIL_SIZE, self::THUMBNAIL_SIZE, function ($constraint) {
            $constraint->aspectRatio();
            $constraint->upsize();
        });
        $thumbnailData = self::compressImage($thumbnail, $extension);
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
    public static function compressExistingImages(string $disk = 'public', string $directory = 'images'): array
    {
        $files = Storage::disk($disk)->allFiles($directory);
        $results = [];
        
        foreach ($files as $filePath) {
            if (self::isImageFile($filePath)) {
                try {
                    $result = self::compressExistingImage($disk, $filePath);
                    $results[] = $result;
                } catch (\Exception $e) {
                    $results[] = [
                        'file' => $filePath,
                        'error' => $e->getMessage(),
                    ];
                }
            }
        }
        
        return $results;
    }
    
    /**
     * Kompresuje pojedynczy istniejący obraz
     */
    private static function compressExistingImage(string $disk, string $filePath): array
    {
        $storage = Storage::disk($disk);
        $originalSize = $storage->size($filePath);
        
        // Załaduj obraz z storage
        $imageData = $storage->get($filePath);
        $manager = new ImageManager(new Driver());
        $image = $manager->read($imageData);
        
        // Optymalizuj
        $image = self::resizeIfNeeded($image);
        $extension = pathinfo($filePath, PATHINFO_EXTENSION);
        $compressedData = self::compressImage($image, $extension);
        
        // Zapisz skompresowany obraz
        $storage->put($filePath, $compressedData);
        
        $newSize = $storage->size($filePath);
        $compressionRatio = round((1 - $newSize / $originalSize) * 100, 1);
        
        return [
            'file' => $filePath,
            'size_before' => $originalSize,
            'size_after' => $newSize,
            'compression_ratio' => $compressionRatio,
            'saved_bytes' => $originalSize - $newSize,
        ];
    }
    
    /**
     * Zmienia rozmiar obrazu jeśli jest za duży
     */
    private static function resizeIfNeeded($image)
    {
        $width = $image->width();
        $height = $image->height();
        
        if ($width > self::MAX_WIDTH || $height > self::MAX_HEIGHT) {
            $image->resize(self::MAX_WIDTH, self::MAX_HEIGHT, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            });
        }
        
        return $image;
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
    
    /**
     * Formatuje bajty do czytelnej postaci
     */
    public static function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
}
