<?php

namespace App\Filament\Pages;

use App\Support\BackupArchive;
use App\Support\FilamentNavigation;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Artisan;
use Livewire\WithFileUploads;
use ZipArchive;

class BackupManager extends Page
{
    use WithFileUploads;

    protected static ?string $navigationIcon = 'heroicon-o-archive-box';

    protected static string $view = 'filament.pages.backup-manager';

    protected static ?string $navigationLabel = 'Kopie zapasowe';

    protected static ?string $navigationGroup = FilamentNavigation::GROUP_SYSTEM;

    protected static ?int $navigationSort = 99;

    public bool $includeDb = true;

    public bool $includeStorage = true;

    public bool $includeCode = true;

    public bool $includeEnv = false;

    public bool $isCreating = false;

    public $restoreFile;

    public ?array $restoreManifest = null;

    public bool $restoreDb = false;

    public bool $restoreStorage = false;

    public bool $restoreCode = false;

    public bool $restoreEnv = false;

    public bool $isRestoring = false;

    public bool $showRestoreConfirm = false;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user && ($user->hasRole('admin') || $user->hasRole('super_admin'));
    }

    public function getTitle(): string
    {
        return 'Kopie zapasowe';
    }

    public function createBackup(): void
    {
        $this->isCreating = true;

        try {
            $components = [];
            if ($this->includeDb) {
                $components[] = BackupArchive::COMPONENT_DB;
            }
            if ($this->includeStorage) {
                $components[] = BackupArchive::COMPONENT_STORAGE;
            }
            if ($this->includeCode) {
                $components[] = BackupArchive::COMPONENT_CODE;
            }
            if ($this->includeEnv) {
                $components[] = BackupArchive::COMPONENT_ENV;
            }

            if (empty($components)) {
                Notification::make()
                    ->title('Wybierz co najmniej jeden komponent')
                    ->warning()
                    ->send();
                $this->isCreating = false;

                return;
            }

            $exitCode = Artisan::call('app:backup', [
                '--components' => implode(',', $components),
            ]);

            $output = trim(Artisan::output());

            if ($exitCode === 0) {
                Notification::make()
                    ->title('Kopia zapasowa utworzona')
                    ->body($output)
                    ->success()
                    ->send();
            } else {
                Notification::make()
                    ->title('Błąd tworzenia kopii')
                    ->body($output)
                    ->danger()
                    ->send();
            }
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Błąd tworzenia kopii')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }

        $this->isCreating = false;
    }

    public function getBackups(): array
    {
        $backupDir = storage_path('backups');
        if (! is_dir($backupDir)) {
            return [];
        }

        $files = glob($backupDir.'/backup_*.zip');
        if (! $files) {
            return [];
        }

        rsort($files);

        $backups = [];
        foreach ($files as $file) {
            $manifest = $this->readManifestFromZip($file);
            if (! $manifest) {
                continue;
            }

            $backups[] = [
                'filename' => basename($file),
                'path' => $file,
                'size' => $this->formatBytes(filesize($file)),
                'size_raw' => filesize($file),
                'created_at' => $manifest['created_at'] ?? '?',
                'components' => $manifest['components'] ?? [],
                'db_driver' => $manifest['db_driver'] ?? '?',
                'php_version' => $manifest['php_version'] ?? '?',
                'storage_files' => $manifest['storage_files'] ?? null,
                'code_files' => $manifest['code_files'] ?? null,
                'storage_scope' => $manifest['storage_scope'] ?? 'public',
                'db_size' => isset($manifest['db_size_bytes']) ? $this->formatBytes($manifest['db_size_bytes']) : null,
            ];
        }

        return $backups;
    }

    public function pruneOldBackups(): void
    {
        try {
            $keep = (int) config('backup.retention_count', 7);
            $exitCode = Artisan::call('app:backup-prune', ['--keep' => $keep]);
            $output = trim(Artisan::output());

            $notification = Notification::make()
                ->title($exitCode === 0 ? 'Retencja zastosowana' : 'Błąd retencji')
                ->body($output !== '' ? $output : "Zachowano {$keep} najnowszych kopii.");
            if ($exitCode === 0) {
                $notification->success();
            } else {
                $notification->danger();
            }
            $notification->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Błąd retencji')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function getScheduleInfo(): array
    {
        return [
            'enabled' => (bool) config('backup.schedule_enabled', true),
            'cron' => (string) config('backup.schedule_cron', '0 2 * * *'),
            'retention' => (int) config('backup.retention_count', 7),
            'components' => (string) config('backup.scheduled_components', 'db,storage'),
        ];
    }

    public function deleteBackup(string $filename): void
    {
        $path = storage_path('backups/'.$filename);

        if (! str_starts_with(realpath($path) ?: '', storage_path('backups'))) {
            Notification::make()->title('Niedozwolona ścieżka')->danger()->send();

            return;
        }

        if (file_exists($path)) {
            unlink($path);
            Notification::make()
                ->title('Kopia usunięta')
                ->body($filename)
                ->success()
                ->send();
        }
    }

    public function updatedRestoreFile(): void
    {
        $this->restoreManifest = null;
        $this->restoreDb = false;
        $this->restoreStorage = false;
        $this->restoreCode = false;
        $this->restoreEnv = false;

        if (! $this->restoreFile) {
            return;
        }

        $tmpPath = $this->restoreFile->getRealPath();
        $manifest = $this->readManifestFromZip($tmpPath);

        if (! $manifest) {
            Notification::make()
                ->title('Nieprawidłowy plik')
                ->body('Archiwum nie zawiera manifest.json.')
                ->danger()
                ->send();
            $this->restoreFile = null;

            return;
        }

        $this->restoreManifest = $manifest;
        $components = $manifest['components'] ?? [];
        $this->restoreDb = in_array(BackupArchive::COMPONENT_DB, $components);
        $this->restoreStorage = in_array(BackupArchive::COMPONENT_STORAGE, $components);
        $this->restoreCode = in_array(BackupArchive::COMPONENT_CODE, $components);
        $this->restoreEnv = in_array(BackupArchive::COMPONENT_ENV, $components);
    }

    public function selectFullApplication(): void
    {
        $this->includeDb = true;
        $this->includeStorage = true;
        $this->includeCode = true;
        $this->includeEnv = false;
    }

    public function selectDataOnly(): void
    {
        $this->includeDb = true;
        $this->includeStorage = true;
        $this->includeCode = false;
        $this->includeEnv = false;
    }

    public function confirmRestore(): void
    {
        $this->showRestoreConfirm = true;
    }

    public function cancelRestore(): void
    {
        $this->showRestoreConfirm = false;
    }

    public function executeRestore(): void
    {
        $this->showRestoreConfirm = false;
        $this->isRestoring = true;

        try {
            if (! $this->restoreFile) {
                Notification::make()->title('Brak pliku do przywrócenia')->warning()->send();
                $this->isRestoring = false;

                return;
            }

            $tmpPath = $this->restoreFile->getRealPath();

            $components = [];
            if ($this->restoreDb) {
                $components[] = BackupArchive::COMPONENT_DB;
            }
            if ($this->restoreStorage) {
                $components[] = BackupArchive::COMPONENT_STORAGE;
            }
            if ($this->restoreCode) {
                $components[] = BackupArchive::COMPONENT_CODE;
            }
            if ($this->restoreEnv) {
                $components[] = BackupArchive::COMPONENT_ENV;
            }

            if (empty($components)) {
                Notification::make()->title('Wybierz co najmniej jeden komponent')->warning()->send();
                $this->isRestoring = false;

                return;
            }

            $exitCode = Artisan::call('app:restore', [
                'path' => $tmpPath,
                '--components' => implode(',', $components),
                '--force' => true,
            ]);

            $output = trim(Artisan::output());

            if ($exitCode === 0) {
                Notification::make()
                    ->title('Przywracanie zakończone')
                    ->body($output)
                    ->success()
                    ->send();
            } else {
                Notification::make()
                    ->title('Błąd przywracania')
                    ->body($output)
                    ->danger()
                    ->send();
            }
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Błąd przywracania')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }

        $this->restoreFile = null;
        $this->restoreManifest = null;
        $this->isRestoring = false;
    }

    private function readManifestFromZip(string $path): ?array
    {
        if (! file_exists($path)) {
            return null;
        }

        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            return null;
        }

        $json = $zip->getFromName('manifest.json');
        $zip->close();

        if ($json === false) {
            return null;
        }

        $data = json_decode($json, true);

        return is_array($data) ? $data : null;
    }

    public function formatBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2).' GB';
        }
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2).' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 1).' KB';
        }

        return $bytes.' B';
    }
}
