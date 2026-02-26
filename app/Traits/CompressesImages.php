<?php

namespace App\Traits;

use App\Services\ImageCompressionService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

trait CompressesImages
{
    /**
     * Hook do kompresji obrazów po upload
     */
    public function afterCreate(): void
    {
        if (method_exists(parent::class, 'afterCreate')) {
            parent::afterCreate();
        }
        $this->compressUploadedImages();
    }
    
    public function afterSave(): void
    {
        if (method_exists(parent::class, 'afterSave')) {
            parent::afterSave();
        }
        $this->compressUploadedImages();
    }
    
    /**
     * Kompresuje obrazy w polach FileUpload
     */
    protected function compressUploadedImages(): void
    {
        $record = $this->getRecord();
        
        if (!$record) {
            return;
        }
        
        // Znajdź pola z obrazami
        $imageFields = $this->getImageFields();
        
        foreach ($imageFields as $field) {
            $this->compressImagesInField($record, $field);
        }
    }
    
    /**
     * Kompresuje obrazy w konkretnym polu
     */
    protected function compressImagesInField($record, string $fieldName): void
    {
        $value = $record->getAttribute($fieldName);
        
        if (empty($value)) {
            return;
        }
        
        // Obsługa pojedynczego pliku
        if (is_string($value)) {
            $this->compressImageFile($record, $fieldName, $value);
            return;
        }
        
        // Obsługa tablicy plików (galeria)
        if (is_array($value)) {
            // Jeśli w tablicy są jakiekolwiek nie-stringi, nie nadpisuj pola (zostaw oryginał)
            $allStrings = true;
            foreach ($value as $file) {
                if (!is_string($file)) {
                    $allStrings = false;
                    break;
                }
            }
            if ($allStrings) {
                $compressedFiles = [];
                foreach ($value as $file) {
                    $compressedPath = $this->compressImageFile($record, $fieldName, $file);
                    $compressedFiles[] = $compressedPath ?: $file;
                }
                $record->update([$fieldName => $compressedFiles]);
            }
        }
    }
    
    /**
     * Kompresuje pojedynczy plik obrazu
     */
    protected function compressImageFile($record, string $fieldName, string $filePath): ?string
    {
        $disk = $this->getImageDisk();
        
        if (!Storage::disk($disk)->exists($filePath)) {
            return null;
        }
        
        try {
            // Sprawdź czy to obraz
            $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
            if (!in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                return null;
            }
            
            // Utwórz TemporaryUploadedFile z istniejącego pliku
            $fullPath = Storage::disk($disk)->path($filePath);
            $mimeType = mime_content_type($fullPath) ?: 'image/jpeg';
            
            // Kompresuj obraz
            $tempFile = new \Illuminate\Http\UploadedFile(
                $fullPath,
                basename($filePath),
                $mimeType,
                null,
                true
            );
            
            $result = ImageCompressionService::compressAndStore(
                $tempFile,
                $disk,
                dirname($filePath)
            );
            
            // Usuń oryginalny plik jeśli kompresja się udała
            if ($result['compression_ratio'] > 5) { // Tylko jeśli oszczędność > 5%
                Storage::disk($disk)->delete($filePath);
                return $result['original'];
            }
            
            return $filePath;
            
        } catch (\Exception $e) {
            // W przypadku błędu, zachowaj oryginalny plik
            Log::error('Image compression failed', [
                'field' => $fieldName,
                'file' => $filePath,
                'error' => $e->getMessage()
            ]);
            
            return null;
        }
    }
    
    /**
     * Zwraca pola z obrazami do kompresji
     */
    protected function getImageFields(): array
    {
        // Domyślne pola - można nadpisać w klasie
        return [
            'featured_image',
            'gallery',
            'gallery_images',
            'image',
            'avatar',
            'photo',
            'attachments',
        ];
    }
    
    /**
     * Zwraca dysk do przechowywania obrazów
     */
    protected function getImageDisk(): string
    {
        return 'public';
    }
}
