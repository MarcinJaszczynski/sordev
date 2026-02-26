<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use App\Models\Media;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('media:sync {--disk=public} {--dir=}', function () {
    $disk = $this->option('disk');
    $dir = $this->option('dir') ?: '/';
    $fs = Storage::disk($disk);
    $this->info("Skanuję '{$disk}:{$dir}'...");

    $files = $fs->allFiles($dir);
    $bar = $this->output->createProgressBar(count($files));
    $bar->start();
    foreach ($files as $path) {
        $bar->advance();
        if (str_ends_with($path, '.gitignore') || str_contains($path, 'livewire-tmp')) {
            continue;
        }
        // utwórz/aktualizuj rekord Media, a mutator path uzupełni metadane
        Media::updateOrCreate(
            ['disk' => $disk, 'path' => $path],
            []
        );
    }
    $bar->finish();
    $this->newLine();
    $this->info('Zakończono synchronizację.');
})->purpose('Synchronizuje istniejące pliki z biblioteką mediów');
