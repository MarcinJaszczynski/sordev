<?php

namespace App\Console\Commands;

use App\Models\Media;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class MediaSyncCommand extends Command
{
    protected $signature = 'media:sync {--disk=public} {--dir=}';
    protected $description = 'Skanuje pliki na dysku i synchronizuje z tabelą media';

    public function handle(): int
    {
        $disk = $this->option('disk');
        $startDir = $this->option('dir');
        $fs = Storage::disk($disk);

        if ($startDir && !$fs->exists($startDir)) {
            $this->warn('Podany katalog nie istnieje na dysku: ' . $startDir);
        }

        $this->info("Skanuję dysk '{$disk}' ...");
        $files = $fs->allFiles($startDir ?: '/');
        $bar = $this->output->createProgressBar(count($files));
        $bar->start();

        foreach ($files as $path) {
            $bar->advance();
            if (str_ends_with($path, '.gitignore') || str_contains($path, 'livewire-tmp')) {
                continue;
            }

            $mime = $fs->mimeType($path) ?: null;
            $size = $fs->size($path) ?: null;
            $filename = basename($path);
            $extension = pathinfo($filename, PATHINFO_EXTENSION) ?: null;

            $width = null; $height = null;
            if ($mime && str_starts_with($mime, 'image/')) {
                try {
                    $localPath = $fs->path($path);
                    $img = @getimagesize($localPath);
                    if ($img) { $width = $img[0] ?? null; $height = $img[1] ?? null; }
                } catch (\Throwable $e) { /* ignore */ }
            }

            Media::updateOrCreate(
                ['disk' => $disk, 'path' => $path],
                compact('filename','extension','mime','size','width','height')
            );
        }

        $bar->finish();
        $this->newLine();
        $this->info('Zakończono synchronizację. Plików: ' . count($files));
        return self::SUCCESS;
    }
}
