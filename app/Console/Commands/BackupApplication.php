<?php

namespace App\Console\Commands;

use App\Support\BackupArchive;
use App\Support\DatabaseCliBinary;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Symfony\Component\Process\Process;
use ZipArchive;

class BackupApplication extends Command
{
    protected $signature = 'app:backup
        {--components=db,storage,code : Comma-separated: db, storage, code, env (code = pliki aplikacji bez vendor)}
        {--full : Skrót: db + storage + code}';

    protected $description = 'Utwórz kopię zapasową (baza, storage, kod aplikacji, opcjonalnie .env) jako ZIP';

    public function handle(): int
    {
        set_time_limit(600);

        if ($this->option('full')) {
            $components = BackupArchive::FULL_APPLICATION_COMPONENTS;
        } else {
            $components = BackupArchive::parseComponents($this->option('components'));
        }

        if (empty($components)) {
            $this->error('Nie wybrano komponentów. Dostępne: '.implode(', ', BackupArchive::ALL_COMPONENTS));

            return self::FAILURE;
        }

        $this->info('Tworzenie kopii zapasowej...');
        $this->info('Komponenty: '.implode(', ', $components));

        $backupDir = storage_path('backups');
        if (! is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        $timestamp = date('Ymd_His');
        $zipPath = $backupDir.'/backup_'.$timestamp.'.zip';

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $this->error('Nie udało się utworzyć pliku ZIP: '.$zipPath);

            return self::FAILURE;
        }

        $manifest = [
            'created_at' => now()->toIso8601String(),
            'app_name' => config('app.name'),
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'db_driver' => config('database.default'),
            'components' => $components,
            'storage_scope' => in_array(BackupArchive::COMPONENT_STORAGE, $components) ? 'full' : null,
        ];

        $errors = [];

        if (in_array(BackupArchive::COMPONENT_DB, $components)) {
            $this->info('  → Eksport bazy danych...');
            $dbResult = $this->backupDatabase($zip, $manifest);
            if ($dbResult !== true) {
                $errors[] = $dbResult;
            }
        }

        if (in_array(BackupArchive::COMPONENT_STORAGE, $components)) {
            $this->info('  → Archiwizacja katalogu storage/ (bez kopii zapasowych i cache)...');
            $storageResult = $this->backupStorage($zip, $manifest);
            if ($storageResult !== true) {
                $errors[] = $storageResult;
            }
        }

        if (in_array(BackupArchive::COMPONENT_CODE, $components)) {
            $this->info('  → Archiwizacja kodu aplikacji (bez vendor/node_modules)...');
            $codeResult = $this->backupCode($zip, $manifest);
            if ($codeResult !== true) {
                $errors[] = $codeResult;
            }
        }

        if (in_array(BackupArchive::COMPONENT_ENV, $components)) {
            $this->info('  → Kopia .env...');
            $envResult = $this->backupEnv($zip);
            if ($envResult !== true) {
                $errors[] = $envResult;
            }
        }

        if (! empty($errors)) {
            $zip->close();
            @unlink($zipPath);

            foreach ($errors as $err) {
                $this->error('  ✗ '.$err);
            }
            $this->error('Kopia zapasowa nie została utworzona — popraw błędy i spróbuj ponownie.');

            return self::FAILURE;
        }

        $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $zip->close();

        $sizeMb = round(filesize($zipPath) / 1024 / 1024, 2);
        $this->line("BACKUP_PATH={$zipPath}");
        $this->info("Kopia zapasowa utworzona: {$zipPath} ({$sizeMb} MB)");

        if (! app()->environment('testing')) {
            Artisan::call('app:backup-prune');
            $pruneOutput = trim(Artisan::output());
            if ($pruneOutput !== '') {
                $this->line($pruneOutput);
            }
        }

        return self::SUCCESS;
    }

    private function backupDatabase(ZipArchive $zip, array &$manifest): true|string
    {
        $driver = config('database.default');

        if ($driver === 'sqlite') {
            return $this->backupSqlite($zip, $manifest);
        }

        return $this->backupMysql($zip, $manifest);
    }

    private function backupSqlite(ZipArchive $zip, array &$manifest): true|string
    {
        $dbPath = config('database.connections.sqlite.database');

        if (! $dbPath || ! file_exists($dbPath)) {
            return 'Plik bazy SQLite nie istnieje: '.($dbPath ?: '(brak ścieżki)');
        }

        $zip->addFile($dbPath, 'database.sqlite');
        $manifest['db_size_bytes'] = filesize($dbPath);
        $manifest['db_file'] = 'database.sqlite';

        return true;
    }

    private function backupMysql(ZipArchive $zip, array &$manifest): true|string
    {
        $mysqldump = DatabaseCliBinary::find('mysqldump');
        if (! $mysqldump) {
            return 'Nie znaleziono programu mysqldump. Zainstaluj mysql-client albo upewnij się, że DBngin/Herd ma MySQL.';
        }

        $connection = Config::get('database.connections.mysql');
        $host = $connection['host'] ?? '127.0.0.1';
        $port = $connection['port'] ?? '3306';
        $database = $connection['database'] ?? '';
        $username = $connection['username'] ?? '';
        $password = $connection['password'] ?? '';

        $tmpFile = tempnam(sys_get_temp_dir(), 'db_backup_');

        $cmd = [
            $mysqldump,
            '--host='.$host,
            '--port='.$port,
            '--user='.$username,
            '--single-transaction',
            '--routines',
            '--triggers',
            '--quick',
            $database,
        ];

        $env = [];
        if (filled($password)) {
            $env['MYSQL_PWD'] = $password;
        }

        $process = new Process($cmd, null, $env);
        $process->setTimeout(300);

        $fp = fopen($tmpFile, 'w');
        $process->run(function ($type, $buffer) use ($fp) {
            if ($type === Process::OUT) {
                fwrite($fp, $buffer);
            }
        });
        fclose($fp);

        if (! $process->isSuccessful()) {
            @unlink($tmpFile);

            return 'mysqldump error: '.$process->getErrorOutput();
        }

        $zip->addFile($tmpFile, 'database.sql');
        $manifest['db_size_bytes'] = filesize($tmpFile);
        $manifest['db_file'] = 'database.sql';

        register_shutdown_function(fn () => @unlink($tmpFile));

        return true;
    }

    private function backupStorage(ZipArchive $zip, array &$manifest): true|string
    {
        $count = BackupArchive::addDirectoryToZip(
            $zip,
            storage_path(),
            'storage',
            fn (string $relative) => BackupArchive::shouldSkipStoragePath($relative),
        );

        $manifest['storage_files'] = $count;
        $manifest['storage_scope'] = 'full';

        return true;
    }

    private function backupCode(ZipArchive $zip, array &$manifest): true|string
    {
        $total = 0;

        foreach (BackupArchive::codeRootPaths() as $path) {
            $absolute = base_path($path);

            if (is_dir($absolute)) {
                $total += BackupArchive::addDirectoryToZip(
                    $zip,
                    $absolute,
                    'code/'.$path,
                    fn (string $relative) => BackupArchive::shouldSkipCodePath($path.'/'.$relative),
                );
            } elseif (is_file($absolute)) {
                $zip->addFile($absolute, 'code/'.$path);
                $total++;
            }
        }

        $manifest['code_files'] = $total;

        return true;
    }

    private function backupEnv(ZipArchive $zip): true|string
    {
        $envPath = base_path('.env');
        if (! file_exists($envPath)) {
            return 'Plik .env nie istnieje.';
        }

        $zip->addFile($envPath, 'dotenv');

        return true;
    }

}
