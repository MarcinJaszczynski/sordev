<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class CompressAssets extends Command
{
    protected $signature = 'assets:compress';
    protected $description = 'Compress built assets with Gzip and Brotli';

    public function handle()
    {
        $this->info('🗜️  Starting asset compression...');
        
        $buildPath = public_path('build');
        
        if (!File::exists($buildPath)) {
            $this->error('❌ Build directory not found. Run "npm run build" first.');
            return 1;
        }
        
        $files = File::allFiles($buildPath);
        $compressed = 0;
        
        foreach ($files as $file) {
            if ($this->shouldCompress($file)) {
                $this->compressFile($file);
                $compressed++;
            }
        }
        
        $this->info("✅ Compressed {$compressed} files successfully!");
        return 0;
    }
    
    private function shouldCompress($file): bool
    {
        $extension = $file->getExtension();
        $compressibleExtensions = ['js', 'css', 'svg', 'json'];
        
        return in_array($extension, $compressibleExtensions) && $file->getSize() > 1024;
    }
    
    private function compressFile($file): void
    {
        $content = file_get_contents($file->getPathname());
        
        // Kompresja Gzip
        $gzipPath = $file->getPathname() . '.gz';
        file_put_contents($gzipPath, gzencode($content, 9));
        
        // Kompresja Brotli (jeśli dostępna)
        if (extension_loaded('brotli') && function_exists('brotli_compress')) {
            $brotliPath = $file->getPathname() . '.br';
            file_put_contents($brotliPath, brotli_compress($content, 11));
        }
        
        $originalSize = $file->getSize();
        $gzipSize = filesize($gzipPath);
        $compression = round((1 - $gzipSize / $originalSize) * 100, 1);
        
        $this->line("📦 {$file->getRelativePathname()}: {$this->formatBytes($originalSize)} → {$this->formatBytes($gzipSize)} ({$compression}% smaller)");
    }
    
    private function formatBytes($bytes, $precision = 2)
    {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
}
