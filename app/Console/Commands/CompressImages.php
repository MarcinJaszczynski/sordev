<?php

namespace App\Console\Commands;

use App\Services\ImageCompressionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CompressImages extends Command
{
    protected $signature = 'images:compress 
                           {--disk=public : The storage disk to use}
                           {--directory=images : The directory to compress images in}
                           {--existing : Compress existing images instead of setting up auto-compression}';
    
    protected $description = 'Compress uploaded images and optimize storage usage';

    public function handle()
    {
        $disk = $this->option('disk');
        $directory = $this->option('directory');
        
        if ($this->option('existing')) {
            return $this->compressExisting($disk, $directory);
        }
        
        $this->info('🖼️  Image compression is now configured!');
        $this->line('');
        $this->line('Automatic compression will be applied to:');
        $this->line('• New uploads through Filament FileUpload components');
        $this->line('• JPEG quality: ' . ImageCompressionService::JPEG_QUALITY . '%');
        $this->line('• WebP quality: ' . ImageCompressionService::WEBP_QUALITY . '%');
        $this->line('• Max dimensions: ' . ImageCompressionService::MAX_WIDTH . 'x' . ImageCompressionService::MAX_HEIGHT . 'px');
        $this->line('• Thumbnail size: ' . ImageCompressionService::THUMBNAIL_SIZE . 'px');
        $this->line('');
        $this->line('Features:');
        $this->line('✅ Automatic resizing of large images');
        $this->line('✅ WebP conversion for better compression');
        $this->line('✅ Thumbnail generation');
        $this->line('✅ Quality optimization');
        $this->line('');
        $this->info('💡 To compress existing images, run: php artisan images:compress --existing');
        
        return 0;
    }
    
    private function compressExisting(string $disk, string $directory): int
    {
        $this->info("🗜️  Compressing existing images in '{$disk}' disk, '{$directory}' directory...");
        
        if (!Storage::disk($disk)->exists($directory)) {
            $this->error("❌ Directory '{$directory}' does not exist on '{$disk}' disk.");
            return 1;
        }
        
        $results = ImageCompressionService::compressExistingImages($disk, $directory);
        
        if (empty($results)) {
            $this->warn('⚠️  No images found to compress.');
            return 0;
        }
        
        $totalSaved = 0;
        $totalOriginal = 0;
        $successCount = 0;
        $errorCount = 0;
        
        $this->table(
            ['File', 'Before', 'After', 'Saved', 'Compression'],
            collect($results)->map(function ($result) use (&$totalSaved, &$totalOriginal, &$successCount, &$errorCount) {
                if (isset($result['error'])) {
                    $errorCount++;
                    return [
                        $result['file'],
                        'ERROR',
                        'ERROR',
                        'ERROR',
                        $result['error']
                    ];
                }
                
                $successCount++;
                $totalSaved += $result['saved_bytes'];
                $totalOriginal += $result['size_before'];
                
                return [
                    basename($result['file']),
                    ImageCompressionService::formatBytes($result['size_before']),
                    ImageCompressionService::formatBytes($result['size_after']),
                    ImageCompressionService::formatBytes($result['saved_bytes']),
                    $result['compression_ratio'] . '%'
                ];
            })->toArray()
        );
        
        $overallCompressionRatio = $totalOriginal > 0 ? round(($totalSaved / $totalOriginal) * 100, 1) : 0;
        
        $this->info("✅ Compressed {$successCount} images successfully!");
        if ($errorCount > 0) {
            $this->warn("⚠️  {$errorCount} images had errors.");
        }
        
        $this->line('');
        $this->info("📊 Compression Statistics:");
        $this->line("• Total space saved: " . ImageCompressionService::formatBytes($totalSaved));
        $this->line("• Overall compression: {$overallCompressionRatio}%");
        $this->line("• Successfully processed: {$successCount} files");
        
        return 0;
    }
}
