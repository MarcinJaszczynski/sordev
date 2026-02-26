<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\File;

class OptimizeAssets extends Command
{
    protected $signature = 'assets:optimize {--analyze : Analyze bundle size}';
    protected $description = 'Optimize and build frontend assets with advanced optimizations';

    public function handle()
    {
        $this->info('🚀 Starting asset optimization...');
        
        // Clear previous builds
        $this->info('🧹 Cleaning previous builds...');
        if (File::exists(public_path('build'))) {
            File::deleteDirectory(public_path('build'));
        }
        
        // Run optimization
        $buildCommand = $this->option('analyze') ? 'npm run build:analyze' : 'npm run build:prod';
        
        $this->info("📦 Running: {$buildCommand}");
        $result = Process::run($buildCommand);
        
        if ($result->failed()) {
            $this->error('❌ Asset optimization failed!');
            $this->line($result->errorOutput());
            return 1;
        }
        
        $this->info('✅ Asset optimization completed!');
        $this->showOptimizationStats();
        
        if ($this->option('analyze')) {
            $this->info('📊 Bundle analysis saved to: storage/app/bundle-analysis.html');
        }
        
        return 0;
    }
    
    private function showOptimizationStats()
    {
        $buildPath = public_path('build');
        
        if (!File::exists($buildPath)) {
            return;
        }
        
        $totalSize = 0;
        $files = File::allFiles($buildPath);
        
        $this->table(
            ['File', 'Size', 'Compressed'],
            collect($files)->map(function ($file) use (&$totalSize) {
                $size = $file->getSize();
                $totalSize += $size;
                
                $gzipFile = $file->getPathname() . '.gz';
                $gzipSize = File::exists($gzipFile) ? File::size($gzipFile) : null;
                
                return [
                    $file->getRelativePathname(),
                    $this->formatBytes($size),
                    $gzipSize ? $this->formatBytes($gzipSize) . ' (' . round(($gzipSize / $size) * 100, 1) . '%)' : 'N/A'
                ];
            })->toArray()
        );
        
        $this->info("📈 Total build size: " . $this->formatBytes($totalSize));
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
