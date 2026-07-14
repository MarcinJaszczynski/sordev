<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class PruneBackupArchives extends Command
{
    protected $signature = 'app:backup-prune {--keep= : Liczba ostatnich kopii do zachowania}';

    protected $description = 'Usuń stare kopie zapasowe, zachowując N najnowszych archiwów';

    public function handle(): int
    {
        $keep = (int) ($this->option('keep') ?: config('backup.retention_count', 7));
        $keep = max(1, $keep);

        $backupDir = storage_path('backups');
        if (! is_dir($backupDir)) {
            $this->info('Brak katalogu kopii zapasowych.');

            return self::SUCCESS;
        }

        $files = glob($backupDir.'/backup_*.zip') ?: [];
        if (count($files) <= $keep) {
            $this->info('Brak kopii do usunięcia (zachowano '.count($files).' / limit '.$keep.').');

            return self::SUCCESS;
        }

        usort($files, fn ($a, $b) => filemtime($b) <=> filemtime($a));
        $toDelete = array_slice($files, $keep);
        $deleted = 0;

        foreach ($toDelete as $file) {
            if (@unlink($file)) {
                $deleted++;
                $this->line('  Usunięto: '.basename($file));
            }
        }

        $this->info("Usunięto {$deleted} starych kopii (zachowano {$keep} najnowszych).");

        return self::SUCCESS;
    }
}
