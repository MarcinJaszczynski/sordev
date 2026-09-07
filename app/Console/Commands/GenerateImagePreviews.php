<?php

namespace App\Console\Commands;

use App\Services\ImageCompressionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class GenerateImagePreviews extends Command
{
    protected $signature = 'images:generate-previews
                            {--disk=public : Dysk storage do przetworzenia}
                            {--directory=event-templates : Katalog z istniejącymi obrazami}
                            {--force : Nadpisz istniejące miniatury i WebP}
                            {--purge-thumbs : Usuń stare pliki w thumbs/ przed regeneracją}';

    protected $description = 'Generuje prewki i warianty WebP dla istniejących zdjęć w storage.';

    public function handle(): int
    {
        $disk = (string) $this->option('disk');
        $directory = trim((string) $this->option('directory'), '/');
        $force = (bool) $this->option('force');
        $purgeThumbs = (bool) $this->option('purge-thumbs');

        if ($directory === '') {
            $this->error('Podaj katalog do przetworzenia, np. --directory=event-templates');

            return self::FAILURE;
        }

        if (! Storage::disk($disk)->exists($directory)) {
            $this->error("Katalog '{$directory}' nie istnieje na dysku '{$disk}'.");

            return self::FAILURE;
        }

        if ($purgeThumbs) {
            $deleted = ImageCompressionService::purgeThumbnails($disk, $directory);
            $this->info("Usunięto stare prewki: {$deleted}");
            // Po purge zawsze nadpisujemy / tworzymy thumbs od zera.
            $force = true;
        }

        $this->info("Generowanie prewek ({$directory}, dysk {$disk}, rozmiar ".ImageCompressionService::THUMBNAIL_SIZE.'px)...');

        $results = ImageCompressionService::compressExistingImages($disk, $directory, $force);

        if (empty($results)) {
            $this->warn('Nie znaleziono obrazów do przetworzenia.');

            return self::SUCCESS;
        }

        $processed = 0;
        $errors = 0;
        $savedBytes = 0;

        foreach ($results as $result) {
            if (isset($result['error'])) {
                $errors++;
                $this->warn('Błąd: '.($result['file'] ?? 'nieznany plik').' → '.$result['error']);

                continue;
            }

            $processed++;
            $savedBytes += (int) ($result['saved_bytes'] ?? 0);
        }

        $this->newLine();
        $this->info("Przetworzono: {$processed}");
        $this->line('Zaoszczędzone miejsce: '.ImageCompressionService::formatBytes($savedBytes));

        if ($errors > 0) {
            $this->warn("Błędy: {$errors}");

            return self::FAILURE;
        }

        $this->info('Prewki zostały wygenerowane pomyślnie.');

        return self::SUCCESS;
    }
}
